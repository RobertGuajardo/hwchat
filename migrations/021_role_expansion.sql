-- Migration 021: Add regional_admin role and region column to users table
-- Run with: psql -U hwchat -d hwchat -W -f migrations/021_role_expansion.sql

BEGIN;

-- Drop existing role CHECK constraint
ALTER TABLE users DROP CONSTRAINT users_role_check;

-- Re-create with regional_admin added
ALTER TABLE users ADD CONSTRAINT users_role_check
    CHECK (role = ANY (ARRAY['superadmin'::text, 'regional_admin'::text, 'tenant_admin'::text, 'builder'::text]));

-- Add region column to users table (for regional_admin scoping)
ALTER TABLE users ADD COLUMN IF NOT EXISTS region TEXT DEFAULT NULL;

-- Add CHECK constraint for valid regions (same slugs as tenants.region)
ALTER TABLE users ADD CONSTRAINT chk_user_region
    CHECK (region IN ('dfw', 'houston', 'austin'));

COMMIT;