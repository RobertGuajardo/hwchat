-- 010_builders.sql
-- Adds builders table for multi-builder communities (Hillwood)

CREATE TABLE IF NOT EXISTS builders (
    id              SERIAL PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name            TEXT NOT NULL,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order      INTEGER NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_builders_tenant ON builders(tenant_id);

-- Add builder reference to bookings
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS builder_id INTEGER REFERENCES builders(id) ON DELETE SET NULL;
