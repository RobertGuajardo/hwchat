# Spec: Role-Based Access — Superuser / Regional Admin / Tenant

> **Date:** April 14, 2026
> **Status:** Spec Complete — Ready for Implementation
> **Branch:** feature/role-expansion
> **Migration:** 021 (next available after 020)

---

## What and Why

The dashboard currently has two functional roles: superadmin (sees everything) and tenant_admin (sees one community). The business operates by region — DFW, Houston, and Austin are managed by different teams. Regional managers, marketers, and third-party SEO vendors need dashboard access scoped to their region without seeing other regions. This feature adds a `regional_admin` role that sits between superadmin and tenant_admin, scopes every dashboard page by role, and removes the Tenant View toggle (replaced by real role-based access).

---

## Users

- **Superuser (superadmin):** Web Apps team, Diana. Sees all communities across all regions. Full access to every page. Manages all users.
- **Regional Admin (regional_admin):** Regional managers, regional marketers, third-party SEO/marketing vendors. Sees only communities in their assigned region. Cannot see master prompt. Can view tenant prompts for reference.
- **Tenant (tenant_admin):** Community managers. Sees only their assigned community. Current behavior — nothing changes for them.
- **Builder (builder):** On-site realtors. Calendar and bookings only. Can also see analytics for their community. *(Note: builder analytics access is new.)*

---

## Requirements

**Role & Database:**

1. `regional_admin` added to the users table role CHECK constraint
2. `region` column added to `users` table (`TEXT DEFAULT NULL`) — used for regional_admin scoping
3. Regional admin's `region` value must match a valid region in the REGIONS constant
4. A regional_admin with no region assigned sees nothing (failsafe)

**Superuser Access (no changes except where noted):**

5. Overview: aggregate stats across all communities (current behavior)
6. Tenants: list all tenants (current behavior)
7. Communities: list all communities (current behavior)
8. Master Prompt: full edit access (current behavior)
9. Leads: all leads with Community column (already built in scope selector)
10. Analytics: filter by All Communities, by Region (DFW/Houston/Austin), or by individual tenant
11. Users: manage all users across all regions (current behavior)

**Regional Admin Access:**

12. Overview: aggregate stats for tenants in their region only
13. Tenants: see only tenants in their region
14. Communities: see only communities in their region
15. Master Prompt: **no access** — page hidden from nav, direct URL returns redirect
16. Tenant Prompts: read-only view of system prompts for communities in their region (new page or read-only mode)
17. Leads: leads from their region only, with Community column
18. Analytics: filter by All (within their region) or by individual tenant in their region
19. Users: see and manage only users assigned to tenants in their region

**Tenant Access:**

20. No changes — sees only their assigned community's data on all pages
21. Current nav, current pages, current behavior

**Builder Access:**

22. Calendar and bookings only (current behavior)
23. Add analytics access for their assigned community (new — currently blocked)

**Dropdown Behavior by Role:**

24. Superuser dropdown: "All Communities" + flat list of all 14 community tenants (current behavior from scope selector)
25. Regional admin dropdown: "All [Region Name]" (e.g. "All Houston") + flat list of tenants in their region
26. Tenant dropdown: only their assigned tenant(s) — or no dropdown if single tenant
27. Builder: no dropdown

**Scope Helpers:**

28. `buildScopeWhereClause()` becomes role-aware — automatically restricts based on the user's role and region, not just session scope_type
29. Superuser "all" = WHERE region IS NOT NULL (current behavior)
30. Regional admin "all" = WHERE tenant_id IN (tenants in their region)
31. Tenant = WHERE tenant_id IN (their assigned tenants)

**Tenant View Toggle:**

32. Remove the Tenant View / Admin Panel toggle entirely from layout.php
33. Each role sees exactly what they're supposed to — no mode switching needed

---

## Data

**Modified Table:**

| Entity | Change | Notes |
|--------|--------|-------|
| users | Add `region TEXT DEFAULT NULL` | Used for regional_admin scoping. NULL for superadmin and tenant_admin. |
| users | ALTER CHECK constraint on `role` | Add `regional_admin` to allowed values |

**Note:** The `users` table PK is `id` (integer, auto-increment). The `region` column reuses the same slugs as `tenants.region` — `dfw`, `houston`, `austin`.

**Role Matrix:**

| Page | superadmin | regional_admin | tenant_admin | builder |
|------|-----------|----------------|-------------|---------|
| Overview | All tenants | Region tenants | Own tenant | No access |
| Tenants | All | Region only | No access | No access |
| Communities | All | Region only | No access | No access |
| Master Prompt | Edit | No access | No access | No access |
| Tenant Prompts | N/A (uses master prompt page) | Read-only, region only | No access | No access |
| Leads | All + Community col | Region + Community col | Own tenant | No access |
| Analytics | All / Region / Tenant filter | Region / Tenant filter | Own tenant | Own tenant |
| Users | All | Region only | No access | No access |
| Settings | All tenants | Region tenants | Own tenant | No access |
| Knowledge Base | All tenants | Region tenants | Own tenant | No access |
| Bookings | All tenants | Region tenants | Own tenant | Own tenant |

---

## Acceptance Criteria

**Role & Database:**

- [ ] `\d users` shows `region TEXT` column
- [ ] CHECK constraint on `role` includes `regional_admin`
- [ ] Create a user with role `regional_admin` and region `houston` — succeeds
- [ ] Create a user with role `regional_admin` and region NULL — succeeds but user sees nothing

**Superuser:**

- [ ] Login as superadmin → sees all nav items, all pages accessible
- [ ] Analytics page has filter: All Communities / DFW / Houston / Austin / individual tenant
- [ ] Leads page shows Community column when viewing all
- [ ] Users page shows all users

**Regional Admin:**

- [ ] Login as regional_admin (region=houston) → only sees Pomona, Legacy, Valencia data
- [ ] Overview stats are Houston-only aggregates
- [ ] Tenants page lists only Houston tenants
- [ ] Master Prompt is not in the nav and direct URL redirects away
- [ ] Tenant Prompts page shows read-only prompts for Houston communities
- [ ] Leads show Houston communities only with Community column
- [ ] Analytics shows "All Houston" default with option to filter to individual Houston tenant
- [ ] Users page shows only users assigned to Houston tenants
- [ ] Dropdown shows "All Houston" + Pomona, Legacy, Valencia — no other communities
- [ ] Cannot access DFW or Austin data via direct URL manipulation

**Tenant:**

- [ ] Login as tenant_admin → current behavior unchanged
- [ ] Sees only their community's data
- [ ] No Tenant View toggle visible

**Builder:**

- [ ] Login as builder → calendar and bookings visible (current behavior)
- [ ] Analytics page now accessible and shows their community's data
- [ ] No access to leads, settings, knowledge base, users, tenants

**Tenant View Toggle:**

- [ ] Toggle is completely removed from layout.php
- [ ] No "Admin Panel" / "Tenant View" switching anywhere in the UI

---

## Constraints

- All page access checks are server-side — never trust client-side role claims
- `buildScopeWhereClause()` must be the single entry point for all scoped queries — no manual SQL
- Regional admin cannot escalate to superadmin access via URL manipulation or session tampering
- Regional admin's region is set by superadmin when creating/editing the user — they cannot change it themselves
- Builder role's new analytics access must be limited to their assigned tenant only
- The `users.region` column is independent of `tenants.region` — they use the same slugs but aren't FK-linked
- Tenants table PK is `id`, users table PK is `id`, other tables use `tenant_id` as FK

---

## Out of Scope

- Self-service user management for tenant owners (RobChat feature, not HWChat)
- SSO integration — future concern, but role structure should accommodate it
- Per-page permission granularity beyond the role matrix above
- Regional admin editing tenant settings — they get read-only prompt access only (full settings TBD)
- Cross-region admin access (an admin managing both Houston and Austin) — if needed later, assign multiple user records or add a junction table

---

## Decisions

| Decision | Why |
|----------|-----|
| `regional_admin` as the role name | Descriptive, won't be confused with a generic "admin" |
| Region stored on `users` table, not through a junction | One admin = one region. Simple. If cross-region is needed later, can add a `user_regions` table. |
| Remove Tenant View toggle entirely | Each role has its own proper scoped view. Toggle was a workaround for not having real roles. |
| Builder gets analytics access | Robert wants on-site realtors to see their community's analytics. Low risk since it's scoped to their tenant. |
| Regional admin gets read-only tenant prompts, not master prompt | They need to know what the bot says for their communities but shouldn't modify the shared behavior layer. |
| `buildScopeWhereClause()` becomes role-aware | Single function handles all scoping logic. Pages don't need to know about roles — they just call the helper. |

---

## Open Questions

- [ ] Should regional_admin be able to edit tenant settings (branding, XO config, etc.) for their region's communities, or is that superadmin-only? Leaning toward read-only for now.
- [ ] For the tenant prompts page — new standalone page, or read-only mode on the existing tenant-edit page? Leaning toward a new simple page that just lists prompts.
