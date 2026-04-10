-- ============================================================
-- RobChat Knowledge Base — Schema Plan
-- PostgreSQL Migration #003 (not yet applied)
-- ============================================================
-- Gives tenants a way to feed their business info to the bot
-- without writing complex system prompts. Three sources:
--   1. FAQ entries (manual Q&A pairs)
--   2. Documents (uploaded PDFs, text files)
--   3. Website pages (auto-scraped from their site)
--
-- The chat endpoint loads relevant KB content and injects it
-- into the system prompt dynamically per conversation.
-- ============================================================

BEGIN;

-- ---------------------------------------------------------------------------
-- 1. KNOWLEDGE BASE ENTRIES — the core content store
-- ---------------------------------------------------------------------------
-- Every piece of knowledge is a "chunk" with a title and content.
-- Chunks are searched by keyword match against the user's message
-- and injected into the system prompt as context.
--
-- source_type tells us where this chunk came from:
--   'faq'      → manually entered Q&A pair
--   'document' → extracted from an uploaded file
--   'webpage'  → scraped from a URL
--   'manual'   → freeform text the tenant typed in
--
CREATE TABLE kb_entries (
    id              SERIAL PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    source_type     TEXT NOT NULL CHECK (source_type IN ('faq', 'document', 'webpage', 'manual')),
    source_ref      TEXT,                   -- filename, URL, or null for manual

    -- Content
    title           TEXT,                   -- question (FAQ), page title, section heading
    content         TEXT NOT NULL,          -- the actual knowledge content
    keywords        TEXT[],                 -- extracted keywords for search matching

    -- Metadata
    category        TEXT,                   -- optional grouping: "pricing", "services", "hours", etc.
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order      INTEGER DEFAULT 0,      -- manual ordering within a category

    -- Tracking
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_kb_entries_tenant ON kb_entries(tenant_id);
CREATE INDEX idx_kb_entries_tenant_active ON kb_entries(tenant_id, is_active);
CREATE INDEX idx_kb_entries_category ON kb_entries(tenant_id, category);
CREATE INDEX idx_kb_entries_keywords ON kb_entries USING GIN(keywords);

CREATE TRIGGER kb_entries_updated_at
    BEFORE UPDATE ON kb_entries
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

-- ---------------------------------------------------------------------------
-- 2. KNOWLEDGE BASE SOURCES — tracks imported documents and scraped pages
-- ---------------------------------------------------------------------------
-- When a tenant uploads a PDF or scrapes a URL, we track it here
-- so they can re-scrape, delete all chunks from a source, etc.
--
CREATE TABLE kb_sources (
    id              SERIAL PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    source_type     TEXT NOT NULL CHECK (source_type IN ('document', 'webpage')),
    name            TEXT NOT NULL,          -- filename or page title
    url             TEXT,                   -- for webpages
    file_path       TEXT,                   -- for uploaded documents (server path)
    file_size       INTEGER,                -- bytes
    mime_type       TEXT,                   -- application/pdf, text/plain, etc.

    -- Processing status
    status          TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'completed', 'failed')),
    chunks_created  INTEGER DEFAULT 0,      -- how many kb_entries were generated
    error_message   TEXT,                   -- if processing failed
    last_processed  TIMESTAMPTZ,

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_kb_sources_tenant ON kb_sources(tenant_id);

-- ---------------------------------------------------------------------------
-- 3. TENANT SETTINGS — knowledge base config
-- ---------------------------------------------------------------------------
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS kb_enabled       BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS kb_max_context   INTEGER NOT NULL DEFAULT 3,    -- max chunks injected per message
    ADD COLUMN IF NOT EXISTS kb_match_threshold REAL NOT NULL DEFAULT 0.3;   -- keyword match sensitivity

COMMIT;


-- ============================================================
-- HOW KB INJECTION WORKS (pseudocode for chat.php)
-- ============================================================
--
-- 1. User sends message: "How much does a website cost?"
--
-- 2. Extract keywords from message:
--    → ["website", "cost", "price", "much"]
--
-- 3. Search kb_entries for this tenant:
--    SELECT * FROM kb_entries
--    WHERE tenant_id = :tid AND is_active = TRUE
--    AND keywords && ARRAY['website','cost','price','much']
--    ORDER BY array_length(
--      ARRAY(SELECT unnest(keywords) INTERSECT SELECT unnest(ARRAY['website','cost','price','much'])),
--      1
--    ) DESC
--    LIMIT 3;
--
-- 4. Build dynamic context block:
--    "=== BUSINESS KNOWLEDGE ===
--     Q: How much does a website cost?
--     A: Our starter sites begin at $2,500. Custom builds range from
--        $5,000-$15,000 depending on complexity...
--
--     Q: What's included in the price?
--     A: All packages include design, development, testing, and
--        30 days of post-launch support..."
--
-- 5. Prepend to system prompt:
--    system_prompt = tenant.system_prompt + "\n\n" + context_block
--
-- 6. Send to LLM as usual
--
-- ============================================================
-- DASHBOARD PAGES NEEDED
-- ============================================================
--
-- Knowledge Base (new main tab)
--   - FAQ Manager
--     - Add/edit/delete Q&A pairs
--     - Drag to reorder
--     - Categories for organization
--
--   - Documents
--     - Upload PDF, TXT, DOCX
--     - Shows processing status
--     - View extracted chunks, edit/delete
--
--   - Website Scraper
--     - Paste URL → scrape and extract content
--     - "Scrape entire site" option (crawls linked pages)
--     - Shows scraped pages list
--     - Re-scrape button per page
--
--   - Settings
--     - Toggle KB on/off
--     - Max context chunks per message
--     - Match sensitivity
--
-- ============================================================
-- FUTURE: VECTOR EMBEDDINGS
-- ============================================================
-- The keyword-based approach works well for v1 and doesn't
-- require any external services. For v2, we can add:
--
--   ALTER TABLE kb_entries ADD COLUMN embedding vector(1536);
--
-- Using pgvector extension + OpenAI embeddings API for
-- semantic search. This finds relevant content even when
-- the user's words don't exactly match the keywords.
-- But keyword matching is good enough to ship.
-- ============================================================
