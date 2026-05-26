<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Repositories;

use PDO;

final class TwoFactorRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function get(string $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT user_id, secret, required, enabled FROM user_2fa WHERE user_id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function upsert(string $userId, ?string $secret, bool $required, bool $enabled): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO user_2fa (user_id, secret, required, enabled, created_at, updated_at)
            VALUES (:id, :secret, :required, :enabled, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                secret = VALUES(secret),
                required = VALUES(required),
                enabled = VALUES(enabled),
                updated_at = NOW()
        ");
        $stmt->execute([
            'id' => $userId,
            'secret' => $secret,
            'required' => $required ? 1 : 0,
            'enabled' => $enabled ? 1 : 0,
        ]);
    }

    public function requireSetup(string $userId): void
    {
        $this->upsert($userId, null, true, false);
    }

    public function disable(string $userId): void
    {
        $this->upsert($userId, null, false, false);
    }

    public function delete(string $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM user_2fa WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
    }
}
