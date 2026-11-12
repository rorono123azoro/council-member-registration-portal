<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;

/**
 * Admin authentication & authorization helper (rules.md §2.9).
 */
final class Auth
{
    private const SESSION_ADMIN_KEY = 'admin_user';

    /**
     * Require an authenticated admin (and optional role).
     * Redirects to login page when logged out; returns 403 on insufficient role.
     *
     * @return array<string, mixed> Current admin session record
     */
    public static function requireAdmin(?string $requiredRole = null, bool $allowPasswordChangePage = false): array
    {
        $sessionAdmin = $_SESSION[self::SESSION_ADMIN_KEY] ?? null;
        if (!is_array($sessionAdmin) || empty($sessionAdmin['id'])) {
            header('Location: /admin/login.php');
            exit;
        }

        // Re-verify active status from DB
        $admin = Admin::findById((int) $sessionAdmin['id']);
        if ($admin === null || (int) $admin['is_active'] !== 1) {
            self::logout();
            header('Location: /admin/login.php');
            exit;
        }

        // Sync role & must_change_password flag
        $_SESSION[self::SESSION_ADMIN_KEY] = [
            'id'                   => (int) $admin['id'],
            'name'                 => $admin['name'],
            'username'             => $admin['username'],
            'email'                => $admin['email'],
            'role'                 => $admin['role'],
            'must_change_password' => (int) $admin['must_change_password'],
        ];

        // Force password change if temporary seed password is still active (AUTH-15)
        if ((int) $admin['must_change_password'] === 1 && !$allowPasswordChangePage) {
            header('Location: /admin/change-password.php');
            exit;
        }

        // Role check (AUTH-16)
        if ($requiredRole !== null && $admin['role'] !== $requiredRole) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
                . '<title>403 Forbidden</title>'
                . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>'
                . '<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">'
                . '<div class="card shadow-sm p-4" style="max-width:460px">'
                . '<h1 class="h4 text-danger mb-2">403 — Access Denied</h1>'
                . '<p class="text-secondary mb-3">You do not have permission to access this section.</p>'
                . '<a href="/admin/dashboard.php" class="btn btn-primary">Return to Dashboard</a>'
                . '</div></body></html>';
            exit;
        }

        return $_SESSION[self::SESSION_ADMIN_KEY];
    }

    /**
     * Establish authenticated admin session with session fixation protection (AUTH-08).
     */
    public static function login(array $admin): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        Csrf::rotate('admin');

        $_SESSION[self::SESSION_ADMIN_KEY] = [
            'id'                   => (int) $admin['id'],
            'name'                 => $admin['name'],
            'username'             => $admin['username'],
            'email'                => $admin['email'],
            'role'                 => $admin['role'],
            'must_change_password' => (int) $admin['must_change_password'],
        ];
        $_SESSION['last_activity'] = time();
    }

    public static function current(): ?array
    {
        $admin = $_SESSION[self::SESSION_ADMIN_KEY] ?? null;
        return is_array($admin) && !empty($admin['id']) ? $admin : null;
    }

    public static function logout(): void
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
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

