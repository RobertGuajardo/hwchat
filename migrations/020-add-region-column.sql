-- Migration 020: Add region column to tenants table
-- Run with: psql -U hwchat -d hwchat -W -f migrations/020-add-region-column.sql

BEGIN;

-- ============================================================
-- 1. Add nullable region column
-- ============================================================

ALTER TABLE tenants ADD COLUMN IF NOT EXISTS region TEXT DEFAULT NULL;

-- ============================================================
-- 2. Seed region values for existing communities
-- ============================================================

-- DFW (9 tenants)
UPDATE tenants SET region = 'dfw'
WHERE id IN ('hw_harvest', 'hw_treeline', 'hw_pecan_square', 'hw_union_park',
             'hw_lilyana', 'hw_landmark', 'hw_ramble', 'hw_parent', 'hw_realtors');

-- Houston (3 tenants)
UPDATE tenants SET region = 'houston'
WHERE id IN ('hw_pomona', 'hw_legacy', 'hw_valencia');

-- Austin (2 tenants)
UPDATE tenants SET region = 'austin'
WHERE id IN ('hw_wolf_ranch', 'hw_melina');

-- demo_001 and hw_superadmin remain NULL — excluded from scope system

-- ============================================================
-- 3. CHECK constraint to enforce valid region values
-- ============================================================

ALTER TABLE tenants ADD CONSTRAINT chk_region CHECK (region IN ('dfw', 'houston', 'austin'));

COMMIT;
