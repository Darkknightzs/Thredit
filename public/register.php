<?php
/**
 * =====================================================================
 * User Registration Page
 * =====================================================================
 * Handles new account creation:
 * - CSRF token validation
 * - Input validation (name, email, password match)
 * - Safe password hashing using PHP password_hash(PASSWORD_DEFAULT)
 * - Automatic login upon successful registration
 * =====================================================================
 */

require_once __DIR__ . '/../includes/header.php';

// Redirect authenticated users
if (is_logged_in()) {
    redirect('public/index.php');
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF token
    validate_csrf();

    // 2. Sanitize and retrieve form inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // 3. Validation rules
    if (mb_strlen($name) < 3 || mb_strlen($name) > 60) {
        $errors[] = 'Full name must be between 3 and 60 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Password confirmation does not match.';
    }

    // 4. Check for duplicate email in database
    if (empty($errors)) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);

            if ($stmt->fetch()) {
                $errors[] = 'An account with this email address already exists.';
            } else {
                // 5. Hash password and insert new user record
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, is_banned, created_at)
                                             VALUES (:name, :email, :password_hash, 'user', 0, NOW())");
                $insertStmt->execute([
                    ':name'          => $name,
                    ':email'         => $email,
                    ':password_hash' => $passwordHash,
                ]);

                $newUserId = (int) $pdo->lastInsertId();

                // 6. Log the newly registered user in automatically
                login_user([
                    'id'    => $newUserId,
                    'name'  => $name,
                    'email' => $email,
                    'role'  => 'user'
                ]);

                set_flash('success', "Welcome to ThredIt, {$name}! Your account has been registered successfully.");
                redirect('public/index.php');
            }
        } catch (PDOException $e) {
            $errors[] = 'A database error occurred. Please try again later.';
        }
    }
}
?>

<div class="card form-card">
    <div style="text-align: center; margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.5rem; margin-bottom: 0.35rem;">Create Your Account</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Join the ThredIt developer community</p>
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

    <form action="<?= url('public/register.php') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label" for="name">Full Name</label>
            <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Alice Johnson" value="<?= e($name) ?>" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com" value="<?= e($email) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 8 characters" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="password_confirm">Confirm Password</label>
            <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Repeat your password" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem; padding: 0.75rem;">
            Create Account
        </button>
    </form>

    <div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: var(--text-muted);">
        Already have an account? <a href="<?= url('public/login.php') ?>" style="color: var(--primary); font-weight: 600;">Sign in here</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
