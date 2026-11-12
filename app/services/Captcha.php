<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Honeypot captcha.
 * Renders a hidden field that bots fill in; real users leave it empty.
 */
final class Captcha
{
    private const FIELD_NAME = 'website_url'; // sounds plausible to bots

    public static function field(): string
    {
        return sprintf(
            '<div class="d-none" aria-hidden="true">' .
            '<label for="%1$s">Leave blank</label>' .
            '<input type="text" id="%1$s" name="%1$s" tabindex="-1" autocomplete="off">' .
            '</div>',
            self::FIELD_NAME
        );
    }

    public static function validate(): bool
    {
        $value = $_POST[self::FIELD_NAME] ?? '';
        return $value === '';
    }

    public static function fieldName(): string
    {
        return self::FIELD_NAME;
    }
}

