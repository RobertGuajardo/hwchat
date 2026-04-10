-- ============================================================
-- HWChat Hillwood Communities — Schema Additions
-- PostgreSQL Migration #006
-- ============================================================
-- Adds Cecilian XO API config, HubSpot integration fields,
-- and community metadata to support the Hillwood multi-tenant
-- chatbot platform.
-- ============================================================
-- Run: psql -U robchat -d robchat -f 006_hillwood_schema.sql
-- ============================================================

BEGIN;

-- ---------------------------------------------------------------------------
-- 1. CECILIAN XO API — per-tenant inventory feed configuration
-- ---------------------------------------------------------------------------
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS xo_api_base_url  TEXT,          -- e.g. 'https://hillwood.thexo.io/o/api/v2/map/consumer'
    ADD COLUMN IF NOT EXISTS xo_project_slug  TEXT,          -- e.g. 'harvest', 'treeline'
    ADD COLUMN IF NOT EXISTS xo_enabled       BOOLEAN NOT NULL DEFAULT FALSE;

-- ---------------------------------------------------------------------------
-- 2. HUBSPOT — lead routing configuration
-- ---------------------------------------------------------------------------
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS hubspot_portal_id TEXT,          -- HubSpot account/portal ID
    ADD COLUMN IF NOT EXISTS hubspot_form_id   TEXT,          -- Form GUID for submissions
    ADD COLUMN IF NOT EXISTS hubspot_api_key   TEXT;          -- Private app token for server-side API

-- ---------------------------------------------------------------------------
-- 3. COMMUNITY METADATA — tenant role and cross-community linking
-- ---------------------------------------------------------------------------
-- community_type defines this tenant's role in the portfolio:
--   'community'  → individual community site (Harvest, Treeline, etc.)
--   'parent'     → HillwoodCommunities.com concierge
--   'realtor'    → HillwoodLovesRealtors.com
--   'kiosk'      → on-property touchscreen (Greeting House, Explore)
--
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS community_type    TEXT DEFAULT 'standard'
        CHECK (community_type IN ('standard', 'community', 'parent', 'realtor', 'kiosk')),
    ADD COLUMN IF NOT EXISTS parent_tenant_id  TEXT REFERENCES tenants(id),  -- links kiosk → community, community → parent
    ADD COLUMN IF NOT EXISTS community_name    TEXT,          -- human-readable: "Harvest", "Treeline", etc.
    ADD COLUMN IF NOT EXISTS community_url     TEXT,          -- https://HarvestByHillwood.com
    ADD COLUMN IF NOT EXISTS community_location TEXT;         -- city/area for cross-community recs: "Argyle, TX"

-- Index for cross-community queries
CREATE INDEX IF NOT EXISTS idx_tenants_community_type ON tenants(community_type);
CREATE INDEX IF NOT EXISTS idx_tenants_parent ON tenants(parent_tenant_id);

COMMIT;
