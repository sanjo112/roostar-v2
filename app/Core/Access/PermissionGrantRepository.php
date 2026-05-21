<?php

declare(strict_types=1);

namespace Roostar\Core\Access;

use PDO;
use Roostar\Core\Support\Str;

final class PermissionGrantRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function grant(string $userId, string $permission, string $scopeType, string $scopeId): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO permission_grants (id, user_id, permission, scope_type, scope_id, created_at)
            VALUES (:id, :user_id, :permission, :scope_type, :scope_id, NOW())
        ");
        $stmt->execute([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'permission' => $permission,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);
    }
}

