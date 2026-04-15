<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../lib/regions.php';
requireMinRole('regional_admin');

$db = Database::db();

// Load tenants in scope with their system prompts
$scopedIds = getScopedTenantIds();

if (empty($scopedIds)) {
    $tenants = [];
} else {
    $placeholders = [];
    $params = [];
    foreach ($scopedIds as $i => $id) {
        $key = "tid_{$i}";
        $placeholders[] = ":{$key}";
        $params[$key] = $id;
    }
    $in = implode(',', $placeholders);
    $stmt = $db->prepare("
        SELECT id, display_name, community_name, system_prompt
        FROM tenants WHERE id IN ({$in})
        ORDER BY display_name
    ");
    $stmt->execute($params);
    $tenants = $stmt->fetchAll();
}

renderHead('Tenant Prompts');
renderNav('tenant_prompts');
?>
    <main class="container" style="max-width:900px;">
        <div class="action-bar">
            <h2>TENANT PROMPTS <span style="color:var(--text-muted);font-size:14px;">(<?php echo count($tenants); ?>)</span></h2>
        </div>

        <?php if (empty($tenants)): ?>
            <div class="empty-state">No tenants in your scope.</div>
        <?php else: ?>
            <?php foreach ($tenants as $t): ?>
            <div style="background:var(--bg-card);border:1px solid var(--border);margin-bottom:12px;">
                <button type="button" class="prompt-toggle" onclick="this.parentElement.classList.toggle('open')"
                    style="width:100%;padding:16px 20px;background:none;border:none;display:flex;align-items:center;justify-content:space-between;cursor:pointer;color:var(--text-bright);font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;letter-spacing:0.02em;text-align:left;">
                    <span><?php echo e($t['community_name'] ?: $t['display_name']); ?> <span style="font-weight:400;font-size:11px;color:var(--text-muted);margin-left:8px;"><?php echo e($t['id']); ?></span></span>
                    <span style="font-size:11px;color:var(--text-muted);">&#9660;</span>
                </button>
                <div class="prompt-body" style="display:none;padding:0 20px 20px;">
                    <pre style="background:var(--bg-input);border:1px solid var(--border);padding:16px;color:var(--text);font-family:'DM Sans',monospace;font-size:12px;line-height:1.7;white-space:pre-wrap;word-break:break-word;max-height:400px;overflow-y:auto;"><?php echo e($t['system_prompt'] ?? '(no prompt set)'); ?></pre>
                    <div style="margin-top:8px;font-size:11px;color:var(--text-muted);">
                        <?php echo number_format(strlen($t['system_prompt'] ?? '')); ?> characters — read-only
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <style>
    .prompt-toggle:hover { background: var(--bg-hover) !important; }
    .open .prompt-body { display: block !important; }
    .open .prompt-toggle span:last-child { transform: rotate(180deg); }
    </style>
<?php renderFooter(); ?>
