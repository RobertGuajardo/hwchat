<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';

// Handle login
$loginError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    if (attemptLogin($_POST['email'], $_POST['password'])) {
        $dest = isSuperAdmin() ? 'super/index.php' : 'index.php';
        header("Location: $dest");
        exit;
    }
    $loginError = true;
}

// Not authenticated — show login
if (!isAuthenticated()) {
    renderHead('Login');
?>
    <div class="login-container">
        <div class="login-box">
            <div class="login-stamp">HWCHAT</div>
            <h1>DASHBOARD</h1>
            <p class="login-sub">Sign in to manage your community chatbot</p>
            <?php if ($loginError): ?>
                <div class="alert alert-error">Invalid email or password.</div>
            <?php endif; ?>
            <form method="POST">
                <input type="email" name="email" class="form-input" placeholder="Email" value="<?php echo e($_POST['email'] ?? ''); ?>" autofocus required>
                <input type="password" name="password" class="form-input" placeholder="Password" required>
                <button type="submit" class="btn btn-primary" style="width:100%;padding:12px;margin-top:4px;">SIGN IN</button>
            </form>
            <div class="login-links">
                Don't have an account? <a href="register.php">Create one</a>
            </div>
        </div>
    </div>
<?php
    renderFooter();
    exit;
}

// ─── Authenticated: show dashboard ───
$tenantId = getTenantId();

// Date range filter
$range = $_GET['range'] ?? '30';
$after = null; $before = null;
$customAfter = $_GET['after'] ?? '';
$customBefore = $_GET['before'] ?? '';

if ($range === 'custom' && $customAfter) {
    $after = $customAfter;
    $before = $customBefore ?: date('Y-m-d');
} elseif ($range !== 'all') {
    $after = date('Y-m-d', strtotime("-{$range} days"));
}

$stats = getStats($tenantId, $after, $before);

// Sessions list
$db = Database::db();
$where = ' AND s.tenant_id = :tenant_id';
$params = ['tenant_id' => $tenantId];
if ($after) { $where .= ' AND s.started_at >= :after'; $params['after'] = $after; }
if ($before) { $where .= ' AND s.started_at <= :before'; $params['before'] = ($before ?: date('Y-m-d')) . ' 23:59:59'; }

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT COUNT(*) FROM sessions s WHERE 1=1 $where");
$stmt->execute($params);
$totalRows = (int) $stmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$stmt = $db->prepare("
    SELECT s.*, (SELECT COUNT(*) FROM leads WHERE session_id = s.id) as lead_count
    FROM sessions s WHERE 1=1 $where
    ORDER BY s.started_at DESC LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$sessions = $stmt->fetchAll();

renderHead('Overview');
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

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($stats['total_sessions']); ?></span>
                <span class="stat-label">SESSIONS</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($stats['total_messages']); ?></span>
                <span class="stat-label">MESSAGES</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($stats['total_leads']); ?></span>
                <span class="stat-label">LEADS</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo $stats['conversion_rate']; ?>%</span>
                <span class="stat-label">CONVERSION</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo $stats['avg_messages']; ?></span>
                <span class="stat-label">AVG MSG/SESSION</span>
            </div>
        </div>

        <!-- Embed Code -->
        <div class="embed-box">
            <span class="meta-label" style="margin-bottom:8px;display:block;">YOUR EMBED CODE</span>
            <code>&lt;script src="https://hwchat.robertguajardo.com/widget/robchat.js" data-robchat-id="<?php echo e($tenantId); ?>" defer&gt;&lt;/script&gt;</code>
        </div>

        <!-- Sessions -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
            <h2 style="font-size:16px;color:#fff;">CONVERSATIONS <span style="color:#666;font-size:14px;">(<?php echo number_format($totalRows); ?>)</span></h2>
            <div style="display:flex;gap:4px;">
                <a href="api.php?action=export&format=csv&range=<?php echo e($range); ?>" class="btn btn-sm">CSV</a>
                <a href="api.php?action=export&format=json&range=<?php echo e($range); ?>" class="btn btn-sm">JSON</a>
            </div>
        </div>

        <?php if (empty($sessions)): ?>
            <div class="empty-state">No conversations yet. Embed your chat widget to get started.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>STARTED</th>
                        <th class="center">MSGS</th>
                        <th>PAGE</th>
                        <th>LEAD</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $s): ?>
                    <tr>
                        <td><a href="session.php?id=<?php echo urlencode($s['id']); ?>"><?php echo e(date('M j, g:ia', strtotime($s['started_at']))); ?></a></td>
                        <td class="center"><?php echo (int)$s['message_count']; ?></td>
                        <td class="truncate" style="max-width:200px;"><?php echo e($s['page_url'] ?? '—'); ?></td>
                        <td>
                            <?php if ($s['lead_count'] > 0): ?>
                                <span class="badge badge-lead">LEAD</span>
                            <?php else: ?>
                                <span class="badge-none">—</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="session.php?id=<?php echo urlencode($s['id']); ?>" class="btn btn-sm">VIEW</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?range=<?php echo e($range); ?>&page=<?php echo $page - 1; ?>" class="btn btn-sm">← PREV</a>
            <?php endif; ?>
            <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?range=<?php echo e($range); ?>&page=<?php echo $page + 1; ?>" class="btn btn-sm">NEXT →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
<?php renderFooter(); ?>
