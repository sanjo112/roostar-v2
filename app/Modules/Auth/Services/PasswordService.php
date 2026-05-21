<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Services;

use PDO;

final class PasswordService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function setTemporaryPassword(string $userId, string $temporaryPassword): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET password_hash = :password_hash,
                force_password_change = 1,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $userId,
            'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
        ]);
    }

    public function changePassword(string $userId, string $newPassword): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET password_hash = :password_hash,
                force_password_change = 0,
                password_changed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
              AND active = 1
        ");
        $stmt->execute([
            'id' => $userId,
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);
    }

    public function verifyCurrentPassword(string $userId, string $password): bool
    {
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id AND active = 1 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $hash = $stmt->fetchColumn();

        return is_string($hash) && password_verify($password, $hash);
    }

    public static function temporaryPassword(): string
    {
        return 'Roostar-' . bin2hex(random_bytes(4)) . '!';
    }
}
