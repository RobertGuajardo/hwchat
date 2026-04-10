-- ============================================================
-- RobChat Plugin Architecture — Schema Plan
-- PostgreSQL Migration #004 (not yet applied)
-- ============================================================
-- A simple, extensible plugin system that lets tenants connect
-- external services without bloating the core product.
--
-- Plugins hook into lifecycle events:
--   - on_lead_captured
--   - on_booking_created
--   - on_booking_cancelled
--   - on_message_received
--   - on_session_started
--   - on_session_ended
--
-- Each plugin type has its own config shape stored as JSONB.
-- The backend loads active plugins and fires them at the right
-- moments — no tenant code execution, just config-driven.
-- ============================================================

BEGIN;

-- ---------------------------------------------------------------------------
-- 1. PLUGIN REGISTRY — defines available plugin types
-- ---------------------------------------------------------------------------
-- This is seeded with the built-in plugin definitions.
-- Tenants don't edit this table — they just install/configure.
--
CREATE TABLE plugin_registry (
    slug            TEXT PRIMARY KEY,       -- 'webhook', 'google_calendar', 'slack', etc.
    name            TEXT NOT NULL,          -- "Webhook"
    description     TEXT,                   -- "Send event data to any URL"
    category        TEXT NOT NULL DEFAULT 'integration',  -- 'calendar', 'crm', 'notification', 'integration'
    icon            TEXT,                   -- emoji or icon name for dashboard
    config_schema   JSONB,                 -- JSON Schema describing what config fields are needed
    events          TEXT[] NOT NULL,        -- which events this plugin can hook into
    is_available    BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order      INTEGER DEFAULT 0
);

-- ---------------------------------------------------------------------------
-- 2. TENANT PLUGINS — installed plugins per tenant
-- ---------------------------------------------------------------------------
CREATE TABLE tenant_plugins (
    id              SERIAL PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    plugin_slug     TEXT NOT NULL REFERENCES plugin_registry(slug),
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    config          JSONB NOT NULL DEFAULT '{}'::jsonb,   -- plugin-specific settings
    events          TEXT[] NOT NULL DEFAULT '{}',          -- which events this install listens to

    -- Tracking
    last_triggered  TIMESTAMPTZ,
    trigger_count   INTEGER NOT NULL DEFAULT 0,
    last_error      TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    UNIQUE (tenant_id, plugin_slug)
);

CREATE INDEX idx_tenant_plugins_tenant ON tenant_plugins(tenant_id);
CREATE INDEX idx_tenant_plugins_active ON tenant_plugins(tenant_id, is_active);

CREATE TRIGGER tenant_plugins_updated_at
    BEFORE UPDATE ON tenant_plugins
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

-- ---------------------------------------------------------------------------
-- 3. PLUGIN EVENT LOG — audit trail of plugin executions
-- ---------------------------------------------------------------------------
CREATE TABLE plugin_events (
    id              SERIAL PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    plugin_slug     TEXT NOT NULL,
    event_type      TEXT NOT NULL,          -- 'on_lead_captured', etc.
    status          TEXT NOT NULL CHECK (status IN ('success', 'failed', 'skipped')),
    request_data    JSONB,                  -- what was sent
    response_data   JSONB,                  -- what came back (truncated)
    error_message   TEXT,
    duration_ms     INTEGER,                -- how long the plugin call took
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_plugin_events_tenant ON plugin_events(tenant_id, created_at DESC);
CREATE INDEX idx_plugin_events_plugin ON plugin_events(tenant_id, plugin_slug);

-- Auto-cleanup: events older than 30 days can be purged by a cron job
-- DELETE FROM plugin_events WHERE created_at < NOW() - INTERVAL '30 days';

-- ---------------------------------------------------------------------------
-- 4. SEED: built-in plugin definitions
-- ---------------------------------------------------------------------------

-- Webhook (already partially implemented via lead_webhook field)
INSERT INTO plugin_registry (slug, name, description, category, icon, events, config_schema, sort_order)
VALUES (
    'webhook',
    'Webhook',
    'Send event data to any URL. Works with Zapier, Make, n8n, or your own server.',
    'integration',
    '🔗',
    ARRAY['on_lead_captured', 'on_booking_created', 'on_booking_cancelled', 'on_message_received'],
    '{
        "type": "object",
        "properties": {
            "url": { "type": "string", "title": "Webhook URL", "description": "POST requests will be sent here" },
            "secret": { "type": "string", "title": "Signing Secret", "description": "Optional — used to verify webhook authenticity" },
            "events": { "type": "array", "title": "Events to send", "items": { "type": "string" } }
        },
        "required": ["url"]
    }'::jsonb,
    1
);

-- Slack Notifications
INSERT INTO plugin_registry (slug, name, description, category, icon, events, config_schema, sort_order)
VALUES (
    'slack',
    'Slack',
    'Get notified in Slack when leads are captured or bookings are made.',
    'notification',
    '💬',
    ARRAY['on_lead_captured', 'on_booking_created'],
    '{
        "type": "object",
        "properties": {
            "webhook_url": { "type": "string", "title": "Slack Webhook URL", "description": "Create at api.slack.com/messaging/webhooks" },
            "channel": { "type": "string", "title": "Channel", "description": "Optional — overrides the webhook default" }
        },
        "required": ["webhook_url"]
    }'::jsonb,
    2
);

-- Email Forwarding (send leads to additional emails beyond the main one)
INSERT INTO plugin_registry (slug, name, description, category, icon, events, config_schema, sort_order)
VALUES (
    'email_forward',
    'Email Forwarding',
    'Send lead notifications to additional email addresses (team members, CRM inboxes).',
    'notification',
    '📧',
    ARRAY['on_lead_captured', 'on_booking_created'],
    '{
        "type": "object",
        "properties": {
            "emails": { "type": "string", "title": "Email Addresses", "description": "Comma-separated list of emails" },
            "include_conversation": { "type": "boolean", "title": "Include conversation transcript", "default": false }
        },
        "required": ["emails"]
    }'::jsonb,
    3
);

-- Google Calendar Sync
INSERT INTO plugin_registry (slug, name, description, category, icon, events, config_schema, sort_order)
VALUES (
    'google_calendar',
    'Google Calendar',
    'Sync bookings to Google Calendar. Blocks off busy times from your existing events.',
    'calendar',
    '📅',
    ARRAY['on_booking_created', 'on_booking_cancelled'],
    '{
        "type": "object",
        "properties": {
            "calendar_id": { "type": "string", "title": "Calendar ID", "description": "Usually your email address" },
            "add_meet_link": { "type": "boolean", "title": "Add Google Meet link", "default": true }
        },
        "required": ["calendar_id"]
    }'::jsonb,
    4
);

-- Outlook Calendar Sync
INSERT INTO plugin_registry (slug, name, description, category, icon, events, config_schema, sort_order)
VALUES (
    'outlook_calendar',
    'Outlook Calendar',
    'Sync bookings to Outlook/Microsoft 365. Blocks off busy times from your existing events.',
    'calendar',
    '📆',
    ARRAY['on_booking_created', 'on_booking_cancelled'],
    '{
        "type": "object",
        "properties": {
            "calendar_id": { "type": "string", "title": "Calendar", "description": "Which Outlook calendar to use" },
            "add_teams_link": { "type": "boolean", "title": "Add Teams meeting link", "default": false }
        },
        "required": []
    }'::jsonb,
    5
);

-- SMS Notifications (Twilio)
INSERT INTO plugin_registry (slug, name, description, category, icon, events, config_schema, sort_order)
VALUES (
    'sms_twilio',
    'SMS Notifications (Twilio)',
    'Get a text message when leads come in. Requires a Twilio account.',
    'notification',
    '📱',
    ARRAY['on_lead_captured', 'on_booking_created'],
    '{
        "type": "object",
        "properties": {
            "account_sid": { "type": "string", "title": "Twilio Account SID" },
            "auth_token": { "type": "string", "title": "Twilio Auth Token" },
            "from_number": { "type": "string", "title": "From Number", "description": "+1234567890 format" },
            "to_number": { "type": "string", "title": "Your Phone Number", "description": "+1234567890 format" }
        },
        "required": ["account_sid", "auth_token", "from_number", "to_number"]
    }'::jsonb,
    6
);

COMMIT;


-- ============================================================
-- HOW PLUGINS FIRE (pseudocode for PHP)
-- ============================================================
--
-- // In backend/lib/PluginManager.php:
--
-- class PluginManager {
--
--     // Fire all active plugins for an event
--     static function fire(string $tenantId, string $event, array $data): void
--     {
--         $plugins = DB::query("
--             SELECT tp.*, pr.slug
--             FROM tenant_plugins tp
--             JOIN plugin_registry pr ON tp.plugin_slug = pr.slug
--             WHERE tp.tenant_id = :tid
--               AND tp.is_active = TRUE
--               AND :event = ANY(tp.events)
--         ");
--
--         foreach ($plugins as $plugin) {
--             $handler = self::getHandler($plugin['plugin_slug']);
--             try {
--                 $result = $handler->execute($plugin['config'], $event, $data);
--                 self::logEvent($tenantId, $plugin['plugin_slug'], $event, 'success', $data, $result);
--             } catch (Exception $e) {
--                 self::logEvent($tenantId, $plugin['plugin_slug'], $event, 'failed', $data, null, $e->getMessage());
--             }
--         }
--     }
--
--     // Each plugin type has a handler class
--     static function getHandler(string $slug): PluginHandler
--     {
--         return match($slug) {
--             'webhook'          => new WebhookPlugin(),
--             'slack'            => new SlackPlugin(),
--             'email_forward'    => new EmailForwardPlugin(),
--             'google_calendar'  => new GoogleCalendarPlugin(),
--             'outlook_calendar' => new OutlookCalendarPlugin(),
--             'sms_twilio'       => new TwilioSmsPlugin(),
--             default            => throw new Exception("Unknown plugin: $slug"),
--         };
--     }
-- }
--
-- // Usage in chat.php after lead capture:
-- PluginManager::fire($tenantId, 'on_lead_captured', [
--     'lead_id' => $leadId,
--     'name'    => $name,
--     'email'   => $email,
--     ...
-- ]);
--
-- // Usage in book.php after booking:
-- PluginManager::fire($tenantId, 'on_booking_created', [
--     'booking_id' => $bookingId,
--     'date'       => $date,
--     'time'       => $time,
--     'guest_name' => $name,
--     ...
-- ]);
--
-- ============================================================
-- DASHBOARD PAGES NEEDED
-- ============================================================
--
-- Plugins (new main tab)
--   - Grid of available plugins with install/uninstall toggle
--   - Each plugin card shows: icon, name, description, status
--   - Click to configure: renders a form from config_schema
--   - Event log viewer: recent executions with success/fail
--
-- ============================================================
-- PLUGIN HANDLER INTERFACE (PHP)
-- ============================================================
--
-- interface PluginHandler {
--     /**
--      * Execute the plugin action.
--      * @param array $config   Plugin config from tenant_plugins.config
--      * @param string $event   Event name (e.g. 'on_lead_captured')
--      * @param array $data     Event payload
--      * @return array|null     Response data for logging
--      */
--     public function execute(array $config, string $event, array $data): ?array;
-- }
--
-- // Example: WebhookPlugin
-- class WebhookPlugin implements PluginHandler {
--     public function execute(array $config, string $event, array $data): ?array {
--         $url = $config['url'];
--         $payload = json_encode([
--             'event' => $event,
--             'timestamp' => date('c'),
--             'data' => $data,
--         ]);
--
--         $ch = curl_init($url);
--         curl_setopt_array($ch, [
--             CURLOPT_POST => true,
--             CURLOPT_POSTFIELDS => $payload,
--             CURLOPT_HTTPHEADER => [
--                 'Content-Type: application/json',
--                 'X-RobChat-Event: ' . $event,
--             ],
--             CURLOPT_TIMEOUT => 5,
--             CURLOPT_RETURNTRANSFER => true,
--         ]);
--
--         if (!empty($config['secret'])) {
--             $sig = hash_hmac('sha256', $payload, $config['secret']);
--             curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
--                 curl_getinfo($ch, CURLINFO_HEADER_OUT) ?: [],
--                 ['X-RobChat-Signature: ' . $sig]
--             ));
--         }
--
--         $response = curl_exec($ch);
--         $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
--         curl_close($ch);
--
--         if ($code >= 400) throw new Exception("Webhook returned $code");
--         return ['status_code' => $code, 'body' => substr($response, 0, 500)];
--     }
-- }
-- ============================================================
