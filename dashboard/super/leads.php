<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/layout.php';
requireSuperAdmin();

$db = Database::db();

// Filters
$filterTenant = $_GET['tenant'] ?? '';
$range = $_GET['range'] ?? '30';
$after = null; $before = null;
if ($range === 'custom' && !empty($_GET['after'])) {
    $after = $_GET['after'];
    $before = $_GET['before'] ?: date('Y-m-d');
} elseif ($range !== 'all') {
    $after = date('Y-m-d', strtotime("-{$range} days"));
}

// Build query
$where = ' WHERE 1=1';
$params = [];
if ($filterTenant) {
    $where .= ' AND l.tenant_id = :tid';
    $params['tid'] = $filterTenant;
}
if ($after) { $where .= ' AND l.created_at >= :after'; $params['after'] = $after; }
if ($before) { $where .= ' AND l.created_at <= :before'; $params['before'] = $before . ' 23:59:59'; }

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT COUNT(*) FROM leads l $where");
$stmt->execute($params);
$totalRows = (int) $stmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$sql = "
    SELECT l.*, t.display_name AS tenant_display, t.community_name, s.page_url
    FROM leads l
    JOIN tenants t ON l.tenant_id = t.id
    LEFT JOIN sessions s ON l.session_id = s.id
    $where
    ORDER BY l.created_at DESC LIMIT :lim OFFSET :off
";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue('lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue('off', $offset, PDO::PARAM_INT);
$stmt->execute();
$leads = $stmt->fetchAll();

// Tenant list for filter dropdown
$stmt = $db->query("SELECT id, community_name, display_name FROM tenants WHERE role = 'tenant_admin' ORDER BY display_name");
$tenantList = $stmt->fetchAll();

renderHead('All Leads');
renderNav('leads');
?>
    <main class="container">
        <!-- Filters -->
        <form method="GET" class="filter-bar">
            <label class="filter-label">PERIOD</label>
            <div class="filter-pills">
                <?php foreach (['7' => '7D', '30' => '30D', '90' => '90D', 'all' => 'ALL'] as $val => $label): ?>
                    <button type="submit" name="range" value="<?php echo $val; ?>" class="pill <?php echo $range === (string)$val ? 'active' : ''; ?>"><?php echo $label; ?></button>
                <?php endforeach; ?>
            </div>
            <label class="filter-label" style="margin-left:16px;">COMMUNITY</label>
            <select name="tenant" class="form-select" style="width:auto;padding:6px 10px;font-size:11px;" onchange="this.form.submit()">
                <option value="">All Communities</option>
                <?php foreach ($tenantList as $tl): ?>
                    <option value="<?php echo e($tl['id']); ?>" <?php echo $filterTenant === $tl['id'] ? 'selected' : ''; ?>>
                        <?php echo e($tl['community_name'] ?: $tl['display_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="range" value="<?php echo e($range); ?>">
        </form>

        <div class="action-bar">
            <h2>LEADS <span style="color:var(--text-muted);font-size:14px;">(<?php echo number_format($totalRows); ?>)</span></h2>
        </div>

        <?php if (empty($leads)): ?>
            <div class="empty-state">No leads found for this period.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>COMMUNITY</th>
                        <th>NAME</th>
                        <th>EMAIL</th>
                        <th>PHONE</th>
                        <th>TYPE</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $l): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo e(date('M j, g:ia', strtotime($l['created_at']))); ?></td>
                        <td>
                            <span class="badge badge-type"><?php echo e(strtoupper($l['community_name'] ?: $l['tenant_display'])); ?></span>
                        </td>
                        <td><?php echo e($l['name'] ?? '—'); ?></td>
                        <td><?php if (!empty($l['email'])): ?><a href="mailto:<?php echo e($l['email']); ?>"><?php echo e($l['email']); ?></a><?php else: ?>—<?php endif; ?></td>
                        <td><?php if (!empty($l['phone'])): ?><a href="tel:<?php echo e($l['phone']); ?>"><?php echo e($l['phone']); ?></a><?php else: ?>—<?php endif; ?></td>
                        <td>
                            <?php if (($l['lead_type'] ?? 'lead') === 'booking'): ?>
                                <span class="badge badge-booking">BOOKING</span>
                            <?php else: ?>
                                <span class="badge badge-lead">LEAD</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($l['session_id']): ?>
                                <a href="../session.php?id=<?php echo urlencode($l['session_id']); ?>" class="btn btn-sm">CHAT</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $qs = http_build_query(array_filter(['range' => $range, 'tenant' => $filterTenant]));
            ?>
            <?php if ($page > 1): ?>
                <a href="?<?php echo $qs; ?>&page=<?php echo $page - 1; ?>" class="btn btn-sm">← PREV</a>
            <?php endif; ?>
            <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?<?php echo $qs; ?>&page=<?php echo $page + 1; ?>" class="btn btn-sm">NEXT →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
<?php renderFooter(); ?>
