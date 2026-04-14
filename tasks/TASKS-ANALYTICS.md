# HWChat Analytics — Task Decomposition

**Spec:** SPEC-ANALYTICS.md
**Date:** April 13, 2026
**Total Tasks:** 19
**Estimated Total Effort:** ~6-8 hours across multiple sessions

---

## Phase 0: Project Setup

Create the CLAUDE.md project memory file so Claude Code has full context on every prompt.

---

### Task 0.1 — [SETUP] Create CLAUDE.md project memory

| Field | Value |
|-------|-------|
| **Spec Sections** | All |
| **Description** | Add CLAUDE.md to the project root with project overview, tech stack, code patterns, auth model, security rules, and file structure |
| **Depends On** | None — this is the first task |
| **Acceptance** | File exists at project root, covers security rules, code patterns, and auth model |
| **Estimated Effort** | 5 min (copy from prepared file) |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
I'm adding a CLAUDE.md project memory file to the root of this project. I have it prepared — I'll paste the contents. Place it at the project root as CLAUDE.md.

[Paste the contents of CLAUDE.md]

After creating the file, read it and confirm you understand the project structure, security rules, and code patterns. Then we'll proceed to Task 1.1.
```

**Verification:**
- [ ] CLAUDE.md exists at project root
- [ ] Covers: project overview, tech stack, file structure, code patterns, auth model, security rules
- [ ] Claude Code confirms it understands the project context

---

## Phase 1: Schema + Migrations

Foundation tables and Database class methods. No UI, no scripts — just the data layer.

---

### Task 1.1 — [MIGRATION] Analytics tables and global settings

| Field | Value |
|-------|-------|
| **Spec Sections** | 5.1, 7.3 |
| **Description** | Create migration 017_analytics_schema.sql with chat_analytics table, chat_analytics_log table, all indexes, and global_settings entries for the analytics LLM config |
| **Depends On** | None |
| **Acceptance** | AC-1.1, AC-1.2, AC-1.3, AC-1.4, AC-1.5, AC-1.6 |
| **Estimated Effort** | 15 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Create the file migrations/017_analytics_schema.sql following the pattern of existing migrations (see migrations/014_superuser_role.sql for format).

This migration creates:

1. chat_analytics table — one row per classified session:
   - id serial PK
   - session_id text FK → sessions(id) ON DELETE CASCADE, UNIQUE
   - tenant_id text FK → tenants(id) ON DELETE CASCADE
   - analyzed_at timestamptz NOT NULL DEFAULT now()
   - message_count integer NOT NULL
   - user_message_count integer NOT NULL
   - intent_level text NOT NULL CHECK (intent_level IN ('browsing', 'interested', 'ready_to_buy'))
   - lead_captured boolean NOT NULL
   - tour_booked boolean NOT NULL
   - xo_tool_called boolean NOT NULL
   - cross_referrals text[] DEFAULT '{}'
   - topics text[] DEFAULT '{}'
   - price_range_min integer (nullable)
   - price_range_max integer (nullable)
   - bedrooms_requested integer (nullable)
   - builders_mentioned text[] DEFAULT '{}'
   - objections text[] DEFAULT '{}'
   - sentiment text NOT NULL CHECK (sentiment IN ('positive', 'neutral', 'negative'))
   - summary text NOT NULL
   - session_started_at timestamptz NOT NULL
   - session_duration_sec integer (nullable)

2. chat_analytics_log table — tracks nightly job runs:
   - id serial PK
   - run_at timestamptz NOT NULL DEFAULT now()
   - sessions_processed integer NOT NULL DEFAULT 0
   - sessions_skipped integer NOT NULL DEFAULT 0
   - errors integer NOT NULL DEFAULT 0
   - duration_sec integer (nullable)
   - error_details jsonb DEFAULT '[]'

3. Indexes:
   - idx_chat_analytics_tenant_time — btree on (tenant_id, session_started_at DESC)
   - idx_chat_analytics_topics — GIN on (topics)
   - idx_chat_analytics_builders — GIN on (builders_mentioned)
   - idx_chat_analytics_objections — GIN on (objections)
   - idx_chat_analytics_intent — btree on (tenant_id, intent_level)
   - idx_chat_analytics_sentiment — btree on (tenant_id, sentiment)

4. Global settings entries (INSERT ... ON CONFLICT DO NOTHING):
   - analytics_llm_provider = 'openai'
   - analytics_api_key = '' (to be filled after migration)
   - analytics_llm_model = 'gpt-4o'

Include the run command in the header comment: psql -U hwchat -d hwchat -f migrations/017_analytics_schema.sql
```

**Verification:**
- [ ] SQL file runs without errors on a clean database
- [ ] chat_analytics table has all columns with correct types and constraints
- [ ] chat_analytics_log table has all columns
- [ ] UNIQUE constraint on session_id prevents duplicates
- [ ] CHECK constraints on intent_level and sentiment enforce valid values
- [ ] All 6 indexes created
- [ ] Global settings rows inserted

---

### Task 1.2 — [SERVICE] Database class analytics methods

| Field | Value |
|-------|-------|
| **Spec Sections** | 5.1, 8.2, 8.4 |
| **Description** | Add analytics CRUD methods to lib/Database.php — insert analytics row, query analytics with filters, log job runs, and support CSV export queries |
| **Depends On** | Task 1.1 |
| **Acceptance** | AC-1.3 (FK enforcement verified via insert) |
| **Estimated Effort** | 20 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Add the following methods to lib/Database.php inside the Database class. Place them in a new section with the comment header "// ------- ANALYTICS METHODS -------".

Follow the existing code patterns — static methods, PDO prepared statements, return arrays.

1. insertAnalytics(array $data): int
   - INSERT into chat_analytics with all fields from $data
   - Return the new row ID
   - Use ON CONFLICT (session_id) DO NOTHING to enforce idempotency

2. getAnalyticsSummary(?string $tenantId, ?string $after, ?string $before): array
   - Returns aggregated stats for the dashboard summary cards:
     total_conversations, total_leads, total_tours, avg_duration_sec,
     lead_capture_rate (leads / conversations as percentage)
   - If $tenantId is null, return all tenants (superadmin view)
   - $after and $before filter on session_started_at

3. getAnalyticsChartData(string $chartType, ?string $tenantId, ?string $after, ?string $before): array
   - $chartType is one of: 'conversations_over_time', 'topics', 'intent', 'sentiment', 'price_ranges', 'objections', 'builders'
   - Returns data formatted for Chart.js (labels + datasets)
   - conversations_over_time: GROUP BY date, return counts per day
   - topics: unnest(topics), COUNT, ORDER BY count DESC, LIMIT 15
   - intent: GROUP BY intent_level, COUNT
   - sentiment: GROUP BY sentiment, COUNT
   - price_ranges: bucket into ranges (under 300k, 300-400k, 400-500k, 500k+), COUNT
   - objections: unnest(objections), COUNT, ORDER BY count DESC
   - builders: unnest(builders_mentioned), COUNT, ORDER BY count DESC, LIMIT 15

4. getAnalyticsExport(?string $tenantId, ?string $after, ?string $before): array
   - SELECT all columns from chat_analytics with tenant/date filters
   - ORDER BY session_started_at DESC
   - Returns raw rows for CSV export

5. logAnalyticsRun(int $processed, int $skipped, int $errors, int $durationSec, array $errorDetails = []): void
   - INSERT into chat_analytics_log

6. getUnanalyzedSessions(bool $backfill = false, int $limit = 100): array
   - SELECT sessions that have no matching chat_analytics row
   - If $backfill is false: only sessions where last_active >= NOW() - INTERVAL '24 hours'
   - If $backfill is true: all unanalyzed sessions (no time filter)
   - Only sessions with message_count >= 2
   - Returns session rows with tenant_id
   - ORDER BY started_at ASC, LIMIT $limit

Do not modify any existing methods. Add these at the end of the class before the closing brace.
```

**Verification:**
- [ ] All 6 methods added to Database.php
- [ ] Methods follow existing code patterns (static, PDO, prepared statements)
- [ ] getUnanalyzedSessions correctly filters by backfill flag
- [ ] getAnalyticsChartData handles all 7 chart types
- [ ] insertAnalytics uses ON CONFLICT for idempotency
- [ ] No existing methods broken

---

## Phase 2: User Accounts + Roles

Decouples identity from tenants. New tables, auth migration, updated login flow.

---

### Task 2.1 — [MIGRATION] User accounts tables

| Field | Value |
|-------|-------|
| **Spec Sections** | 5.2 |
| **Description** | Create migration 018_user_accounts.sql with users table, user_tenants junction table, indexes, and updated_at trigger |
| **Depends On** | Task 1.1 (sequential migration numbering) |
| **Acceptance** | AC-2.1, AC-2.2, AC-2.9 |
| **Estimated Effort** | 10 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Create the file migrations/018_user_accounts.sql following the existing migration pattern.

This migration creates:

1. users table:
   - id serial PK
   - email text UNIQUE NOT NULL
   - password_hash text NOT NULL
   - display_name text NOT NULL
   - role text NOT NULL DEFAULT 'tenant_admin' CHECK (role IN ('superadmin', 'tenant_admin', 'builder'))
   - is_active boolean NOT NULL DEFAULT true
   - last_login_at timestamptz (nullable)
   - created_at timestamptz NOT NULL DEFAULT now()
   - updated_at timestamptz NOT NULL DEFAULT now()

2. user_tenants table:
   - id serial PK
   - user_id integer NOT NULL FK → users(id) ON DELETE CASCADE
   - tenant_id text NOT NULL FK → tenants(id) ON DELETE CASCADE
   - created_at timestamptz NOT NULL DEFAULT now()
   - UNIQUE constraint on (user_id, tenant_id)

3. Indexes:
   - idx_user_tenants_user — btree on (user_id)
   - idx_user_tenants_tenant — btree on (tenant_id)

4. Apply the existing update_updated_at() trigger to the users table (same pattern as bookings, tenants, etc.)

Include the run command in the header comment.
```

**Verification:**
- [ ] SQL runs without errors
- [ ] users table has all columns with correct types
- [ ] user_tenants has unique constraint preventing duplicate assignments
- [ ] Foreign keys cascade on delete
- [ ] updated_at trigger fires on user updates

---

### Task 2.2 — [MIGRATION] Migrate existing tenant logins to users

| Field | Value |
|-------|-------|
| **Spec Sections** | 5.2 (migration path) |
| **Description** | Create migration 019_migrate_tenant_logins.sql that copies existing tenant email/password/role into the users table and creates user_tenants entries |
| **Depends On** | Task 2.1 |
| **Acceptance** | AC-2.3 |
| **Estimated Effort** | 15 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Create the file migrations/019_migrate_tenant_logins.sql.

This migration copies existing tenant login credentials into the new users table:

1. For each tenant that has an email and password_hash:
   INSERT INTO users (email, password_hash, display_name, role)
   SELECT email, password_hash, display_name, role FROM tenants
   WHERE email IS NOT NULL AND password_hash IS NOT NULL
   ON CONFLICT (email) DO NOTHING

2. For each migrated user, create a user_tenants entry linking them to their original tenant:
   INSERT INTO user_tenants (user_id, tenant_id)
   SELECT u.id, t.id
   FROM users u
   JOIN tenants t ON t.email = u.email
   ON CONFLICT (user_id, tenant_id) DO NOTHING

3. Add a comment noting that tenants.email, tenants.password_hash, and tenants.role columns are retained during the transition period and should NOT be dropped until the new auth flow is confirmed working.

This migration is additive — it does not remove or modify any existing tenant columns. Rollback-safe.

Include the run command in the header comment.
```

**Verification:**
- [ ] Every tenant with email/password_hash gets a corresponding users row
- [ ] Every migrated user gets a user_tenants entry linking to their tenant
- [ ] Superadmin tenant → superadmin user
- [ ] ON CONFLICT prevents duplicate inserts on re-run
- [ ] Existing tenant columns untouched

---

### Task 2.3 — [SERVICE] Database class user auth methods

| Field | Value |
|-------|-------|
| **Spec Sections** | 5.2 |
| **Description** | Add user authentication and management methods to lib/Database.php |
| **Depends On** | Task 2.1, Task 2.2 |
| **Acceptance** | AC-2.5, AC-2.6, AC-2.7 |
| **Estimated Effort** | 20 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Add the following methods to lib/Database.php in a new section with the comment header "// ------- USER AUTH METHODS -------".

1. verifyUserLogin(string $email, string $password): ?array
   - Query users table by email where is_active = true
   - Verify password with password_verify()
   - On success, update last_login_at to NOW()
   - Return array with: id, email, display_name, role (exclude password_hash)
   - Return null on failure

2. getUserTenants(int $userId): array
   - If user role is 'superadmin': return ALL active tenants (SELECT from tenants WHERE is_active = true AND role = 'tenant_admin')
   - Otherwise: return only assigned tenants via user_tenants join
   - Return array of: tenant id, display_name, community_name, community_type

3. getUserById(int $userId): ?array
   - SELECT from users WHERE id = $userId AND is_active = true
   - Return user row without password_hash, or null

4. createUser(string $email, string $password, string $displayName, string $role, array $tenantIds): int
   - INSERT into users with password_hash(PASSWORD_BCRYPT)
   - INSERT into user_tenants for each tenant ID
   - Return the new user ID
   - Use a transaction to ensure atomicity

5. updateUserTenants(int $userId, array $tenantIds): void
   - DELETE all existing user_tenants for this user
   - INSERT new user_tenants for each tenant ID
   - Use a transaction

Do not modify the existing verifyTenantLogin() method — it stays as a fallback during the transition period.
```

**Verification:**
- [ ] verifyUserLogin authenticates against users table
- [ ] getUserTenants returns all tenants for superadmin, assigned only for others
- [ ] createUser uses transaction for atomicity
- [ ] Existing verifyTenantLogin untouched

---

### Task 2.4 — [AUTH] Update dashboard auth to use users table

| Field | Value |
|-------|-------|
| **Spec Sections** | 5.2 (migration path), 4.3 |
| **Description** | Update dashboard/auth.php to authenticate against the users table and store user_id + assigned tenant list in session |
| **Depends On** | Task 2.3 |
| **Acceptance** | AC-2.4, AC-2.8 |
| **Estimated Effort** | 20 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Update dashboard/auth.php to authenticate against the users table instead of the tenants table.

Changes to attemptLogin():
1. First try Database::verifyUserLogin($email, $password)
2. If that returns a user, set these session vars:
   - $_SESSION['user_id'] = $user['id']
   - $_SESSION['user_email'] = $user['email']
   - $_SESSION['user_name'] = $user['display_name']
   - $_SESSION['user_role'] = $user['role']
   - $_SESSION['user_tenants'] = Database::getUserTenants($user['id'])
   — Also set the first assigned tenant as the active tenant:
   - $_SESSION['tenant_id'] = first tenant's id (or '' if no tenants)
   - $_SESSION['tenant_name'] = first tenant's display_name
   — Keep $_SESSION['tenant_role'] = $user['role'] for backward compatibility
   - $_SESSION['tenant_email'] = $user['email'] for backward compatibility
3. If verifyUserLogin returns null, fall back to Database::verifyTenantLogin() for backward compatibility during transition
4. Return true on success, false on failure

Add new helper functions:
- getUserId(): int — returns $_SESSION['user_id']
- getUserTenants(): array — returns $_SESSION['user_tenants']
- getActiveTenantId(): string — returns $_SESSION['tenant_id']
- isBuilder(): bool — returns $_SESSION['user_role'] === 'builder'
- canAccessAnalytics(): bool — returns role is superadmin or tenant_admin (not builder)
- switchTenant(string $tenantId): bool — validates the tenant is in user_tenants, updates $_SESSION['tenant_id'] and $_SESSION['tenant_name'], returns success

Update isAuthenticated() to check for user_id OR tenant_id (backward compatible).
Update isSuperAdmin() to check user_role first, fall back to tenant_role.
Keep all existing functions working — this is additive, not destructive.
```

**Verification:**
- [ ] Login works against users table
- [ ] Fallback to tenant login still works
- [ ] Session contains user_id, user_role, user_tenants array
- [ ] canAccessAnalytics() returns false for builder role
- [ ] switchTenant() validates against user's assigned tenants
- [ ] Existing dashboard pages still work with no changes

---

### Task 2.5 — [UI] Community switcher for multi-tenant users

| Field | Value |
|-------|-------|
| **Spec Sections** | 5.2 |
| **Description** | Add a community selector dropdown to the dashboard topbar for users with access to multiple tenants |
| **Depends On** | Task 2.4 |
| **Acceptance** | AC-2.5 |
| **Estimated Effort** | 20 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Update dashboard/includes/layout.php to add a community switcher dropdown in the topbar.

In the renderNav() function, after the existing topbar-right content but before the logout link:

1. Check if the user has more than one tenant in $_SESSION['user_tenants']
2. If yes, render a <select> dropdown styled to match the existing dashboard theme:
   - Each option is a tenant from user_tenants (value = tenant_id, text = display_name or community_name)
   - The currently active tenant ($_SESSION['tenant_id']) is selected
   - On change, submit to a small AJAX handler that calls switchTenant()
   - After switching, reload the page

3. Create dashboard/switch-tenant.php:
   - Require auth
   - Accept POST with tenant_id
   - Call switchTenant($tenantId) from auth.php
   - Return JSON {success: true/false}

Style the dropdown to match the existing topbar aesthetic:
- Background: var(--bg-input)
- Border: 1px solid var(--border)
- Color: var(--text)
- Font: 'DM Sans', 11px
- Height matches other topbar elements

If user has only one tenant, don't show the dropdown — just show the tenant name as before.
```

**Verification:**
- [ ] Users with 1 tenant see no dropdown (existing behavior)
- [ ] Users with 2+ tenants see a dropdown in the topbar
- [ ] Selecting a different tenant reloads the page with new tenant context
- [ ] All dashboard pages respect the switched tenant
- [ ] Superadmin sees all tenants in the dropdown

---

## Phase 3: Nightly Tagging Job

LLM classification of conversations, CLI script, backfill support.

---

### Task 3.1 — [SERVICE] LLM classification helper

| Field | Value |
|-------|-------|
| **Spec Sections** | 7.2, 7.3 |
| **Description** | Create lib/LLMClassifier.php — sends conversation to OpenAI or Anthropic API and returns structured classification JSON |
| **Depends On** | Phase 1 complete |
| **Acceptance** | AC-3.3 |
| **Estimated Effort** | 25 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Create lib/LLMClassifier.php with a class that sends conversations to an LLM for analytics classification.

The class should:

1. Constructor accepts: $provider ('openai' or 'anthropic'), $apiKey, $model
   - These come from global_settings (analytics_llm_provider, analytics_api_key, analytics_llm_model)

2. classify(array $messages, string $tenantId): ?array
   - $messages is an array of ['role' => 'user'|'assistant', 'content' => '...']
   - Format messages as "visitor: ..." and "assistant: ..." strings
   - Build the classification prompt from the spec (Section 7.2):
     Topics: pricing, amenities, schools, builders, floorplans, lot_info, location, hoa, move_in, tours, financing, inventory, community_info
     Objections: price_too_high, hoa_concerns, distance, flood_zone, construction, limited_inventory, school_concerns
   - Call the LLM API via cURL:
     For OpenAI: POST https://api.openai.com/v1/chat/completions
     For Anthropic: POST https://api.anthropic.com/v1/messages
   - Parse the JSON response
   - Validate the response structure (all required fields present, valid enum values)
   - Return the parsed classification array, or null on failure

3. Error handling:
   - If cURL fails, return null and log to STDERR
   - If JSON parsing fails, return null and log the raw response to STDERR
   - If response validation fails (bad enum values, missing fields), return null with details
   - Timeout: 30 seconds per request

Follow the existing cURL patterns from the codebase (see api/chat.php for OpenAI call examples).
Include the classification prompt as a class constant CLASSIFICATION_PROMPT.
```

**Verification:**
- [ ] OpenAI API calls work with valid key
- [ ] Anthropic API calls work with valid key
- [ ] Invalid JSON from LLM returns null gracefully
- [ ] Missing fields in response returns null
- [ ] Invalid enum values caught and rejected
- [ ] 30-second timeout enforced

---

### Task 3.2 — [SCRIPT] Analytics tagger CLI script

| Field | Value |
|-------|-------|
| **Spec Sections** | 7.1, 7.4, 7.5, 7.6 |
| **Description** | Create scripts/analytics-tagger.php — the nightly cron job that classifies unanalyzed sessions |
| **Depends On** | Task 1.2, Task 3.1 |
| **Acceptance** | AC-3.1, AC-3.2, AC-3.4, AC-3.5, AC-3.6, AC-3.7, AC-3.8 |
| **Estimated Effort** | 30 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Create scripts/analytics-tagger.php following the pattern of scripts/backfill-embeddings.php.

Usage:
  php scripts/analytics-tagger.php                    # Normal nightly run (last 24h)
  php scripts/analytics-tagger.php --backfill         # Process ALL historical sessions
  php scripts/analytics-tagger.php --tenant=pecan_square  # Filter to one tenant
  php scripts/analytics-tagger.php --batch=50         # Batch size (default 100)
  php scripts/analytics-tagger.php --dry-run          # Show what would be processed without calling LLM

The script should:

1. Load config and connect to DB (same pattern as backfill-embeddings.php)
2. Load LLM config from global_settings: analytics_llm_provider, analytics_api_key, analytics_llm_model
3. Validate the API key is set (exit with error if empty)
4. Parse CLI args: --backfill, --tenant=, --batch=, --dry-run
5. Call Database::getUnanalyzedSessions($backfill, $batchSize)
6. For each session:
   a. Fetch messages: Database::getMessages($sessionId)
   b. Skip if fewer than 2 messages (log skip)
   c. Create LLMClassifier instance and call classify()
   d. If classification succeeds:
      - Build the chat_analytics row from the classification + session data
      - session_started_at from session
      - session_duration_sec = EXTRACT(EPOCH FROM last_active - started_at)
      - lead_captured from sessions.lead_captured
      - tour_booked = check if a booking exists for this session_id
      - Call Database::insertAnalytics()
   e. If classification fails: increment error count, add to errorDetails array
   f. Print progress: [N/Total] tenant_id/session_id ... OK/FAILED
   g. Rate limit: sleep 200ms between requests, 1s pause every $batchSize

7. After processing all sessions in the batch:
   - Log the run via Database::logAnalyticsRun()
   - Print summary: Processed: N, Skipped: N, Errors: N, Duration: Ns

8. If --backfill and there are more unanalyzed sessions, print "Run again to process next batch"

The script must be idempotent — re-running processes only unanalyzed sessions.
```

**Verification:**
- [ ] Normal mode processes only last 24h sessions
- [ ] --backfill processes all historical sessions
- [ ] --tenant filters to specific community
- [ ] --dry-run shows sessions without calling LLM
- [ ] Sessions with < 2 messages skipped
- [ ] Failed classifications logged but don't stop the job
- [ ] Run logged to chat_analytics_log
- [ ] Idempotent — re-running is safe

---

### Task 3.3 — [SCRIPT] Run backfill and validate

| Field | Value |
|-------|-------|
| **Spec Sections** | 7.5 |
| **Description** | Run the backfill against existing test data and verify the analytics data is correctly populated |
| **Depends On** | Task 3.2 |
| **Acceptance** | AC-3.9, AC-3.10 |
| **Estimated Effort** | 15 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Run the analytics backfill and validate the results.

1. First, do a dry run to see how many sessions exist:
   php scripts/analytics-tagger.php --backfill --dry-run

2. Run the actual backfill:
   php scripts/analytics-tagger.php --backfill

3. If there are more sessions to process, run again until all are done.

4. Validate the results by running these queries:
   - SELECT COUNT(*) FROM chat_analytics;  (should match processed sessions)
   - SELECT tenant_id, COUNT(*) FROM chat_analytics GROUP BY tenant_id ORDER BY count DESC;
   - SELECT unnest(topics) AS topic, COUNT(*) FROM chat_analytics GROUP BY topic ORDER BY count DESC LIMIT 10;
   - SELECT intent_level, COUNT(*) FROM chat_analytics GROUP BY intent_level;
   - SELECT sentiment, COUNT(*) FROM chat_analytics GROUP BY sentiment;
   - SELECT * FROM chat_analytics_log ORDER BY run_at DESC LIMIT 5;

5. Show me the results of each query so we can verify the data looks right.
```

**Verification:**
- [ ] Backfill completes without fatal errors
- [ ] chat_analytics has rows for processed sessions
- [ ] Topics, intent levels, and sentiment values are valid enum values
- [ ] chat_analytics_log has run records
- [ ] Data spread looks reasonable (not all sessions tagged identically)

---

## Phase 4: Dashboard + Reporting

Analytics tab with charts, period filters, CSV export.

---

### Task 4.1 — [UI] Add analytics nav link and access control

| Field | Value |
|-------|-------|
| **Spec Sections** | 8.1 |
| **Description** | Add "ANALYTICS" tab to dashboard nav for superadmin and tenant_admin roles. Block builder access. |
| **Depends On** | Task 2.4 |
| **Acceptance** | AC-4.1, AC-4.2 |
| **Estimated Effort** | 10 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Update dashboard/includes/layout.php to add an ANALYTICS tab to the navigation.

In the renderNav() function:

1. For the superadmin tabs (when $isSuper && $inSuperDir):
   Add 'analytics' => ['url' => 'analytics.php', 'label' => 'ANALYTICS'] after 'leads'

2. For the tenant_admin tabs (else block):
   Add 'analytics' => ['url' => 'analytics.php', 'label' => 'ANALYTICS'] after 'leads'
   BUT only if canAccessAnalytics() returns true (this excludes builders)

The tab should appear between LEADS and BOOKINGS in the tenant view, and after LEADS in the super view.

Do not create the analytics.php page yet — just add the nav link. The page will be created in the next task.
```

**Verification:**
- [ ] Superadmin sees ANALYTICS tab in super admin panel
- [ ] Tenant admin sees ANALYTICS tab in their dashboard
- [ ] Builder role does NOT see ANALYTICS tab
- [ ] Tab links to analytics.php

---

### Task 4.2 — [UI] Analytics dashboard page — layout and summary cards

| Field | Value |
|-------|-------|
| **Spec Sections** | 8.1, 8.2, 8.3 |
| **Description** | Create dashboard/analytics.php with period filters, community filter (superadmin), and summary metric cards |
| **Depends On** | Task 4.1, Task 1.2 |
| **Acceptance** | AC-4.3, AC-4.4, AC-4.5, AC-4.6 |
| **Estimated Effort** | 30 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Create dashboard/analytics.php following the pattern of existing dashboard pages (see dashboard/session.php and dashboard/leads.php for reference).

The page should:

1. Require auth + analytics access:
   require_once __DIR__ . '/auth.php';
   require_once __DIR__ . '/includes/layout.php';
   requireAuth();
   if (!canAccessAnalytics()) { header('Location: index.php'); exit; }

2. Period selector at the top:
   - Buttons: Today, This Week, This Month, This Quarter, This Year, All Time
   - Custom date range picker (start date + end date inputs)
   - Store selection in URL params (?period=month or ?after=2026-01-01&before=2026-03-31)
   - Default to "This Month"

3. Community filter (superadmin only):
   - Dropdown with "All Communities" + each tenant
   - Uses $_GET['tenant'] parameter

4. Summary cards row (styled to match existing dashboard cards):
   - Total Conversations
   - Leads Captured
   - Tours Booked
   - Avg Session Duration (formatted as Xm Ys)
   - Lead Capture Rate (as percentage)
   Use Database::getAnalyticsSummary() with the selected filters.

5. Chart containers (empty divs with IDs — charts loaded in next task):
   - #chart-conversations (line chart area)
   - #chart-topics (horizontal bar)
   - #chart-intent (donut)
   - #chart-sentiment (donut)
   - #chart-price-ranges (bar)
   - #chart-objections (horizontal bar)
   - #chart-builders (horizontal bar)
   Arrange in a responsive grid: 2 columns on desktop, 1 on mobile.

6. CSV Export button at the top right:
   <a href="export-analytics.php?{current_filters}" class="btn">EXPORT CSV</a>

Style everything using the existing CSS variables from layout.php (var(--bg-card), var(--text), var(--border), etc.).
Use renderHead('Analytics'), renderNav('analytics'), renderFooter().
```

**Verification:**
- [ ] Page loads with correct auth check
- [ ] Period selector changes the displayed data
- [ ] Community filter visible for superadmin, hidden for tenant_admin
- [ ] Summary cards show correct aggregated numbers
- [ ] Chart container divs present with correct IDs
- [ ] CSV export link includes current filter params

---

### Task 4.3 — [API] Analytics chart data endpoint

| Field | Value |
|-------|-------|
| **Spec Sections** | 8.3 |
| **Description** | Create dashboard/api-analytics.php — AJAX endpoint that returns chart data as JSON |
| **Depends On** | Task 1.2, Task 4.2 |
| **Acceptance** | AC-4.7 |
| **Estimated Effort** | 15 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Create dashboard/api-analytics.php following the pattern of dashboard/api.php.

This endpoint returns JSON chart data for the analytics dashboard.

1. Require auth + analytics access
2. Accept GET parameters: chart (required), tenant (optional), after (optional), before (optional)
3. Validate chart parameter is one of: conversations_over_time, topics, intent, sentiment, price_ranges, objections, builders
4. Scope tenant_id:
   - If superadmin and tenant param provided: use that tenant
   - If superadmin and no tenant param: null (all tenants)
   - If tenant_admin: use their active tenant ID (ignore tenant param)
5. Call Database::getAnalyticsChartData($chart, $tenantId, $after, $before)
6. Return JSON response with: { success: true, data: {...} } or { success: false, error: "..." }
7. Set Content-Type: application/json header

Response format for each chart type should be Chart.js-ready:
{
  "labels": ["Jan 1", "Jan 2", ...],
  "datasets": [{ "data": [5, 12, ...], "label": "Conversations" }]
}
```

**Verification:**
- [ ] Endpoint returns valid JSON for all 7 chart types
- [ ] Tenant scoping enforced (tenant_admin can't see other tenants)
- [ ] Empty data returns empty arrays, not errors
- [ ] Invalid chart parameter returns error response

---

### Task 4.4 — [UI] Chart.js charts and CSV export

| Field | Value |
|-------|-------|
| **Spec Sections** | 8.3, 8.4 |
| **Description** | Wire up Chart.js to the analytics page and create the CSV export endpoint |
| **Depends On** | Task 4.2, Task 4.3 |
| **Acceptance** | AC-4.7, AC-4.8, AC-4.9 |
| **Estimated Effort** | 30 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Two parts: wire up Chart.js on the analytics page, and create the CSV export.

PART 1: Add Chart.js to dashboard/analytics.php

1. Add Chart.js CDN in the page head (before renderNav):
   <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

2. Add a <script> block at the bottom that:
   - Reads the current filter params from the URL
   - For each chart container (#chart-conversations, #chart-topics, etc.):
     a. Fetch data from api-analytics.php?chart=TYPE&tenant=X&after=Y&before=Z
     b. Create the appropriate Chart.js chart:
        - conversations_over_time: Line chart (line color: var(--blue) → use #3B7DD8)
        - topics: Horizontal bar chart
        - intent: Doughnut chart (browsing=#6B7A94, interested=#3B7DD8, ready_to_buy=#4A8C5C)
        - sentiment: Doughnut chart (positive=#4A8C5C, neutral=#6B7A94, negative=#C85555)
        - price_ranges: Vertical bar chart
        - objections: Horizontal bar chart
        - builders: Horizontal bar chart
     c. Use the dashboard color scheme: chart backgrounds transparent, grid lines var(--border), text var(--text-muted)
   - Handle empty data gracefully (show "No data for this period" message)

3. Chart.js global config to match the dark dashboard theme:
   - Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim()
   - Chart.defaults.borderColor = 'rgba(255,255,255,0.06)'

PART 2: Create dashboard/export-analytics.php

1. Require auth + analytics access
2. Read filter params: tenant, after, before
3. Scope tenant same as api-analytics.php
4. Call Database::getAnalyticsExport($tenantId, $after, $before)
5. Set headers:
   Content-Type: text/csv
   Content-Disposition: attachment; filename="hwchat-analytics-{tenant}-{after}-{before}.csv"
6. Output CSV with fputcsv():
   - Header row with all column names
   - For array columns (topics, objections, builders_mentioned, cross_referrals): implode with ", " so they're readable in spreadsheets
   - Session_started_at formatted as Y-m-d H:i:s

The charts should update when the period selector or community filter changes — add event listeners that re-fetch data and destroy/recreate charts.
```

**Verification:**
- [ ] All 7 charts render with real data
- [ ] Charts use dashboard color scheme
- [ ] Charts update when period or community filter changes
- [ ] Empty periods show "No data" message instead of broken chart
- [ ] CSV downloads with correct filename
- [ ] CSV arrays are human-readable in Excel/Google Sheets
- [ ] CSV respects current filter selections

---

### Task 4.5 — [UI] Superadmin analytics view

| Field | Value |
|-------|-------|
| **Spec Sections** | 8.1 |
| **Description** | Create dashboard/super/analytics.php for the superadmin panel with cross-community analytics view |
| **Depends On** | Task 4.2, Task 4.3, Task 4.4 |
| **Acceptance** | AC-4.3 |
| **Estimated Effort** | 15 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Create dashboard/super/analytics.php for the superadmin analytics view.

This is essentially the same as dashboard/analytics.php but:
1. Requires superadmin auth (requireSuperAdmin())
2. The community filter defaults to "All Communities" showing aggregate data
3. Uses renderNav('analytics') within the super directory context
4. API calls go to ../api-analytics.php (or adjust paths)
5. CSV export links to ../export-analytics.php

You can either:
- Duplicate dashboard/analytics.php with path adjustments, OR
- Refactor the shared logic into an include and use it in both locations

Choose whichever approach is cleaner given the existing codebase patterns. The existing super/ pages (index.php, tenants.php, leads.php) are standalone files, so duplication with path adjustment is consistent with the codebase.
```

**Verification:**
- [ ] Superadmin can access analytics from the admin panel
- [ ] "All Communities" aggregates data across all tenants
- [ ] Community filter allows drilling into individual tenants
- [ ] Charts and CSV work correctly from the super directory

---

## Phase 5: RobChat Port

Same schema and logic, deployed to a separate database.

---

### Task 5.1 — [MIGRATION] Deploy analytics schema to RobChat

| Field | Value |
|-------|-------|
| **Spec Sections** | 9 (Phase 5 ACs) |
| **Description** | Run the analytics and user account migrations against RobChat's database |
| **Depends On** | Phase 4 complete |
| **Acceptance** | AC-5.1 |
| **Estimated Effort** | 10 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Adapt and run migrations 017, 018, and 019 against the RobChat database.

Review each migration for any HWChat-specific references (tenant IDs, community names, Hillwood-specific data) and remove or parameterize them.

Migration 019 (tenant login migration) will need adjustment since RobChat has different tenants.

Run each migration and verify the tables are created correctly.
```

**Verification:**
- [ ] chat_analytics, chat_analytics_log, users, user_tenants tables exist in RobChat DB
- [ ] No HWChat-specific data in the schema
- [ ] Indexes and constraints match HWChat

---

### Task 5.2 — [CONFIG] Configure tagging job for RobChat

| Field | Value |
|-------|-------|
| **Spec Sections** | 9 (Phase 5 ACs) |
| **Description** | Set up the analytics tagger script to work against RobChat's database and LLM config |
| **Depends On** | Task 5.1 |
| **Acceptance** | AC-5.2, AC-5.4 |
| **Estimated Effort** | 15 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Configure the analytics-tagger.php script to work with RobChat.

Since RobChat and HWChat share the same codebase structure (lib/Database.php, config.php), the script should work by pointing to RobChat's config.

1. Verify that the classification prompt in LLMClassifier.php does not contain HWChat/Hillwood-specific language. The prompt should be generic enough to classify any chatbot conversation.
   - If it references "real estate community" specifically, make it configurable via a global_setting (analytics_classification_context) that defaults to a generic description.

2. Set the global_settings entries for analytics in RobChat's database.

3. Run a test: php scripts/analytics-tagger.php --dry-run
   Verify it finds unanalyzed sessions.

4. Run a small batch: php scripts/analytics-tagger.php --batch=10
   Verify classifications are written correctly.
```

**Verification:**
- [ ] Tagger script runs against RobChat database
- [ ] Classification prompt is generic (not Hillwood-specific)
- [ ] Analytics rows written to RobChat's chat_analytics table
- [ ] No hardcoded HWChat references in shared code

---

### Task 5.3 — [UI] Verify dashboard analytics in RobChat

| Field | Value |
|-------|-------|
| **Spec Sections** | 9 (Phase 5 ACs) |
| **Description** | Verify the analytics dashboard works in RobChat's admin panel |
| **Depends On** | Task 5.2 |
| **Acceptance** | AC-5.3 |
| **Estimated Effort** | 10 min |
| **Status** | [ ] Not Started |
| **Deviations** | |

**Prompt for Claude Code:**
```
Verify the analytics dashboard works correctly in RobChat:

1. Log into RobChat's dashboard
2. Navigate to the Analytics tab
3. Verify:
   - Summary cards show data from the test batch
   - Charts render correctly
   - Period filters work
   - CSV export downloads with correct data
   - Community filter (if applicable) scopes correctly

4. If any issues are found, fix them and document what was changed.
```

**Verification:**
- [ ] Analytics tab accessible in RobChat dashboard
- [ ] Charts render with RobChat data
- [ ] No HWChat branding or references visible
- [ ] CSV export works

---

## Summary

| Phase | Tasks | Estimated Time |
|-------|-------|---------------|
| Phase 0 — Setup | 1 task | ~5 min |
| Phase 1 — Schema | 2 tasks | ~35 min |
| Phase 2 — User Accounts | 5 tasks | ~85 min |
| Phase 3 — Tagging Job | 3 tasks | ~70 min |
| Phase 4 — Dashboard | 5 tasks | ~100 min |
| Phase 5 — RobChat Port | 3 tasks | ~35 min |
| **Total** | **19 tasks** | **~5.5 hours** |

**Cron job setup (after Phase 3):**
```
# Add to server crontab:
0 2 * * * cd /path/to/hwchat.robertguajardo.com && php scripts/analytics-tagger.php >> logs/analytics-tagger.log 2>&1
```
