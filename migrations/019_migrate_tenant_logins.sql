-- Migration 019: Migrate tenant logins into users table
-- Run with: psql -U hwchat -d hwchat -f migrations/019_migrate_tenant_logins.sql
--
-- This is additive — no existing tenant columns are modified or dropped.
-- tenants.email, tenants.password_hash, and tenants.role are retained during
-- the transition period. Do NOT drop them until the new auth flow in
-- dashboard/auth.php is confirmed working in production.

BEGIN;

-- ============================================================
-- 1. Copy tenant credentials into users
-- ============================================================

INSERT INTO users (email, password_hash, display_name, role)
SELECT email, password_hash, display_name, role
FROM tenants
WHERE email IS NOT NULL AND password_hash IS NOT NULL
ON CONFLICT (email) DO NOTHING;

-- ============================================================
-- 2. Link each migrated user to their original tenant
-- ============================================================

INSERT INTO user_tenants (user_id, tenant_id)
SELECT u.id, t.id
FROM users u
JOIN tenants t ON t.email = u.email
ON CONFLICT (user_id, tenant_id) DO NOTHING;

COMMIT;
