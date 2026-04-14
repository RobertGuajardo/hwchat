-- Migration 018: User accounts — users table, user_tenants junction, indexes, trigger
-- Run with: psql -U hwchat -d hwchat -f migrations/018_user_accounts.sql

BEGIN;

-- ============================================================
-- 1. users — decoupled identity (one login → many tenants)
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id              serial          PRIMARY KEY,
    email           text            NOT NULL UNIQUE,
    password_hash   text            NOT NULL,
    display_name    text            NOT NULL,
    role            text            NOT NULL DEFAULT 'tenant_admin'
                                    CHECK (role IN ('superadmin', 'tenant_admin', 'builder')),
    is_active       boolean         NOT NULL DEFAULT true,
    last_login_at   timestamptz,
    created_at      timestamptz     NOT NULL DEFAULT now(),
    updated_at      timestamptz     NOT NULL DEFAULT now()
);

-- ============================================================
-- 2. user_tenants — which tenants a user can access
-- ============================================================

CREATE TABLE IF NOT EXISTS user_tenants (
    id              serial          PRIMARY KEY,
    user_id         integer         NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tenant_id       text            NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    created_at      timestamptz     NOT NULL DEFAULT now(),
    UNIQUE (user_id, tenant_id)
);

-- ============================================================
-- 3. Indexes
-- ============================================================

CREATE INDEX IF NOT EXISTS idx_user_tenants_user
    ON user_tenants (user_id);

CREATE INDEX IF NOT EXISTS idx_user_tenants_tenant
    ON user_tenants (tenant_id);

-- ============================================================
-- 4. Trigger: auto-update updated_at on users
-- ============================================================

CREATE TRIGGER users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

COMMIT;
