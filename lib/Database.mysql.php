<?php
/**
 * RobChat Database — MySQL PDO wrapper
 */

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

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
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['db_host'] ?? 'localhost',
            $config['db_port'] ?? 3306,
            $config['db_name'] ?? 'robchat'
        );

        self::$instance = new PDO($dsn, $config['db_user'] ?? '', $config['db_password'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$instance;
    }

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

    public static function getTenantConfig(string $tenantId): ?array
    {
        $stmt = self::db()->prepare('
            SELECT id, display_name, greeting, accent_color, accent_gradient,
                   ai_accent, widget_position, quick_replies, calendar_enabled,
                   allowed_origins
            FROM tenants
            WHERE id = :id AND is_active = 1
        ');
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['quick_replies']   = json_decode($row['quick_replies'] ?? '[]', true) ?: [];
        $row['allowed_origins'] = json_decode($row['allowed_origins'] ?? '[]', true) ?: [];

        return $row;
    }

    public static function getTenant(string $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenants WHERE id = :id AND is_active = 1');
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['quick_replies']   = json_decode($row['quick_replies'] ?? '[]', true) ?: [];
        $row['allowed_origins'] = json_decode($row['allowed_origins'] ?? '[]', true) ?: [];
        $row['booking_days']    = json_decode($row['booking_days'] ?? '[1,2,3,4,5]', true) ?: [1,2,3,4,5];

        return $row;
    }

    public static function verifyTenantLogin(string $email, string $password): ?array
    {
        $stmt = self::db()->prepare('SELECT id, email, password_hash, display_name FROM tenants WHERE email = :email AND is_active = 1');
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

    public static function getOrCreateSession(string $sessionId, string $tenantId, array $meta = []): array
    {
        $stmt = self::db()->prepare('SELECT * FROM sessions WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $sessionId, 'tenant_id' => $tenantId]);
        $session = $stmt->fetch();

        if ($session) {
            $update = self::db()->prepare('UPDATE sessions SET last_active = NOW() WHERE id = :id');
            $update->execute(['id' => $sessionId]);
            return $session;
        }

        $stmt = self::db()->prepare('
            INSERT INTO sessions (id, tenant_id, page_url, user_agent, ip_hash)
            VALUES (:id, :tenant_id, :page_url, :user_agent, :ip_hash)
        ');
        $stmt->execute([
            'id'         => $sessionId,
            'tenant_id'  => $tenantId,
            'page_url'   => $meta['page_url'] ?? null,
            'user_agent' => $meta['user_agent'] ?? null,
            'ip_hash'    => $meta['ip_hash'] ?? null,
        ]);

        $stmt = self::db()->prepare('SELECT * FROM sessions WHERE id = :id');
        $stmt->execute(['id' => $sessionId]);
        return $stmt->fetch();
    }

    // -----------------------------------------------------------------------
    // MESSAGE METHODS
    // -----------------------------------------------------------------------

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

        $update = self::db()->prepare('
            UPDATE sessions SET message_count = message_count + 1, last_active = NOW()
            WHERE id = :id
        ');
        $update->execute(['id' => $sessionId]);
    }

    public static function getMessages(string $sessionId, int $limit = 50): array
    {
        $stmt = self::db()->prepare('
            SELECT role, content, created_at FROM messages
            WHERE session_id = :session_id
            ORDER BY created_at ASC
            LIMIT :lim
        ');
        $stmt->bindValue('session_id', $sessionId);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // -----------------------------------------------------------------------
    // LEAD METHODS
    // -----------------------------------------------------------------------

    public static function saveLead(string $tenantId, array $data): int
    {
        $stmt = self::db()->prepare('
            INSERT INTO leads (tenant_id, session_id, name, email, company, phone, project_type, budget, message, source_page, lead_type)
            VALUES (:tenant_id, :session_id, :name, :email, :company, :phone, :project_type, :budget, :message, :source_page, :lead_type)
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

        $leadId = (int) self::db()->lastInsertId();

        if (!empty($data['session_id'])) {
            $update = self::db()->prepare('UPDATE sessions SET lead_captured = 1 WHERE id = :id');
            $update->execute(['id' => $data['session_id']]);
        }

        return $leadId;
    }

    // -----------------------------------------------------------------------
    // RATE LIMITING
    // -----------------------------------------------------------------------

    public static function checkRateLimit(string $tenantId, string $ipHash, int $perMinute, int $perHour): bool
    {
        self::db()->exec("DELETE FROM rate_limits WHERE expires_at < NOW()");

        if (!self::incrementWindow($tenantId, $ipHash, 'minute', $perMinute, 1)) {
            return false;
        }

        if (!self::incrementWindow($tenantId, $ipHash, 'hour', $perHour, 60)) {
            return false;
        }

        return true;
    }

    private static function incrementWindow(string $tenantId, string $ipHash, string $window, int $limit, int $intervalMinutes): bool
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
                VALUES (:tenant_id, :ip_hash, :window_type, 1, DATE_ADD(NOW(), INTERVAL :mins MINUTE))
                ON DUPLICATE KEY UPDATE count = count + 1, expires_at = DATE_ADD(NOW(), INTERVAL :mins2 MINUTE)
            ");
            $insert->execute([
                'tenant_id'   => $tenantId,
                'ip_hash'     => $ipHash,
                'window_type' => $window,
                'mins'        => $intervalMinutes,
                'mins2'       => $intervalMinutes,
            ]);
        }

        return true;
    }
}
