<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Repositories;

use PDO;
use Roostar\Core\Security\Encryptor;

final class ProfileRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function find(string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                u.email,
                u.naam_encrypted,
                u.role,
                u.last_login_at,
                u.password_changed_at,
                s.naam_encrypted AS school_naam_encrypted,
                sg.naam_encrypted AS scholengroep_naam_encrypted
            FROM users u
            LEFT JOIN scholen s ON s.id = u.school_id
            LEFT JOIN scholengroepen sg ON sg.id = u.scholengroep_id
            WHERE u.id = :id
              AND u.active = 1
            LIMIT 1
        ");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'naam' => $this->decrypt((string) $row['naam_encrypted']),
            'email' => $row['email'],
            'role' => $row['role'],
            'role_label' => $this->roleLabel((string) $row['role']),
            'school_naam' => $row['school_naam_encrypted'] ? $this->decrypt((string) $row['school_naam_encrypted']) : '-',
            'scholengroep_naam' => $row['scholengroep_naam_encrypted'] ? $this->decrypt((string) $row['scholengroep_naam_encrypted']) : '-',
            'last_login_at' => $row['last_login_at'],
            'password_changed_at' => $row['password_changed_at'],
        ];
    }

    private function decrypt(string $value): string
    {
        try {
            return $this->encryptor->decrypt($value);
        } catch (\Throwable) {
            return '[onleesbaar]';
        }
    }

    private function roleLabel(string $role): string
    {
        return [
            'roostar_admin' => 'Roostar Admin',
            'sg_admin' => 'Scholengroep admin',
            'school_admin' => 'School admin',
            'afdelingsleider' => 'Afdelingsleider',
            'rooster_medewerker' => 'Roostermedewerker',
            'leraar' => 'Leraar',
            'leerling' => 'Leerling',
        ][$role] ?? $role;
    }
}
