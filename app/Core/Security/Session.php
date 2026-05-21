<?php

declare(strict_types=1);

namespace Roostar\Core\Security;

final class Session
{
    public static function start(string $name): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($name);
        session_set_cookie_params([
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']),
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

