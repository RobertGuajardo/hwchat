<?php
/**
 * Region constants and scope helper functions.
 *
 * Role-aware: automatically restricts based on user's role and region.
 *   - superadmin: all tenants with region IS NOT NULL, or a specific tenant
 *   - regional_admin: tenants in their assigned region, or a specific tenant within it
 *   - tenant_admin / builder: only their assigned tenants
 *
 * Scope is stored in $_SESSION as:
 *   scope_type   = 'all' | 'tenant'
 *   scope_value  = null  | '{tenant id}'
 *   user_role    = 'superadmin' | 'regional_admin' | 'tenant_admin' | 'builder'
 *   user_region  = 'dfw' | 'houston' | 'austin' | null
 *   user_tenants = [{id, display_name, ...}, ...]
 */

require_once __DIR__ . '/Database.php';

const REGIONS = [
    'dfw'     => 'Dallas Fort Worth',
    'houston' => 'Houston',
    'austin'  => 'Austin',
];

/**
 * Get the tenant IDs included in the current scope, filtered by role.
 */
function getScopedTenantIds(): ?array
{
    $role       = $_SESSION['user_role'] ?? $_SESSION['tenant_role'] ?? 'tenant_admin';
    $region     = $_SESSION['user_region'] ?? null;
    $scopeType  = $_SESSION['scope_type'] ?? 'all';
    $scopeValue = $_SESSION['scope_value'] ?? null;

    // Specific tenant selected — applies to superadmin and regional_admin
    if ($scopeType === 'tenant' && $scopeValue) {
        // Regional admin: validate the tenant is in their region
        if ($role === 'regional_admin' && $region) {
            $stmt = Database::db()->prepare('SELECT id FROM tenants WHERE id = :id AND region = :region');
            $stmt->execute(['id' => $scopeValue, 'region' => $region]);
            if (!$stmt->fetch()) {
                return []; // tenant not in their region — return empty
            }
        }
        return [$scopeValue];
    }

    // "all" scope
    if ($role === 'superadmin') {
        $stmt = Database::db()->query('SELECT id FROM tenants WHERE region IS NOT NULL');
        return array_column($stmt->fetchAll(), 'id');
    }

    if ($role === 'regional_admin' && $region) {
        $stmt = Database::db()->prepare('SELECT id FROM tenants WHERE region = :region');
        $stmt->execute(['region' => $region]);
        return array_column($stmt->fetchAll(), 'id');
    }

    // tenant_admin / builder — return assigned tenants
    $tenants = $_SESSION['user_tenants'] ?? [];
    return array_column($tenants, 'id');
}

/**
 * Build a WHERE clause fragment for scope filtering.
 *
 * Usage:
 *   $scope = buildScopeWhereClause('l');
 *   $sql = "SELECT l.* FROM leads l WHERE 1=1 {$scope['clause']}";
 *   $stmt = $pdo->prepare($sql);
 *   $stmt->execute($scope['params']);
 *
 * @param string $tableAlias  Table alias (e.g. 'l' for leads). Empty for no alias.
 * @return array{clause: string, params: array}
 */
function buildScopeWhereClause(string $tableAlias = ''): array
{
    $prefix     = $tableAlias ? "{$tableAlias}." : '';
    $role       = $_SESSION['user_role'] ?? $_SESSION['tenant_role'] ?? 'tenant_admin';
    $region     = $_SESSION['user_region'] ?? null;
    $scopeType  = $_SESSION['scope_type'] ?? 'all';
    $scopeValue = $_SESSION['scope_value'] ?? null;

    // Specific tenant selected
    if ($scopeType === 'tenant' && $scopeValue) {
        // Regional admin: add region check to prevent accessing tenants outside their region
        if ($role === 'regional_admin' && $region) {
            return [
                'clause' => " AND {$prefix}tenant_id = :scope_tenant_id AND {$prefix}tenant_id IN (SELECT id FROM tenants WHERE region = :scope_region)",
                'params' => ['scope_tenant_id' => $scopeValue, 'scope_region' => $region],
            ];
        }
        return [
            'clause' => " AND {$prefix}tenant_id = :scope_tenant_id",
            'params' => ['scope_tenant_id' => $scopeValue],
        ];
    }

    // "all" scope — varies by role
    if ($role === 'superadmin') {
        return [
            'clause' => " AND {$prefix}tenant_id IN (SELECT id FROM tenants WHERE region IS NOT NULL)",
            'params' => [],
        ];
    }

    if ($role === 'regional_admin' && $region) {
        return [
            'clause' => " AND {$prefix}tenant_id IN (SELECT id FROM tenants WHERE region = :scope_region)",
            'params' => ['scope_region' => $region],
        ];
    }

    // tenant_admin / builder — restrict to assigned tenants
    $tenants = $_SESSION['user_tenants'] ?? [];
    $ids = array_column($tenants, 'id');
    if (empty($ids)) {
        return ['clause' => ' AND 1=0', 'params' => []]; // no access
    }
    // Build parameterized IN clause
    $placeholders = [];
    $params = [];
    foreach ($ids as $i => $id) {
        $key = "scope_tid_{$i}";
        $placeholders[] = ":{$key}";
        $params[$key] = $id;
    }
    return [
        'clause' => " AND {$prefix}tenant_id IN (" . implode(',', $placeholders) . ")",
        'params' => $params,
    ];
}

/**
 * Get a display label for the current scope.
 */
function getScopeLabel(): string
{
    $role       = $_SESSION['user_role'] ?? $_SESSION['tenant_role'] ?? 'tenant_admin';
    $region     = $_SESSION['user_region'] ?? null;
    $scopeType  = $_SESSION['scope_type'] ?? 'all';
    $scopeValue = $_SESSION['scope_value'] ?? null;

    // Specific tenant selected
    if ($scopeType === 'tenant' && $scopeValue) {
        $stmt = Database::db()->prepare('SELECT display_name FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $scopeValue]);
        $row = $stmt->fetch();
        return $row ? $row['display_name'] : $scopeValue;
    }

    // "all" scope label varies by role
    if ($role === 'regional_admin' && $region && isset(REGIONS[$region])) {
        return 'All ' . REGIONS[$region];
    }

    return 'All Communities';
}

/**
 * Get the list of tenants available in the scope dropdown.
 * Filtered by role: superadmin sees all with region, regional_admin sees their region only.
 */
function getScopedTenantList(): array
{
    $role   = $_SESSION['user_role'] ?? $_SESSION['tenant_role'] ?? 'tenant_admin';
    $region = $_SESSION['user_region'] ?? null;

    if ($role === 'regional_admin' && $region) {
        $stmt = Database::db()->prepare('SELECT id, display_name FROM tenants WHERE region = :region ORDER BY display_name');
        $stmt->execute(['region' => $region]);
        return $stmt->fetchAll();
    }

    // Superadmin — all tenants with a region
    $stmt = Database::db()->query('SELECT id, display_name FROM tenants WHERE region IS NOT NULL ORDER BY display_name');
    return $stmt->fetchAll();
}
