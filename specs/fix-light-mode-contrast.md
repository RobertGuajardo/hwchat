# Spec: Fix Light Mode Contrast — Replace Hardcoded Colors with CSS Variables

**Status:** Draft
**Branch:** `fix/light-mode-contrast`
**Date:** 2026-04-10

---

## Problem

The dashboard has three themes (Hillwood, Light, Dark) controlled by CSS variables in `layout.php`. However, most dashboard pages use **hardcoded inline colors** in `style=""` attributes — `color:#fff`, `color:#555`, `background:#141414`, etc. These don't respond to theme switching.

In Light Mode, this causes white text (`#fff`) on white backgrounds, dark backgrounds (`#141414`) on light pages, and muted text (`#555`, `#666`) that's barely readable. The theme toggle is effectively broken for Light Mode.

**Affected users:** Dashboard admins and superusers.

## Solution

Replace all hardcoded inline colors with the corresponding CSS variables. Where inline styles are necessary (dynamic values), use `var()` references. Where possible, move inline styles to CSS classes in `layout.php`.

## Scope

### In Scope
- All `dashboard/*.php` files
- All `dashboard/super/*.php` files
- `dashboard/includes/layout.php` (add any missing utility classes)

### Out of Scope
- Widget theming (robchat.js — separate system, not affected)
- API endpoints
- Adding new themes

## Technical Design

### Color Mapping

| Hardcoded Value | Replace With | Meaning |
|----------------|-------------|---------|
| `color:#fff` | `color:var(--text-bright)` | Primary headings, active labels |
| `color:#ddd`, `color:#ccc` | `color:var(--text)` | Body text, content |
| `color:#999`, `color:#888` | `color:var(--text-muted)` | Secondary/meta text |
| `color:#666`, `color:#555` | `color:var(--text-muted)` | Dimmed text, hints |
| `background:#141414` | `background:var(--bg-card)` | Card/section backgrounds |
| `background:#0c0c0c` | `background:var(--bg-body)` | Inactive/dimmed backgrounds |
| `border:1px solid rgba(255,255,255,0.06)` | `border:1px solid var(--border)` | Card borders |
| `border:1px solid rgba(255,255,255,0.03)` | `border:1px solid var(--border-light)` | Subtle borders |

**Keep hardcoded (these are intentional brand/status colors):**
- `color:#f87171` / `color:#4ade80` — red/green status badges
- `color:#A78BFA` — purple accent (time slots, KB titles)
- `color:#FF4D2E` — orange link accent
- `color:#C45D4F` — danger/remove button
- `color:#C9A96E` — gold SUPER badge
- Theme switcher button backgrounds (they represent the theme color)

### Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `dashboard/includes/layout.php` | Modified | Add utility classes for common patterns, fix `.action-bar h2` hardcoded color |
| `dashboard/bookings.php` | Modified | ~25 inline color replacements |
| `dashboard/knowledge-base.php` | Modified | ~30 inline color replacements |
| `dashboard/settings.php` | Modified | ~8 inline color replacements |
| `dashboard/dashboard_settings.php` | Modified | ~8 inline color replacements |
| `dashboard/dashboard_index.php` | Modified | ~2 inline color replacements |
| `dashboard/index.php` | Modified | ~2 inline color replacements |
| `dashboard/leads.php` | Modified | ~2 inline color replacements |
| `dashboard/session.php` | Modified | ~2 inline color replacements |
| `dashboard/super/tenants.php` | Modified | ~2 inline color replacements |
| `dashboard/super/leads.php` | Modified | ~2 inline color replacements |
| `dashboard/super/tenant-edit.php` | Modified | ~2 inline color replacements |

### Database Changes
None.

### API Changes
None.

### Widget Changes
None.

## Acceptance Criteria

- [ ] Switch to Light Mode — no white-on-white text anywhere in the dashboard
- [ ] Switch to Light Mode — no dark (#141414) card backgrounds on the light page
- [ ] Switch to Dark Mode — all pages still look correct (no regressions)
- [ ] Switch to Hillwood Mode — all pages still look correct (no regressions)
- [ ] Status badge colors (red/green/purple/gold) remain consistent across all themes
- [ ] Theme switcher dots in topbar still show correct preview colors

## Risks & Open Questions

- Some pages may have colors I missed — do a visual audit of every dashboard page in all 3 themes after the fix
- The `a:hover` color `#93bef0` in layout.php is also hardcoded — consider adding a `--blue-hover` variable

## Task Breakdown

See `/tasks/2026-04-10-light-mode-contrast.md`
