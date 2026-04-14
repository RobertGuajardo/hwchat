<?php
/**
 * RobChat Database — PostgreSQL PDO wrapper
 *
 * Provides connection pooling (singleton), tenant lookups,
 * session/message/lead CRUD, and rate limiting.
 */

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Get or create the PDO connection (singleton).
     */
    public static function connect(array $config = []): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (empty($config)) {
            $config = self::$config;
        }
        self::$config = $config;

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $config['db_host'] ?? 'localhost',
            $config['db_port'] ?? 5432,
            $config['db_name'] ?? 'robchat'
        );

        self::$instance = new PDO($dsn, $config['db_user'] ?? '', $config['db_password'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$instance;
    }

    /**
     * Shortcut to get the connection.
     */
    public static function db(): PDO
    {
        if (self::$instance === null) {
            throw new RuntimeException('Database not connected. Call Database::connect($config) first.');
        }
        return self::$instance;
    }

    // -----------------------------------------------------------------------
    // TENANT METHODS
    // -----------------------------------------------------------------------

    /**
     * Fetch a tenant's public config (for the widget).
     * Returns null if tenant not found or inactive.
     */
    public static function getTenantConfig(string $tenantId): ?array
    {
        $stmt = self::db()->prepare('
            SELECT id, display_name, greeting, accent_color, accent_gradient,
                   ai_accent, widget_position, quick_replies, calendar_enabled,
                   allowed_origins, color_header_bg, color_header_text,
                   color_secondary, color_quick_btn_bg, color_quick_btn_text,
                   color_user_bubble, color_ai_bubble_border,
                   color_footer_bg, color_footer_text, color_send_btn,
                   community_phone, community_email, community_address
            FROM tenants
            WHERE id = :id AND is_active = TRUE
        ');
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        // Decode JSONB fields
        $row['quick_replies']   = json_decode($row['quick_replies'] ?? '[]', true);
        $row['allowed_origins'] = json_decode($row['allowed_origins'] ?? '[]', true);

        return $row;
    }

    /**
     * Fetch a tenant's full record (for backend use — includes LLM keys, etc.).
     */
    public static function getTenant(string $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenants WHERE id = :id AND is_active = TRUE');
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        // Decode JSONB fields
        $row['quick_replies']   = json_decode($row['quick_replies'] ?? '[]', true);
        $row['allowed_origins'] = json_decode($row['allowed_origins'] ?? '[]', true);
        $row['booking_days']    = json_decode($row['booking_days'] ?? '[1,2,3,4,5]', true);

        return $row;
    }

    /**
     * Fetch a global setting by key (e.g. master_system_prompt).
     */
    public static function getGlobalSetting(string $key): string
    {
        $stmt = self::db()->prepare("SELECT value FROM global_settings WHERE key = :key");
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : '';
    }

    /**
     * Fetch the master system prompt shared by all tenants.
     */
    public static function getMasterPrompt(): string
    {
        return self::getGlobalSetting('master_system_prompt');
    }

    /**
     * Get all communities for the directory editor (excludes superadmin and parent).
     */
    public static function getCommunities(): array
    {
        $stmt = self::db()->query("
            SELECT id, display_name, community_name, community_location, community_url,
                   community_type, school_district, community_tagline, is_active
            FROM tenants
            WHERE role = 'tenant_admin' AND community_type IN ('community', 'standard')
            ORDER BY community_name, display_name
        ");
        return $stmt->fetchAll();
    }

    /**
     * Update a community's directory fields.
     */
    public static function updateCommunityFields(string $id, array $fields): void
    {
        $stmt = self::db()->prepare('
            UPDATE tenants SET
                community_name = :community_name,
                community_location = :community_location,
                community_url = :community_url,
                school_district = :school_district,
                community_tagline = :community_tagline,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            'community_name'     => $fields['community_name'] ?? '',
            'community_location' => $fields['community_location'] ?? '',
            'community_url'      => $fields['community_url'] ?? '',
            'school_district'    => $fields['school_district'] ?? '',
            'community_tagline'  => $fields['community_tagline'] ?? '',
            'id'                 => $id,
        ]);
    }

    /**
     * Generate the HILLWOOD COMMUNITY DIRECTORY text block from the database.
     */
    public static function generateDirectoryText(): string
    {
        $communities = self::getCommunities();
        $crossRef = json_decode(self::getGlobalSetting('cross_referral_groups'), true) ?: [];

        // Build directory listing
        $lines = ["HILLWOOD COMMUNITY DIRECTORY:", ""];
        foreach ($communities as $c) {
            if (!$c['is_active'] || empty($c['community_name'])) continue;
            $parts = [$c['community_name']];
            if ($c['community_location']) $parts[] = $c['community_location'];
            if ($c['community_tagline']) $parts[] = $c['community_tagline'];
            if ($c['school_district']) $parts[] = $c['school_district'];
            $url = $c['community_url'] ? preg_replace('#^https?://(www\.)?#', '', $c['community_url']) : '';
            if ($url) $parts[] = $url;
            $lines[] = "- " . implode(" — ", $parts);
        }

        // Build cross-referral guide
        $lines[] = "";
        $lines[] = "CROSS-REFERRAL GUIDE (use when a visitor's needs suggest a better fit):";
        $lines[] = "";

        // Name lookup
        $nameMap = [];
        foreach ($communities as $c) {
            $nameMap[$c['id']] = $c['community_name'] ?: $c['display_name'];
        }

        if (!empty($crossRef['by_location'])) {
            $lines[] = "By location:";
            foreach ($crossRef['by_location'] as $region => $ids) {
                $names = array_filter(array_map(fn($id) => $nameMap[$id] ?? null, $ids));
                if ($names) $lines[] = "- {$region}: " . implode(", ", $names);
            }
            $lines[] = "";
        }

        if (!empty($crossRef['by_lifestyle'])) {
            $lines[] = "By lifestyle:";
            foreach ($crossRef['by_lifestyle'] as $style => $ids) {
                $names = array_filter(array_map(fn($id) => $nameMap[$id] ?? null, $ids));
                if ($names) $lines[] = "- {$style}: " . implode(", ", $names);
            }
            $lines[] = "";
        }

        // School district (auto-generated from tenant data)
        $lines[] = "By school district preference:";
        $districtMap = [];
        foreach ($communities as $c) {
            if (!$c['is_active'] || !$c['school_district']) continue;
            $districts = array_map('trim', explode('&', $c['school_district']));
            foreach ($districts as $d) {
                $districtMap[$d][] = $c['community_name'] ?: $c['display_name'];
            }
        }
        ksort($districtMap);
        foreach ($districtMap as $district => $names) {
            $lines[] = "- {$district}: " . implode(", ", $names);
        }
        $lines[] = "";
        $lines[] = "Note: For pricing and availability at any community, always direct visitors to use that community's website or chat assistant for current information. Do not guess at price ranges.";

        return implode("\n", $lines);
    }

    /**
     * Update a global setting.
     */
    public static function setGlobalSetting(string $key, string $value): void
    {
        $stmt = self::db()->prepare("
            INSERT INTO global_settings (key, value, updated_at)
            VALUES (:key, :value, NOW())
            ON CONFLICT (key) DO UPDATE SET value = :value2, updated_at = NOW()
        ");
        $stmt->execute(['key' => $key, 'value' => $value, 'value2' => $value]);
    }

    /**
     * Get all tenant records (superadmin).
     */
    public static function getAllTenants(): array
    {
        $stmt = self::db()->query("
            SELECT id, display_name, email, community_name, community_type,
                   community_location, is_active, role, created_at
            FROM tenants
            ORDER BY community_type, display_name
        ");
        return $stmt->fetchAll();
    }

    /**
     * Update a tenant's password (superadmin reset).
     */
    public static function updateTenantPassword(string $tenantId, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = self::db()->prepare('UPDATE tenants SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $tenantId]);
    }

    /**
     * Toggle tenant active status.
     */
    public static function setTenantActive(string $tenantId, bool $active): void
    {
        $stmt = self::db()->prepare('UPDATE tenants SET is_active = :active, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['active' => $active ? 'true' : 'false', 'id' => $tenantId]);
    }

    /**
     * Verify tenant dashboard password.
     */
    public static function verifyTenantLogin(string $email, string $password): ?array
    {
        $stmt = self::db()->prepare('SELECT id, email, password_hash, display_name, role FROM tenants WHERE email = :email AND is_active = TRUE');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password_hash'])) {
            unset($row['password_hash']);
            return $row;
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // SESSION METHODS
    // -----------------------------------------------------------------------

    /**
     * Get or create a chat session.
     */
    public static function getOrCreateSession(string $sessionId, string $tenantId, array $meta = []): array
    {
        // Try to find existing session
        $stmt = self::db()->prepare('SELECT * FROM sessions WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $sessionId, 'tenant_id' => $tenantId]);
        $session = $stmt->fetch();

        if ($session) {
            // Update last_active
            $update = self::db()->prepare('UPDATE sessions SET last_active = NOW() WHERE id = :id');
            $update->execute(['id' => $sessionId]);
            return $session;
        }

        // Create new session
        $stmt = self::db()->prepare('
            INSERT INTO sessions (id, tenant_id, page_url, user_agent, ip_hash)
            VALUES (:id, :tenant_id, :page_url, :user_agent, :ip_hash)
            RETURNING *
        ');
        $stmt->execute([
            'id'         => $sessionId,
            'tenant_id'  => $tenantId,
            'page_url'   => $meta['page_url'] ?? null,
            'user_agent' => $meta['user_agent'] ?? null,
            'ip_hash'    => $meta['ip_hash'] ?? null,
        ]);

        return $stmt->fetch();
    }

    // -----------------------------------------------------------------------
    // MESSAGE METHODS
    // -----------------------------------------------------------------------

    /**
     * Save a message to a session.
     */
    public static function saveMessage(string $sessionId, string $role, string $content, int $tokens = 0, string $provider = null): void
    {
        $stmt = self::db()->prepare('
            INSERT INTO messages (session_id, role, content, tokens_used, llm_provider)
            VALUES (:session_id, :role, :content, :tokens, :provider)
        ');
        $stmt->execute([
            'session_id' => $sessionId,
            'role'       => $role,
            'content'    => $content,
            'tokens'     => $tokens,
            'provider'   => $provider,
        ]);

        // Increment message count on session
        $update = self::db()->prepare('
            UPDATE sessions SET message_count = message_count + 1, last_active = NOW()
            WHERE id = :id
        ');
        $update->execute(['id' => $sessionId]);
    }

    /**
     * Get messages for a session.
     */
    public static function getMessages(string $sessionId, int $limit = 50): array
    {
        $stmt = self::db()->prepare('
            SELECT role, content, created_at FROM messages
            WHERE session_id = :session_id
            ORDER BY created_at ASC
            LIMIT :limit
        ');
        $stmt->bindValue('session_id', $sessionId);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // -----------------------------------------------------------------------
    // LEAD METHODS
    // -----------------------------------------------------------------------

    /**
     * Save a captured lead.
     */
    public static function saveLead(string $tenantId, array $data): int
    {
        $stmt = self::db()->prepare('
            INSERT INTO leads (tenant_id, session_id, name, email, company, phone, project_type, budget, message, source_page, lead_type)
            VALUES (:tenant_id, :session_id, :name, :email, :company, :phone, :project_type, :budget, :message, :source_page, :lead_type)
            RETURNING id
        ');
        $stmt->execute([
            'tenant_id'    => $tenantId,
            'session_id'   => $data['session_id'] ?? null,
            'name'         => $data['name'] ?? null,
            'email'        => $data['email'] ?? null,
            'company'      => $data['company'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'project_type' => $data['project_type'] ?? null,
            'budget'       => $data['budget'] ?? null,
            'message'      => $data['message'] ?? null,
            'source_page'  => $data['source_page'] ?? null,
            'lead_type'    => $data['lead_type'] ?? 'lead',
        ]);

        $result = $stmt->fetch();

        // Mark session as lead captured
        if (!empty($data['session_id'])) {
            $update = self::db()->prepare('UPDATE sessions SET lead_captured = TRUE WHERE id = :id');
            $update->execute(['id' => $data['session_id']]);
        }

        return (int) $result['id'];
    }

    // -----------------------------------------------------------------------
    // RATE LIMITING
    // -----------------------------------------------------------------------

    /**
     * Check and increment rate limit. Returns true if request is allowed.
     */
    public static function checkRateLimit(string $tenantId, string $ipHash, int $perMinute, int $perHour): bool
    {
        // Clean expired entries
        self::db()->exec("DELETE FROM rate_limits WHERE expires_at < NOW()");

        // Check minute window
        if (!self::incrementWindow($tenantId, $ipHash, 'minute', $perMinute, '1 minute')) {
            return false;
        }

        // Check hour window
        if (!self::incrementWindow($tenantId, $ipHash, 'hour', $perHour, '1 hour')) {
            return false;
        }

        return true;
    }

    private static function incrementWindow(string $tenantId, string $ipHash, string $window, int $limit, string $interval): bool
    {
        $stmt = self::db()->prepare('
            SELECT count FROM rate_limits
            WHERE tenant_id = :tenant_id AND ip_hash = :ip_hash AND window_type = :window_type AND expires_at > NOW()
        ');
        $stmt->execute(['tenant_id' => $tenantId, 'ip_hash' => $ipHash, 'window_type' => $window]);
        $row = $stmt->fetch();

        if ($row) {
            if ($row['count'] >= $limit) {
                return false;
            }
            $update = self::db()->prepare('
                UPDATE rate_limits SET count = count + 1
                WHERE tenant_id = :tenant_id AND ip_hash = :ip_hash AND window_type = :window_type
            ');
            $update->execute(['tenant_id' => $tenantId, 'ip_hash' => $ipHash, 'window_type' => $window]);
        } else {
            $insert = self::db()->prepare("
                INSERT INTO rate_limits (tenant_id, ip_hash, window_type, count, expires_at)
                VALUES (:tenant_id, :ip_hash, :window_type, 1, NOW() + INTERVAL '$interval')
                ON CONFLICT (tenant_id, ip_hash, window_type) DO UPDATE SET count = rate_limits.count + 1, expires_at = NOW() + INTERVAL '$interval'
            ");
            $insert->execute(['tenant_id' => $tenantId, 'ip_hash' => $ipHash, 'window_type' => $window]);
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // USER AUTH METHODS
    // -----------------------------------------------------------------------

    /**
     * Verify a user login against the users table.
     * Updates last_login_at on success. Returns user data or null.
     */
    public static function verifyUserLogin(string $email, string $password): ?array
    {
        $stmt = self::db()->prepare('
            SELECT id, email, password_hash, display_name, role
            FROM users WHERE email = :email AND is_active = TRUE
        ');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            return null;
        }

        $update = self::db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $update->execute(['id' => $row['id']]);

        unset($row['password_hash']);
        return $row;
    }

    /**
     * Get tenants a user can access.
     * Superadmins get all active tenants; others get only assigned tenants.
     */
    public static function getUserTenants(int $userId): array
    {
        // Check if superadmin
        $stmt = self::db()->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user && $user['role'] === 'superadmin') {
            $stmt = self::db()->query("
                SELECT id, display_name, community_name, community_type
                FROM tenants
                WHERE is_active = TRUE AND role = 'tenant_admin'
                ORDER BY community_name, display_name
            ");
            return $stmt->fetchAll();
        }

        $stmt = self::db()->prepare('
            SELECT t.id, t.display_name, t.community_name, t.community_type
            FROM user_tenants ut
            JOIN tenants t ON ut.tenant_id = t.id
            WHERE ut.user_id = :user_id AND t.is_active = TRUE
            ORDER BY t.community_name, t.display_name
        ');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a user by ID (excludes password_hash).
     */
    public static function getUserById(int $userId): ?array
    {
        $stmt = self::db()->prepare('
            SELECT id, email, display_name, role, is_active, last_login_at, created_at, updated_at
            FROM users WHERE id = :id AND is_active = TRUE
        ');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create a new user with tenant assignments. Uses a transaction for atomicity.
     */
    public static function createUser(string $email, string $password, string $displayName, string $role, array $tenantIds): int
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare('
                INSERT INTO users (email, password_hash, display_name, role)
                VALUES (:email, :hash, :name, :role)
                RETURNING id
            ');
            $stmt->execute([
                'email' => $email,
                'hash'  => $hash,
                'name'  => $displayName,
                'role'  => $role,
            ]);
            $userId = (int)$stmt->fetch()['id'];

            $insert = $db->prepare('
                INSERT INTO user_tenants (user_id, tenant_id) VALUES (:uid, :tid)
            ');
            foreach ($tenantIds as $tid) {
                $insert->execute(['uid' => $userId, 'tid' => $tid]);
            }

            $db->commit();
            return $userId;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Replace a user's tenant assignments. Uses a transaction for atomicity.
     */
    public static function updateUserTenants(int $userId, array $tenantIds): void
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM user_tenants WHERE user_id = :uid')
               ->execute(['uid' => $userId]);

            $insert = $db->prepare('
                INSERT INTO user_tenants (user_id, tenant_id) VALUES (:uid, :tid)
            ');
            foreach ($tenantIds as $tid) {
                $insert->execute(['uid' => $userId, 'tid' => $tid]);
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // ANALYTICS METHODS
    // -----------------------------------------------------------------------

    /**
     * Insert an analytics row for a classified session.
     * Uses ON CONFLICT to enforce idempotency — re-processing the same session is a no-op.
     * Returns the row ID, or 0 if the session was already analyzed.
     */
    public static function insertAnalytics(array $data): int
    {
        $stmt = self::db()->prepare('
            INSERT INTO chat_analytics (
                session_id, tenant_id, message_count, user_message_count,
                intent_level, lead_captured, tour_booked, xo_tool_called,
                cross_referrals, topics, price_range_min, price_range_max,
                bedrooms_requested, builders_mentioned, objections,
                sentiment, summary, session_started_at, session_duration_sec
            ) VALUES (
                :session_id, :tenant_id, :message_count, :user_message_count,
                :intent_level, :lead_captured, :tour_booked, :xo_tool_called,
                :cross_referrals, :topics, :price_range_min, :price_range_max,
                :bedrooms_requested, :builders_mentioned, :objections,
                :sentiment, :summary, :session_started_at, :session_duration_sec
            )
            ON CONFLICT (session_id) DO NOTHING
            RETURNING id
        ');
        $stmt->execute([
            'session_id'          => $data['session_id'],
            'tenant_id'           => $data['tenant_id'],
            'message_count'       => $data['message_count'],
            'user_message_count'  => $data['user_message_count'],
            'intent_level'        => $data['intent_level'],
            'lead_captured'       => $data['lead_captured'] ? 'true' : 'false',
            'tour_booked'         => $data['tour_booked'] ? 'true' : 'false',
            'xo_tool_called'      => $data['xo_tool_called'] ? 'true' : 'false',
            'cross_referrals'     => '{' . implode(',', array_map(fn($v) => '"' . str_replace('"', '\\"', $v) . '"', $data['cross_referrals'] ?? [])) . '}',
            'topics'              => '{' . implode(',', array_map(fn($v) => '"' . str_replace('"', '\\"', $v) . '"', $data['topics'] ?? [])) . '}',
            'price_range_min'     => $data['price_range_min'] ?? null,
            'price_range_max'     => $data['price_range_max'] ?? null,
            'bedrooms_requested'  => $data['bedrooms_requested'] ?? null,
            'builders_mentioned'  => '{' . implode(',', array_map(fn($v) => '"' . str_replace('"', '\\"', $v) . '"', $data['builders_mentioned'] ?? [])) . '}',
            'objections'          => '{' . implode(',', array_map(fn($v) => '"' . str_replace('"', '\\"', $v) . '"', $data['objections'] ?? [])) . '}',
            'sentiment'           => $data['sentiment'],
            'summary'             => $data['summary'],
            'session_started_at'  => $data['session_started_at'],
            'session_duration_sec'=> $data['session_duration_sec'] ?? null,
        ]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : 0;
    }

    /**
     * Get aggregated analytics summary for dashboard stat cards.
     * If $tenantId is null, returns stats across all tenants (superadmin view).
     */
    public static function getAnalyticsSummary(?string $tenantId, ?string $after, ?string $before): array
    {
        $where = 'WHERE 1=1';
        $params = [];
        if ($tenantId) {
            $where .= ' AND tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }
        if ($after) {
            $where .= ' AND session_started_at >= :after';
            $params['after'] = $after;
        }
        if ($before) {
            $where .= ' AND session_started_at <= :before';
            $params['before'] = $before . ' 23:59:59';
        }

        $stmt = self::db()->prepare("
            SELECT
                COUNT(*)::int AS total_conversations,
                COALESCE(SUM(CASE WHEN lead_captured THEN 1 ELSE 0 END), 0)::int AS total_leads,
                COALESCE(SUM(CASE WHEN tour_booked THEN 1 ELSE 0 END), 0)::int AS total_tours,
                COALESCE(AVG(session_duration_sec)::int, 0) AS avg_duration_sec,
                CASE WHEN COUNT(*) > 0
                    THEN ROUND(SUM(CASE WHEN lead_captured THEN 1 ELSE 0 END)::numeric / COUNT(*) * 100, 1)
                    ELSE 0
                END AS lead_capture_rate
            FROM chat_analytics
            $where
        ");
        $stmt->execute($params);
        return $stmt->fetch() ?: [
            'total_conversations' => 0,
            'total_leads' => 0,
            'total_tours' => 0,
            'avg_duration_sec' => 0,
            'lead_capture_rate' => 0,
        ];
    }

    /**
     * Get chart data formatted for Chart.js.
     * $chartType: conversations_over_time, topics, intent, sentiment, price_ranges, objections, builders
     */
    public static function getAnalyticsChartData(string $chartType, ?string $tenantId, ?string $after, ?string $before): array
    {
        $where = 'WHERE 1=1';
        $params = [];
        if ($tenantId) {
            $where .= ' AND tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }
        if ($after) {
            $where .= ' AND session_started_at >= :after';
            $params['after'] = $after;
        }
        if ($before) {
            $where .= ' AND session_started_at <= :before';
            $params['before'] = $before . ' 23:59:59';
        }

        switch ($chartType) {
            case 'conversations_over_time':
                $stmt = self::db()->prepare("
                    SELECT session_started_at::date AS day, COUNT(*)::int AS count
                    FROM chat_analytics $where
                    GROUP BY day ORDER BY day ASC
                ");
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                return [
                    'labels' => array_column($rows, 'day'),
                    'datasets' => [['label' => 'Conversations', 'data' => array_map('intval', array_column($rows, 'count'))]],
                ];

            case 'topics':
                $stmt = self::db()->prepare("
                    SELECT t AS label, COUNT(*)::int AS count
                    FROM chat_analytics, unnest(topics) AS t $where
                    GROUP BY t ORDER BY count DESC LIMIT 15
                ");
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                return [
                    'labels' => array_column($rows, 'label'),
                    'datasets' => [['label' => 'Topics', 'data' => array_map('intval', array_column($rows, 'count'))]],
                ];

            case 'intent':
                $stmt = self::db()->prepare("
                    SELECT intent_level AS label, COUNT(*)::int AS count
                    FROM chat_analytics $where
                    GROUP BY intent_level ORDER BY count DESC
                ");
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                return [
                    'labels' => array_column($rows, 'label'),
                    'datasets' => [['label' => 'Intent', 'data' => array_map('intval', array_column($rows, 'count'))]],
                ];

            case 'sentiment':
                $stmt = self::db()->prepare("
                    SELECT sentiment AS label, COUNT(*)::int AS count
                    FROM chat_analytics $where
                    GROUP BY sentiment ORDER BY count DESC
                ");
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                return [
                    'labels' => array_column($rows, 'label'),
                    'datasets' => [['label' => 'Sentiment', 'data' => array_map('intval', array_column($rows, 'count'))]],
                ];

            case 'price_ranges':
                $stmt = self::db()->prepare("
                    SELECT
                        CASE
                            WHEN price_range_max < 300000 THEN 'Under 300k'
                            WHEN price_range_min < 400000 AND price_range_max >= 300000 THEN '300k–400k'
                            WHEN price_range_min < 500000 AND price_range_max >= 400000 THEN '400k–500k'
                            ELSE '500k+'
                        END AS label,
                        COUNT(*)::int AS count
                    FROM chat_analytics $where
                        AND price_range_min IS NOT NULL
                    GROUP BY label ORDER BY count DESC
                ");
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                return [
                    'labels' => array_column($rows, 'label'),
                    'datasets' => [['label' => 'Price Ranges', 'data' => array_map('intval', array_column($rows, 'count'))]],
                ];

            case 'objections':
                $stmt = self::db()->prepare("
                    SELECT o AS label, COUNT(*)::int AS count
                    FROM chat_analytics, unnest(objections) AS o $where
                    GROUP BY o ORDER BY count DESC
                ");
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                return [
                    'labels' => array_column($rows, 'label'),
                    'datasets' => [['label' => 'Objections', 'data' => array_map('intval', array_column($rows, 'count'))]],
                ];

            case 'builders':
                $stmt = self::db()->prepare("
                    SELECT b AS label, COUNT(*)::int AS count
                    FROM chat_analytics, unnest(builders_mentioned) AS b $where
                    GROUP BY b ORDER BY count DESC LIMIT 15
                ");
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                return [
                    'labels' => array_column($rows, 'label'),
                    'datasets' => [['label' => 'Builders', 'data' => array_map('intval', array_column($rows, 'count'))]],
                ];

            default:
                return ['labels' => [], 'datasets' => []];
        }
    }

    /**
     * Export all analytics rows for CSV download.
     */
    public static function getAnalyticsExport(?string $tenantId, ?string $after, ?string $before): array
    {
        $where = 'WHERE 1=1';
        $params = [];
        if ($tenantId) {
            $where .= ' AND ca.tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }
        if ($after) {
            $where .= ' AND ca.session_started_at >= :after';
            $params['after'] = $after;
        }
        if ($before) {
            $where .= ' AND ca.session_started_at <= :before';
            $params['before'] = $before . ' 23:59:59';
        }

        $stmt = self::db()->prepare("
            SELECT ca.*, t.display_name AS tenant_name, t.community_name
            FROM chat_analytics ca
            JOIN tenants t ON ca.tenant_id = t.id
            $where
            ORDER BY ca.session_started_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Log an analytics tagger job run.
     */
    public static function logAnalyticsRun(int $processed, int $skipped, int $errors, int $durationSec, array $errorDetails = []): void
    {
        $stmt = self::db()->prepare('
            INSERT INTO chat_analytics_log (sessions_processed, sessions_skipped, errors, duration_sec, error_details)
            VALUES (:processed, :skipped, :errors, :duration, :details)
        ');
        $stmt->execute([
            'processed' => $processed,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'duration'  => $durationSec,
            'details'   => json_encode($errorDetails),
        ]);
    }

    /**
     * Get sessions that have not been analyzed yet.
     * If $backfill is false, only returns sessions from the last 24 hours.
     * Only returns sessions with at least 2 messages.
     */
    public static function getUnanalyzedSessions(bool $backfill = false, int $limit = 100): array
    {
        $timeFilter = $backfill ? '' : 'AND s.last_active >= NOW() - INTERVAL \'24 hours\'';

        $stmt = self::db()->prepare("
            SELECT s.id, s.tenant_id, s.started_at, s.last_active, s.message_count
            FROM sessions s
            LEFT JOIN chat_analytics ca ON s.id = ca.session_id
            WHERE ca.id IS NULL
              AND s.message_count >= 2
              $timeFilter
            ORDER BY s.started_at ASC
            LIMIT :lim
        ");
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
