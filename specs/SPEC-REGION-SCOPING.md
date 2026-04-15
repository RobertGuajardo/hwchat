# Spec: Superadmin Scope Selector & Region Infrastructure

> **Date:** April 14, 2026
> **Status:** Spec Complete — Ready for Implementation
> **Branch:** feature/scope-selector
> **Migration:** 020 (next available after 019)

---

## What and Why

The superadmin topbar dropdown currently scopes to a single tenant at all times — there's no way to see aggregate data across all communities. This feature adds an "All Communities" option to the dropdown so superadmin pages can show combined data. Separately, a `region` column is added to the `tenants` table (DFW / Houston / Austin) as infrastructure for the Phase 2 analytics dashboard, where region will be a filter/sort dimension. The dropdown itself stays flat — region grouping only matters in analytics, not navigation.

---

## Users

- **Superadmin (me):** Selects "All Communities" or a specific tenant from the topbar dropdown. Views aggregate or single-tenant data on Leads. Assigns regions to tenants in tenant settings.
- **Tenant Admin:** No change — always scoped to their assigned tenant(s). Never sees the scope dropdown.

---

## Requirements

**Dropdown & Scope (build now):**

1. Topbar dropdown includes "All Communities" option at the top of the existing flat tenant list
2. "All Communities" is the default selection on superadmin login
3. Dropdown is a flat list — no region headers, no grouping, no nesting
4. Dropdown only shows tenants that have a region assigned (non-null) — demo and admin tenants with NULL region are excluded
5. Current scope selection visually highlighted in dropdown and persists across page navigation
6. Scope stored in `$_SESSION` as `scope_type` (`all` | `tenant`) and `scope_value` (`null` | `{tenant id}`)
7. `buildScopeWhereClause()` helper function provides prepared-statement-safe SQL filtering — all scope queries must use this, never manual SQL
8. "All Communities" scope filters to tenants WHERE `region IS NOT NULL` — it does not include every row in the tenants table
9. Leads page shows all leads with a Community column when scope is "all", hides the column for single-tenant scope
10. Leads CSV export includes Community column when scope is "all"
11. Tenant View mode requires a specific tenant selected — if scope is "all" when toggling to Tenant View, prompt user to pick a tenant
12. Switching from Tenant View back to Admin Panel preserves the last admin scope selection
13. Superadmin header in topbar-left shows scope-appropriate label: "Hillwood AI Chatbot" (all) or tenant display name (single tenant)

**Region Infrastructure (build now, used by analytics in Phase 2):**

14. `tenants` table has a `region` column (`TEXT DEFAULT NULL`) with a CHECK constraint: `region IN ('dfw', 'houston', 'austin')`
15. NULL region = tenant is invisible to the scope system (excluded from dropdown, aggregate views, and analytics)
16. Existing community tenants seeded with correct region values in migration
17. `demo_001` and `hw_superadmin` remain NULL — excluded from everything
18. Tenant edit page includes a Region dropdown for assigning/changing a tenant's region
19. Region dropdown includes a "None" option for tenants that should be excluded from the scope system
20. `REGIONS` constant defined in `lib/regions.php` with display name mapping

---

## Data

**New Column:**

| Entity | Field | Type | Notes |
|--------|-------|------|-------|
| tenants | region | TEXT DEFAULT NULL | CHECK constraint: `region IN ('dfw', 'houston', 'austin')`. NULL = excluded from scope system. |

**Note:** The tenants table primary key column is `id` (not `tenant_id`). Other tables reference it via `tenant_id` foreign keys.

**Confirmed Tenant IDs and Region Assignments:**

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

**Session Variables:**

```php
$_SESSION['scope_type']  = 'all' | 'tenant';
$_SESSION['scope_value'] = null | '{tenant id}';  // e.g. 'hw_harvest'
```

---

## Acceptance Criteria

**Dropdown & Scope:**

- [ ] Fresh superadmin login → scope defaults to "All Communities", header shows "Hillwood AI Chatbot"
- [ ] Dropdown shows "All Communities" at top, then flat list of 14 community tenants (demo_001 and hw_superadmin not listed)
- [ ] Click "All Communities" → session sets `scope_type=all`, `scope_value=null`
- [ ] Click "Treeline by Hillwood" → session sets `scope_type=tenant`, `scope_value=hw_treeline`
- [ ] Current selection visually highlighted in dropdown
- [ ] Navigate between pages → scope selection persists
- [ ] Leads page with "All Communities" → shows leads from all 14 community tenants with Community column (no demo_001 or hw_superadmin leads)
- [ ] Leads page with single tenant → current behavior, no Community column
- [ ] CSV export from leads matches the currently scoped view
- [ ] Toggle to Tenant View from "All Communities" → prompted to pick a tenant
- [ ] Toggle from Tenant View back to Admin Panel → returns to previous admin scope
- [ ] Header shows "Hillwood AI Chatbot" (all) or tenant display name (single tenant)
- [ ] Non-superadmin users see no scope dropdown and are unaffected

**Region Infrastructure:**

- [ ] `SELECT id, display_name, region FROM tenants ORDER BY region, display_name;` → Houston: hw_pomona, hw_legacy, hw_valencia; Austin: hw_wolf_ranch, hw_melina; DFW: 9 tenants; NULL: demo_001, hw_superadmin
- [ ] CHECK constraint exists: `\d tenants` shows `chk_region`
- [ ] Edit a community tenant → region dropdown shows current value pre-selected
- [ ] Change region from DFW to Houston, save → DB updated, reload shows saved value
- [ ] Submit invalid region value → rejected
- [ ] Set region to "None" on a tenant → DB stores NULL, tenant disappears from dropdown and aggregate views

---

## Constraints

- Scope filtering is server-side only — never trust client-side scope values
- `buildScopeWhereClause()` uses prepared statements — no raw SQL interpolation
- Non-superadmin users bypass the scope system entirely — always scoped to their assigned tenant(s)
- Scope dropdown is only rendered for the superadmin role
- Must use existing PHP session auth — no new auth layer
- Migration must be backward-compatible (ADD COLUMN IF NOT EXISTS, no destructive changes)
- Tenants table PK is `id`, not `tenant_id` — all SQL must use the correct column name

---

## Out of Scope

- Analytics dashboard (Phase 2) — this spec builds the region data layer only, not charts/dashboards or region-based filtering UI
- Region as a dropdown filter/grouping in the topbar — region filtering lives in analytics, not navigation
- Cross-region comparison views (Phase 3)
- Regional manager user role — future user accounts overhaul
- Users page changes — already shows all users regardless
- Communities page grouping by region — optional cosmetic, not in this feature

---

## Decisions

| Decision | Why |
|----------|-----|
| Dropdown stays flat — no region grouping in navigation | Region scoping is an analytics concern, not a navigation concern. Keep the dropdown simple. |
| Region is nullable — NULL means excluded from scope system | demo_001 and hw_superadmin aren't real communities. NULL region automatically excludes them from dropdown, aggregate data, and analytics without hardcoding IDs. Future test/admin tenants just don't get a region. |
| "All Communities" filters to `WHERE region IS NOT NULL` | Cleaner than maintaining an exclusion list. Any tenant without a region is invisible to the scope system. |
| Region stored as TEXT with CHECK constraint, not a separate table | Only 3 regions, unlikely to change often. Adding a region = ALTER CHECK + update constant. |
| Region display names in PHP constant, not DB | Easy to update without migration, display-only concern |
| `buildScopeWhereClause()` only handles `all` and `tenant` — no region scope type | Region filtering comes in Phase 2 analytics with its own UI, not through the global scope system |
| hw_parent and hw_realtors default to DFW | They need a region for organizational grouping; DFW is HQ |
| Scope stored in `$_SESSION`, not DB | Scope is ephemeral per-session, not a persistent user preference |

---

## Open Questions

None — all resolved.
