<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Services;

use Roostar\Modules\Auth\Repositories\UserRepository;

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

        AuthSession::login((string) $user['id']);
        $this->users->touchLastLogin((string) $user['id']);

        return ['success' => true, 'user_id' => (string) $user['id']];
    }
}
