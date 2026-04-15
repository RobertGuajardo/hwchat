<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/layout.php';
requireMinRole('regional_admin');

$db = Database::db();
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetId = $_POST['tenant_id'] ?? '';

    if ($action === 'reset_password' && $targetId) {
        $newPass = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            Database::updateTenantPassword($targetId, $newPass);
            $success = "Password reset for {$targetId}.";
        }
    }

    if ($action === 'toggle_active' && $targetId) {
        $newState = ($_POST['new_state'] ?? '1') === '1';
        Database::setTenantActive($targetId, $newState);
        $success = $targetId . ($newState ? ' activated.' : ' deactivated.');
    }

    if ($action === 'create_tenant') {
        $newId = trim($_POST['new_id'] ?? '');
        $newEmail = trim($_POST['new_email'] ?? '');
        $newPass = $_POST['new_password'] ?? '';
        $newName = trim($_POST['new_display_name'] ?? '');
        $newCommunity = trim($_POST['new_community_name'] ?? '');

        if (!$newId || !$newEmail || !$newPass || !$newName) {
            $error = 'All fields are required.';
        } elseif (strlen($newPass) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $newId)) {
            $error = 'Tenant ID must be lowercase letters, numbers, and underscores only.';
        } else {
            try {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $db->prepare("
                    INSERT INTO tenants (id, email, password_hash, display_name, community_name, role, is_active)
                    VALUES (:id, :email, :hash, :name, :community, 'tenant_admin', TRUE)
                ");
                $stmt->execute([
                    'id' => $newId,
                    'email' => $newEmail,
                    'hash' => $hash,
                    'name' => $newName,
                    'community' => $newCommunity ?: $newName,
                ]);
                $success = "Tenant '{$newId}' created.";
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'duplicate') ? 'Tenant ID or email already exists.' : 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Load tenants — regional admin scoped to their region
if (isRegionalAdmin()) {
    $myRegion = getUserRegion();
    $stmt = $db->prepare("
        SELECT id, display_name, email, community_name, community_type,
               community_location, is_active, role, region, created_at
        FROM tenants WHERE region = :region
        ORDER BY community_type, display_name
    ");
    $stmt->execute(['region' => $myRegion]);
    $tenants = $stmt->fetchAll();
} else {
    $tenants = Database::getAllTenants();
}

renderHead('Manage Tenants');
renderNav('tenants');
?>
    <main class="container">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="action-bar">
            <h2>TENANTS <span style="color:var(--text-muted);font-size:14px;">(<?php echo count($tenants); ?>)</span></h2>
            <button class="btn btn-primary" onclick="document.getElementById('createModal').style.display='flex'">+ NEW TENANT</button>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>TENANT</th>
                        <th>EMAIL</th>
                        <th>TYPE</th>
                        <th>ROLE</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $t): ?>
                    <tr class="tenant-row">
                        <td>
                            <div class="tenant-name"><?php echo e($t['community_name'] ?: $t['display_name']); ?></div>
                            <div class="tenant-id"><?php echo e($t['id']); ?></div>
                        </td>
                        <td><?php echo e($t['email']); ?></td>
                        <td><span class="badge badge-type"><?php echo e(strtoupper($t['community_type'] ?? 'standard')); ?></span></td>
                        <td>
                            <?php if ($t['role'] === 'superadmin'): ?>
                                <span class="badge badge-super">SUPER</span>
                            <?php else: ?>
                                <span class="badge badge-type">TENANT</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['is_active']): ?>
                                <span class="badge badge-active">ACTIVE</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">OFF</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <button class="btn btn-sm" onclick="showResetModal('<?php echo e($t['id']); ?>', '<?php echo e($t['display_name']); ?>')">RESET PW</button>
                            <?php if ($t['role'] !== 'superadmin'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="tenant_id" value="<?php echo e($t['id']); ?>">
                                <input type="hidden" name="new_state" value="<?php echo $t['is_active'] ? '0' : '1'; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $t['is_active'] ? 'btn-danger' : ''; ?>" onclick="return confirm('<?php echo $t['is_active'] ? 'Deactivate' : 'Activate'; ?> <?php echo e($t['display_name']); ?>?')">
                                    <?php echo $t['is_active'] ? 'DEACTIVATE' : 'ACTIVATE'; ?>
                                </button>
                            </form>
                            <a href="tenant-edit.php?id=<?php echo urlencode($t['id']); ?>" class="btn btn-sm">EDIT</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Reset Password Modal -->
    <div id="resetModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:200;align-items:center;justify-content:center;">
        <div style="background:var(--navy);border:1px solid var(--border-light);padding:32px;width:100%;max-width:400px;">
            <h3 style="margin-bottom:16px;">RESET PASSWORD</h3>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">Resetting password for <strong id="resetTenantLabel" style="color:var(--text-bright);"></strong></p>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="tenant_id" id="resetTenantId">
                <div class="form-group">
                    <label class="form-label">NEW PASSWORD</label>
                    <input type="text" name="new_password" class="form-input" placeholder="Min 8 characters" required minlength="8">
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary">RESET</button>
                    <button type="button" class="btn" onclick="document.getElementById('resetModal').style.display='none'">CANCEL</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Tenant Modal -->
    <div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:200;align-items:center;justify-content:center;">
        <div style="background:var(--navy);border:1px solid var(--border-light);padding:32px;width:100%;max-width:480px;">
            <h3 style="margin-bottom:16px;">CREATE TENANT</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_tenant">
                <div class="form-group">
                    <label class="form-label">TENANT ID</label>
                    <input type="text" name="new_id" class="form-input" placeholder="hw_community_name" required pattern="[a-z0-9_]+">
                    <div class="form-hint">Lowercase, no spaces. Used in embed code: data-robchat-id="hw_xxx"</div>
                </div>
                <div class="form-group">
                    <label class="form-label">DISPLAY NAME</label>
                    <input type="text" name="new_display_name" class="form-input" placeholder="Community Name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">COMMUNITY NAME</label>
                    <input type="text" name="new_community_name" class="form-input" placeholder="Harvest, Treeline, etc.">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">EMAIL</label>
                        <input type="email" name="new_email" class="form-input" placeholder="community@hillwoodcommunities.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">PASSWORD</label>
                        <input type="text" name="new_password" class="form-input" placeholder="Min 8 characters" required minlength="8">
                    </div>
                </div>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <button type="submit" class="btn btn-primary">CREATE</button>
                    <button type="button" class="btn" onclick="document.getElementById('createModal').style.display='none'">CANCEL</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showResetModal(id, name) {
        document.getElementById('resetTenantId').value = id;
        document.getElementById('resetTenantLabel').textContent = name;
        document.getElementById('resetModal').style.display = 'flex';
    }
    </script>
<?php renderFooter(); ?>
