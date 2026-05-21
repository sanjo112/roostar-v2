<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM users
            WHERE email = :email
              AND active = 1
            LIMIT 1
        ");
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    public function findActiveById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM users
            WHERE id = :id
              AND active = 1
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    public function permissionsForUser(string $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT permission, scope_type, scope_id
            FROM permission_grants
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function touchLastLogin(string $userId): void
    {
        $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $userId]);
    }
}

