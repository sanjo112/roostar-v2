<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Services;

use Roostar\Modules\Auth\Repositories\UserRepository;
use Roostar\Modules\Auth\Repositories\TwoFactorRepository;
use Roostar\Core\Database\Connection;

final class AuthService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function attempt(string $email, string $password): array
    {
        $user = $this->users->findActiveByEmail($email);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return ['success' => false, 'error' => 'Onjuist e-mailadres of wachtwoord.'];
        }

        // check two-factor requirements
        $two = new TwoFactorRepository(Connection::get());
        $t = $two->get((string) $user['id']);

        if (is_array($t) && (int) ($t['required'] ?? 0) === 1) {
            if (empty($t['secret'])) {
                // require setup
                return ['success' => false, 'two_factor' => 'setup', 'user_id' => (string) $user['id']];
            }

            if ((int) ($t['enabled'] ?? 0) === 1) {
                // require challenge
                return ['success' => false, 'two_factor' => 'challenge', 'user_id' => (string) $user['id']];
            }
        }

        AuthSession::login((string) $user['id']);
        $this->users->touchLastLogin((string) $user['id']);

        return ['success' => true, 'user_id' => (string) $user['id']];
    }
}
