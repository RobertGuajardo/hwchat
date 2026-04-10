<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireAuth();

$db = Database::db();
$tenantId = getTenantId();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare('SELECT COUNT(*) FROM leads WHERE tenant_id = :tid');
$stmt->execute(['tid' => $tenantId]);
$totalRows = (int) $stmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$stmt = $db->prepare('
    SELECT l.*, s.page_url FROM leads l
    LEFT JOIN sessions s ON l.session_id = s.id
    WHERE l.tenant_id = :tid
    ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset
');
$stmt->bindValue('tid', $tenantId);
$stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$leads = $stmt->fetchAll();

renderHead('Leads');
renderNav('leads');
?>
    <main class="container">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <h2 style="font-size:16px;color:var(--text-bright);">LEADS <span style="color:var(--text-muted);font-size:14px;">(<?php echo number_format($totalRows); ?>)</span></h2>
        </div>

        <?php if (empty($leads)): ?>
            <div class="empty-state">No leads captured yet. Leads appear here when visitors share their contact info through your chatbot.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>NAME</th>
                        <th>EMAIL</th>
                        <th>PHONE</th>
                        <th>TYPE</th>
                        <th>PROJECT</th>
                        <th>BUDGET</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $l): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo e(date('M j, g:ia', strtotime($l['created_at']))); ?></td>
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
                        <td><?php echo e($l['project_type'] ?? '—'); ?></td>
                        <td><?php echo e($l['budget'] ?? '—'); ?></td>
                        <td>
                            <?php if ($l['session_id']): ?>
                                <a href="session.php?id=<?php echo urlencode($l['session_id']); ?>" class="btn btn-sm">CHAT</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="btn btn-sm">← PREV</a>
            <?php endif; ?>
            <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="btn btn-sm">NEXT →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
<?php renderFooter(); ?>
