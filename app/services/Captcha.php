<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Dual anti-bot protection:
 * 1. Session-backed math challenge (works offline, no third-party keys required).
 * 2. Honeypot hidden field ('website_url') to trap automated bots silently.
 */
final class Captcha
{
    public const HONEYPOT_FIELD = 'website_url';
    public const ANSWER_FIELD   = 'captcha_answer';
    private const SESSION_KEY   = '_captcha_challenge';

    /**
     * Get or generate a math captcha question for the current session.
     */
    public static function question(bool $refresh = false): string
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            session_start();
        }

        if ($refresh || empty($_SESSION[self::SESSION_KEY]['question'])) {
            $a = random_int(2, 15);
            $b = random_int(1, 9);
            $_SESSION[self::SESSION_KEY] = [
                'question' => sprintf('What is %d + %d?', $a, $b),
                'answer'   => (string) ($a + $b),
            ];
        }

        return (string) $_SESSION[self::SESSION_KEY]['question'];
    }

    /**
     * Refresh the challenge (e.g., after successful submission).
     */
    public static function refresh(): string
    {
        return self::question(true);
    }

    /**
     * Validate the submitted math captcha answer.
     */
    public static function validateAnswer(?string $submitted): bool
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            return false;
        }

        $expected = $_SESSION[self::SESSION_KEY]['answer'] ?? null;
        if (!is_string($expected) || $expected === '' || $submitted === null) {
            return false;
        }

        $clean = trim($submitted);
        if ($clean === '' || !ctype_digit($clean)) {
            return false;
        }

        return hash_equals($expected, $clean);
    }

    /**
     * Return true if a bot filled the hidden honeypot field.
     */
    public static function isHoneypotTriggered(array $post): bool
    {
        $value = $post[self::HONEYPOT_FIELD] ?? '';
        return is_string($value) && trim($value) !== '';
    }

    /**
     * Render the hidden honeypot field HTML.
     */
    public static function honeypotField(): string
    {
        return sprintf(
            '<div class="visually-hidden" aria-hidden="true">'
            . '<label for="%1$s">Leave this field blank</label>'
            . '<input type="text" id="%1$s" name="%1$s" tabindex="-1" autocomplete="off" value="">'
            . '</div>',
            self::HONEYPOT_FIELD
        );
    }
}
