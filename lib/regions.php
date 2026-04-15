<?php
/**
 * Region constants and scope helper functions for the superadmin scope selector.
 *
 * Scope is stored in $_SESSION as:
 *   scope_type  = 'all' | 'tenant'
 *   scope_value = null  | '{tenant id}'
 *
 * "All Communities" means tenants WHERE region IS NOT NULL — excludes demo and admin tenants.
 */

require_once __DIR__ . '/Database.php';

const REGIONS = [
    'dfw'     => 'Dallas Fort Worth',
    'houston' => 'Houston',
    'austin'  => 'Austin',
];

/**
 * Get the tenant IDs included in the current scope.
 * "all" scope: all tenant IDs where region IS NOT NULL.
 * "tenant" scope: single-element array with the selected tenant ID.
 */
function getScopedTenantIds(): ?array
{
    $scopeType  = $_SESSION['scope_type'] ?? 'all';
    $scopeValue = $_SESSION['scope_value'] ?? null;

    if ($scopeType === 'tenant' && $scopeValue) {
        return [$scopeValue];
    }

    // "all" scope — fetch all tenants with a region assigned
    $stmt = Database::db()->query('SELECT id FROM tenants WHERE region IS NOT NULL');
    return array_column($stmt->fetchAll(), 'id');
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
    $prefix = $tableAlias ? "{$tableAlias}." : '';
    $scopeType  = $_SESSION['scope_type'] ?? 'all';
    $scopeValue = $_SESSION['scope_value'] ?? null;

    if ($scopeType === 'tenant' && $scopeValue) {
        return [
            'clause' => " AND {$prefix}tenant_id = :scope_tenant_id",
            'params' => ['scope_tenant_id' => $scopeValue],
        ];
    }

    // "all" scope — include only tenants with a region (excludes demo/admin)
    return [
        'clause' => " AND {$prefix}tenant_id IN (SELECT id FROM tenants WHERE region IS NOT NULL)",
        'params' => [],
    ];
}

/**
 * Get a display label for the current scope.
 * "all" → "All Communities"
 * "tenant" → the tenant's display_name
 */
function getScopeLabel(): string
{
    $scopeType  = $_SESSION['scope_type'] ?? 'all';
    $scopeValue = $_SESSION['scope_value'] ?? null;

    if ($scopeType === 'tenant' && $scopeValue) {
        $stmt = Database::db()->prepare('SELECT display_name FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $scopeValue]);
        $row = $stmt->fetch();
        return $row ? $row['display_name'] : $scopeValue;
    }

    return 'All Communities';
}

/**
 * Get the list of tenants available in the scope dropdown.
 * Only tenants with a non-null region are included.
 */
function getScopedTenantList(): array
{
    $stmt = Database::db()->query('SELECT id, display_name FROM tenants WHERE region IS NOT NULL ORDER BY display_name');
    return $stmt->fetchAll();
}
