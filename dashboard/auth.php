<?php
/**
 * HWChat Dashboard — Authentication & Helpers
 * Supports superadmin (all tenants) and tenant_admin (single tenant) roles.
 */

session_start();

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    die('Config file not found. Copy config.example.php to config.php.');
}
$config = require $configPath;

require_once __DIR__ . '/../lib/Database.php';

try {
    Database::connect($config);
} catch (PDOException $e) {
    die('Database connection failed.');
}

function isAuthenticated(): bool {
    return !empty($_SESSION['user_id']) || (!empty($_SESSION['tenant_id']) && !empty($_SESSION['tenant_email']));
}

function requireAuth(): void {
    if (!isAuthenticated()) {
        $loginUrl = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'super' ? '../index.php' : 'index.php';
        header("Location: $loginUrl");
        exit;
    }
}

function requireSuperAdmin(): void {
    requireAuth();
    if (!isSuperAdmin()) {
        $dashUrl = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'super' ? '../index.php' : 'index.php';
        header("Location: $dashUrl");
        exit;
    }
}

function isSuperAdmin(): bool {
    return ($_SESSION['user_role'] ?? $_SESSION['tenant_role'] ?? '') === 'superadmin';
}

function getTenantId(): string {
    return $_SESSION['tenant_id'] ?? '';
}

function getTenantName(): string {
    return $_SESSION['tenant_name'] ?? 'Dashboard';
}

function getTenantRole(): string {
    return $_SESSION['tenant_role'] ?? 'tenant_admin';
}

function attemptLogin(string $email, string $password): bool {
    // Try users table first (new auth)
    $user = Database::verifyUserLogin($email, $password);
    if ($user) {
        $tenants = Database::getUserTenants($user['id']);
        $firstTenant = $tenants[0] ?? null;

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_name']     = $user['display_name'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['user_region']   = $user['region'] ?? null;
        $_SESSION['user_tenants']  = $tenants;
        $_SESSION['tenant_id']     = $firstTenant['id'] ?? '';
        $_SESSION['tenant_name']   = $firstTenant['display_name'] ?? '';
        // Backward-compatible session vars
        $_SESSION['tenant_email']  = $user['email'];
        $_SESSION['tenant_role']   = $user['role'];
        // Scope defaults by role
        if ($user['role'] === 'superadmin' || $user['role'] === 'regional_admin') {
            $_SESSION['scope_type']  = 'all';
            $_SESSION['scope_value'] = null;
        }
        return true;
    }

    // Fall back to tenants table (legacy auth — transition period)
    $tenant = Database::verifyTenantLogin($email, $password);
    if ($tenant) {
        $_SESSION['tenant_id']    = $tenant['id'];
        $_SESSION['tenant_email'] = $tenant['email'];
        $_SESSION['tenant_name']  = $tenant['display_name'];
        $_SESSION['tenant_role']  = $tenant['role'] ?? 'tenant_admin';
        return true;
    }

    return false;
}

function getUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function getUserTenants(): array {
    return $_SESSION['user_tenants'] ?? [];
}

function getActiveTenantId(): string {
    return $_SESSION['tenant_id'] ?? '';
}

function isBuilder(): bool {
    return ($_SESSION['user_role'] ?? $_SESSION['tenant_role'] ?? '') === 'builder';
}

function canAccessAnalytics(): bool {
    $role = $_SESSION['user_role'] ?? $_SESSION['tenant_role'] ?? '';
    return in_array($role, ['superadmin', 'regional_admin', 'tenant_admin', 'builder']);
}

function switchTenant(string $tenantId): bool {
    $tenants = $_SESSION['user_tenants'] ?? [];
    foreach ($tenants as $t) {
        if ($t['id'] === $tenantId) {
            $_SESSION['tenant_id']   = $t['id'];
            $_SESSION['tenant_name'] = $t['display_name'];
            return true;
        }
    }
    return false;
}

function isRegionalAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'regional_admin';
}

function getUserRegion(): ?string {
    return $_SESSION['user_region'] ?? null;
}

/**
 * Role hierarchy: superadmin > regional_admin > tenant_admin > builder.
 * Redirects if the user's role is below the minimum required.
 */
function requireMinRole(string $minRole): void {
    requireAuth();
    $hierarchy = ['superadmin' => 4, 'regional_admin' => 3, 'tenant_admin' => 2, 'builder' => 1];
    $userRole = $_SESSION['user_role'] ?? $_SESSION['tenant_role'] ?? 'builder';
    $userLevel = $hierarchy[$userRole] ?? 0;
    $minLevel  = $hierarchy[$minRole] ?? 0;
    if ($userLevel < $minLevel) {
        $dashUrl = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'super' ? '../index.php' : 'index.php';
        header("Location: $dashUrl");
        exit;
    }
}

/**
 * Check if the current role can access a given page.
 * Page names match the role matrix in SPEC-ROLE-EXPANSION.md.
 */
function canAccessPage(string $page): bool {
    $role = $_SESSION['user_role'] ?? $_SESSION['tenant_role'] ?? 'builder';
    $matrix = [
        'overview'       => ['superadmin', 'regional_admin', 'tenant_admin'],
        'tenants'        => ['superadmin', 'regional_admin'],
        'communities'    => ['superadmin', 'regional_admin'],
        'master_prompt'  => ['superadmin'],
        'tenant_prompts' => ['regional_admin'],
        'leads'          => ['superadmin', 'regional_admin', 'tenant_admin'],
        'analytics'      => ['superadmin', 'regional_admin', 'tenant_admin', 'builder'],
        'users'          => ['superadmin', 'regional_admin'],
        'settings'       => ['superadmin', 'regional_admin', 'tenant_admin'],
        'knowledge'      => ['superadmin', 'regional_admin', 'tenant_admin'],
        'bookings'       => ['superadmin', 'regional_admin', 'tenant_admin', 'builder'],
    ];
    $allowed = $matrix[$page] ?? [];
    return in_array($role, $allowed);
}

/**
 * Get stats — pass null for tenantId to get all tenants (superadmin).
 */
function getStats(?string $tenantId = null, ?string $after = null, ?string $before = null): array {
    $db = Database::db();

    $where = '';
    $params = [];

    if ($tenantId) {
        $where .= ' AND s.tenant_id = :tenant_id';
        $params['tenant_id'] = $tenantId;
    }
    if ($after) {
        $where .= ' AND s.started_at >= :after';
        $params['after'] = $after;
    }
    if ($before) {
        $where .= ' AND s.started_at <= :before';
        $params['before'] = $before . ' 23:59:59';
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM sessions s WHERE 1=1 $where");
    $stmt->execute($params);
    $totalSessions = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM messages m JOIN sessions s ON m.session_id = s.id WHERE 1=1 $where");
    $stmt->execute($params);
    $totalMessages = (int) $stmt->fetchColumn();

    $leadWhere = ' WHERE 1=1';
    $leadParams = [];
    if ($tenantId) {
        $leadWhere .= ' AND l.tenant_id = :tenant_id';
        $leadParams['tenant_id'] = $tenantId;
    }
    if ($after) { $leadWhere .= ' AND l.created_at >= :after'; $leadParams['after'] = $after; }
    if ($before) { $leadWhere .= ' AND l.created_at <= :before'; $leadParams['before'] = $before . ' 23:59:59'; }
    $stmt = $db->prepare("SELECT COUNT(*) FROM leads l $leadWhere");
    $stmt->execute($leadParams);
    $totalLeads = (int) $stmt->fetchColumn();

    return [
        'total_sessions'  => $totalSessions,
        'total_messages'  => $totalMessages,
        'total_leads'     => $totalLeads,
        'conversion_rate' => $totalSessions > 0 ? round(($totalLeads / $totalSessions) * 100, 1) : 0,
        'avg_messages'    => $totalSessions > 0 ? round($totalMessages / $totalSessions, 1) : 0,
    ];
}

/**
 * Per-tenant stats breakdown for superadmin overview.
 */
function getAllTenantStats(?string $after = null, ?string $before = null): array {
    $db = Database::db();

    $dateWhere = '';
    $params = [];
    if ($after) { $dateWhere .= " AND s.started_at >= :after"; $params['after'] = $after; }
    if ($before) { $dateWhere .= " AND s.started_at <= :before"; $params['before'] = $before . ' 23:59:59'; }

    $leadDateWhere = '';
    $leadParams = [];
    if ($after) { $leadDateWhere .= " AND l.created_at >= :lead_after"; $leadParams['lead_after'] = $after; }
    if ($before) { $leadDateWhere .= " AND l.created_at <= :lead_before"; $leadParams['lead_before'] = $before . ' 23:59:59'; }

    $sql = "
        SELECT
            t.id,
            t.display_name,
            t.community_name,
            t.community_type,
            t.is_active,
            t.email,
            COALESCE(sess.cnt, 0) AS sessions,
            COALESCE(msg.cnt, 0) AS messages,
            COALESCE(ld.cnt, 0) AS leads,
            sess.latest
        FROM tenants t
        LEFT JOIN (
            SELECT tenant_id, COUNT(*) AS cnt, MAX(started_at) AS latest
            FROM sessions s WHERE 1=1 $dateWhere
            GROUP BY tenant_id
        ) sess ON sess.tenant_id = t.id
        LEFT JOIN (
            SELECT s.tenant_id, COUNT(*) AS cnt
            FROM messages m JOIN sessions s ON m.session_id = s.id
            WHERE 1=1 $dateWhere
            GROUP BY s.tenant_id
        ) msg ON msg.tenant_id = t.id
        LEFT JOIN (
            SELECT tenant_id, COUNT(*) AS cnt
            FROM leads l WHERE 1=1 $leadDateWhere
            GROUP BY tenant_id
        ) ld ON ld.tenant_id = t.id
        WHERE t.role = 'tenant_admin'
        ORDER BY COALESCE(sess.cnt, 0) DESC, t.display_name
    ";

    $allParams = array_merge($params, $leadParams);
    $stmt = $db->prepare($sql);
    $stmt->execute($allParams);
    return $stmt->fetchAll();
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}
