-- ============================================================
-- RobChat: iCal Sync Schema Migration
-- Run on VPS: psql -U robchat -d robchat -f 001-ical-sync-schema.sql
-- ============================================================

-- 1. Add iCal columns to availability_overrides
ALTER TABLE availability_overrides ADD COLUMN IF NOT EXISTS ical_uid TEXT;
ALTER TABLE availability_overrides ADD COLUMN IF NOT EXISTS source TEXT NOT NULL DEFAULT 'manual';
ALTER TABLE availability_overrides ADD COLUMN IF NOT EXISTS description TEXT;

-- 2. Partial unique index for upsert (one row per iCal event per tenant)
CREATE UNIQUE INDEX IF NOT EXISTS idx_overrides_ical_uid
    ON availability_overrides (tenant_id, ical_uid)
    WHERE ical_uid IS NOT NULL;

-- 3. Add iCal feed URL to tenants (single-provider for now)
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS ical_feed_url TEXT;

-- 4. Set Bonnie's feed URL
UPDATE tenants
SET ical_feed_url = 'https://glossgenius.com/calendar-sync/ed8xQfPoO0iYG8HNR1nr8H'
WHERE id = 'honeyb_d04fd899';

-- Verify
SELECT id, ical_feed_url FROM tenants WHERE id = 'honeyb_d04fd899';
\d availability_overrides
