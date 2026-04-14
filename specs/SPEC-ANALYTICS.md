# HWChat Analytics & User Accounts — Feature Spec

**Version:** 1.0
**Date:** April 13, 2026
**Author:** Robert Guajardo
**Status:** Draft

---

## 1. Overview

Add a conversation analytics system and multi-user authentication model to HWChat. The analytics system classifies chat conversations via a nightly LLM tagging job and surfaces structured insights through a dashboard with charts, CSV export, and configurable reporting periods. The user accounts system decouples authentication from tenants, enabling one login to access multiple communities with role-based permissions.

This feature set will be ported to RobChat after proving out on HWChat.

---

## 2. Goals

- Turn raw chat conversations into structured, queryable marketing intelligence
- Enable reporting by day, week, month, quarter, year, and on-demand
- Give marketing teams actionable data without requiring dashboard access (CSV export, future email digests)
- Allow users like Diana (DFW Director) to access multiple communities with a single login
- Support three permission tiers: superadmin, tenant_admin, builder
- Prepare the identity model for future SSO integration
- Build cleanly enough to port to RobChat with minimal rework

---

## 3. Non-Goals (This Phase)

- Real-time tagging at conversation end (future optimization if nightly isn't fast enough)
- BDX feed integration (pending research on API requirements — decision before or after Azure migration)
- Advanced analytics: XO tool call tracking, lead funnel metrics, cross-referral tracking, builder interest reports, competitive mentions, temporal patterns, failed response tracking, widget source tracking (all post-Azure migration)
- Automated weekly email digests per community (Phase 3 — after dashboard is proven)
- SSO implementation (user accounts are SSO-ready but SSO itself is out of scope)

---

## 4. Current State

### 4.1 Existing Tables (Relevant to Analytics)

**sessions**
| Column | Type | Notes |
|--------|------|-------|
| id | text PK | Session identifier |
| tenant_id | text FK → tenants | Community this session belongs to |
| started_at | timestamptz | Session start |
| last_active | timestamptz | Last message timestamp |
| page_url | text | Page the widget was opened from |
| user_agent | text | Browser/device info |
| ip_hash | text | Hashed IP for rate limiting |
| lead_captured | boolean | Whether a lead was collected |
| message_count | integer | Total messages in session |

**messages**
| Column | Type | Notes |
|--------|------|-------|
| id | serial PK | |
| session_id | text FK → sessions | |
| role | text | 'user' or 'assistant' |
| content | text | Message body |
| tokens_used | integer | LLM tokens consumed |
| llm_provider | text | 'openai' or 'anthropic' |
| created_at | timestamptz | |

**leads**
| Column | Type | Notes |
|--------|------|-------|
| id | serial PK | |
| session_id | text FK → sessions | |
| tenant_id | text FK → tenants | |
| name | text | |
| email | text | |
| phone | text | |
| lead_type | varchar(20) | Default 'lead' |
| source_page | text | Page the lead came from |
| created_at | timestamptz | |

**bookings**
| Column | Type | Notes |
|--------|------|-------|
| id | serial PK | |
| tenant_id | text FK → tenants | |
| session_id | text FK → sessions | |
| builder_id | integer FK → builders | |
| booking_date | date | |
| status | text | 'confirmed', 'cancelled', 'completed', 'no_show' |
| guest_name | text | |
| guest_email | text | |

**tenants** (auth-relevant columns only)
| Column | Type | Notes |
|--------|------|-------|
| id | text PK | Tenant slug |
| email | text UNIQUE | Login email |
| password_hash | text | bcrypt hash |
| role | text | 'superadmin' or 'tenant_admin' |
| display_name | text | Community display name |
| parent_tenant_id | text FK → tenants | Parent community (for hierarchy) |
| community_type | text | 'standard', 'community', 'parent', 'realtor', 'kiosk' |

### 4.2 Current Auth Model

Authentication is coupled to the tenants table. Each tenant row has `email`, `password_hash`, and `role`. Logging in means logging in *as* a tenant. There is no separate concept of a "user." This means:

- Diana (DFW Director overseeing multiple communities) needs separate logins for each tenant
- Builder realtors have no way to access the booking calendar without a full tenant login
- No path to SSO — identity is tenant-level, not user-level

### 4.3 Backend

- **Language:** PHP (plain PHP, no framework)
- **Database layer:** Custom `Database` class (`lib/Database.php`) — PDO singleton with static methods
- **API routes:** Individual PHP files (`api/chat.php`, `api/book.php`, `api/capture-lead.php`, etc.)
- **Dashboard:** Server-rendered PHP pages (`dashboard/`) — no SPA, no React
- **Auth:** PHP sessions (`session_start()`) — `$_SESSION` stores `tenant_id`, `tenant_email`, `tenant_name`, `tenant_role`. Login via `Database::verifyTenantLogin()` which queries `tenants` table directly.
- **Widget:** TypeScript (client-side chat widget, `widget/`)
- **Hosting:** InMotion Hosting (migrating to Azure later)

**Implications for this feature:**
- Nightly tagging job = PHP CLI script executed by server cron
- Dashboard analytics tab = PHP page with Chart.js for client-side charts (no build step needed)
- Auth migration = update `Database::verifyTenantLogin()` to query `users` table, update `$_SESSION` to include `user_id` and assigned tenant list
- CSV export = PHP script generating CSV with `fputcsv()` and streaming via headers

### 4.4 Existing Indexes

Relevant indexes already in place:
- `idx_sessions_tenant` — btree on (tenant_id)
- `idx_sessions_tenant_active` — btree on (tenant_id, last_active DESC)
- `idx_messages_session` — btree on (session_id)
- `idx_messages_session_time` — btree on (session_id, created_at)
- `idx_leads_tenant` — btree on (tenant_id)
- `idx_leads_tenant_time` — btree on (tenant_id, created_at DESC)

---

## 5. New Data Model

### 5.1 Phase 1 — Analytics Tables

**chat_analytics** — one row per classified session

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | serial | PK | |
| session_id | text | FK → sessions(id) ON DELETE CASCADE, UNIQUE | One analysis per session |
| tenant_id | text | FK → tenants(id) ON DELETE CASCADE | Denormalized for query speed |
| analyzed_at | timestamptz | NOT NULL, DEFAULT now() | When the tagging job processed this |
| message_count | integer | NOT NULL | Total messages in session |
| user_message_count | integer | NOT NULL | Visitor messages only |
| intent_level | text | NOT NULL, CHECK | 'browsing', 'interested', 'ready_to_buy' |
| lead_captured | boolean | NOT NULL | Did we capture a lead |
| tour_booked | boolean | NOT NULL | Did they schedule a tour |
| xo_tool_called | boolean | NOT NULL | Did the bot query XO inventory |
| cross_referrals | text[] | DEFAULT '{}' | Tenant IDs of communities referred to |
| topics | text[] | DEFAULT '{}' | Tag array — see Section 6 for categories |
| price_range_min | integer | | Lowest price mentioned or searched (nullable) |
| price_range_max | integer | | Highest price mentioned or searched (nullable) |
| bedrooms_requested | integer | | If visitor specified (nullable) |
| builders_mentioned | text[] | DEFAULT '{}' | Builder names discussed |
| objections | text[] | DEFAULT '{}' | Concerns raised — see Section 6 |
| sentiment | text | NOT NULL, CHECK | 'positive', 'neutral', 'negative' |
| summary | text | NOT NULL | 1–2 sentence LLM summary |
| session_started_at | timestamptz | NOT NULL | Denormalized from sessions.started_at |
| session_duration_sec | integer | | Computed: last_active - started_at |

**Indexes for chat_analytics:**
- `idx_chat_analytics_tenant_time` — btree on (tenant_id, session_started_at DESC)
- `idx_chat_analytics_topics` — GIN on (topics)
- `idx_chat_analytics_builders` — GIN on (builders_mentioned)
- `idx_chat_analytics_objections` — GIN on (objections)
- `idx_chat_analytics_intent` — btree on (tenant_id, intent_level)
- `idx_chat_analytics_sentiment` — btree on (tenant_id, sentiment)
- `idx_chat_analytics_session` — UNIQUE on (session_id) — enforced by constraint

**chat_analytics_log** — tracks nightly job runs

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | serial | PK | |
| run_at | timestamptz | NOT NULL, DEFAULT now() | When the job started |
| sessions_processed | integer | NOT NULL, DEFAULT 0 | Successfully tagged |
| sessions_skipped | integer | NOT NULL, DEFAULT 0 | Already tagged or too short |
| errors | integer | NOT NULL, DEFAULT 0 | Failed classifications |
| duration_sec | integer | | How long the job took |
| error_details | jsonb | DEFAULT '[]' | Array of {session_id, error} for debugging |

### 5.2 Phase 2 — User Accounts Tables

**users** — decoupled identity

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | serial | PK | |
| email | text | UNIQUE, NOT NULL | Login email |
| password_hash | text | NOT NULL | bcrypt |
| display_name | text | NOT NULL | Full name |
| role | text | NOT NULL, CHECK | 'superadmin', 'tenant_admin', 'builder' |
| is_active | boolean | NOT NULL, DEFAULT true | Soft disable |
| last_login_at | timestamptz | | Track login activity |
| created_at | timestamptz | NOT NULL, DEFAULT now() | |
| updated_at | timestamptz | NOT NULL, DEFAULT now() | |

**user_tenants** — many-to-many: which communities a user can access

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | serial | PK | |
| user_id | integer | FK → users(id) ON DELETE CASCADE, NOT NULL | |
| tenant_id | text | FK → tenants(id) ON DELETE CASCADE, NOT NULL | |
| created_at | timestamptz | NOT NULL, DEFAULT now() | |
| UNIQUE | | (user_id, tenant_id) | No duplicate assignments |

**Indexes for user tables:**
- `idx_users_email` — UNIQUE (enforced by constraint)
- `idx_user_tenants_user` — btree on (user_id)
- `idx_user_tenants_tenant` — btree on (tenant_id)

**Role definitions:**

| Role | Scope | Permissions |
|------|-------|-------------|
| superadmin | All tenants | Full access to all communities, analytics, settings, user management |
| tenant_admin | Assigned tenants only | Analytics, lead management, settings for their communities |
| builder | Assigned tenants only | Calendar/booking access only — no analytics, no settings |

**Migration path for existing auth:** Existing tenant logins (email + password_hash on tenants table) will be migrated to the users table. Each existing tenant login becomes a user row with a corresponding user_tenants entry. The tenants table retains email/password_hash columns during transition but new logins authenticate against the users table. Tenants.role column becomes deprecated — role lives on users.role.

---

## 6. Tagging Categories

### 6.1 Topics (text[] on chat_analytics.topics)

Starting set — expand based on what marketing actually uses:

| Tag | Trigger |
|-----|---------|
| pricing | Price mentioned, price range searched, "how much", "cost" |
| amenities | Pool, trails, parks, gym, clubhouse, playground |
| schools | School district, school ratings, nearby schools |
| builders | Specific builder names, "who builds here" |
| floorplans | Floorplans, square footage, layout, "how big" |
| lot_info | Lot size, lot type, cul-de-sac, corner lot |
| location | Distance to city, commute, "how far from Dallas" |
| hoa | HOA fees, HOA rules, restrictions |
| move_in | Move-in timeline, availability, "when can I move in" |
| tours | Tour scheduling, visiting, model homes, open house |
| financing | Financing options, mortgage, down payment, FHA/VA |
| inventory | General "what's available", searching inventory |
| community_info | General community questions, "tell me about" |

### 6.2 Objections (text[] on chat_analytics.objections)

| Tag | Trigger |
|-----|---------|
| price_too_high | "too expensive", "out of budget", sticker shock |
| hoa_concerns | HOA fees or rules as a negative |
| distance | "too far", commute concerns |
| flood_zone | Flood zone questions, flooding concerns |
| construction | Construction noise, timeline, nearby development |
| limited_inventory | "nothing available", "all sold out" |
| school_concerns | School quality concerns |

### 6.3 Intent Levels

| Level | Signal |
|-------|--------|
| browsing | Vague questions, "just looking", single-message sessions, generic "what do you have" |
| interested | Specific feature questions, price range narrowing, comparing communities, 3+ message sessions |
| ready_to_buy | Asking about move-in dates, tour scheduling, financing, specific homes, mentioning pre-approval |

### 6.4 Sentiment

| Value | Signal |
|-------|--------|
| positive | Enthusiastic language, "love this", "perfect", "exactly what I'm looking for", scheduled a tour |
| neutral | Factual Q&A, no strong emotion either direction |
| negative | Frustration, "disappointed", "too expensive", abandoned after objection, repeated questions (signal of bad answers) |

---

## 7. Nightly Tagging Job

### 7.1 Behavior

1. Query sessions where `last_active` is within the past 24 hours AND no matching row exists in `chat_analytics`
2. Skip sessions with fewer than 2 messages (bot greeting + 1 visitor message minimum)
3. For each qualifying session, pull all messages ordered by `created_at`
4. Send the conversation to the LLM with a classification prompt (see 7.2)
5. Parse the structured response and INSERT into `chat_analytics`
6. Log the run to `chat_analytics_log`

### 7.2 Classification Prompt (Template)

```
You are analyzing a chatbot conversation from a real estate community website. Classify this conversation and return ONLY valid JSON with no additional text.

Conversation:
{messages formatted as "visitor: ..." and "assistant: ..."}

Return this exact JSON structure:
{
  "intent_level": "browsing" | "interested" | "ready_to_buy",
  "topics": ["pricing", "amenities", ...],
  "price_range_min": number or null,
  "price_range_max": number or null,
  "bedrooms_requested": number or null,
  "builders_mentioned": ["Builder Name", ...],
  "objections": ["price_too_high", "hoa_concerns", ...],
  "cross_referrals": ["tenant_id_1", ...],
  "sentiment": "positive" | "neutral" | "negative",
  "xo_tool_called": true | false,
  "summary": "1-2 sentence summary of the conversation"
}

Use only these topic tags: pricing, amenities, schools, builders, floorplans, lot_info, location, hoa, move_in, tours, financing, inventory, community_info

Use only these objection tags: price_too_high, hoa_concerns, distance, flood_zone, construction, limited_inventory, school_concerns
```

### 7.3 LLM Configuration

The tagging job uses a dedicated API key stored in `global_settings`, decoupled from tenant LLM configs:

| Key | Value | Notes |
|-----|-------|-------|
| `analytics_llm_provider` | `openai` or `anthropic` | Which provider the job calls |
| `analytics_api_key` | API key string | Dedicated key for analytics — cost tracked separately |
| `analytics_llm_model` | e.g. `gpt-4o` or `claude-sonnet-4-20250514` | Model to use for classification |

Starting with OpenAI. Switching to Claude requires only updating these three settings — no code change.

### 7.4 Scheduling

- Runs nightly during off-peak hours (2:00 AM CT recommended)
- Processes all sessions from the past 24 hours
- Idempotent — the UNIQUE constraint on session_id prevents double-processing
- If the job fails mid-run, it can be safely re-run; already-processed sessions are skipped

### 7.5 Historical Backfill

On first run, the job processes ALL existing sessions (not just the last 24 hours). This populates the dashboard with historical data — including test data — so analytics charts are populated for demo purposes from day one.

- Backfill runs in batches of 100 sessions to avoid API rate limits
- A `--backfill` CLI flag triggers this mode: `php scripts/analytics-tagger.php --backfill`
- Normal nightly runs (without the flag) only process the last 24 hours
- After backfill completes, switch to the normal nightly cron schedule

### 7.6 Error Handling

- If LLM classification fails for a session (invalid JSON, timeout, API error), log the error to `chat_analytics_log.error_details` and continue processing remaining sessions
- Do not retry failed sessions in the same run — they'll be picked up on the next run if still within the 24-hour window (or on the next backfill batch)
- If a session is older than 48 hours and still unprocessed, skip it and log a warning (does not apply during backfill mode)

---

## 8. Dashboard & Reporting

### 8.1 Analytics Tab

New tab in the existing superadmin dashboard. Access control:

| Role | Sees |
|------|------|
| superadmin | All communities, with ability to filter by community |
| tenant_admin | Only their assigned communities |
| builder | No access to analytics tab |

### 8.2 Reporting Periods

All reports use the same underlying data with different date filters:

| Period | Filter Logic |
|--------|-------------|
| Daily | session_started_at within selected date |
| Weekly | session_started_at within selected ISO week |
| Monthly | session_started_at within selected month |
| Quarterly | session_started_at within selected quarter |
| Yearly | session_started_at within selected year |
| On-demand | Custom date range picker |

### 8.3 Dashboard Metrics

**Summary cards (top of dashboard):**
- Total conversations (period)
- Total leads captured (period)
- Tours booked (period)
- Average session duration
- Lead capture rate (leads / conversations)

**Charts:**
- Conversations over time (line chart, by day/week/month depending on period)
- Top topics (horizontal bar chart)
- Intent distribution (pie/donut chart — browsing vs. interested vs. ready_to_buy)
- Sentiment breakdown (pie/donut chart)
- Price range demand (bar chart — buckets: under $300k, $300-400k, $400-500k, $500k+)
- Top objections (horizontal bar chart)
- Top builders mentioned (horizontal bar chart)

**Charting library:** Chart.js — loaded via CDN, no build step required. Matches the server-rendered PHP dashboard architecture.

### 8.4 CSV Export

- Export button on the analytics dashboard
- Exports the current filtered view (community + date range) as CSV
- Columns match the chat_analytics table fields
- Arrays (topics, objections, builders_mentioned) serialized as comma-separated strings within the CSV cell
- Filename format: `hwchat-analytics-{tenant_id}-{start_date}-{end_date}.csv`

---

## 9. Acceptance Criteria

### Phase 1 — Schema & Migrations

- [ ] AC-1.1: `chat_analytics` table created with all columns, types, constraints, and indexes as specified
- [ ] AC-1.2: `chat_analytics_log` table created with all columns
- [ ] AC-1.3: Foreign keys to `sessions` and `tenants` enforced with ON DELETE CASCADE
- [ ] AC-1.4: UNIQUE constraint on `chat_analytics.session_id` prevents duplicate analysis
- [ ] AC-1.5: CHECK constraints on `intent_level` and `sentiment` enforce valid values
- [ ] AC-1.6: GIN indexes on `topics`, `builders_mentioned`, and `objections` support array queries
- [ ] AC-1.7: Migration is reversible (down migration drops tables cleanly)

### Phase 2 — User Accounts & Roles

- [ ] AC-2.1: `users` table created with email/password auth fields
- [ ] AC-2.2: `user_tenants` junction table created with unique constraint on (user_id, tenant_id)
- [ ] AC-2.3: Existing tenant logins migrated to users table with corresponding user_tenants entries
- [ ] AC-2.4: Login authenticates against `users` table, not `tenants`
- [ ] AC-2.5: Superadmin users see all tenants; tenant_admin users see only assigned tenants
- [ ] AC-2.6: Builder users can access calendar/bookings for assigned tenants only
- [ ] AC-2.7: Builder users cannot access analytics, settings, or lead management
- [ ] AC-2.8: `$_SESSION` includes user_id, role, display_name, and array of assigned tenant IDs
- [ ] AC-2.9: `updated_at` trigger applied to `users` table

### Phase 3 — Nightly Tagging Job

- [ ] AC-3.1: Job processes sessions from the past 24 hours that have no existing chat_analytics row
- [ ] AC-3.2: Sessions with fewer than 2 messages are skipped
- [ ] AC-3.3: LLM classification returns valid JSON matching the expected schema
- [ ] AC-3.4: Parsed results inserted into `chat_analytics` with correct foreign keys
- [ ] AC-3.5: Job run logged to `chat_analytics_log` with counts and duration
- [ ] AC-3.6: Failed classifications logged to `error_details` without stopping the job
- [ ] AC-3.7: Job is idempotent — safe to re-run without duplicate rows
- [ ] AC-3.8: Job completes within reasonable time for expected volume (14 tenants, ~50-200 sessions/day)
- [ ] AC-3.9: `--backfill` flag processes all historical sessions in batches of 100
- [ ] AC-3.10: Backfill populates dashboard with test/historical data for demo purposes

### Phase 4 — Dashboard & Reporting

- [ ] AC-4.1: Analytics tab visible to superadmin and tenant_admin roles
- [ ] AC-4.2: Analytics tab hidden from builder role
- [ ] AC-4.3: Superadmin sees all communities with filter dropdown
- [ ] AC-4.4: Tenant_admin sees only their assigned communities
- [ ] AC-4.5: Period selector works for daily, weekly, monthly, quarterly, yearly, and custom date range
- [ ] AC-4.6: Summary cards display correct aggregated metrics for the selected period and community
- [ ] AC-4.7: Charts render correctly with real data
- [ ] AC-4.8: CSV export downloads with correct data matching the current filter
- [ ] AC-4.9: CSV arrays (topics, objections, builders) are human-readable in spreadsheet software

### Phase 5 — RobChat Port

- [ ] AC-5.1: Same schema deployed to RobChat's database (separate database, same structure)
- [ ] AC-5.2: Nightly tagging job works against RobChat's session/message tables
- [ ] AC-5.3: Dashboard analytics tab functions in RobChat's admin panel
- [ ] AC-5.4: No HWChat-specific hardcoding (tenant names, community types) in shared code

---

## 10. Constraints

- **Database:** PostgreSQL 16 (current), portable to Azure PostgreSQL
- **No real-time impact:** Analytics runs off-hours only — never adds latency to the live chat flow
- **LLM cost:** Nightly job uses one LLM call per session. At ~100 sessions/day across 14 tenants, estimate ~100 API calls/night. Budget accordingly.
- **No breaking changes:** Existing dashboard, API routes, and widget behavior are untouched. Analytics is purely additive.
- **Schema backward compatibility:** The `tenants.email`, `tenants.password_hash`, and `tenants.role` columns remain during the transition period. They are not dropped until all login flows are confirmed working against the `users` table.

---

## 11. Resolved Decisions

| Decision | Resolution | Rationale |
|----------|------------|-----------|
| Topics storage | text[] array with GIN index | Simpler than junction table, PostgreSQL GIN is fast, no schema change needed to add categories |
| Denormalize session_started_at | Yes, copy to chat_analytics | Avoids joining sessions on every dashboard query |
| sentiment/intent_level type | text with CHECK constraint | Matches existing pattern in schema, easy to expand |
| User accounts model | Separate users table + user_tenants junction | Decouples identity from tenants, enables multi-community access, SSO-ready |
| Builder access scope | Calendar/bookings only | Builders need tour calendar but not analytics or community settings |
| Nightly vs. real-time tagging | Nightly first | No load on live chat, cheaper, simpler. Move to real-time only if nightly isn't fast enough |
| Auth migration strategy | Additive — keep tenant auth columns during transition | Zero downtime, rollback-safe |
| Project organization | Same project, separate chats per phase | Analytics is a feature of HWChat, not a separate product |
| Charting library | Chart.js via CDN | No build step needed — dashboard is server-rendered PHP, not React |
| BDX integration timing | Deferred pending research | Robert researching BDX API requirements in parallel |
| Analytics LLM config | Dedicated key via global_settings | Decoupled from tenant LLM keys — cost tracked separately, provider switchable without code changes |
| Historical backfill | Yes — process all existing sessions on first run | Test data populates the dashboard for demo purposes. Throttle at 100 sessions per batch to avoid API rate limits |

---

## 12. Open Questions

| # | Question | Blocker For | Status |
|---|----------|-------------|--------|
| 1 | ~~What is HWChat's backend language/framework?~~ | ~~API routes, cron job implementation, auth middleware~~ | ✅ PHP (plain, no framework), PDO/PostgreSQL |
| 2 | ~~How does the current dashboard auth work?~~ | ~~Phase 2 auth migration~~ | ✅ PHP sessions — `$_SESSION` with tenant_id/email/name/role |
| 3 | ~~What LLM should the nightly job use?~~ | ~~Phase 3 cost and config~~ | ✅ Dedicated key via `global_settings` (`analytics_llm_provider` + `analytics_api_key`). Start with OpenAI, switchable to Anthropic without code changes. |
| 4 | ~~Should the nightly job process ALL historical sessions on first run?~~ | ~~Phase 3 initial backfill~~ | ✅ Yes — backfill all existing sessions (including test data) to populate the dashboard for demo purposes. Throttle at 100 sessions per batch. |
| 5 | What is the BDX feed API format? (REST/JSON? IP restrictions? Auth?) | BDX timing decision | ⏳ Robert researching |

---

## 13. Build Phases (Chat Mapping)

| Phase | Chat | Scope | Depends On |
|-------|------|-------|------------|
| 1 | Build Chat 1 | Schema + migrations (chat_analytics, chat_analytics_log) | Spec complete |
| 2 | Build Chat 2 | User accounts (users, user_tenants, auth migration, role-based access) | Phase 1 |
| 3 | Build Chat 3 | Nightly LLM tagging job (cron, classification prompt, error handling) | Phase 1, Phase 2 |
| 4 | Build Chat 4 | Dashboard analytics tab (charts, CSV export, period filters) | Phase 1, Phase 2, Phase 3 |
| 5 | Build Chat 5 | Port to RobChat (same schema, separate database) | Phase 4 |
