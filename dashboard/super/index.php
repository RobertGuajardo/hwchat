<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/layout.php';
requireSuperAdmin();

// Date range filter
$range = $_GET['range'] ?? '30';
$after = null; $before = null;
if ($range === 'custom' && !empty($_GET['after'])) {
    $after = $_GET['after'];
    $before = $_GET['before'] ?: date('Y-m-d');
} elseif ($range !== 'all') {
    $after = date('Y-m-d', strtotime("-{$range} days"));
}

// Aggregate stats (all tenants)
$stats = getStats(null, $after, $before);

// Per-tenant breakdown
$tenantStats = getAllTenantStats($after, $before);

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
