<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CSRF token generation and validation.
 * Tokens are stored per session and verified with hash_equals().
 */
final class Csrf
{
    private const TOKEN_LENGTH = 32;
    private const SESSION_KEY  = '_csrf_tokens';

    /**
     * Get or create the stable per-session CSRF token for a form key.
     */
    public static function token(string $formKey = 'default'): string
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            session_start();
        }

        if (
            !isset($_SESSION[self::SESSION_KEY][$formKey])
            || !is_string($_SESSION[self::SESSION_KEY][$formKey])
            || $_SESSION[self::SESSION_KEY][$formKey] === ''
        ) {
            $_SESSION[self::SESSION_KEY][$formKey] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }

        return $_SESSION[self::SESSION_KEY][$formKey];
    }

    /**
     * Alias for token() for backward compatibility.
     */
    public static function generate(string $formKey = 'default'): string
    {
        return self::token($formKey);
    }

    /**
     * Force-rotate a CSRF token (e.g., on login or logout).
     */
    public static function rotate(string $formKey = 'default'): string
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            session_start();
        }

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::SESSION_KEY][$formKey] = $token;
        return $token;
    }

    /**
     * Validate a supplied CSRF token against the session token.
     */
    public static function validate(string $formKey = 'default', ?string $token = null): bool
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            return false;
        }

        $stored = $_SESSION[self::SESSION_KEY][$formKey] ?? null;

        if (!is_string($stored) || !is_string($token) || $stored === '' || $token === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    /**
     * Render a hidden CSRF input field.
     */
    public static function field(string $formKey = 'default'): string
    {
        $token = self::token($formKey);
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Validate CSRF token from POST body or X-CSRF-Token header.
     */
    public static function validateRequest(string $formKey = 'default'): bool
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return self::validate($formKey, is_string($token) ? $token : null);
    }
}
