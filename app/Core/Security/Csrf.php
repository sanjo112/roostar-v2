<?php

declare(strict_types=1);

namespace Roostar\Core\Security;

final class Csrf
{
    public static function token(string $key = '_csrf_token'): string
    {
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[$key];
    }

    public static function verify(string $token, string $key = '_csrf_token'): bool
    {
        return isset($_SESSION[$key]) && hash_equals((string) $_SESSION[$key], $token);
    }
}

