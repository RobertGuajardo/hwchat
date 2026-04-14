<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/layout.php';
requireSuperAdmin();

$db = Database::db();
$success = '';
$error = '';

// Determine view mode
$editId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isNew = ($_GET['action'] ?? '') === 'new';
$isEdit = $editId || $isNew;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $email       = trim($_POST['email'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $password    = $_POST['password'] ?? '';
        $role        = $_POST['role'] ?? 'tenant_admin';
        $tenantIds   = $_POST['tenant_ids'] ?? [];

        if (!$email || !$displayName || !$password) {
            $error = 'Email, display name, and password are required.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!in_array($role, ['superadmin', 'tenant_admin', 'builder'])) {
            $error = 'Invalid role.';
        } else {
            try {
                Database::createUser($email, $password, $displayName, $role, $tenantIds);
                $_SESSION['flash_success'] = "User \"{$displayName}\" created.";
                header('Location: users.php');
                exit;
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'duplicate') ? 'A user with that email already exists.' : 'Database error: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'update' && $editId) {
        $email       = trim($_POST['email'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $password    = $_POST['password'] ?? '';
        $role        = $_POST['role'] ?? 'tenant_admin';
        $isActive    = !empty($_POST['is_active']);
        $tenantIds   = $_POST['tenant_ids'] ?? [];

        if (!$email || !$displayName) {
            $error = 'Email and display name are required.';
        } elseif ($password && strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!in_array($role, ['superadmin', 'tenant_admin', 'builder'])) {
            $error = 'Invalid role.';
        } else {
            try {
                Database::updateUser($editId, [
                    'email'        => $email,
                    'display_name' => $displayName,
                    'password'     => $password,
                    'role'         => $role,
                    'is_active'    => $isActive,
                ]);
                Database::updateUserTenants($editId, $role === 'superadmin' ? [] : $tenantIds);
                $_SESSION['flash_success'] = "User \"{$displayName}\" updated.";
                header('Location: users.php');
                exit;
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'duplicate') ? 'A user with that email already exists.' : 'Save failed: ' . $e->getMessage();
            }
        }
    }
}

// Flash message from redirect
if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Load data for edit view
$editUser = null;
$assignedTenantIds = [];
if ($editId) {
    $editUser = Database::getUserById($editId);
    if (!$editUser) { header('Location: users.php'); exit; }
    // Load assigned tenants
    $stmt = $db->prepare('SELECT tenant_id FROM user_tenants WHERE user_id = :uid');
    $stmt->execute(['uid' => $editId]);
    $assignedTenantIds = array_column($stmt->fetchAll(), 'tenant_id');
}

// Load all active tenants for the assignment checkboxes
$allTenants = Database::getAllTenants();

renderHead($isEdit ? ($isNew ? 'Add User' : 'Edit User') : 'User Management');
renderNav('users');
?>
    <main class="container" style="max-width:900px;">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

    <?php if ($isEdit): ?>
        <!-- ═══ ADD / EDIT USER VIEW ═══ -->
        <div style="margin-bottom:24px;">
            <a href="users.php" class="btn btn-sm btn-ghost">&larr; BACK TO USERS</a>
        </div>

        <div class="action-bar">
            <h2><?php echo $isNew ? 'ADD USER' : 'EDIT USER'; ?></h2>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="<?php echo $isNew ? 'create' : 'update'; ?>">

            <div class="form-section">
                <h3>ACCOUNT</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">DISPLAY NAME</label>
                        <input type="text" name="display_name" class="form-input" value="<?php echo e($editUser['display_name'] ?? ($_POST['display_name'] ?? '')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">EMAIL</label>
                        <input type="email" name="email" class="form-input" value="<?php echo e($editUser['email'] ?? ($_POST['email'] ?? '')); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">PASSWORD<?php echo $isNew ? '' : ' (leave blank to keep current)'; ?></label>
                        <input type="password" name="password" class="form-input" placeholder="Min 8 characters" <?php echo $isNew ? 'required' : ''; ?> minlength="8">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ROLE</label>
                        <select name="role" id="role-select" class="form-select">
                            <?php
                            $currentRole = $editUser['role'] ?? ($_POST['role'] ?? 'tenant_admin');
                            foreach (['superadmin', 'tenant_admin', 'builder'] as $r): ?>
                                <option value="<?php echo $r; ?>" <?php echo $currentRole === $r ? 'selected' : ''; ?>><?php echo strtoupper(str_replace('_', ' ', $r)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if (!$isNew): ?>
                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="is_active" value="1" <?php echo ($editUser['is_active'] ?? true) ? 'checked' : ''; ?>>
                        Account Active
                    </label>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-section" id="tenant-assignment">
                <h3>TENANT ASSIGNMENT</h3>
                <p class="form-hint" style="margin-bottom:16px;" id="tenant-hint">Select which communities this user can access.</p>
                <p class="form-hint" style="margin-bottom:16px;display:none;" id="super-hint">Superadmin users automatically have access to all communities.</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;" id="tenant-checkboxes">
                    <?php foreach ($allTenants as $t):
                        if ($t['role'] === 'superadmin') continue;
                        $checked = in_array($t['id'], $assignedTenantIds) ? 'checked' : '';
                    ?>
                    <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);cursor:pointer;font-size:13px;color:var(--text);">
                        <input type="checkbox" name="tenant_ids[]" value="<?php echo e($t['id']); ?>" <?php echo $checked; ?>>
                        <?php echo e($t['community_name'] ?: $t['display_name']); ?>
                        <span style="font-size:11px;color:var(--text-muted);margin-left:auto;"><?php echo e($t['id']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:flex;gap:8px;padding-top:16px;">
                <button type="submit" class="btn btn-primary" style="padding:10px 28px;"><?php echo $isNew ? 'CREATE USER' : 'SAVE CHANGES'; ?></button>
                <a href="users.php" class="btn">CANCEL</a>
            </div>
        </form>

        <script>
        (function() {
            var roleSelect = document.getElementById('role-select');
            var tenantSection = document.getElementById('tenant-assignment');
            var tenantHint = document.getElementById('tenant-hint');
            var superHint = document.getElementById('super-hint');
            var checkboxes = document.getElementById('tenant-checkboxes');

            function toggleTenants() {
                var isSuperadmin = roleSelect.value === 'superadmin';
                checkboxes.style.display = isSuperadmin ? 'none' : '';
                tenantHint.style.display = isSuperadmin ? 'none' : '';
                superHint.style.display = isSuperadmin ? '' : 'none';
            }

            roleSelect.addEventListener('change', toggleTenants);
            toggleTenants();
        })();
        </script>

    <?php else: ?>
        <!-- ═══ USER LIST VIEW ═══ -->
        <?php $users = Database::getAllUsers(); ?>

        <div class="action-bar">
            <h2>USERS <span style="color:var(--text-muted);font-size:14px;">(<?php echo count($users); ?>)</span></h2>
            <a href="?action=new" class="btn btn-primary">+ ADD USER</a>
        </div>

        <?php if (empty($users)): ?>
            <div class="empty-state">No users yet. Run migration 019 to migrate existing tenant logins.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>NAME</th>
                        <th>EMAIL</th>
                        <th>ROLE</th>
                        <th>STATUS</th>
                        <th class="center">TENANTS</th>
                        <th>LAST LOGIN</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td style="font-weight:600;color:var(--text-bright);"><?php echo e($u['display_name']); ?></td>
                        <td><?php echo e($u['email']); ?></td>
                        <td>
                            <?php if ($u['role'] === 'superadmin'): ?>
                                <span class="badge badge-super">SUPERADMIN</span>
                            <?php elseif ($u['role'] === 'builder'): ?>
                                <span class="badge badge-active">BUILDER</span>
                            <?php else: ?>
                                <span class="badge badge-type">TENANT ADMIN</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="badge badge-active">ACTIVE</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">OFF</span>
                            <?php endif; ?>
                        </td>
                        <td class="center">
                            <?php echo $u['role'] === 'superadmin' ? 'ALL' : (int)$u['tenant_count']; ?>
                        </td>
                        <td style="color:var(--text-muted);font-size:12px;">
                            <?php echo $u['last_login_at'] ? e(date('M j, g:ia', strtotime($u['last_login_at']))) : 'Never'; ?>
                        </td>
                        <td>
                            <a href="?id=<?php echo (int)$u['id']; ?>" class="btn btn-sm">EDIT</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    <?php endif; ?>
    </main>
<?php renderFooter(); ?>
