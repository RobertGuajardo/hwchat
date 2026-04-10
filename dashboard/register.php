<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';

// Already logged in
if (isAuthenticated()) { header('Location: index.php'); exit; }

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');

    // Validate
    if (empty($email) || empty($password) || empty($displayName)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = Database::db();

        // Check if email already exists
        $stmt = $db->prepare('SELECT id FROM tenants WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            // Generate tenant ID from display name
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $displayName));
            $slug = trim($slug, '_');
            $tenantId = substr($slug, 0, 30) . '_' . substr(bin2hex(random_bytes(4)), 0, 8);

            try {
                $stmt = $db->prepare('
                    INSERT INTO tenants (id, email, password_hash, display_name)
                    VALUES (:id, :email, :password_hash, :display_name)
                ');
                $stmt->execute([
                    'id'            => $tenantId,
                    'email'         => $email,
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'display_name'  => $displayName,
                ]);

                // Auto-login
                $_SESSION['tenant_id']    = $tenantId;
                $_SESSION['tenant_email'] = $email;
                $_SESSION['tenant_name']  = $displayName;

                header('Location: settings.php');
                exit;
            } catch (Exception $e) {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

renderHead('Create Account');
?>
    <div class="login-container">
        <div class="login-box">
            <div class="login-stamp">HWCHAT</div>
            <h1>CREATE ACCOUNT</h1>
            <p class="login-sub">Set up your AI chatbot in minutes</p>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="display_name" class="form-input" placeholder="Assistant name (e.g. Acme AI)" value="<?php echo e($_POST['display_name'] ?? ''); ?>" required>
                <input type="email" name="email" class="form-input" placeholder="Email" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                <input type="password" name="password" class="form-input" placeholder="Password (8+ characters)" required>
                <input type="password" name="confirm" class="form-input" placeholder="Confirm password" required>
                <button type="submit" class="btn btn-primary" style="width:100%;padding:12px;margin-top:4px;">CREATE ACCOUNT</button>
            </form>
            <div class="login-links">
                Already have an account? <a href="index.php">Sign in</a>
            </div>
        </div>
    </div>
<?php renderFooter(); ?>
