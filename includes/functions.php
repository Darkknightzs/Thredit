<?php
/**
 * =====================================================================
 * Core Helper Functions
 * =====================================================================
 * Contains common utility functions used across the application:
 * - URL building & base path calculation
 * - Safe HTML output escaping (XSS prevention)
 * - Flash message system
 * - Relative time calculation
 * - Code formatting for technical discussions
 * =====================================================================
 */

// Enforce application timezone default (defaults to Asia/Kolkata / IST)
if (!date_default_timezone_get() || date_default_timezone_get() === 'UTC') {
    $tz = function_exists('env') ? (string) env('APP_TIMEZONE', 'Asia/Kolkata') : 'Asia/Kolkata';
    date_default_timezone_set(!empty($tz) ? $tz : 'Asia/Kolkata');
}

if (!function_exists('get_base_url')) {
    /**
     * Dynamically detects the application's base URL.
     * Works seamlessly in XAMPP root, subfolders (/forum), or php -S server.
     *
     * @return string
     */
    function get_base_url(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // SCRIPT_NAME represents the URI path of the executing script (e.g. /forum/public/index.php)
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = dirname($scriptPath);

        // Strip subdirectories like /public, /admin, /includes to find the root folder
        $baseFolder = preg_replace('#/(public|admin|includes|api)$#i', '', $scriptDir);

        if ($baseFolder === '/' || $baseFolder === '\\' || $baseFolder === '.') {
            $baseFolder = '';
        }

        return rtrim($protocol . $host . $baseFolder, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generates a fully qualified URL for an internal path.
     * Example: url('public/login.php') -> http://localhost/forum/public/login.php
     *
     * @param string $path
     * @return string
     */
    function url(string $path = ''): string
    {
        $base = get_base_url();
        $trimmedPath = ltrim($path, '/');
        return $trimmedPath === '' ? $base : $base . '/' . $trimmedPath;
    }
}

if (!function_exists('asset_url')) {
    /**
     * Generates a URL for static assets (CSS, JS, images) with cache-busting version query.
     *
     * @param string $path
     * @return string
     */
    function asset_url(string $path): string
    {
        $cleanPath = ltrim($path, '/');
        $fullPath = __DIR__ . '/../assets/' . $cleanPath;
        $v = file_exists($fullPath) ? filemtime($fullPath) : time();
        return url('assets/' . $cleanPath) . '?v=' . $v;
    }
}

if (!function_exists('e')) {
    /**
     * Escapes data for safe HTML rendering, strictly preventing XSS (Cross-Site Scripting).
     *
     * @param mixed $string
     * @return string
     */
    function e(mixed $string): string
    {
        return htmlspecialchars((string) ($string ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirects to a specified URL and exits.
     *
     * @param string $path URL or relative path
     * @return void
     */
    function redirect(string $path): void
    {
        // If not an absolute URL, prepend application base url
        if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
            $path = url($path);
        }

        header("Location: $path");
        exit;
    }
}

if (!function_exists('is_safe_redirect')) {
    /**
     * Validates that a redirect target is a safe relative path.
     * Only allows values starting with a single "/" (no "//", no backslashes,
     * no scheme such as javascript:, http:, https:, data:, etc.).
     * Also strictly rejects any ASCII control characters (\x00-\x1F, \x7F, including \t, \r, \n)
     * to prevent Open Redirect (CWE-601) and HTTP Response Splitting / Header Injection (CWE-113).
     *
     * @param string $url The redirect URL to validate
     * @return bool True if the URL is a safe relative path
     */
    function is_safe_redirect(string $url): bool
    {
        $url = trim($url);

        // Must not be empty
        if ($url === '') {
            return false;
        }

        // Reject ASCII control characters (0x00-0x1F and 0x7F), including tab, CR, LF
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        // Must start with exactly one "/" (reject "//" which is a protocol-relative URL)
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return false;
        }

        // Reject backslashes (browsers may interpret "\/\" as "//")
        if (str_contains($url, '\\')) {
            return false;
        }

        // Reject any URL containing a colon before the first "/" after position 0
        // This blocks schemes like javascript:, data:, vbscript:, etc.
        $pathPart = substr($url, 1); // Remove leading "/"
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:/', $pathPart)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('set_flash')) {
    /**
     * Sets a session flash message (persists for one page redirect).
     *
     * @param string $type success|danger|warning|info
     * @param string $message
     * @return void
     */
    function set_flash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_message'] = [
            'type'    => $type,
            'message' => $message,
        ];
    }
}

if (!function_exists('get_flash')) {
    /**
     * Retrieves and clears the flash message.
     *
     * @return array|null ['type' => ..., 'message' => ...]
     */
    function get_flash(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $flash;
        }
        return null;
    }
}

if (!function_exists('time_ago')) {
    /**
     * Converts a datetime string to human-readable relative time (e.g., "5 mins ago").
     *
     * @param string $datetime
     * @return string
     */
    function time_ago(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return $datetime;
        }

        $difference = time() - $timestamp;

        if ($difference < 60) {
            return 'just now';
        }
        if ($difference < 3600) {
            $mins = floor($difference / 60);
            return $mins . 'm ago';
        }
        if ($difference < 86400) {
            $hours = floor($difference / 3600);
            return $hours . 'h ago';
        }
        if ($difference < 604800) {
            $days = floor($difference / 86400);
            return $days . 'd ago';
        }

        return date('M j, Y', $timestamp);
    }
}

if (!function_exists('truncate')) {
    /**
     * Truncates text to a specified maximum length with ellipsis.
     *
     * @param string $text
     * @param int $length
     * @return string
     */
    function truncate(string $text, int $length = 150): string
    {
        $text = strip_tags($text);
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length, 'UTF-8') . '...';
    }
}

if (!function_exists('format_post_body')) {
    /**
     * Formats technical discussion body content:
     * - Protects against XSS by escaping HTML
     * - Parses markdown-style triple backtick code blocks (```code```)
     * - Converts line breaks to <br> for regular text
     *
     * @param string $body
     * @return string Safe HTML
     */
    function format_post_body(string $body): string
    {
        // 1. Extract triple-backtick code blocks to protect them
        $codeBlocks = [];
        $pattern = '/```([a-zA-Z0-9_-]*)\n?(.*?)```/s';

        $bodyWithPlaceholders = preg_replace_callback($pattern, function ($matches) use (&$codeBlocks) {
            $placeholder = '___CODE_BLOCK_' . count($codeBlocks) . '___';
            $lang = trim($matches[1]);
            $code = $matches[2];
            $codeBlocks[$placeholder] = [
                'lang' => $lang,
                'code' => $code
            ];
            return $placeholder;
        }, $body);

        // 2. Escape all standard text to prevent XSS
        $escaped = e($bodyWithPlaceholders);

        // 3. Parse markdown headings (###, ##, #) at line starts
        $escaped = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $escaped);
        $escaped = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $escaped);
        $escaped = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $escaped);

        // Parse inline code: `code`
        $escaped = preg_replace('/`([^`\r\n]+)`/', '<code>$1</code>', $escaped);

        // 4. Convert standard text newlines to <br>
        $formatted = nl2br($escaped);

        // Remove superfluous <br> tags directly following heading closing tags
        $formatted = preg_replace('/(<\/(?:h1|h2|h3)>)\s*<br\s*\/?>/i', '$1', $formatted);

        // 5. Restore and highlight code blocks with safe pre/code styling
        foreach ($codeBlocks as $placeholder => $block) {
            $safeCode = e(rtrim($block['code']));
            $langBadge = !empty($block['lang']) ? '<span class="code-lang-tag">' . e($block['lang']) . '</span>' : '';
            $html = '<div class="code-block-wrapper">' . $langBadge . '<pre><code class="language-' . e($block['lang']) . '">' . $safeCode . '</code></pre></div>';
            $formatted = str_replace($placeholder, $html, $formatted);
        }

        return $formatted;
    }
}

if (!function_exists('get_target_vote_score')) {
    /**
     * Calculates the net vote score (sum of +1 and -1 votes) for a thread or reply.
     *
     * @param PDO $pdo
     * @param string $targetType 'thread'|'reply'
     * @param int $targetId
     * @return int
     */
    function get_target_vote_score(PDO $pdo, string $targetType, int $targetId): int
    {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(value), 0) AS score FROM votes WHERE target_type = :target_type AND target_id = :target_id");
        $stmt->execute([
            ':target_type' => $targetType,
            ':target_id'   => $targetId
        ]);
        $row = $stmt->fetch();
        return (int) ($row['score'] ?? 0);
    }
}

if (!function_exists('get_user_vote_value')) {
    /**
     * Checks if a user has upvoted (+1), downvoted (-1), or not voted (0) on a target.
     *
     * @param PDO $pdo
     * @param int|null $userId
     * @param string $targetType 'thread'|'reply'
     * @param int $targetId
     * @return int 1, -1, or 0
     */
    function get_user_vote_value(PDO $pdo, ?int $userId, string $targetType, int $targetId): int
    {
        if (empty($userId)) {
            return 0;
        }

        $stmt = $pdo->prepare("SELECT value FROM votes WHERE user_id = :user_id AND target_type = :target_type AND target_id = :target_id LIMIT 1");
        $stmt->execute([
            ':user_id'     => $userId,
            ':target_type' => $targetType,
            ':target_id'   => $targetId
        ]);
        $row = $stmt->fetch();
        return (int) ($row['value'] ?? 0);
    }
}

if (!function_exists('get_all_categories')) {
    /**
     * Retrieves all categories along with their thread counts.
     *
     * @param PDO $pdo
     * @return array
     */
    function get_all_categories(PDO $pdo): array
    {
        $sql = "SELECT c.*, COUNT(t.id) AS thread_count
                FROM categories c
                LEFT JOIN threads t ON c.id = t.category_id
                GROUP BY c.id, c.name, c.description
                ORDER BY c.name ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    }
}

if (!function_exists('get_community_stats')) {
    /**
     * Retrieves high-level community statistics for the Reddit-style sidebar.
     *
     * @param PDO $pdo
     * @return array
     */
    function get_community_stats(PDO $pdo): array
    {
        try {
            $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $threadCount = (int) $pdo->query("SELECT COUNT(*) FROM threads")->fetchColumn();
            $replyCount = (int) $pdo->query("SELECT COUNT(*) FROM replies")->fetchColumn();
            return [
                'users'    => $userCount,
                'threads'  => $threadCount,
                'replies'  => $replyCount
            ];
        } catch (Throwable $e) {
            return ['users' => 0, 'threads' => 0, 'replies' => 0];
        }
    }
}

if (!function_exists('render_default_avatar_svg')) {
    /**
     * Renders the authentic silhouette user avatar SVG matching user request.
     *
     * @param int $size
     * @return string
     */
    function render_default_avatar_svg(int $size = 20): string
    {
        return '<svg viewBox="0 0 32 32" width="' . $size . '" height="' . $size . '" fill="currentColor" class="default-avatar-svg" aria-hidden="true">'
            . '<circle cx="16" cy="9.5" r="5.8" />'
            . '<path d="M5 26 C 5 19.5, 9.9 15, 16 15 C 22.1 15, 27 19.5, 27 26 C 27 27.5, 25.5 28, 24 28 L 8 28 C 6.5 28, 5 27.5, 5 26 Z" />'
            . '</svg>';
    }
}

