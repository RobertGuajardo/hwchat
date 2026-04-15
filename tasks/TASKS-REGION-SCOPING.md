# Tasks: Superadmin Scope Selector & Region Infrastructure

> **Spec:** `specs/SPEC-REGION-SCOPING.md`
> **Date:** April 14, 2026
> **Branch:** `feature/scope-selector`

---

## Before Starting

- [x] Spec is done — no open questions
- [ ] Branch created: `git checkout -b feature/scope-selector`
- [ ] CLAUDE.md updated with Scope Selector section
- [x] Tenant IDs confirmed from DB — exact values in spec

---

## Phase A — Database & Region Infrastructure

### Task A.1: Migration 020 — Add region column to tenants table

**What:** Create and apply `migrations/020-add-region-column.sql`.

**Steps:**
1. Write migration with nullable region column: `ALTER TABLE tenants ADD COLUMN IF NOT EXISTS region TEXT DEFAULT NULL;`
2. Set DFW tenants: `UPDATE tenants SET region = 'dfw' WHERE id IN ('hw_harvest', 'hw_treeline', 'hw_pecan_square', 'hw_union_park', 'hw_lilyana', 'hw_landmark', 'hw_ramble', 'hw_parent', 'hw_realtors');`
3. Set Houston tenants: `UPDATE tenants SET region = 'houston' WHERE id IN ('hw_pomona', 'hw_legacy', 'hw_valencia');`
4. Set Austin tenants: `UPDATE tenants SET region = 'austin' WHERE id IN ('hw_wolf_ranch', 'hw_melina');`
5. Leave demo_001 and hw_superadmin as NULL (no UPDATE needed)
6. Add CHECK constraint: `ALTER TABLE tenants ADD CONSTRAINT chk_region CHECK (region IN ('dfw', 'houston', 'austin'));`
7. Apply: `psql -U hwchat -d hwchat -W -f migrations/020-add-region-column.sql`

**Verify:**
- [ ] `SELECT id, display_name, region FROM tenants ORDER BY region NULLS LAST, display_name;`
- [ ] DFW: hw_harvest, hw_landmark, hw_legacy... (9 tenants)
- [ ] Houston: hw_pomona, hw_legacy, hw_valencia
- [ ] Austin: hw_wolf_ranch, hw_melina
- [ ] NULL: demo_001, hw_superadmin
- [ ] `\d tenants` shows `chk_region` constraint

---

### Task A.2: Create REGIONS constant and scope helper functions

**What:** Create `lib/regions.php` with REGIONS constant and scope helpers.

**Steps:**
1. Create `lib/regions.php`
2. Define `REGIONS` constant: `['dfw' => 'Dallas Fort Worth', 'houston' => 'Houston', 'austin' => 'Austin']`
3. Implement `getScopedTenantIds(): ?array` — for "all" scope, returns array of all tenant IDs where `region IS NOT NULL`. For "tenant" scope, returns single-element array with the tenant ID.
4. Implement `buildScopeWhereClause(string $tableAlias = ''): array` — returns `['clause' => '...', 'params' => [...]]` using prepared statement params. For "all", filters to tenants with non-null region. For "tenant", filters to the specific tenant ID. The alias parameter refers to the table being filtered (e.g. `l` for leads), and the WHERE targets the `tenant_id` FK column on that table.
5. Implement `getScopeLabel(): string` — returns "All Communities" for all scope, or the tenant's `display_name` for tenant scope

**Verify:**
- [ ] `php -l lib/regions.php` → no syntax errors
- [ ] All functions exist and are callable
- [ ] "All" scope excludes demo_001 and hw_superadmin
- [ ] "Tenant" scope returns correct WHERE clause for a single tenant

---

### Task A.3: Add region field to tenant-edit.php

**What:** Add a Region dropdown to the tenant edit form. On save, update the `region` column.

**Steps:**
1. Include `lib/regions.php` in `dashboard/super/tenant-edit.php`
2. Add `<select name="region">` with "None" option (value="") plus options from REGIONS constant
3. Pre-select the current tenant's region value (or "None" if NULL)
4. On POST save, include `region` in the UPDATE query — store NULL if "None" selected
5. Validate submitted region value is in `array_keys(REGIONS)` or empty string (for NULL)

**Verify:**
- [ ] Edit hw_harvest → region dropdown shows "Dallas Fort Worth" pre-selected
- [ ] Change region from DFW to Houston, save → DB shows `region = 'houston'`
- [ ] Reload edit page → dropdown shows Houston
- [ ] Submit invalid region value → rejected
- [ ] Set region to "None" → DB stores NULL, tenant excluded from dropdown/aggregates

---

## Phase B — Dropdown & Scope System

### Task B.1: Add "All Communities" to topbar dropdown

**What:** Add "All Communities" at the top of the existing flat tenant dropdown. Create `set-scope.php` endpoint to update session. Only show tenants with non-null region.

**Steps:**
1. Include `lib/regions.php` in `dashboard/includes/layout.php`
2. Query tenants WHERE `region IS NOT NULL` for the dropdown list
3. Add "All Communities" option at the top
4. Keep the rest as a flat list of community tenants (14 tenants, no demo_001 or hw_superadmin)
5. Highlight currently selected scope (all or specific tenant)
6. Create `dashboard/api/set-scope.php` — accepts scope_type + scope_value, validates, updates `$_SESSION`
7. On dropdown selection, POST to `set-scope.php` and reload page

**Verify:**
- [ ] Dropdown shows "All Communities" at top, then 14 community tenants
- [ ] demo_001 and hw_superadmin do not appear in dropdown
- [ ] Click "All Communities" → session = `scope_type=all, scope_value=null`
- [ ] Click "Treeline by Hillwood" → session = `scope_type=tenant, scope_value=hw_treeline`
- [ ] Current selection visually highlighted
- [ ] Selection persists across page navigation

---

### Task B.2: Set login defaults and Tenant View toggle logic

**What:** Default superadmin to "All Communities" on login. Handle Tenant View toggle when scope is "all".

**Steps:**
1. In login handler: if user is superadmin, set `$_SESSION['scope_type'] = 'all'` and `$_SESSION['scope_value'] = null`
2. Tenant View toggle: if scope is "all" and user toggles to Tenant View, show prompt to select a specific tenant
3. Switching from Tenant View back to Admin Panel preserves last admin scope

**Verify:**
- [ ] Fresh superadmin login → scope is "All Communities"
- [ ] Toggle to Tenant View from "All Communities" → prompted to pick a tenant
- [ ] Toggle back to Admin Panel → scope returns to previous admin scope

---

## Phase C — Data Page Integration

### Task C.1: Wire scope filtering into Leads page

**What:** Make `super/leads.php` respect scope. Add Community column when viewing all.

**Steps:**
1. Include `lib/regions.php` in `dashboard/super/leads.php`
2. Call `buildScopeWhereClause()` and inject into the leads SQL query
3. When scope is "all": JOIN to tenants table, add Community column showing `display_name`
4. When scope is single tenant: current behavior, no Community column
5. Update CSV export to include Community column when scope is "all"

**Verify:**
- [ ] "All Communities" → leads from all 14 community tenants with Community column (no demo_001/hw_superadmin leads)
- [ ] Single tenant selected → only that tenant's leads, no Community column
- [ ] CSV export matches the currently scoped view

---

### Task C.2: Fix superadmin header display

**What:** Replace the hardcoded/first-tenant name in the topbar-left with `getScopeLabel()`.

**Steps:**
1. In `layout.php` topbar-left, replace the current tenant name output with `getScopeLabel()`
2. "All Communities" scope → display "Hillwood AI Chatbot"
3. Single tenant scope → display tenant's `display_name` (current behavior)

**Verify:**
- [ ] Login as superadmin → header shows "Hillwood AI Chatbot"
- [ ] Select a tenant → header shows that tenant's display name

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
| **Total** | | |
