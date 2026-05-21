<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Services;

use Roostar\Core\Auth\UserContext;
use Roostar\Core\Database\Connection;
use Roostar\Modules\Auth\Repositories\UserRepository;

final class AuthSession
{
    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?string
    {
        return is_string($_SESSION['user_id'] ?? null) ? $_SESSION['user_id'] : null;
    }

    public static function userContext(): ?UserContext
    {
        $userId = self::id();

        if (!$userId) {
            return null;
        }

        try {
            $repository = new UserRepository(Connection::get());
            $user = $repository->findActiveById($userId);

            if (!$user) {
                self::logout();
                return null;
            }

            return new UserContext(
                id: (string) $user['id'],
                role: (string) $user['role'],
                scholengroepId: $user['scholengroep_id'] ?? null,
                schoolId: $user['school_id'] ?? null,
                forcePasswordChange: (bool) ($user['force_password_change'] ?? false),
                permissions: $repository->permissionsForUser((string) $user['id']),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public static function login(string $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
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
                $params['secure'],
                $params['httponly'],
            );
        }

        session_destroy();
    }
}
