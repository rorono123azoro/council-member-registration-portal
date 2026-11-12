<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CSRF token generation and validation.
 * Tokens are stored in the session, single-use per form.
 */
final class Csrf
{
    private const TOKEN_LENGTH = 32;
    private const SESSION_KEY  = '_csrf_tokens';

    public static function generate(string $formKey = 'default'): string
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::SESSION_KEY][$formKey] = $token;

        return $token;
    }

    public static function validate(string $formKey = 'default', ?string $token = null): bool
    {
        if (!isset($_SESSION)) {
            return false;
        }

        $stored = $_SESSION[self::SESSION_KEY][$formKey] ?? null;

        if ($stored === null || $token === null) {
            return false;
        }

        // Consume the token (single-use)
        unset($_SESSION[self::SESSION_KEY][$formKey]);

        return hash_equals($stored, $token);
    }

    public static function field(string $formKey = 'default'): string
    {
        $token = self::generate($formKey);
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    public static function validateRequest(string $formKey = 'default'): bool
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return self::validate($formKey, $token);
    }
}

