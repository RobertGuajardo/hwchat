-- ============================================================
-- RobChat Native Scheduler — Schema Plan
-- PostgreSQL Migration #002 (not yet applied)
-- ============================================================
-- This adds a built-in scheduling system so tenants can accept
-- bookings without any external calendar setup. Google Calendar
-- and Outlook are optional sync targets.
-- ============================================================

BEGIN;

-- ---------------------------------------------------------------------------
-- 1. AVAILABILITY RULES — recurring weekly schedule per tenant
-- ---------------------------------------------------------------------------
-- Each row = one block of available time on a given weekday.
-- A tenant can have multiple rows (e.g. Mon 9-12, Mon 1-5).
--
-- Example:
--   tenant_id=acme, day_of_week=1, start_time=09:00, end_time=12:00
--   tenant_id=acme, day_of_week=1, start_time=13:00, end_time=17:00
--   tenant_id=acme, day_of_week=2, start_time=09:00, end_time=17:00
--
CREATE TABLE availability_rules (
    id              SERIAL PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    day_of_week     INTEGER NOT NULL CHECK (day_of_week BETWEEN 0 AND 6),  -- 0=Sun, 1=Mon, ..., 6=Sat
    start_time      TIME NOT NULL,          -- e.g. 09:00
    end_time        TIME NOT NULL,          -- e.g. 17:00
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT valid_time_range CHECK (end_time > start_time)
);

CREATE INDEX idx_availability_tenant ON availability_rules(tenant_id);

-- ---------------------------------------------------------------------------
-- 2. AVAILABILITY OVERRIDES — block off or open up specific dates
-- ---------------------------------------------------------------------------
-- Handles vacations, holidays, or one-off open times.
--
-- type = 'blocked'  → no bookings allowed in this window (vacation, holiday)
-- type = 'open'     → extra availability outside normal rules (Saturday event)
--
CREATE TABLE availability_overrides (
    id              SERIAL PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    override_date   DATE NOT NULL,
    start_time      TIME,                   -- null for full-day block
    end_time        TIME,                   -- null for full-day block
    override_type   TEXT NOT NULL DEFAULT 'blocked' CHECK (override_type IN ('blocked', 'open')),
    reason          TEXT                    -- "Thanksgiving", "Conference", etc.
);

CREATE INDEX idx_overrides_tenant_date ON availability_overrides(tenant_id, override_date);

-- ---------------------------------------------------------------------------
-- 3. BOOKING SETTINGS — per-tenant scheduling config
-- ---------------------------------------------------------------------------
-- Stored directly on the tenants table (new columns).
-- These were partially there already; this adds the scheduler-specific ones.
--
-- ALTER TABLE tenants ADD COLUMN IF NOT EXISTS:
--   booking_slot_minutes    INTEGER DEFAULT 30        -- slot duration
--   booking_buffer_minutes  INTEGER DEFAULT 0         -- gap between bookings
--   booking_notice_hours    INTEGER DEFAULT 24        -- min advance notice
--   booking_window_days     INTEGER DEFAULT 14        -- how far out to show slots
--   booking_timezone        TEXT DEFAULT 'America/Chicago'  (already exists)
--   booking_confirmation_email BOOLEAN DEFAULT TRUE   -- send confirmation
--   booking_reminder_hours  INTEGER DEFAULT 24        -- reminder X hours before

ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS booking_slot_minutes    INTEGER NOT NULL DEFAULT 30,
    ADD COLUMN IF NOT EXISTS booking_buffer_minutes  INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS booking_notice_hours    INTEGER NOT NULL DEFAULT 24,
    ADD COLUMN IF NOT EXISTS booking_window_days     INTEGER NOT NULL DEFAULT 14,
    ADD COLUMN IF NOT EXISTS booking_confirmation_email BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS booking_reminder_hours  INTEGER DEFAULT 24;

-- ---------------------------------------------------------------------------
-- 4. BOOKINGS — confirmed appointments
-- ---------------------------------------------------------------------------
-- Each row = one booked appointment. The source of truth for what's taken.
-- The availability endpoint checks this table to subtract booked slots.
--
CREATE TABLE bookings (
    id              SERIAL PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    session_id      TEXT REFERENCES sessions(id) ON DELETE SET NULL,  -- chat that created it
    booking_date    DATE NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    timezone        TEXT NOT NULL DEFAULT 'America/Chicago',

    -- Guest info
    guest_name      TEXT NOT NULL,
    guest_email     TEXT NOT NULL,
    guest_phone     TEXT,
    guest_notes     TEXT,

    -- Status
    status          TEXT NOT NULL DEFAULT 'confirmed' CHECK (status IN ('confirmed', 'cancelled', 'completed', 'no_show')),
    cancelled_at    TIMESTAMPTZ,
    cancel_reason   TEXT,

    -- Sync
    google_event_id TEXT,                   -- if synced to Google Calendar
    outlook_event_id TEXT,                  -- if synced to Outlook

    -- Notifications
    confirmation_sent BOOLEAN NOT NULL DEFAULT FALSE,
    reminder_sent   BOOLEAN NOT NULL DEFAULT FALSE,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT valid_booking_time CHECK (end_time > start_time)
);

CREATE INDEX idx_bookings_tenant ON bookings(tenant_id);
CREATE INDEX idx_bookings_tenant_date ON bookings(tenant_id, booking_date);
CREATE INDEX idx_bookings_status ON bookings(tenant_id, status);
CREATE INDEX idx_bookings_guest_email ON bookings(tenant_id, guest_email);

-- Trigger for updated_at
CREATE TRIGGER bookings_updated_at
    BEFORE UPDATE ON bookings
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

-- ---------------------------------------------------------------------------
-- 5. CALENDAR CONNECTIONS — OAuth tokens for Google/Outlook sync
-- ---------------------------------------------------------------------------
-- Optional. A tenant connects their Google or Outlook account, and:
-- - New bookings create events in their external calendar
-- - Existing events in the external calendar block off availability
--
CREATE TABLE calendar_connections (
    id              SERIAL PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    provider        TEXT NOT NULL CHECK (provider IN ('google', 'outlook')),
    calendar_id     TEXT,                   -- which calendar to sync with
    access_token    TEXT NOT NULL,           -- encrypted at rest
    refresh_token   TEXT,                    -- encrypted at rest
    token_expires_at TIMESTAMPTZ,
    sync_enabled    BOOLEAN NOT NULL DEFAULT TRUE,
    last_synced_at  TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    UNIQUE (tenant_id, provider)
);

COMMIT;


-- ============================================================
-- HOW THE AVAILABILITY API WORKS (pseudocode)
-- ============================================================
--
-- GET /api/availability.php?tenant_id=acme&date=2026-03-20
--
-- 1. Load tenant's booking settings (slot duration, buffer, timezone, window)
-- 2. Load availability_rules for that day_of_week
-- 3. Load availability_overrides for that specific date
-- 4. Load existing bookings for that date
-- 5. If external calendar connected, fetch busy times
-- 6. Generate all possible slots from the rules
-- 7. Subtract: booked slots + overrides(blocked) + external busy times
-- 8. Add: overrides(open)
-- 9. Filter: slots must be >= booking_notice_hours from now
-- 10. Return available slots as JSON
--
-- POST /api/book.php
--
-- 1. Validate: slot is still available (re-check DB)
-- 2. Insert into bookings table
-- 3. Send confirmation email to guest + tenant
-- 4. If Google/Outlook connected, create external event
-- 5. Return confirmation
--
-- ============================================================
-- DASHBOARD PAGES NEEDED
-- ============================================================
--
-- Settings > Scheduling
--   - Toggle scheduling on/off
--   - Set availability (visual weekly grid)
--   - Slot duration, buffer, advance notice, booking window
--   - Connect Google Calendar (OAuth flow)
--   - Connect Outlook (OAuth flow)
--
-- Bookings (new tab)
--   - List of upcoming/past bookings
--   - Cancel/reschedule actions
--   - Filter by status, date range
--
-- ============================================================
