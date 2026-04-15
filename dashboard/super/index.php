<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../lib/regions.php';
requireMinRole('regional_admin');

// Date range filter
$range = $_GET['range'] ?? '30';
$after = null; $before = null;
if ($range === 'custom' && !empty($_GET['after'])) {
    $after = $_GET['after'];
    $before = $_GET['before'] ?: date('Y-m-d');
} elseif ($range !== 'all') {
    $after = date('Y-m-d', strtotime("-{$range} days"));
}

$db = Database::db();

// Build scope-aware tenant filter using buildScopeWhereClause
// This targets the tenants table (alias 't'), filtering by t.tenant_id — but tenants PK is 'id'.
// So we use getScopedTenantIds() to get the list and build an IN clause.
$scopedIds = getScopedTenantIds();

if (empty($scopedIds)) {
    $tenantStats = [];
} else {
    $ph = [];
    $scopeParams = [];
    foreach ($scopedIds as $i => $id) {
        $key = "stid_{$i}";
        $ph[] = ":{$key}";
        $scopeParams[$key] = $id;
    }
    $inClause = implode(',', $ph);

    $dateWhere = '';
    $dateParams = [];
    if ($after) { $dateWhere .= ' AND s.started_at >= :after'; $dateParams['after'] = $after; }
    if ($before) { $dateWhere .= ' AND s.started_at <= :before'; $dateParams['before'] = $before . ' 23:59:59'; }

    $leadDateWhere = '';
    $leadParams = [];
    if ($after) { $leadDateWhere .= ' AND l.created_at >= :lead_after'; $leadParams['lead_after'] = $after; }
    if ($before) { $leadDateWhere .= ' AND l.created_at <= :lead_before'; $leadParams['lead_before'] = $before . ' 23:59:59'; }

    $sql = "
        SELECT
            t.id, t.display_name, t.community_name, t.community_type, t.is_active, t.email,
            COALESCE(sess.cnt, 0) AS sessions,
            COALESCE(msg.cnt, 0) AS messages,
            COALESCE(ld.cnt, 0) AS leads,
            sess.latest
        FROM tenants t
        LEFT JOIN (
            SELECT tenant_id, COUNT(*) AS cnt, MAX(started_at) AS latest
            FROM sessions s WHERE 1=1 {$dateWhere}
            GROUP BY tenant_id
        ) sess ON sess.tenant_id = t.id
        LEFT JOIN (
            SELECT s.tenant_id, COUNT(*) AS cnt
            FROM messages m JOIN sessions s ON m.session_id = s.id
            WHERE 1=1 {$dateWhere}
            GROUP BY s.tenant_id
        ) msg ON msg.tenant_id = t.id
        LEFT JOIN (
            SELECT tenant_id, COUNT(*) AS cnt
            FROM leads l WHERE 1=1 {$leadDateWhere}
            GROUP BY tenant_id
        ) ld ON ld.tenant_id = t.id
        WHERE t.id IN ({$inClause})
        ORDER BY COALESCE(sess.cnt, 0) DESC, t.display_name
    ";

    $allParams = array_merge($scopeParams, $dateParams, $leadParams);
    $stmt = $db->prepare($sql);
    $stmt->execute($allParams);
    $tenantStats = $stmt->fetchAll();
}

// Compute aggregate stats from the scoped tenant breakdown
$stats = [
    'total_sessions'  => array_sum(array_column($tenantStats, 'sessions')),
    'total_messages'  => array_sum(array_column($tenantStats, 'messages')),
    'total_leads'     => array_sum(array_column($tenantStats, 'leads')),
    'conversion_rate' => 0,
    'avg_messages'    => 0,
];
if ($stats['total_sessions'] > 0) {
    $stats['conversion_rate'] = round(($stats['total_leads'] / $stats['total_sessions']) * 100, 1);
    $stats['avg_messages'] = round($stats['total_messages'] / $stats['total_sessions'], 1);
}

renderHead('Admin Overview');
renderNav('overview');
?>
    <main class="container">
        <!-- Date Range -->
        <form method="GET" class="filter-bar">
            <label class="filter-label">PERIOD</label>
            <div class="filter-pills">
                <?php foreach (['7' => '7D', '30' => '30D', '90' => '90D', 'all' => 'ALL'] as $val => $label): ?>
                    <button type="submit" name="range" value="<?php echo $val; ?>" class="pill <?php echo $range === (string)$val ? 'active' : ''; ?>"><?php echo $label; ?></button>
                <?php endforeach; ?>
            </div>
        </form>

        <!-- Aggregate Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-value"><?php echo count($tenantStats); ?></span>
                <span class="stat-label">ACTIVE TENANTS</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($stats['total_sessions']); ?></span>
                <span class="stat-label">TOTAL SESSIONS</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($stats['total_messages']); ?></span>
                <span class="stat-label">TOTAL MESSAGES</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($stats['total_leads']); ?></span>
                <span class="stat-label">TOTAL LEADS</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo $stats['conversion_rate']; ?>%</span>
                <span class="stat-label">CONVERSION</span>
            </div>
        </div>

        <!-- Per-Tenant Breakdown -->
        <div class="action-bar">
            <h2>COMMUNITY BREAKDOWN</h2>
        </div>

        <?php if (empty($tenantStats)): ?>
            <div class="empty-state">No tenant activity found for this period.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>COMMUNITY</th>
                        <th>TYPE</th>
                        <th class="center">SESSIONS</th>
                        <th class="center">MESSAGES</th>
                        <th class="center">LEADS</th>
                        <th class="center">CONV %</th>
                        <th>LAST ACTIVE</th>
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenantStats as $t): ?>
                    <tr class="tenant-row">
                        <td>
                            <div class="tenant-name"><?php echo e($t['community_name'] ?: $t['display_name']); ?></div>
                            <div class="tenant-id"><?php echo e($t['id']); ?></div>
                        </td>
                        <td><span class="badge badge-type"><?php echo e(strtoupper($t['community_type'] ?? 'standard')); ?></span></td>
                        <td class="center"><?php echo number_format($t['sessions']); ?></td>
                        <td class="center"><?php echo number_format($t['messages']); ?></td>
                        <td class="center">
                            <?php if ($t['leads'] > 0): ?>
                                <span class="badge badge-lead"><?php echo number_format($t['leads']); ?></span>
                            <?php else: ?>
                                <span class="badge-none">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="center">
                            <?php echo $t['sessions'] > 0 ? round(($t['leads'] / $t['sessions']) * 100, 1) . '%' : '—'; ?>
                        </td>
                        <td>
                            <?php echo $t['latest'] ? timeAgo($t['latest']) : '<span class="badge-none">never</span>'; ?>
                        </td>
                        <td>
                            <?php if ($t['is_active']): ?>
                                <span class="badge badge-active">ACTIVE</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">OFF</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
<?php renderFooter(); ?>
