<?php

declare(strict_types=1);

namespace Roostar\Modules\Schools\Repositories;

use PDO;
use Roostar\Core\Auth\UserContext;
use Roostar\Core\Security\Encryptor;

final class SchoolRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function accessibleFor(UserContext $user): array
    {
        if ($user->role === 'roostar_admin') {
            $stmt = $this->db->query("SELECT * FROM scholen WHERE active = 1 ORDER BY created_at");
            return $this->decryptRows($stmt->fetchAll());
        }

        if ($user->schoolId) {
            $stmt = $this->db->prepare("SELECT * FROM scholen WHERE id = :id AND active = 1 ORDER BY created_at");
            $stmt->execute(['id' => $user->schoolId]);
            return $this->decryptRows($stmt->fetchAll());
        }

        if ($user->scholengroepId) {
            $stmt = $this->db->prepare("
                SELECT *
                FROM scholen
                WHERE scholengroep_id = :scholengroep_id
                    AND active = 1
                ORDER BY created_at
            ");
            $stmt->execute(['scholengroep_id' => $user->scholengroepId]);
            return $this->decryptRows($stmt->fetchAll());
        }

        return [];
    }

    private function decryptRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $row['naam'] = $this->decrypt((string) $row['naam_encrypted']);
            unset($row['naam_encrypted']);

            return $row;
        }, $rows);
    }

    private function decrypt(string $value): string
    {
        try {
            return $this->encryptor->decrypt($value);
        } catch (\Throwable) {
            return '[onleesbaar]';
        }
    }
}
