<?php

declare(strict_types=1);

namespace Roostar\Modules\Schools;

use PDO;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;

final class SchoolCreator
{
    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function createScholengroep(string $name): string
    {
        $existing = $this->findScholengroepByName($name);

        if ($existing) {
            return $existing;
        }

        $id = Str::uuid();
        $stmt = $this->db->prepare("
            INSERT INTO scholengroepen (id, naam_encrypted, naam_search_hash, created_at, updated_at)
            VALUES (:id, :naam_encrypted, :naam_search_hash, NOW(), NOW())
        ");
        $stmt->execute([
            'id' => $id,
            'naam_encrypted' => $this->encryptor->encrypt($name),
            'naam_search_hash' => Str::searchHash($name),
        ]);

        return $id;
    }

    public function createSchool(string $scholengroepId, string $name): string
    {
        $existing = $this->findSchoolByName($scholengroepId, $name);

        if ($existing) {
            return $existing;
        }

        $id = Str::uuid();
        $stmt = $this->db->prepare("
            INSERT INTO scholen (id, scholengroep_id, naam_encrypted, naam_search_hash, created_at, updated_at)
            VALUES (:id, :scholengroep_id, :naam_encrypted, :naam_search_hash, NOW(), NOW())
        ");
        $stmt->execute([
            'id' => $id,
            'scholengroep_id' => $scholengroepId,
            'naam_encrypted' => $this->encryptor->encrypt($name),
            'naam_search_hash' => Str::searchHash($name),
        ]);

        return $id;
    }

    private function findScholengroepByName(string $name): ?string
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM scholengroepen
            WHERE naam_search_hash = :hash
            LIMIT 1
        ");
        $stmt->execute(['hash' => Str::searchHash($name)]);
        $id = $stmt->fetchColumn();

        return is_string($id) ? $id : null;
    }

    private function findSchoolByName(string $scholengroepId, string $name): ?string
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM scholen
            WHERE scholengroep_id = :scholengroep_id
              AND naam_search_hash = :hash
            LIMIT 1
        ");
        $stmt->execute([
            'scholengroep_id' => $scholengroepId,
            'hash' => Str::searchHash($name),
        ]);
        $id = $stmt->fetchColumn();

        return is_string($id) ? $id : null;
    }
}

