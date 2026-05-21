<?php

declare(strict_types=1);

namespace Roostar\Core\Audit;

use PDO;
use Roostar\Core\Support\Str;

final class AuditLogger
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function record(
        string $action,
        ?string $userId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
        ?string $ipAddress = null,
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO audit_log (
                id,
                user_id,
                action,
                entity_type,
                entity_id,
                metadata_json,
                ip_address,
                created_at
            ) VALUES (
                :id,
                :user_id,
                :action,
                :entity_type,
                :entity_id,
                :metadata_json,
                :ip_address,
                NOW()
            )
        ");

        $stmt->execute([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'ip_address' => $ipAddress,
        ]);
    }
}
