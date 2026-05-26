<?php

declare(strict_types=1);

namespace Roostar\Modules\Users\Repositories;

use PDO;
use Roostar\Core\Auth\UserContext;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;

final class UserDirectoryRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function listFor(UserContext $user, ?string $schoolFilter = null, ?string $roleFilter = null, ?string $statusFilter = null, array $excludedRoles = []): array
    {
        [$scopeSql, $params] = $this->scopeFor($user);

        if ($schoolFilter) {
            $scopeSql .= " AND u.school_id = :school_filter";
            $params['school_filter'] = $schoolFilter;
        }

        if ($roleFilter) {
            $scopeSql .= " AND u.role = :role_filter";
            $params['role_filter'] = $roleFilter;
        }

        $excludedRoles = array_values(array_filter($excludedRoles, static fn (mixed $role): bool => is_string($role) && $role !== ''));
        if ($excludedRoles !== []) {
            $placeholders = [];
            foreach ($excludedRoles as $index => $role) {
                $key = 'excluded_role_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $role;
            }

            $scopeSql .= " AND u.role NOT IN (" . implode(', ', $placeholders) . ")";
        }

        if ($statusFilter === 'active') {
            $scopeSql .= " AND u.active = 1";
        }

        if ($statusFilter === 'inactive') {
            $scopeSql .= " AND u.active = 0";
        }

        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.email,
                u.naam_encrypted,
                u.role,
                u.scholengroep_id,
                u.school_id,
                u.active,
                u.last_login_at,
                u.created_at,
                s.naam_encrypted AS school_naam_encrypted,
                GROUP_CONCAT(pg.permission ORDER BY pg.permission SEPARATOR ',') AS permissions
            FROM users u
            LEFT JOIN scholen s ON s.id = u.school_id
            LEFT JOIN permission_grants pg ON pg.user_id = u.id
            WHERE {$scopeSql}
            GROUP BY u.id, s.id
            ORDER BY u.role, u.email
        ");
        $stmt->execute($params);

        return array_map(fn (array $row): array => $this->present($row), $stmt->fetchAll());
    }

    public function countsByRole(UserContext $user): array
    {
        $counts = [];

        foreach ($this->listFor($user) as $row) {
            $role = (string) $row['role'];
            $counts[$role] = ($counts[$role] ?? 0) + 1;
        }

        return $counts;
    }

    public function findManageableById(UserContext $user, string $targetUserId): ?array
    {
        [$scopeSql, $params] = $this->scopeFor($user);
        $params['target_user_id'] = $targetUserId;

        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.email,
                u.naam_encrypted,
                u.role,
                u.scholengroep_id,
                u.school_id,
                u.active,
                u.last_login_at,
                u.created_at,
                s.naam_encrypted AS school_naam_encrypted,
                GROUP_CONCAT(pg.permission ORDER BY pg.permission SEPARATOR ',') AS permissions
            FROM users u
            LEFT JOIN scholen s ON s.id = u.school_id
            LEFT JOIN permission_grants pg ON pg.user_id = u.id
            WHERE {$scopeSql}
              AND u.id = :target_user_id
            GROUP BY u.id, s.id
            LIMIT 1
        ");
        $stmt->execute($params);
        $row = $stmt->fetch();

        return is_array($row) ? $this->present($row) : null;
    }

    public function deactivate(string $targetUserId): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET active = 0,
                deactivated_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $targetUserId]);
    }

    public function reactivate(string $targetUserId): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET active = 1,
                deactivated_at = NULL,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $targetUserId]);
    }

    public function emailExistsForOtherUser(string $email, string $targetUserId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM users
            WHERE email = :email
              AND id <> :target_user_id
            LIMIT 1
        ");
        $stmt->execute([
            'email' => mb_strtolower(trim($email)),
            'target_user_id' => $targetUserId,
        ]);

        return is_string($stmt->fetchColumn());
    }

    public function updateProfile(string $targetUserId, array $data): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));

        $stmt = $this->db->prepare("
            UPDATE users
            SET email = :email,
                naam_encrypted = :naam_encrypted,
                naam_search_hash = :naam_search_hash,
                role = :role,
                scholengroep_id = :scholengroep_id,
                school_id = :school_id,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $targetUserId,
            'email' => $email,
            'naam_encrypted' => $this->encryptor->encrypt($name),
            'naam_search_hash' => Str::searchHash($name),
            'role' => (string) ($data['role'] ?? ''),
            'scholengroep_id' => $data['scholengroep_id'] ?? null,
            'school_id' => $data['school_id'] ?? null,
        ]);
    }

    private function scopeFor(UserContext $user): array
    {
        if ($user->role === 'roostar_admin') {
            return ['1 = 1', []];
        }

        if ($user->schoolId) {
            return [
                "u.school_id = :school_id",
                ['school_id' => $user->schoolId],
            ];
        }

        if ($user->scholengroepId) {
            return [
                "(u.scholengroep_id = :scholengroep_id OR s.scholengroep_id = :scholengroep_id2)",
                [
                    'scholengroep_id' => $user->scholengroepId,
                    'scholengroep_id2' => $user->scholengroepId,
                ],
            ];
        }

        return ['u.id = :user_id', ['user_id' => $user->id]];
    }

    private function present(array $row): array
    {
        $permissions = array_values(array_filter(explode(',', (string) ($row['permissions'] ?? ''))));

        return [
            'id' => $row['id'],
            'naam' => $this->decrypt((string) $row['naam_encrypted']),
            'email' => $row['email'],
            'role' => $row['role'],
            'role_label' => $this->roleLabel((string) $row['role']),
            'school_id' => $row['school_id'],
            'school_naam' => $row['school_naam_encrypted'] ? $this->decrypt((string) $row['school_naam_encrypted']) : '',
            'active' => (bool) $row['active'],
            'last_login_at' => $row['last_login_at'],
            'created_at' => $row['created_at'],
            'can_generate_roster' => in_array('roster.generate', $permissions, true),
            'permissions' => $permissions,
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
