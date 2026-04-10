# HWChat Build — Phase 1, Step 1: XO Integration & Schema

**Date:** April 1, 2026
**What this covers:** Cecilian XO API integration, HubSpot config fields, community metadata, and LLM tool-use support.

---

## Files Changed / Added

### NEW: `migrations/006_hillwood_schema.sql`
Adds these columns to the `tenants` table:
- `xo_api_base_url`, `xo_project_slug`, `xo_enabled` — Cecilian XO feed config
- `hubspot_portal_id`, `hubspot_form_id`, `hubspot_api_key` — HubSpot integration
- `community_type`, `parent_tenant_id`, `community_name`, `community_url`, `community_location` — community metadata

**To apply:** `psql -U robchat -d robchat -f migrations/006_hillwood_schema.sql`

### NEW: `lib/CecilianXO.php`
PHP client for the Cecilian XO property API. Queries `/homes` and `/homesites` endpoints, applies client-side filters (beds, baths, stories, sqft, status), and formats results for chat context. Key methods:
- `search(criteria, limit)` — search inventory with filters
- `getBuilders()` — list active builders in the community

### MODIFIED: `api/chat.php`
Major changes:
- Added `require_once` for CecilianXO
- If a tenant has XO enabled, the LLM gets a `search_available_homes` tool definition
- New `callOpenAIWithTools()` and `callAnthropicWithTools()` — handle the tool-use loop:
  1. LLM receives the user message + tool definition
  2. If LLM decides to search inventory, it calls the tool with extracted criteria
  3. chat.php executes the XO API query via CecilianXO class
  4. Results are sent back to the LLM
  5. LLM generates a conversational response with real property data
- Original `callOpenAI()` / `callAnthropic()` preserved as legacy wrappers
- Fixed "salon's hours" → "business hours" in hours context

### MODIFIED: `dashboard/settings.php`
Added form sections:
- **HubSpot Integration** — Portal ID and Form ID fields
- **Property Inventory (Cecilian XO)** — enable toggle, API base URL, project slug
- **Community Info** — community type dropdown, name, URL, location

---

## How It Works (XO Tool Use Flow)

```
Visitor: "Do you have any 4-bedroom homes under $500K?"
    │
    ▼
chat.php: builds messages + system prompt + KB context
    │
    ▼
LLM receives message WITH tool definition: search_available_homes
    │
    ▼
LLM decides to call tool: { beds_min: 4, price_max: 500000 }
    │
    ▼
chat.php: executes CecilianXO->search() against the XO API
    │
    ▼
XO API returns matching homes from live inventory
    │
    ▼
chat.php: sends results back to LLM
    │
    ▼
LLM: "Great news! I found 3 homes that match. Here's a 4-bed by
      Highland Homes at $475,000 on Maple Drive — it's move-in ready..."
```

Non-XO tenants (like HoneyB) are completely unaffected — when `xo_enabled` is false, no tools are passed and the LLM call works exactly as before.

---

## Next Steps
- Create Harvest and Treeline tenant records with system prompts
- Scrape community websites for KB population
- Wire HubSpot lead routing (server-side submission after lead capture)
- Test end-to-end with real XO API data
