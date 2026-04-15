# CLAUDE.md — HWChat (RobChat → Hillwood Fork)

## What This Is

HWChat is a multi-tenant AI chatbot platform built in plain PHP with PostgreSQL 16 + pgvector. Each Hillwood community (Harvest, Treeline, Pecan Square, etc.) is a tenant with its own branding, knowledge base, system prompt, and booking system. The widget is embedded via a `<script>` tag on each community's WordPress site.

This is a personal freelance project by Robert Guajardo. HWChat is the Hillwood-specific fork of the RobChat platform. The codebase is intentionally simple — no frameworks, no build tools beyond the widget bundler, no ORM.

Currently adding: conversation analytics (nightly LLM tagging, dashboard with charts, CSV export) and multi-user accounts with role-based access.

## Production Environment

- **Host:** InMotion VPS (shared with other projects)
- **URL:** https://hwchat.robertguajardo.com
- **PHP:** 8.4
- **Database:** PostgreSQL 16 with pgvector extension
- **OS:** Ubuntu/Debian Linux
- **Domain routing:** Apache with .htaccess
- **LLM Providers:** OpenAI and Anthropic (configurable per tenant)
- **Charts:** Chart.js via CDN (no build step)

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
│   ├── LLMClassifier.php   # Analytics LLM classification helper
│   ├── regions.php         # REGIONS constant + scope helpers (scope selector feature)
│   ├── CecilianXO.php      # Cecilian XO property API client (homes/homesites)
│   └── Embeddings.php      # OpenAI text-embedding-3-small → pgvector
├── dashboard/              # Admin panel (PHP + HTML)
│   ├── auth.php            # Auth helpers, login, session management
│   ├── includes/layout.php # Shared layout (renderHead, renderNav, renderFooter)
│   ├── index.php           # Overview page
│   ├── session.php         # View single conversation
│   ├── settings.php        # Tenant settings (XO, HubSpot, branding)
│   ├── knowledge-base.php  # KB management + website scraper
│   ├── leads.php           # Lead viewer
│   ├── bookings.php        # Booking viewer
│   ├── analytics.php       # Analytics dashboard
│   ├── api.php             # Dashboard AJAX handler
│   ├── api-analytics.php   # Analytics chart data endpoint
│   ├── api/set-scope.php   # Scope switch endpoint (scope selector feature)
│   ├── export-analytics.php # CSV export
│   ├── switch-tenant.php   # Multi-tenant switcher
│   └── super/              # Superuser admin
│       ├── tenants.php     # Multi-tenant management
│       ├── tenant-edit.php # Individual tenant config (includes region dropdown)
│       ├── communities.php # Community directory
│       ├── master-prompt.php # Global system prompt
│       └── leads.php       # Cross-tenant lead viewer (scope-aware)
├── widget/
│   └── robchat.js          # Bundled/minified chat widget (Shadow DOM, vanilla JS)
├── scripts/                # Maintenance & scraping
│   ├── analytics-tagger.php # Nightly analytics job
│   ├── scrape-wp-universal.php  # WordPress site scraper
│   ├── backfill-embeddings.php  # Regenerate embeddings
│   └── scrape-all.sh            # Batch scrape all communities
├── migrations/             # PostgreSQL schema (001–020, applied in order)
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
- Each tenant has a unique `id` column (PK) — e.g. `hw_harvest`, `hw_treeline`
- Other tables reference tenants via `tenant_id` foreign key columns
- `community_type` field: `community`, `parent`, `realtor`, `kiosk`, `standard`
- Parent-child linking via `parent_tenant_id` (kiosk → community → parent)
- Per-tenant: system prompt, LLM keys, branding colors, allowed CORS origins, XO config, HubSpot config
- `region` column (nullable) ties community tenants to a geographic region (DFW / Houston / Austin) — used for analytics grouping, not navigation. NULL region = excluded from scope system.

### Widget Embedding
```html
<script src="https://hwchat.robertguajardo.com/widget/robchat.js"
        data-robchat-id="hw_harvest" defer></script>
```
Widget uses Shadow DOM for style isolation. All theming comes from tenant config API.

### Database
- **PostgreSQL 16** with **pgvector** for embeddings
- Tables: `tenants`, `sessions`, `messages`, `leads`, `bookings`, `kb_entries`, `kb_sources`, `availability_rules`, `availability_overrides`, `rate_limits`, `builders`, `chat_analytics`, `chat_analytics_log`, `users`, `user_tenants`, `global_settings`
- **Important:** Tenants table PK is `id` (not `tenant_id`). Other tables use `tenant_id` as the FK.
- Migrations in `/migrations/` (001–019 applied, 020 next available)
- `Database.php` is a static class — no ORM, all raw SQL via PDO

### External Integrations
- **Cecilian XO API** — real-time property inventory (homes, homesites, builders)
- **OpenAI API** — chat completions + embeddings (text-embedding-3-small, 1536 dims)
- **Anthropic API** — chat completions (Claude)
- **HubSpot** — lead routing (portal ID, form ID, API key per tenant)

## Code Patterns

### Database Methods
All database operations go through `lib/Database.php` as static methods:
```php
public static function methodName(string $param): ?array {
    $stmt = self::db()->prepare('SELECT ... WHERE col = :param');
    $stmt->execute(['param' => $param]);
    return $stmt->fetch(); // or fetchAll()
}
```
- Always use PDO prepared statements with named parameters
- Return arrays (fetchAll) or nullable arrays (fetch) or void
- Group methods under comment headers: `// ------- SECTION NAME -------`

### Migrations
Plain SQL files in `migrations/`, numbered sequentially:
```sql
-- Migration NNN: Description
-- Run with: psql -U hwchat -d hwchat -f migrations/NNN_name.sql

ALTER TABLE ...;
CREATE TABLE ...;
INSERT INTO ... ON CONFLICT ... DO NOTHING;
```
- Next available number: 020
- Use `IF NOT EXISTS` for CREATE TABLE, `ON CONFLICT DO NOTHING` for INSERT
- Include the run command in the header comment

### Dashboard Pages
```php
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireAuth();

// Query data...
$db = Database::db();

renderHead('Page Title');
renderNav('active_tab_name');
?>
    <main class="container">
        <!-- Page content -->
    </main>
<?php renderFooter(); ?>
```
- Auth check at the top of every page
- Use `e()` for HTML escaping (defined in auth.php)
- Use existing CSS variables: `var(--bg-card)`, `var(--text)`, `var(--border)`, `var(--blue)`, `var(--gold)`, etc.
- Three themes supported: hillwood (default navy), light, dark

### CLI Scripts
```php
<?php
require_once __DIR__ . '/../lib/Database.php';
$config = require __DIR__ . '/../config.php';
Database::connect($config);
$db = Database::db();

// Parse CLI args
foreach ($argv as $arg) {
    if (strpos($arg, '--flag=') === 0) {
        $value = substr($arg, 7);
    }
}

// Progress output
echo "[$n/$total] Processing: $label ... OK\n";
```
- Load config relative to script location
- Parse args from `$argv`
- Print progress to stdout, errors to STDERR
- Rate limit API calls (sleep between batches)

### API Endpoints
```php
<?php
require_once __DIR__ . '/bootstrap.php';
// bootstrap.php loads config, connects DB, sets up CORS, provides jsonResponse/jsonError/getJsonInput
```

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

## Auth Model (Transitioning)

**Current (legacy):** Login authenticates against `tenants.email` + `tenants.password_hash`. Session stores `tenant_id`, `tenant_email`, `tenant_name`, `tenant_role`.

**New (in progress):** Login authenticates against `users.email` + `users.password_hash`. Session stores `user_id`, `user_email`, `user_name`, `user_role`, `user_tenants[]`, plus `tenant_id`/`tenant_name` for the active tenant.

**During transition:** `attemptLogin()` tries the users table first, falls back to the tenants table. Both paths set the same backward-compatible session vars so existing pages don't break.

### Roles
| Role | Scope | Access |
|------|-------|--------|
| superadmin | All tenants | Everything — dashboard, analytics, settings, user management |
| tenant_admin | Assigned tenants | Dashboard, analytics, leads, bookings, settings, knowledge base |
| builder | Assigned tenants | Calendar and bookings only |

### Key Auth Functions (dashboard/auth.php)
- `requireAuth()` — redirect to login if not authenticated
- `requireSuperAdmin()` — redirect if not superadmin
- `isSuperAdmin()` — check role
- `canAccessAnalytics()` — true for superadmin and tenant_admin, false for builder
- `isBuilder()` — true for builder role
- `getUserTenants()` — array of tenants the logged-in user can access
- `getActiveTenantId()` — currently selected tenant
- `switchTenant($tenantId)` — change active tenant (validates against user's assigned tenants)

## Security Rules

### NEVER DO
- **Never** log, echo, print, or expose passwords, password hashes, or API keys in:
  - CLI script output or progress messages
  - JSON API responses
  - HTML pages or error messages
  - Log files (use generic "auth failed" not "password mismatch for hash $2y$...")
- **Never** concatenate user input into SQL — always use PDO prepared statements with bound parameters
- **Never** use MD5, SHA1, or plain text for passwords — always `password_hash($password, PASSWORD_BCRYPT)`
- **Never** trust client-side role claims — validate `$_SESSION['user_role']` server-side on every request
- **Never** expose `global_settings` API keys in frontend responses
- **Never** allow builder role to access analytics, settings, leads, or knowledge base pages/endpoints
- **Never** let `switchTenant()` accept a tenant ID that's not in the user's assigned tenant list
- **Never** commit `config.php` — it contains database credentials and API keys
- **Never** modify robchat.js directly — it's a bundled/minified output
- **Never** run migrations out of order — they depend on each other
- **Never** hardcode tenant-specific logic in chat.php — use the tenant config / system prompt
- **Never** add Composer or npm dependencies — this project is intentionally dependency-free on the backend
- **Never** build scope SQL manually — always use `buildScopeWhereClause()` from `lib/regions.php`
- **Never** use `tenant_id` when querying the tenants table directly — the PK column is `id`

### ALWAYS DO
- Validate auth and role at the top of every dashboard page and API endpoint
- Use `e()` (htmlspecialchars) when outputting user data in HTML
- Use `getIpHash()` for rate limiting — never store raw IPs
- Scope database queries by tenant_id for non-superadmin users
- Use `ON CONFLICT` for idempotent inserts (especially in migrations and the analytics tagger)
- Hash IPs with SHA-256 before storing (`hash('sha256', $ip)`)
- Check `canAccessAnalytics()` on every analytics page AND every analytics API endpoint — both

### API Key Handling
- LLM API keys for the analytics job are stored in `global_settings` table (not config.php)
- Tenant-specific LLM keys are stored in the `tenants` table
- The `config.php` file has default fallback keys
- None of these should ever appear in API responses, dashboard HTML, or CLI output

## Git Workflow

- `main` branch is the canonical source
- Feature work happens on branches: `feature/description` or `fix/description`
- Specs go in `/specs/` (see SPEC-TEMPLATE.md)
- Task breakdowns go in `/tasks/` (see TASK-DECOMPOSITION-TEMPLATE.md)
- Deploy to VPS via git pull or deploy webhook

## Active Hillwood Communities

Harvest, Treeline, Pecan Square, Union Park, Wolf Ranch, Valencia, Pomona, Lilyana, Ramble, Landmark, Legacy, Melina, plus the parent tenant (hillwoodcommunities.com) and Hillwood Loves Realtors.

Non-community tenants (excluded from scope system): demo_001 (Acme AI Assistant), hw_superadmin (Hillwood Admin).

## Scope Selector & Region Infrastructure

**Spec:** `specs/SPEC-REGION-SCOPING.md`
**Tasks:** `tasks/TASKS-REGION-SCOPING.md`
**Migration:** 020

### What This Feature Does

Adds an "All Communities" option to the superadmin topbar dropdown so aggregate data can be viewed across all community tenants. The dropdown stays flat — no region grouping in navigation. Separately, a `region` column on the `tenants` table ties each community to a geographic region (DFW / Houston / Austin) as infrastructure for the Phase 2 analytics dashboard, where region will be a filter/sort dimension.

### Two Separate Concerns

1. **Dropdown & scope** — "All Communities" + flat list of individual tenants (only those with non-null region). Two scope types: `all` and `tenant`. No region-level scoping in the dropdown.
2. **Region data layer** — `region` column on `tenants` (nullable), REGIONS constant, region dropdown on tenant-edit. This exists for analytics, not for the topbar.

### Region Mapping (confirmed from DB)

| id | display_name | region |
|----|-------------|--------|
| hw_harvest | Harvest by Hillwood | dfw |
| hw_treeline | Treeline by Hillwood | dfw |
| hw_pecan_square | Pecan Square by Hillwood | dfw |
| hw_union_park | Union Park by Hillwood | dfw |
| hw_lilyana | Lilyana by Hillwood | dfw |
| hw_landmark | Landmark by Hillwood | dfw |
| hw_ramble | Ramble by Hillwood | dfw |
| hw_parent | Hillwood Communities | dfw |
| hw_realtors | Hillwood Loves Realtors | dfw |
| hw_pomona | Pomona by Hillwood | houston |
| hw_legacy | Legacy by Hillwood | houston |
| hw_valencia | Valencia by Hillwood | houston |
| hw_wolf_ranch | Wolf Ranch by Hillwood | austin |
| hw_melina | Melina by Hillwood | austin |
| demo_001 | Acme AI Assistant | NULL |
| hw_superadmin | Hillwood Admin | NULL |

### Nullable Region Logic

- `region IS NOT NULL` = community tenant, participates in scope system, appears in dropdown and aggregates
- `region IS NULL` = excluded from everything — dropdown, aggregate views, analytics
- "All Communities" scope means `WHERE tenant_id IN (SELECT id FROM tenants WHERE region IS NOT NULL)` — not literally all rows

### Key Files (Scope Selector)

| File | Purpose |
|------|---------|
| `lib/regions.php` | REGIONS constant, scope helpers (`getScopedTenantIds`, `buildScopeWhereClause`, `getScopeLabel`) |
| `migrations/020-add-region-column.sql` | Adds `region TEXT DEFAULT NULL` to tenants table with CHECK constraint |
| `dashboard/includes/layout.php` | "All Communities" added to topbar dropdown + header label fix |
| `dashboard/api/set-scope.php` | Endpoint to update `$_SESSION` scope on dropdown selection |
| `dashboard/super/tenant-edit.php` | Region dropdown added to tenant settings |
| `dashboard/super/leads.php` | Scope-aware filtering + Community column when viewing all |

### Session Variables (Scope)

```php
$_SESSION['scope_type']  = 'all' | 'tenant';
$_SESSION['scope_value'] = null | '{tenant id}';  // e.g. 'hw_harvest'
```

Superadmin login defaults to `scope_type = 'all'`.

### Code Patterns for Scope

Always use `buildScopeWhereClause()` for scope filtering — never build scope SQL manually:

```php
require_once __DIR__ . '/../lib/regions.php';
$scope = buildScopeWhereClause('l');  // 'l' = table alias for leads
$sql = "SELECT l.* FROM leads l WHERE 1=1 {$scope['clause']} ORDER BY l.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($scope['params']);
```

Note: `buildScopeWhereClause()` targets the `tenant_id` FK column on the aliased table (not the tenants table `id` column directly).

### Constraints (Scope Selector)

- Region column has a CHECK constraint: `region IN ('dfw', 'houston', 'austin')` — NULL is allowed (passes CHECK by default in PostgreSQL)
- Adding a new region requires: (1) ALTER the CHECK constraint, (2) add to REGIONS constant
- Scope dropdown is superadmin-only — tenant_admin users never see it
- Tenant View mode requires a specific tenant selected (cannot impersonate "all")
- Region does NOT affect the dropdown or scope system — it is metadata for analytics only

### Known Gotcha

The superadmin header in `layout.php` topbar-left currently shows the first tenant's name instead of a generic label. Task C.2 fixes this to show `getScopeLabel()` — either "Hillwood AI Chatbot" (all) or the tenant display name.

## Current Feature Work

- **Feature Spec:** SPEC-REGION-SCOPING.md (Scope Selector & Region Infrastructure)
- **Task Decomposition:** TASKS-REGION-SCOPING.md
- **Current Phase:** Starting Phase A (Database & Region Infrastructure)

## Testing Approach

- No automated test framework in place — manual verification
- CLI scripts: run with `--dry-run` flag first, check output, then run for real
- Migrations: run against dev database first, verify with `\d table_name` in psql
- Dashboard: manual click-through after each task
- Analytics data: validate with SQL queries after backfill
