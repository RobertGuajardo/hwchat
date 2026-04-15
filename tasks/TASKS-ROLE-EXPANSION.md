# Tasks: Role-Based Access — Superuser / Regional Admin / Tenant

> **Spec:** `specs/SPEC-ROLE-EXPANSION.md`
> **Date:** April 14, 2026
> **Branch:** `feature/role-expansion`

---

## Before Starting

- [ ] Spec is done — open questions resolved
- [ ] Branch created: `git checkout -b feature/role-expansion`
- [ ] CLAUDE.md updated with Role Expansion section
- [ ] Scope selector feature is deployed and working (migration 020 applied)
- [ ] Backup taken: `pg_dump -U hwchat -d hwchat --schema-only -f hwchat_schema_pre021.sql`

---

## Phase A — Database & Auth Foundation

### Task A.1: Migration 021 — Add regional_admin role and region column to users

**What:** Create and apply `migrations/021-role-expansion.sql`. Adds `regional_admin` to the role CHECK constraint and adds a `region` column to the users table.

**Steps:**
1. Drop existing CHECK constraint on `users.role`
2. Re-create CHECK constraint: `role IN ('superadmin', 'regional_admin', 'tenant_admin', 'builder')`
3. Add column: `ALTER TABLE users ADD COLUMN IF NOT EXISTS region TEXT DEFAULT NULL;`
4. Add CHECK constraint on users.region: `ALTER TABLE users ADD CONSTRAINT chk_user_region CHECK (region IN ('dfw', 'houston', 'austin'));`
5. Apply: `psql -U hwchat -d hwchat -W -f migrations/021-role-expansion.sql`

**Verify:**
- [ ] `\d users` shows `region TEXT` column with `chk_user_region` constraint
- [ ] Role CHECK constraint includes `regional_admin`
- [ ] Can INSERT a user with `role='regional_admin', region='houston'`
- [ ] Can INSERT a user with `role='regional_admin', region=NULL` (allowed but sees nothing)
- [ ] Cannot INSERT a user with `role='regional_admin', region='invalid'`

---

### Task A.2: Update auth.php with regional_admin helpers

**What:** Add role-checking functions and update existing auth helpers for the new role.

**Steps:**
1. Add `isRegionalAdmin(): bool` — checks `$_SESSION['user_role'] === 'regional_admin'`
2. Add `getUserRegion(): ?string` — returns `$_SESSION['user_region']` or null
3. Add `requireMinRole(string $minRole): void` — redirects if user's role is below the minimum (superadmin > regional_admin > tenant_admin > builder)
4. Update login handler to store `$_SESSION['user_region']` from `users.region` column
5. Add `canAccessPage(string $page): bool` — returns whether the current role can access a given page name (uses the role matrix from the spec)

**Verify:**
- [ ] `php -l dashboard/auth.php` → no syntax errors
- [ ] `isRegionalAdmin()` returns true for regional_admin role
- [ ] `getUserRegion()` returns the region slug from session
- [ ] Login as regional_admin stores region in session

---

### Task A.3: Make buildScopeWhereClause() role-aware

**What:** Update `lib/regions.php` so scope helpers automatically restrict based on the user's role and region.

**Steps:**
1. `buildScopeWhereClause()` logic:
   - Superadmin + scope "all" → `WHERE region IS NOT NULL` (current behavior)
   - Superadmin + scope "tenant" → `WHERE tenant_id = :tid` (current behavior)
   - Regional admin + scope "all" → `WHERE tenant_id IN (SELECT id FROM tenants WHERE region = :user_region)`
   - Regional admin + scope "tenant" → `WHERE tenant_id = :tid` (but validate tenant is in their region)
   - Tenant admin → `WHERE tenant_id IN (user's assigned tenants)`
   - Builder → `WHERE tenant_id IN (user's assigned tenants)`
2. `getScopedTenantIds()` — same logic, returns array of IDs
3. `getScopeLabel()` — returns "All Communities" (superadmin all), "All Houston" (regional_admin all), or tenant display_name

**Verify:**
- [ ] Superadmin "all" → returns clause filtering to non-null region tenants
- [ ] Regional admin (houston) "all" → returns clause filtering to Houston tenants only
- [ ] Regional admin (houston) selecting a DFW tenant → rejected or filtered out
- [ ] Tenant admin → returns clause for their assigned tenants

---

## Phase B — Page Access & Navigation

### Task B.1: Update layout.php — role-based navigation and dropdown

**What:** Render different nav items and dropdown based on role. Remove Tenant View toggle.

**Steps:**
1. Remove the Tenant View / Admin Panel toggle entirely
2. Superadmin nav: Overview, Tenants, Communities, Master Prompt, Leads, Analytics, Users, Settings, Knowledge Base, Bookings
3. Regional admin nav: Overview, Tenants, Communities, Tenant Prompts, Leads, Analytics, Users, Settings, Knowledge Base, Bookings (no Master Prompt)
4. Tenant admin nav: Overview, Leads, Bookings, Knowledge Base, Settings, Analytics
5. Builder nav: Bookings, Analytics
6. Dropdown by role:
   - Superadmin: "All Communities" + all 14 community tenants
   - Regional admin: "All [Region Name]" + tenants in their region
   - Tenant admin: their assigned tenant(s)
   - Builder: no dropdown
7. Header label: "Hillwood AI Chatbot" (superadmin all), "[Region] Communities" (regional_admin all), tenant display_name (specific tenant)

**Verify:**
- [ ] Login as superadmin → full nav, full dropdown, no Tenant View toggle
- [ ] Login as regional_admin (houston) → restricted nav, dropdown shows "All Houston" + 3 tenants
- [ ] Login as tenant_admin → current nav, no toggle
- [ ] Login as builder → only Bookings and Analytics in nav
- [ ] Tenant View toggle is gone for all roles

---

### Task B.2: Add page-level access guards

**What:** Every dashboard page checks role access at the top. Unauthorized access redirects.

**Steps:**
1. Super-only pages (`master-prompt.php`, `super/tenants.php` [superadmin-only features]): add `requireSuperAdmin()` or equivalent
2. Regional-and-above pages: add `requireMinRole('regional_admin')` check
3. Tenant-and-above pages: existing `requireAuth()` suffices
4. Builder pages: bookings + analytics only — other pages redirect
5. For regional_admin accessing `super/` pages: scope queries to their region, not all tenants
6. Direct URL access to restricted pages → redirect to overview with flash message or just redirect

**Verify:**
- [ ] Regional admin accessing `/dashboard/super/master-prompt.php` → redirected
- [ ] Builder accessing `/dashboard/leads.php` → redirected
- [ ] Regional admin accessing `/dashboard/super/tenants.php` → sees only their region's tenants
- [ ] All redirects are server-side, not client-side

---

## Phase C — Page Scoping

### Task C.1: Scope Overview page by role

**What:** Overview stats respect the user's role scope.

**Steps:**
1. Use `buildScopeWhereClause()` for all stat queries on the overview page
2. Superadmin: aggregate across all community tenants
3. Regional admin: aggregate across their region's tenants only
4. Tenant admin: their tenant only (current behavior)

**Verify:**
- [ ] Superadmin overview → stats for all 14 communities
- [ ] Regional admin (houston) overview → stats for Pomona, Legacy, Valencia only
- [ ] Tenant admin → stats for their community only

---

### Task C.2: Scope Tenants and Communities pages by role

**What:** Tenants and Communities pages filter by the user's region when role is regional_admin.

**Steps:**
1. Use `buildScopeWhereClause()` or direct region filtering when querying tenants list
2. Superadmin: sees all tenants/communities
3. Regional admin: sees only tenants in their region

**Verify:**
- [ ] Superadmin → all tenants listed
- [ ] Regional admin (houston) → only Pomona, Legacy, Valencia listed
- [ ] Regional admin cannot see DFW or Austin tenants

---

### Task C.3: Scope Leads page by role

**What:** Leads page already has scope-awareness from the scope selector. Ensure it respects role-based scoping.

**Steps:**
1. Verify `buildScopeWhereClause()` is used (should be from scope selector work)
2. Community column shows for multi-tenant views (superadmin all, regional_admin all)
3. CSV export respects role scoping

**Verify:**
- [ ] Superadmin "All Communities" → all leads with Community column
- [ ] Regional admin "All Houston" → Houston leads only with Community column
- [ ] Regional admin cannot see DFW leads via URL params

---

### Task C.4: Scope Analytics by role with region filter

**What:** Analytics page gets a region filter for superadmin. Regional admin is automatically scoped. Builder gets access.

**Steps:**
1. Superadmin analytics: add filter buttons/dropdown for All / DFW / Houston / Austin / individual tenant
2. Regional admin analytics: default to "All [Region]", can filter to individual tenant in their region
3. Tenant admin analytics: their tenant only (current behavior)
4. Builder analytics: their tenant only (new — remove the `canAccessAnalytics()` block for builders or update it)
5. Update `api-analytics.php` to respect role scoping
6. Update `export-analytics.php` to respect role scoping

**Verify:**
- [ ] Superadmin → analytics filter shows All / DFW / Houston / Austin / per-tenant
- [ ] Regional admin (houston) → analytics shows All Houston / Pomona / Legacy / Valencia
- [ ] Builder → analytics page accessible, shows their community's data
- [ ] CSV export respects the role-based filter

---

### Task C.5: Scope Users page by role

**What:** Regional admin sees only users assigned to tenants in their region.

**Steps:**
1. Query users JOIN user_tenants JOIN tenants WHERE tenants.region = user's region
2. Regional admin can only add/edit users with tenant assignments in their region
3. Regional admin cannot assign a user to a tenant outside their region
4. Superadmin: current behavior — sees all users

**Verify:**
- [ ] Superadmin → all users listed
- [ ] Regional admin (houston) → only users assigned to Houston tenants
- [ ] Regional admin cannot add a user and assign them to a DFW tenant

---

### Task C.6: Create Tenant Prompts page for regional_admin

**What:** New page (`dashboard/super/tenant-prompts.php` or `dashboard/tenant-prompts.php`) showing read-only system prompts for communities in the regional_admin's region.

**Steps:**
1. Create page with auth check: `requireMinRole('regional_admin')`
2. Query tenants in user's region, select `id`, `display_name`, `system_prompt`
3. Display each community's prompt in a read-only format (collapsible panels or cards)
4. Superadmin can also access this page (sees all communities' prompts)
5. Add to nav for regional_admin role

**Verify:**
- [ ] Regional admin (houston) → sees Pomona, Legacy, Valencia prompts, read-only
- [ ] Cannot edit any prompts from this page
- [ ] Superadmin → sees all community prompts
- [ ] Tenant admin → no access (redirected)

---

### Task C.7: Remove Tenant View toggle

**What:** Remove the toggle from layout.php and any associated session/JS logic.

**Steps:**
1. Remove the toggle switch HTML from `layout.php`
2. Remove any JS event handlers for the toggle
3. Remove any session variables related to the toggle mode (`$_SESSION['view_mode']` or similar)
4. Remove the `set-scope.php` logic related to mode switching (keep scope type/value logic)
5. Clean up any CSS specific to the toggle

**Verify:**
- [ ] No toggle visible for any role
- [ ] No JS errors in console
- [ ] Scope dropdown still works (all/tenant selection)
- [ ] No orphaned session variables

---

## Deviation Log

| Task | What changed | Why |
|------|-------------|-----|
| | | |

---

## Time Tracking

| Task | Estimated | Actual |
|------|-----------|--------|
| A.1 | | |
| A.2 | | |
| A.3 | | |
| B.1 | | |
| B.2 | | |
| C.1 | | |
| C.2 | | |
| C.3 | | |
| C.4 | | |
| C.5 | | |
| C.6 | | |
| C.7 | | |
| **Total** | | |
