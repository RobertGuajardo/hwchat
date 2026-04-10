<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireAuth();

$sessionId = $_GET['id'] ?? '';
if (!$sessionId) { header('Location: index.php'); exit; }

$db = Database::db();
$tenantId = getTenantId();

// Get session (superadmin can view any; tenant_admin scoped to own)
if (isSuperAdmin()) {
    $stmt = $db->prepare('SELECT * FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
} else {
    $stmt = $db->prepare('SELECT * FROM sessions WHERE id = :id AND tenant_id = :tid');
    $stmt->execute(['id' => $sessionId, 'tid' => $tenantId]);
}
$session = $stmt->fetch();
if (!$session) { header('Location: index.php'); exit; }

// Messages
$stmt = $db->prepare('SELECT * FROM messages WHERE session_id = :id ORDER BY created_at ASC');
$stmt->execute(['id' => $sessionId]);
$messages = $stmt->fetchAll();

// Lead
if (isSuperAdmin()) {
    $stmt = $db->prepare('SELECT * FROM leads WHERE session_id = :id');
    $stmt->execute(['id' => $sessionId]);
} else {
    $stmt = $db->prepare('SELECT * FROM leads WHERE session_id = :id AND tenant_id = :tid');
    $stmt->execute(['id' => $sessionId, 'tid' => $tenantId]);
}
$lead = $stmt->fetch();

renderHead('Session');
renderNav('overview');
?>
    <main class="container" style="max-width:900px;">
        <div style="margin-bottom:24px;">
            <a href="index.php" class="btn btn-sm btn-ghost">← BACK TO LIST</a>
        </div>

        <div class="session-meta">
            <div class="meta-item">
                <span class="meta-label">Session ID</span>
                <span class="meta-value mono"><?php echo e(substr($session['id'], 0, 16)); ?>…</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Started</span>
                <span class="meta-value"><?php echo e(date('M j, Y g:i:s A', strtotime($session['started_at']))); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Last Active</span>
                <span class="meta-value"><?php echo e(date('M j, Y g:i:s A', strtotime($session['last_active']))); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Messages</span>
                <span class="meta-value"><?php echo (int)$session['message_count']; ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Source Page</span>
                <span class="meta-value"><?php echo e($session['page_url'] ?? '—'); ?></span>
            </div>
        </div>

        <?php if ($lead): ?>
        <div class="lead-card">
            <h3>✓ LEAD CAPTURED</h3>
            <div class="lead-grid">
                <?php foreach (['name','email','phone','company','project_type','budget'] as $field): ?>
                    <?php if (!empty($lead[$field])): ?>
                    <div class="lead-field">
                        <span class="meta-label"><?php echo e(ucwords(str_replace('_', ' ', $field))); ?></span>
                        <span class="meta-value"><?php echo e($lead[$field]); ?></span>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!empty($lead['message'])): ?>
                <div class="lead-field" style="grid-column:1/-1;">
                    <span class="meta-label">Message</span>
                    <span class="meta-value"><?php echo e($lead['message']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h2 style="font-size:16px;color:#fff;">CONVERSATION (<?php echo count($messages); ?>)</h2>
        </div>

        <?php if (empty($messages)): ?>
            <div class="empty-state">No messages in this session.</div>
        <?php else: ?>
            <?php foreach ($messages as $m): ?>
            <div class="message message-<?php echo e($m['role']); ?>">
                <div class="message-header">
                    <span>
                        <span class="message-role"><?php echo e(strtoupper($m['role'])); ?></span>
                        <?php if ($m['role'] === 'assistant' && $m['llm_provider']): ?>
                            <span class="message-provider">(<?php echo e($m['llm_provider']); ?>)</span>
                        <?php endif; ?>
                    </span>
                    <span class="message-time"><?php echo e(date('g:i:s A', strtotime($m['created_at']))); ?></span>
                </div>
                <div class="message-content"><?php echo e($m['content']); ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="display:flex;gap:8px;margin-top:32px;padding-top:24px;border-top:1px solid rgba(255,255,255,0.06);">
            <a href="index.php" class="btn">← BACK</a>
            <button class="btn btn-danger" onclick="deleteSession()">DELETE SESSION</button>
        </div>
    </main>

    <script>
    function deleteSession() {
        if (!confirm('Delete this conversation? This cannot be undone.')) return;
        fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete', ids: ['<?php echo e($sessionId); ?>'] })
        }).then(r => r.json()).then(d => {
            if (d.success) window.location.href = 'index.php';
            else alert('Error: ' + (d.error || 'Unknown'));
        });
    }
    </script>
<?php renderFooter(); ?>
