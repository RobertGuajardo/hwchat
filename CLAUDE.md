# CLAUDE.md — HWChat (RobChat → Hillwood Fork)

## What This Is

HWChat is a multi-tenant AI chatbot platform built in plain PHP with PostgreSQL 16 + pgvector. Each Hillwood community (Harvest, Treeline, Pecan Square, etc.) is a tenant with its own branding, knowledge base, system prompt, and booking system. The widget is embedded via a `<script>` tag on each community's WordPress site.

This is a personal freelance project by Robert Guajardo. HWChat is the Hillwood-specific fork of the RobChat platform. The codebase is intentionally simple — no frameworks, no build tools beyond the widget bundler, no ORM.

Currently adding: role-based access expansion (superuser / regional admin / tenant) and conversation analytics.

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
│   ├── regions.php         # REGIONS constant + scope helpers (role-aware)
│   ├── CecilianXO.php      # Cecilian XO property API client (homes/homesites)
│   └── Embeddings.php      # OpenAI text-embedding-3-small → pgvector
├── dashboard/              # Admin panel (PHP + HTML)
│   ├── auth.php            # Auth helpers, login, session management, role checks
│   ├── includes/layout.php # Shared layout — role-based nav and dropdown
│   ├── index.php           # Overview page (scoped by role)
│   ├── session.php         # View single conversation
│   ├── settings.php        # Tenant settings (XO, HubSpot, branding)
│   ├── knowledge-base.php  # KB management + website scraper
│   ├── leads.php           # Lead viewer
│   ├── bookings.php        # Booking viewer
│   ├── analytics.php       # Analytics dashboard (scoped by role)
│   ├── api.php             # Dashboard AJAX handler
│   ├── api-analytics.php   # Analytics chart data endpoint (scoped by role)
│   ├── api/set-scope.php   # Scope switch endpoint
│   ├── export-analytics.php # CSV export (scoped by role)
│   ├── switch-tenant.php   # Multi-tenant switcher
│   ├── tenant-prompts.php  # Read-only tenant prompts (regional_admin)
│   └── super/              # Superuser admin
│       ├── tenants.php     # Multi-tenant management (scoped by role)
│       ├── tenant-edit.php # Individual tenant config (includes region dropdown)
│       ├── communities.php # Community directory (scoped by role)
│       ├── master-prompt.php # Global system prompt (superadmin only)
│       └── leads.php       # Cross-tenant lead viewer (scoped by role)
├── widget/
│   └── robchat.js          # Bundled/minified chat widget (Shadow DOM, vanilla JS)
├── scripts/                # Maintenance & scraping
│   ├── analytics-tagger.php # Nightly analytics job
│   ├── scrape-wp-universal.php  # WordPress site scraper
│   ├── backfill-embeddings.php  # Regenerate embeddings
│   └── scrape-all.sh            # Batch scrape all communities
├── migrations/             # PostgreSQL schema (001–021, applied in order)
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
- `region` column (nullable) ties community tenants to a geographic region (DFW / Houston / Austin) — used for analytics grouping and regional admin scoping. NULL region = excluded from scope system.

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
- Migrations in `/migrations/` (001–020 applied, 021 next available)
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
- Next available number: 021
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

## Auth Model

### Roles (4 tiers)
| Role | Slug | Scope | Access |
|------|------|-------|--------|
| Superuser | `superadmin` | All tenants, all regions | Everything — all pages, all data, user management |
| Regional Admin | `regional_admin` | Tenants in their assigned region | Region-scoped pages, read-only tenant prompts, no master prompt |
| Tenant | `tenant_admin` | Assigned tenants only | Their community's dashboard, analytics, leads, bookings, settings, KB |
| Builder | `builder` | Assigned tenants only | Calendar, bookings, and analytics only |

### Key Auth Functions (dashboard/auth.php)
- `requireAuth()` — redirect to login if not authenticated
- `requireSuperAdmin()` — redirect if not superadmin
- `requireMinRole(string $minRole)` — redirect if below minimum role level
- `isSuperAdmin()` — check role
- `isRegionalAdmin()` — check role
- `getUserRegion()` — returns region slug for regional_admin, null for others
- `canAccessAnalytics()` — true for superadmin, regional_admin, tenant_admin, and builder
- `isBuilder()` — true for builder role
- `canAccessPage(string $page)` — checks role matrix for page access
- `getUserTenants()` — array of tenants the logged-in user can access
- `getActiveTenantId()` — currently selected tenant
- `switchTenant($tenantId)` — change active tenant (validates against user's scope)

### Login Session Variables
```php
$_SESSION['user_id']       // int
$_SESSION['user_email']    // string
$_SESSION['user_name']     // string
$_SESSION['user_role']     // 'superadmin' | 'regional_admin' | 'tenant_admin' | 'builder'
$_SESSION['user_region']   // 'dfw' | 'houston' | 'austin' | null
$_SESSION['user_tenants']  // array of tenant IDs
$_SESSION['scope_type']    // 'all' | 'tenant'
$_SESSION['scope_value']   // null | '{tenant id}'
```

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
- **Never** allow builder role to access leads, settings, knowledge base, users, or tenants pages/endpoints
- **Never** allow regional_admin to access master prompt or data outside their region
- **Never** let `switchTenant()` accept a tenant ID that's not in the user's allowed scope
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
- Scope database queries using `buildScopeWhereClause()` — it handles role-based filtering automatically
- Use `ON CONFLICT` for idempotent inserts (especially in migrations and the analytics tagger)
- Hash IPs with SHA-256 before storing (`hash('sha256', $ip)`)
- Check page access with `canAccessPage()` or role-specific guards on every page

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

## Scope Selector & Region Infrastructure (COMPLETE)

**Migration:** 020 (applied)

The superadmin topbar dropdown has an "All Communities" option that shows aggregate data across all community tenants. Dropdown is a flat list — no region grouping. Region column on tenants table ties each community to DFW / Houston / Austin for analytics filtering. Tenants with NULL region are excluded from the scope system.

**Key files:** `lib/regions.php`, `dashboard/api/set-scope.php`, `dashboard/includes/layout.php`

**Session:** `$_SESSION['scope_type']` = `all` | `tenant`, `$_SESSION['scope_value']` = null | tenant id

**Code pattern:** Always use `buildScopeWhereClause()` from `lib/regions.php` for scope filtering.

## Role-Based Access Expansion (IN PROGRESS)

**Spec:** `specs/SPEC-ROLE-EXPANSION.md`
**Tasks:** `tasks/TASKS-ROLE-EXPANSION.md`
**Migration:** 021

### What This Feature Does

Adds `regional_admin` role between superadmin and tenant_admin. Every dashboard page is scoped by role. Regional admins see only their region's data. The Tenant View toggle is removed — replaced by real role-based access.

### Role Matrix

| Page | superadmin | regional_admin | tenant_admin | builder |
|------|-----------|----------------|-------------|---------|
| Overview | All tenants | Region tenants | Own tenant | No access |
| Tenants | All | Region only | No access | No access |
| Communities | All | Region only | No access | No access |
| Master Prompt | Edit | No access | No access | No access |
| Tenant Prompts | All (read) | Region (read) | No access | No access |
| Leads | All + Community col | Region + Community col | Own tenant | No access |
| Analytics | All/Region/Tenant | Region/Tenant | Own tenant | Own tenant |
| Users | All | Region only | No access | No access |
| Settings | All tenants | Region tenants | Own tenant | No access |
| Knowledge Base | All tenants | Region tenants | Own tenant | No access |
| Bookings | All tenants | Region tenants | Own tenant | Own tenant |

### Dropdown by Role

- **Superadmin:** "All Communities" + all 14 community tenants
- **Regional Admin:** "All [Region Name]" + tenants in their region
- **Tenant Admin:** their assigned tenant(s)
- **Builder:** no dropdown

### buildScopeWhereClause() is role-aware

The scope helper automatically restricts based on role and region:
- Superadmin "all" → WHERE region IS NOT NULL
- Regional admin "all" → WHERE tenant_id IN (tenants in their region)
- Tenant admin → WHERE tenant_id IN (assigned tenants)

## Current Feature Work

- **Feature Spec:** SPEC-ROLE-EXPANSION.md (Role-Based Access)
- **Task Decomposition:** TASKS-ROLE-EXPANSION.md
- **Current Phase:** Starting Phase A (Database & Auth Foundation)

## Testing Approach

- No automated test framework in place — manual verification
- CLI scripts: run with `--dry-run` flag first, check output, then run for real
- Migrations: run against dev database first, verify with `\d table_name` in psql
- Dashboard: manual click-through after each task
- Analytics data: validate with SQL queries after backfill
