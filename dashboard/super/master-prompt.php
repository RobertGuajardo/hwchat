<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/layout.php';
requireSuperAdmin();

$success = '';
$error = '';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_master';

    if ($action === 'save_master') {
        $newPrompt = $_POST['master_prompt'] ?? '';
        if (empty(trim($newPrompt))) {
            $error = 'Master prompt cannot be empty.';
        } else {
            Database::setGlobalSetting('master_system_prompt', trim($newPrompt));
            $success = 'Master system prompt updated. All 14 tenants will use this on their next conversation.';
        }
    }
}

// Load current values
$masterPrompt = Database::getMasterPrompt();
$charCount = strlen($masterPrompt);

// Get tenant count for display
$db = Database::db();
$stmt = $db->query("SELECT COUNT(*) FROM tenants WHERE role = 'tenant_admin' AND is_active = TRUE");
$activeTenants = (int) $stmt->fetchColumn();

renderHead('Master System Prompt');
renderNav('master');
?>
    <main class="container" style="max-width:1000px;">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="action-bar">
            <div>
                <h2>MASTER SYSTEM PROMPT</h2>
                <p style="color:var(--text-muted);font-size:12px;margin-top:4px;">
                    Shared by all <?php echo $activeTenants; ?> active communities. Prepended before each tenant's own prompt.
                </p>
            </div>
        </div>

        <!-- Architecture Diagram -->
        <div style="background:var(--navy-card);border:1px solid var(--border);padding:20px;margin-bottom:24px;">
            <span class="meta-label" style="display:block;margin-bottom:12px;">PROMPT ASSEMBLY ORDER</span>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:13px;">
                <span style="background:rgba(201,169,110,0.15);color:var(--gold);border:1px solid rgba(201,169,110,0.3);padding:6px 12px;">MASTER PROMPT</span>
                <span style="color:var(--text-muted);">→</span>
                <span style="background:rgba(59,125,216,0.12);color:var(--blue-light);border:1px solid rgba(59,125,216,0.3);padding:6px 12px;">TENANT PROMPT</span>
                <span style="color:var(--text-muted);">→</span>
                <span style="background:rgba(255,255,255,0.05);color:var(--text);border:1px solid var(--border);padding:6px 12px;">HOURS</span>
                <span style="color:var(--text-muted);">→</span>
                <span style="background:rgba(255,255,255,0.05);color:var(--text);border:1px solid var(--border);padding:6px 12px;">KB CONTEXT</span>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save_master">

            <div class="form-group">
                <textarea name="master_prompt" class="prompt-editor" id="promptEditor"><?php echo e($masterPrompt); ?></textarea>
                <div class="char-count">
                    <span id="charCount"><?php echo number_format($charCount); ?></span> characters
                    · Updated: <?php
                        $stmt = $db->prepare("SELECT updated_at FROM global_settings WHERE key = 'master_system_prompt'");
                        $stmt->execute();
                        $row = $stmt->fetch();
                        echo $row ? date('M j, Y g:i A', strtotime($row['updated_at'])) : 'never';
                    ?>
                </div>
            </div>

            <div style="display:flex;gap:8px;align-items:center;">
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;">SAVE MASTER PROMPT</button>
                <span style="font-size:12px;color:var(--text-muted);">Changes take effect immediately on the next conversation.</span>
            </div>
        </form>

        <!-- Quick reference: tenant prompts -->
        <div style="margin-top:48px;">
            <h3 style="font-size:14px;color:var(--text-bright);margin-bottom:16px;letter-spacing:0.06em;">TENANT PROMPTS (FOR REFERENCE)</h3>
            <p style="color:var(--text-muted);font-size:12px;margin-bottom:16px;">
                Each community has its own prompt appended after the master. These are edited in each tenant's Settings page.
            </p>
            <?php
            $stmt = $db->query("SELECT id, display_name, community_name, LENGTH(system_prompt) as prompt_len FROM tenants WHERE role = 'tenant_admin' ORDER BY display_name");
            $rows = $stmt->fetchAll();
            ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>COMMUNITY</th>
                            <th>TENANT ID</th>
                            <th class="center">PROMPT LENGTH</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="tenant-name"><?php echo e($r['community_name'] ?: $r['display_name']); ?></td>
                            <td class="tenant-id"><?php echo e($r['id']); ?></td>
                            <td class="center"><?php echo number_format($r['prompt_len']); ?> chars</td>
                            <td><a href="tenant-edit.php?id=<?php echo urlencode($r['id']); ?>" class="btn btn-sm">VIEW</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
    const editor = document.getElementById('promptEditor');
    const counter = document.getElementById('charCount');
    editor.addEventListener('input', function() {
        counter.textContent = this.value.length.toLocaleString();
    });
    </script>
<?php renderFooter(); ?>
