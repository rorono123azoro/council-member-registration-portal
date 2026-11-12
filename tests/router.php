<?php

declare(strict_types=1);

/**
 * Development router for PHP built-in server:
 *   php -S 127.0.0.1:8000 -t public tests/router.php
 * Mirrors Apache .htaccess protections for directory listing and sensitive paths.
 */

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$publicDir = dirname(__DIR__) . '/public';
$fullPath = realpath($publicDir . $uriPath);

// Block sensitive top-level paths or dotfiles
if (
    preg_match('#^/(app|storage|database|docs|tests|vendor)(/|$)#i', $uriPath)
    || preg_match('#/\.#', $uriPath)
    || preg_match('#\.(sql|log|md|env|json|lock|sh)$#i', $uriPath)
) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Prevent directory listing on directories without index.php
if ($uriPath !== '/' && $fullPath !== false && is_dir($fullPath)) {
    if (!file_exists($fullPath . '/index.php')) {
        http_response_code(403);
        echo 'Directory listing denied';
        return true;
    }
    return false;
}

// Serve existing static or PHP files inside public/
if ($fullPath !== false && str_starts_with($fullPath, realpath($publicDir)) && is_file($fullPath)) {
    return false;
}

// Root defaults to index.php
if ($uriPath === '/') {
    require $publicDir . '/index.php';
    return true;
}

http_response_code(404);
echo 'Not Found';
return true;

