-- Migration 017: Analytics schema — chat_analytics, chat_analytics_log, indexes, global settings
-- Run with: psql -U hwchat -d hwchat -f migrations/017_analytics_schema.sql

BEGIN;

-- ============================================================
-- 1. chat_analytics — one row per classified session
-- ============================================================

CREATE TABLE IF NOT EXISTS chat_analytics (
    id                  serial          PRIMARY KEY,
    session_id          text            NOT NULL UNIQUE REFERENCES sessions(id) ON DELETE CASCADE,
    tenant_id           text            NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    analyzed_at         timestamptz     NOT NULL DEFAULT now(),
    message_count       integer         NOT NULL,
    user_message_count  integer         NOT NULL,
    intent_level        text            NOT NULL CHECK (intent_level IN ('browsing', 'interested', 'ready_to_buy')),
    lead_captured       boolean         NOT NULL,
    tour_booked         boolean         NOT NULL,
    xo_tool_called      boolean         NOT NULL,
    cross_referrals     text[]          DEFAULT '{}',
    topics              text[]          DEFAULT '{}',
    price_range_min     integer,
    price_range_max     integer,
    bedrooms_requested  integer,
    builders_mentioned  text[]          DEFAULT '{}',
    objections          text[]          DEFAULT '{}',
    sentiment           text            NOT NULL CHECK (sentiment IN ('positive', 'neutral', 'negative')),
    summary             text            NOT NULL,
    session_started_at  timestamptz     NOT NULL,
    session_duration_sec integer
);

-- ============================================================
-- 2. chat_analytics_log — tracks nightly job runs
-- ============================================================

CREATE TABLE IF NOT EXISTS chat_analytics_log (
    id                  serial          PRIMARY KEY,
    run_at              timestamptz     NOT NULL DEFAULT now(),
    sessions_processed  integer         NOT NULL DEFAULT 0,
    sessions_skipped    integer         NOT NULL DEFAULT 0,
    errors              integer         NOT NULL DEFAULT 0,
    duration_sec        integer,
    error_details       jsonb           DEFAULT '[]'
);

-- ============================================================
-- 3. Indexes for chat_analytics
-- ============================================================

CREATE INDEX IF NOT EXISTS idx_chat_analytics_tenant_time
    ON chat_analytics (tenant_id, session_started_at DESC);

CREATE INDEX IF NOT EXISTS idx_chat_analytics_topics
    ON chat_analytics USING GIN (topics);

CREATE INDEX IF NOT EXISTS idx_chat_analytics_builders
    ON chat_analytics USING GIN (builders_mentioned);

CREATE INDEX IF NOT EXISTS idx_chat_analytics_objections
    ON chat_analytics USING GIN (objections);

CREATE INDEX IF NOT EXISTS idx_chat_analytics_intent
    ON chat_analytics (tenant_id, intent_level);

CREATE INDEX IF NOT EXISTS idx_chat_analytics_sentiment
    ON chat_analytics (tenant_id, sentiment);

-- ============================================================
-- 4. Global settings for analytics LLM configuration
-- ============================================================

INSERT INTO global_settings (key, value) VALUES
    ('analytics_llm_provider', 'openai'),
    ('analytics_api_key', ''),
    ('analytics_llm_model', 'gpt-4o')
ON CONFLICT (key) DO NOTHING;

COMMIT;
