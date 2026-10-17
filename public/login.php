<?php
/**
 * =====================================================================
 * User Login Page
 * =====================================================================
 * Authenticates users using password_verify() and session_regenerate_id().
 * Strictly blocks banned users from accessing the system.
 * Includes demo account helper for college viva examination.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/header.php';

// Redirect already logged-in users
if (is_logged_in()) {
    redirect('public/index.php');
}

$errors = [];
$email = '';
$redirectTo = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
// Note: is_safe_redirect() is now provided as a shared helper in includes/functions.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF token
    validate_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = 'Please enter both your email address and password.';
    } else {
        try {
            $pdo = get_db_connection();

            // --- Login Rate Limiting (5 attempts per email+IP per 10 minutes) ---
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            // Clean up expired attempts (older than 10 minutes)
            $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 10 MINUTE)")
                ->execute();

            // Count recent failed attempts for this email+IP combination
            $rateLimitStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM login_attempts WHERE email = :email AND ip_address = :ip AND attempted_at >= (NOW() - INTERVAL 10 MINUTE)"
            );
            $rateLimitStmt->execute([':email' => $email, ':ip' => $clientIp]);
            $recentAttempts = (int) $rateLimitStmt->fetchColumn();

            if ($recentAttempts >= 5) {
                $errors[] = 'Too many failed login attempts. Please wait 10 minutes before trying again.';
            } else {
                $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, is_banned FROM users WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                // 2. Validate password hash
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $errors[] = 'Invalid email address or password.';

                    // Record the failed attempt for rate limiting
                    $pdo->prepare("INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (:email, :ip, NOW())")
                        ->execute([':email' => $email, ':ip' => $clientIp]);
                } elseif ((int) $user['is_banned'] === 1) {
                    // 3. Strict ban enforcement
                    $errors[] = 'Your account has been suspended by an administrator.';
                } else {
                    // 4. Successful login — clear any failed attempts for this email+IP
                    $pdo->prepare("DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip")
                        ->execute([':email' => $email, ':ip' => $clientIp]);

                    login_user($user);
                    set_flash('success', "Welcome back, {$user['name']}!");

                    // Handle redirect back to previously visited page if safe
                    if (is_safe_redirect($redirectTo)) {
                        header("Location: " . $redirectTo);
                        exit;
                    }

                    redirect('public/index.php');
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database connection error. Please ensure MySQL or TiDB is reachable.';
        }
    }
}
?>

<div class="card form-card">
    <div style="text-align: center; margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.5rem; margin-bottom: 0.35rem;">Sign In to ThredIt</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Enter your credentials to participate in discussions</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin-left: 1.25rem;">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?= url('public/login.php') ?>" method="POST">
        <?= csrf_field() ?>
        <?php if (!empty($redirectTo)): ?>
            <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="dark@example.com" value="<?= e($email) ?>" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem; padding: 0.75rem;">
            Sign In
        </button>
    </form>

    <div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: var(--text-muted);">
        Don't have an account yet? <a href="<?= url('public/register.php') ?>" style="color: var(--primary); font-weight: 600;">Create one here</a>
    </div>

    <!-- Demo Credentials Helper Box -->
    <div class="demo-creds-container" style="margin-top: 2rem; padding: 1.1rem; background-color: var(--bg-card-hover, var(--bg-surface)); border-radius: var(--radius-md); border: 1px dashed var(--border-color); font-size: 0.85rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
            <div style="font-weight: 700; color: var(--text-primary); font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
                <span>🔑</span> Quick Demo Accounts:
            </div>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Click to auto-fill</span>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem;">
            <div class="demo-cred-card" onclick="fillCreds('dark@example.com', 'Admin@123')" style="cursor: pointer; padding: 0.6rem 0.75rem; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); transition: all 0.15s ease;">
                <div style="font-weight: 600; color: var(--text-primary); display: flex; align-items: center; justify-content: space-between;">
                    <span>👑 Dark</span>
                    <span class="badge badge-role-admin" style="font-size: 10px; padding: 1px 6px;">Admin</span>
                </div>
                <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 2px;">dark@example.com</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Pass: Admin@123</div>
            </div>

            <div class="demo-cred-card" onclick="fillCreds('deepak@example.com', 'Mod@123')" style="cursor: pointer; padding: 0.6rem 0.75rem; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); transition: all 0.15s ease;">
                <div style="font-weight: 600; color: var(--text-primary); display: flex; align-items: center; justify-content: space-between;">
                    <span>🛡️ Deepak</span>
                    <span class="badge badge-role-mod" style="font-size: 10px; padding: 1px 6px;">Mod</span>
                </div>
                <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 2px;">deepak@example.com</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Pass: Mod@123</div>
            </div>

            <div class="demo-cred-card" onclick="fillCreds('ananya@example.com', 'User@123')" style="cursor: pointer; padding: 0.6rem 0.75rem; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); transition: all 0.15s ease;">
                <div style="font-weight: 600; color: var(--text-primary);">Ananya Sen</div>
                <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 2px;">ananya@example.com</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Pass: User@123</div>
            </div>

            <div class="demo-cred-card" onclick="fillCreds('bhavin@example.com', 'User@123')" style="cursor: pointer; padding: 0.6rem 0.75rem; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); transition: all 0.15s ease;">
                <div style="font-weight: 600; color: var(--text-primary);">Bhavin Patel</div>
                <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 2px;">bhavin@example.com</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Pass: User@123</div>
            </div>
        </div>
    </div>

    <script>
    function fillCreds(email, password) {
        var emailInput = document.getElementById('email');
        var passInput = document.getElementById('password');
        if (emailInput && passInput) {
            emailInput.value = email;
            passInput.value = password;
            emailInput.focus();
        }
    }
    </script>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
