<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * One-shot session flash messages.
 * set() stores a message; get() retrieves and clears it.
 */
final class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public static function get(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }

    public static function has(): bool
    {
        return !empty($_SESSION['flash']);
    }
}
