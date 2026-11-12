<?php

declare(strict_types=1);

/**
 * Bootstrap — loaded by every entry point.
 * Sets up autoloading, environment, extension checks, error handling,
 * security headers, session hardening, and global helpers.
 */

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
define('LOG_PATH', STORAGE_PATH . '/logs');

if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0750, true);
}
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0750, true);
}

// Check required PHP extensions (ENV-06)
$requiredExtensions = ['pdo_mysql', 'fileinfo', 'mbstring', 'openssl'];
$missingExtensions = array_filter(
    $requiredExtensions,
    static fn(string $ext): bool => !extension_loaded($ext)
);
if ($missingExtensions !== []) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Server configuration error: missing required PHP extensions (' . implode(', ', $missingExtensions) . ').';
    exit(1);
}

$autoloadPath = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Application dependencies are not installed. Run composer install.';
    exit(1);
}
require_once $autoloadPath;

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

// Error handling (rules.md §2.8, DEP-02, SEC-09)
$appEnv = (string) ($_ENV['APP_ENV'] ?? 'production');
$debug = ($appEnv !== 'production')
    && filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php_errors.log');

// Timezone (DEP-06)
date_default_timezone_set((string) ($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata'));

/**
 * Determine whether current request targets an API endpoint.
 */
function isApiRequest(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, '/api/');
}

/**
 * Write a structured message to the application log without sensitive data.
 */
function appLog(string $level, string $message): void
{
    $line = sprintf(
        "[%s] [%s] %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message
    );
    error_log($line, 3, LOG_PATH . '/app.log');
}

// Convert warnings/notices to ErrorException in all modes so nothing slips silently
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (\Throwable $e) use ($debug): void {
    appLog('ERROR', get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (isApiRequest()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        $payload = [
            'ok' => false,
            'errors' => ['_general' => $debug ? $e->getMessage() : 'An unexpected error occurred. Please try again later.'],
        ];
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit(1);
    }

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    $msg = $debug
        ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        : 'We are experiencing a temporary service issue. Please try again shortly.';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Service Unavailable</title>'
        . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>'
        . '<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">'
        . '<div class="card shadow-sm p-4" style="max-width:480px">'
        . '<h1 class="h4 text-danger mb-2">Service Unavailable</h1>'
        . '<p class="mb-0 text-secondary">' . $msg . '</p>'
        . '</div></body></html>';
    exit(1);
});

// Security headers (SEC-05, SEC-06)
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $isInlineFile = str_ends_with($reqPath, '/admin/file.php');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: ' . ($isInlineFile ? 'SAMEORIGIN' : 'DENY'));
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
        . "img-src 'self' data: blob:; "
        . "frame-ancestors " . ($isInlineFile ? "'self'" : "'none'") . "; "
        . "base-uri 'self'; "
        . "form-action 'self'"
    );
}

// Session hardening (rules.md §2.9, AUTH-09, AUTH-11)
if (PHP_SAPI !== 'cli') {
    $sessionLifetime = (int) ($_ENV['SESSION_LIFETIME'] ?? 7200);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    ini_set('session.cookie_lifetime', '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Idle timeout check
    if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $sessionLifetime) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
        session_destroy();
        session_start();
        $_SESSION['session_expired'] = true;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * HTML-escape helper (rules.md §2.2).
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Send a JSON response and terminate cleanly (rules.md §3).
 */
function jsonResponse(bool $ok, array $data = [], array $errors = [], int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
    }

    $payload = ['ok' => $ok];
    if ($data !== []) {
        $payload['data'] = $data;
    }
    if ($errors !== []) {
        $payload['errors'] = $errors;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Resolve client IP safely.
 */
function clientIp(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
}

/**
 * Load app config array.
 */
function appConfig(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require BASE_PATH . '/app/config/app.php';
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}
