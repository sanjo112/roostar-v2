<?php

declare(strict_types=1);

namespace Roostar\Modules\Users;

use PDO;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;

final class UserCreator
{
    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function create(array $data): string
    {
        $name = trim((string) $data['name']);
        $email = mb_strtolower(trim((string) $data['email']));
        $existing = $this->findIdByEmail($email);

        if ($existing) {
            return $existing;
        }

        $id = Str::uuid();

        $stmt = $this->db->prepare("
            INSERT INTO users (
                id,
                email,
                naam_encrypted,
                naam_search_hash,
                password_hash,
                role,
                scholengroep_id,
                school_id,
                active,
                created_at,
                updated_at
            ) VALUES (
                :id,
                :email,
                :naam_encrypted,
                :naam_search_hash,
                :password_hash,
                :role,
                :scholengroep_id,
                :school_id,
                1,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'id' => $id,
            'email' => $email,
            'naam_encrypted' => $this->encryptor->encrypt($name),
            'naam_search_hash' => Str::searchHash($name),
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'role' => (string) $data['role'],
            'scholengroep_id' => $data['scholengroep_id'] ?? null,
            'school_id' => $data['school_id'] ?? null,
        ]);

        return $id;
    }

    public function emailExists(string $email): bool
    {
        return $this->findIdByEmail(mb_strtolower(trim($email))) !== null;
    }

    private function findIdByEmail(string $email): ?string
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $id = $stmt->fetchColumn();

        return is_string($id) ? $id : null;
    }
}
