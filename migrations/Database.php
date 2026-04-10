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
                   allowed_origins
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
}
