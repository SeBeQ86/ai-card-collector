<?php

declare(strict_types=1);

namespace App\Security;

final class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    public static function generate(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(string $token): bool
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        $stored = $_SESSION[self::SESSION_KEY];
        unset($_SESSION[self::SESSION_KEY]);

        return hash_equals($stored, $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::generate(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
