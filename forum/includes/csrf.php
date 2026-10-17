<?php
/**
 * =====================================================================
 * CSRF (Cross-Site Request Forgery) Protection
 * =====================================================================
 * Generates and validates cryptographic tokens for all state-changing
 * POST/PUT/DELETE requests.
 *
 * Essential for College Project security demonstration:
 * - Prevents malicious websites from submitting forms on behalf of an
 *   authenticated student or administrator.
 * =====================================================================
 */

if (!function_exists('generate_csrf_token')) {
    /**
     * Generate or retrieve the current session's CSRF token.
     * Uses cryptographically secure random bytes.
     *
     * @return string 64-character hexadecimal token
     */
    function generate_csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Alias for generate_csrf_token()
     */
    function csrf_token(): string
    {
        return generate_csrf_token();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Outputs an HTML hidden input containing the CSRF token.
     * Usage inside any form: <?= csrf_field() ?>
     *
     * @return string
     */
    function csrf_field(): string
    {
        $token = htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Verifies the submitted token against the session token.
     * Uses hash_equals to protect against timing attacks.
     *
     * @param string|null $token
     * @return bool
     */
    function verify_csrf_token(?string $token = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($token === null) {
            // Check POST parameter or HTTP header (useful for AJAX fetch requests)
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('validate_csrf')) {
    /**
     * Strictly validates the CSRF token or terminates the script with a 403 error.
     */
    function validate_csrf(): void
    {
        if (!verify_csrf_token()) {
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Security Error: Invalid or expired CSRF token.']);
                exit;
            }
            die("<h3>403 Forbidden</h3><p>CSRF token validation failed. Please refresh the page and try again.</p>");
        }
    }
}
