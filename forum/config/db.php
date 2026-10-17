<?php
/**
 * =====================================================================
 * Database Connection Manager (PDO)
 * =====================================================================
 * This file provides a centralized database connection using PHP Data
 * Objects (PDO). It automatically detects the active environment from
 * config/.env:
 *   - APP_ENV=local : Local MySQL / MariaDB (XAMPP, default port 3306)
 *   - APP_ENV=tidb  : Cloud TiDB Distributed SQL (Port 4000 with TLS)
 *
 * Designed for BCA College Project viva explanation:
 * - Uses Prepared Statements to completely prevent SQL Injection
 * - Singleton pattern ensures only ONE database connection is opened per request
 *
 * Security:
 * - TiDB: never silently disables certificate verification; if the CA
 *   file is missing, a clear error is shown instead.
 * - The raw PDO exception message is shown ONLY when APP_DEBUG=true;
 *   otherwise a generic message is displayed and details are logged.
 * =====================================================================
 */

// Prevent multiple definitions of helper functions if included repeatedly
if (!function_exists('load_env_file')) {

    /**
     * Minimalist .env file parser.
     * Reads key=value pairs into PHP's $_ENV and getenv().
     *
     * @param string $filePath Path to the .env file
     * @return array Loaded environment variables
     */
    function load_env_file(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $variables = [];

        foreach ($lines as $line) {
            // Trim whitespace
            $line = trim($line);

            // Skip comment lines starting with '#'
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Split line by the first '=' character
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);

                // Remove surrounding quotes if present (e.g., "value" or 'value')
                if (
                    (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))
                ) {
                    $val = substr($val, 1, -1);
                }

                $_ENV[$key] = $val;
                putenv("$key=$val");
                $variables[$key] = $val;
            }
        }

        return $variables;
    }
}

// Load environment file from the current directory
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    // Fallback to .env.example if .env has not been created yet
    $envPath = __DIR__ . '/.env.example';
}
load_env_file($envPath);

if (!function_exists('env')) {
    /**
     * Get an environment variable value with an optional fallback default.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

if (!function_exists('get_db_connection')) {
    /**
     * Returns a singleton PDO database connection instance.
     * Switchable between local MySQL and TiDB Cloud via config/.env.
     *
     * @return PDO
     * @throws RuntimeException If connection cannot be established
     */
    function get_db_connection(): PDO
    {
        static $pdo = null;

        // Reuse existing connection if already established
        if ($pdo !== null) {
            return $pdo;
        }

        $appEnv = strtolower(trim((string) env('APP_ENV', 'local')));
        $appDebug = strtolower(trim((string) env('APP_DEBUG', 'false')));
        $isDebug = in_array($appDebug, ['true', '1', 'yes'], true);

        // Standard PDO options for security and reliability
        $options = [
            // Throw PDOExceptions on any SQL error so they can be handled gracefully
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return associative arrays by default (column_name => value)
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Disable native emulation to enforce genuine server-side prepared statements
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            if ($appEnv === 'tidb') {
                // =========================================================
                // TiDB Cloud (MySQL-Compatible Distributed Database)
                // =========================================================
                $host     = (string) env('TIDB_DB_HOST', 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com');
                $port     = (int) env('TIDB_DB_PORT', 4000);
                $database = (string) env('TIDB_DB_DATABASE', 'forum_db');
                $username = (string) env('TIDB_DB_USERNAME', '');
                $password = (string) env('TIDB_DB_PASSWORD', '');
                $sslCa    = (string) env('TIDB_DB_SSL_CA', '');

                // TiDB Cloud requires TLS encryption — never silently disable verification.
                // If the CA file path is set, verify it exists; otherwise show a clear error.
                if (!empty($sslCa)) {
                    // Support relative path resolution (e.g. ca-bundle.crt or relative to config)
                    if (!file_exists($sslCa)) {
                        if (file_exists(__DIR__ . '/' . $sslCa)) {
                            $sslCa = __DIR__ . '/' . $sslCa;
                        } elseif (file_exists(__DIR__ . '/ca-bundle.crt')) {
                            $sslCa = __DIR__ . '/ca-bundle.crt';
                        }
                    }

                    if (!file_exists($sslCa)) {
                        $msg = "TiDB SSL Error: The CA certificate file was not found at: " . htmlspecialchars($sslCa, ENT_QUOTES, 'UTF-8');
                        $msg .= "\n\nPlease verify the TIDB_DB_SSL_CA path in your config/.env file.";
                        error_log("TiDB SSL CA file not found: {$sslCa}");
                        echo "<div style='font-family:sans-serif; max-width:650px; margin:40px auto; padding:24px; border:1px solid #ef4444; border-radius:8px; background:#fef2f2; color:#991b1b;'>"
                           . "<h3>TiDB SSL Configuration Error</h3>"
                           . "<p>" . nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')) . "</p>"
                           . "</div>";
                        exit(1);
                    }
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
                } else {
                    // No CA file path configured at all — this is an error for TiDB Cloud.
                    // Do NOT silently disable SSL_VERIFY_SERVER_CERT.
                    error_log("TiDB SSL CA path (TIDB_DB_SSL_CA) is not configured in config/.env");
                    echo "<div style='font-family:sans-serif; max-width:650px; margin:40px auto; padding:24px; border:1px solid #ef4444; border-radius:8px; background:#fef2f2; color:#991b1b;'>"
                       . "<h3>TiDB SSL Configuration Error</h3>"
                       . "<p>The <code>TIDB_DB_SSL_CA</code> path is not set in <code>config/.env</code>.</p>"
                       . "<p>TiDB Cloud requires a valid CA certificate for TLS connections. Please configure the path to your CA bundle file.</p>"
                       . "</div>";
                    exit(1);
                }

                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password, $options);

            } else {
                // =========================================================
                // Local Environment (XAMPP MySQL / MariaDB)
                // =========================================================
                $host     = (string) env('LOCAL_DB_HOST', '127.0.0.1');
                $port     = (int) env('LOCAL_DB_PORT', 3306);
                $database = (string) env('LOCAL_DB_DATABASE', 'forum_db');
                $username = (string) env('LOCAL_DB_USERNAME', 'root');
                $password = (string) env('LOCAL_DB_PASSWORD', '');

                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password, $options);
            }

            // Ensure ONLY_FULL_GROUP_BY does not break aggregated joins across MySQL 8 and TiDB
            try {
                $pdo->exec("SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''))");
            } catch (Throwable $e) {
                // Silently ignore if database engine does not permit setting session sql_mode
            }

            return $pdo;

        } catch (PDOException $e) {
            // Log the full error details for server-side debugging
            error_log("Database Connection Error ({$appEnv}): " . $e->getMessage());

            $mode = strtoupper($appEnv);

            if ($isDebug) {
                // APP_DEBUG=true: Show the raw PDO exception message for developers
                $msg = "<h3>Database Connection Error ({$mode} Mode)</h3>";
                $msg .= "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";

                if ($appEnv === 'local') {
                    $msg .= "<p><strong>Troubleshooting Tips:</strong></p>";
                    $msg .= "<ul>";
                    $msg .= "<li>Make sure Apache and MySQL are started in the XAMPP Control Panel.</li>";
                    $msg .= "<li>Ensure you imported <code>database.sql</code> into phpMyAdmin or MySQL CLI.</li>";
                    $msg .= "<li>Verify database credentials in <code>config/.env</code>.</li>";
                    $msg .= "</ul>";
                } else {
                    $msg .= "<p><strong>TiDB Cloud Troubleshooting Tips:</strong></p>";
                    $msg .= "<ul>";
                    $msg .= "<li>Verify your cluster host, username (e.g. <code>xxx.root</code>) and password in <code>config/.env</code>.</li>";
                    $msg .= "<li>Ensure port 4000 is open in your local network/firewall.</li>";
                    $msg .= "<li>Check that your IP address is allowed in the TiDB Cloud Traffic Filter / IP Access List.</li>";
                    $msg .= "</ul>";
                }
            } else {
                // APP_DEBUG=false: Show a generic, safe error message to end users
                $msg = "<h3>Database Connection Error</h3>";
                $msg .= "<p>We are unable to connect to the database at this time. Please try again later or contact the administrator.</p>";
                $msg .= "<p><em>Error details have been logged for the system administrator.</em></p>";
            }

            // Die with styled diagnostic error
            echo "<div style='font-family:sans-serif; max-width:650px; margin:40px auto; padding:24px; border:1px solid #ef4444; border-radius:8px; background:#fef2f2; color:#991b1b;'>{$msg}</div>";
            exit(1);
        }
    }
}
