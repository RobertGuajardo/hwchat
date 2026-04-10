# CLAUDE.md — HWChat (RobChat → Hillwood Fork)

## What This Is

HWChat is a multi-tenant AI chatbot platform built in plain PHP with PostgreSQL 16 + pgvector. Each Hillwood community (Harvest, Treeline, Pecan Square, etc.) is a tenant with its own branding, knowledge base, system prompt, and booking system. The widget is embedded via a `<script>` tag on each community's WordPress site.

This is a personal freelance project by Robert Guajardo. HWChat is the Hillwood-specific fork of the RobChat platform. The codebase is intentionally simple — no frameworks, no build tools beyond the widget bundler, no ORM.

## Production Environment

- **Host:** InMotion VPS (shared with other projects)
- **URL:** https://hwchat.robertguajardo.com
- **PHP:** 8.4
- **Database:** PostgreSQL 16 with pgvector extension
- **OS:** Ubuntu/Debian Linux
- **Domain routing:** Apache with .htaccess

## Project Structure

```
hwchat/
├── api/                    # JSON API endpoints
│   ├── bootstrap.php       # Config loader, DB connection, CORS, JSON helpers
│   ├── chat.php            # Main chat endpoint (LLM calls, tool use, action parsing)
│   ├── tenant-config.php   # Widget config endpoint (GET, public)
│   ├── capture-lead.php    # Lead form submission
│   ├── book.php            # Booking submission
│   ├── availability.php    # Calendar availability check
│   ├── health.php          # Health check
│   └── flush-cache.php     # Cache invalidation
├── lib/                    # Core classes
│   ├── Database.php        # Static PDO singleton — all DB queries
│   ├── CecilianXO.php      # Cecilian XO property API client (homes/homesites)
│   └── Embeddings.php      # OpenAI text-embedding-3-small → pgvector
├── dashboard/              # Admin panel (PHP + HTML)
│   ├── index.php           # Login page
│   ├── session.php         # Session management
│   ├── auth.php            # Authentication
│   ├── settings.php        # Tenant settings (XO, HubSpot, branding)
│   ├── knowledge-base.php  # KB management + website scraper
│   ├── leads.php           # Lead viewer
│   ├── bookings.php        # Booking viewer
│   └── super/              # Superuser admin
│       ├── tenants.php     # Multi-tenant management
│       ├── tenant-edit.php # Individual tenant config
│       ├── communities.php # Community directory
│       ├── master-prompt.php # Global system prompt
│       └── leads.php       # Cross-tenant lead viewer
├── widget/
│   └── robchat.js          # Bundled/minified chat widget (Shadow DOM, vanilla JS)
├── scripts/                # Maintenance & scraping
│   ├── scrape-wp-universal.php  # WordPress site scraper
│   ├── backfill-embeddings.php  # Regenerate embeddings
│   └── scrape-all.sh            # Batch scrape all communities
├── migrations/             # PostgreSQL schema (001–016, applied in order)
├── config.php              # LIVE CREDENTIALS — gitignored, never committed
├── config.example.php      # Template for config.php
├── .htaccess               # Apache routing
├── setup.sh                # One-command DB setup (runs all migrations)
└── deploy-webhook.php      # Git webhook for auto-deploy
```

## Architecture

### Request Flow (Chat)
1. Widget loads tenant config via `GET /api/tenant-config.php?id=hw_harvest`
2. User sends message → `POST /api/chat.php` with `tenant_id`, `session_id`, `message`, `history`
3. `bootstrap.php` loads config, connects to Postgres, handles CORS
4. `chat.php` loads tenant, checks rate limit, retrieves/creates session
5. If tenant has KB entries → pgvector similarity search for RAG context
6. If tenant has XO enabled → LLM gets `search_available_homes` tool definition
7. LLM call (OpenAI or Anthropic) with system prompt + KB context + conversation history
8. Tool-use loop: if LLM calls XO tool → execute search → return results → LLM generates final reply
9. Response parsed for `[ACTION:...]` blocks (show_lead_form, show_calendar, etc.)

### Multi-Tenancy Model
- Each tenant has a unique `id` (e.g., `hw_harvest`, `hw_treeline`)
- `community_type` field: `community`, `parent`, `realtor`, `kiosk`, `standard`
- Parent-child linking via `parent_tenant_id` (kiosk → community → parent)
- Per-tenant: system prompt, LLM keys, branding colors, allowed CORS origins, XO config, HubSpot config

### Widget Embedding
```html
<script src="https://hwchat.robertguajardo.com/widget/robchat.js"
        data-robchat-id="hw_harvest" defer></script>
```
Widget uses Shadow DOM for style isolation. All theming comes from tenant config API.

### Database
- **PostgreSQL 16** with **pgvector** for embeddings
- Tables: `tenants`, `sessions`, `messages`, `leads`, `bookings`, `kb_entries`, `kb_sources`, `availability_rules`, `availability_overrides`, `rate_limits`, `builders`
- Migrations in `/migrations/` (001–016), applied via `setup.sh`
- `Database.php` is a static class — no ORM, all raw SQL via PDO

### External Integrations
- **Cecilian XO API** — real-time property inventory (homes, homesites, builders)
- **OpenAI API** — chat completions + embeddings (text-embedding-3-small, 1536 dims)
- **Anthropic API** — chat completions (Claude)
- **HubSpot** — lead routing (portal ID, form ID, API key per tenant)

## Key Conventions

### Code Style
- Plain PHP — no frameworks, no Composer dependencies
- Static classes (`Database::`, `Embeddings::`, `CecilianXO` is instantiated per-tenant)
- All API endpoints return JSON via `jsonResponse()` / `jsonError()`
- CORS handled centrally in `bootstrap.php` with per-tenant origin lists
- Error logging to `chat_errors.log`

### Naming
- Tenant IDs: `hw_harvest`, `hw_treeline`, `hw_pecan_square`, etc.
- DB columns: `snake_case`
- PHP variables: `$camelCase`
- JS widget: `HWChat` namespace, CSS classes prefixed `rc-` (legacy from RobChat)
- Migration files: `NNN_description.sql`

### Security
- `config.php` is **gitignored** — contains DB password, OpenAI key, Anthropic key
- API keys can be stored per-tenant (encrypted in DB) or fall back to defaults in config
- Rate limiting per tenant (per-minute and per-hour, by IP hash)
- IP addresses are SHA-256 hashed, never stored raw
- CORS whitelist per tenant + global allowed origins

## Git Workflow

- `main` branch is the canonical source
- Feature work happens on branches: `feature/description` or `fix/description`
- Specs go in `/specs/` (see SPEC-TEMPLATE.md)
- Task breakdowns go in `/tasks/` (see TASK-DECOMPOSITION-TEMPLATE.md)
- Deploy to VPS via git pull or deploy webhook

## Active Hillwood Communities

Harvest, Treeline, Pecan Square, Union Park, Wolf Ranch, Valencia, Pomona, Lilyana, Ramble, Landmark, Legacy, Melina, plus the parent tenant (hillwoodcommunities.com).

## What NOT to Do

- **Never commit config.php** — it has live API keys and DB credentials
- **Never modify robchat.js directly** — it's a bundled/minified output
- **Never run migrations out of order** — they depend on each other
- **Never hardcode tenant-specific logic in chat.php** — use the tenant config / system prompt
- **Never add Composer or npm dependencies** — this project is intentionally dependency-free on the backend
