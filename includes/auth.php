<?php
/**
 * =====================================================================
 * Authentication & Role-Based Authorization
 * =====================================================================
 * Manages user session state, role validation (Guest, User, Moderator, Admin),
 * ban enforcement, and session fixation defense.
 *
 * IMPORTANT: require_login() calls sync_user_status() so that banned users
 * and role changes are enforced on EVERY handler (vote.php, reply.php,
 * moderate.php, delete_thread.php, admin pages), not only on pages
 * that include header.php.
 * =====================================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

// Initialize session with secure configuration
if (session_status() === PHP_SESSION_NONE) {
    // Prevent JavaScript from reading the session cookie (protects against XSS session theft)
    ini_set('session.cookie_httponly', '1');
    // Enforce using only cookies for session management (prevents session hijacking via URL parameters)
    ini_set('session.use_only_cookies', '1');
    // Protect against CSRF attacks in modern browsers
    ini_set('session.cookie_samesite', 'Lax');

    // Add Secure flag automatically when running over HTTPS
    // This ensures the session cookie is only sent over encrypted connections
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}

if (!function_exists('is_logged_in')) {
    /**
     * Check if a user is currently logged in.
     *
     * @return bool
     */
    function is_logged_in(): bool
    {
        return !empty($_SESSION['user']['id']);
    }
}

if (!function_exists('current_user')) {
    /**
     * Retrieve the currently authenticated user's session data.
     *
     * @return array|null
     */
    function current_user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('current_user_id')) {
    /**
     * Retrieve the ID of the currently logged-in user.
     *
     * @return int|null
     */
    function current_user_id(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
    }
}

if (!function_exists('is_admin')) {
    /**
     * Check if the authenticated user is an administrator.
     * Uses the session role which is kept in sync by sync_user_status().
     *
     * @return bool
     */
    function is_admin(): bool
    {
        return is_logged_in() && (($_SESSION['user']['role'] ?? '') === 'admin');
    }
}

if (!function_exists('is_moderator')) {
    /**
     * Check if the user is a moderator or an administrator (moderator permissions).
     * Uses the session role which is kept in sync by sync_user_status().
     *
     * @return bool
     */
    function is_moderator(): bool
    {
        if (!is_logged_in()) {
            return false;
        }
        $role = $_SESSION['user']['role'] ?? '';
        return in_array($role, ['moderator', 'admin'], true);
    }
}

if (!function_exists('login_user')) {
    /**
     * Authenticate and set up the session for a user.
     * Regenerates the session ID to defend against session fixation attacks.
     *
     * @param array $user Database user row
     * @return void
     */
    function login_user(array $user): void
    {
        // Regenerate session ID upon privilege change to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role']
        ];
    }
}

if (!function_exists('logout_user')) {
    /**
     * Terminate the user session securely and remove the cookie.
     *
     * @return void
     */
    function logout_user(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}

if (!function_exists('sync_user_status')) {
    /**
     * Verifies that the current user has not been banned or had their role changed.
     * Call this on protected pages to immediately invalidate banned sessions.
     *
     * Called automatically by require_login(), so banned users and role changes
     * are enforced on EVERY handler (not just pages that render header.php).
     *
     * @param PDO|null $pdo Optional PDO connection; fetched automatically if null
     * @return void
     */
    function sync_user_status(?PDO $pdo = null): void
    {
        if (!is_logged_in()) {
            return;
        }

        // If no PDO was passed, get one (this allows header.php to pass its own $pdo)
        if ($pdo === null) {
            $pdo = get_db_connection();
        }

        $userId = current_user_id();
        $stmt = $pdo->prepare("SELECT role, is_banned FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        // If user no longer exists or has been banned by an admin
        if (!$user || (int) $user['is_banned'] === 1) {
            logout_user();
            set_flash('danger', 'Your account has been suspended or no longer exists. Please contact support.');
            redirect('public/login.php');
        }

        // Keep session role synchronized with the database
        // This ensures require_admin() and require_moderator() use the fresh DB role
        $_SESSION['user']['role'] = $user['role'];
    }
}

if (!function_exists('require_login')) {
    /**
     * Guard: Requires the user to be logged in.
     * If not logged in, stores the target URL and redirects to the login page.
     *
     * ALSO calls sync_user_status() to enforce bans and role changes on every
     * handler (vote.php, reply.php, moderate.php, delete_thread.php, admin pages).
     *
     * @return void
     */
    function require_login(): void
    {
        if (!is_logged_in()) {
            set_flash('warning', 'Please sign in to access this feature.');
            $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
            redirect('public/login.php?redirect=' . urlencode($currentUrl));
        }

        // Sync role + ban status from the database on every authenticated request
        // This ensures that if an admin bans or demotes a user while they are active,
        // the change takes effect immediately on the NEXT request
        sync_user_status();
    }
}

if (!function_exists('require_admin')) {
    /**
     * Guard: Requires the user to have administrator privileges.
     * Returns 403 Forbidden otherwise.
     *
     * Uses the session role which has already been refreshed from the DB
     * by require_login() -> sync_user_status().
     *
     * @return void
     */
    function require_admin(): void
    {
        require_login();
        // At this point sync_user_status() has already updated $_SESSION['user']['role']
        // from the database, so is_admin() checks the FRESH role, not a stale one
        if (!is_admin()) {
            http_response_code(403);
            die("<h3>403 Forbidden</h3><p>Access denied. Administrator privileges are required to view this page.</p>");
        }
    }
}

if (!function_exists('require_moderator')) {
    /**
     * Guard: Requires moderator or admin privileges.
     * Returns 403 Forbidden otherwise.
     *
     * Uses the session role which has already been refreshed from the DB
     * by require_login() -> sync_user_status().
     *
     * @return void
     */
    function require_moderator(): void
    {
        require_login();
        // At this point sync_user_status() has already updated $_SESSION['user']['role']
        // from the database, so is_moderator() checks the FRESH role, not a stale one
        if (!is_moderator()) {
            http_response_code(403);
            die("<h3>403 Forbidden</h3><p>Access denied. Moderator or Administrator privileges are required.</p>");
        }
    }
}
