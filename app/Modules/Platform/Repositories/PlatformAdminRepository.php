<?php

declare(strict_types=1);

namespace Roostar\Modules\Platform\Repositories;

use PDO;
use Roostar\Core\Access\PermissionGrantRepository;
use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Access\RoleDefaults;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;
use Roostar\Modules\Schools\SchoolCreator;
use Roostar\Modules\Users\UserCreator;

final class PlatformAdminRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function customers(): array
    {
        $stmt = $this->db->query("
            SELECT
                s.id,
                s.scholengroep_id,
                s.naam_encrypted AS school_naam_encrypted,
                s.active,
                s.archived_at,
                s.created_at,
                sg.naam_encrypted AS groep_naam_encrypted,
                COUNT(DISTINCT u.id) AS gebruikers_count,
                SUM(CASE WHEN u.role = 'school_admin' THEN 1 ELSE 0 END) AS admins_count
            FROM scholen s
            INNER JOIN scholengroepen sg ON sg.id = s.scholengroep_id
            LEFT JOIN users u ON u.school_id = s.id
            GROUP BY s.id
            ORDER BY s.active DESC, s.created_at DESC
        ");

        return array_map(fn (array $row): array => [
            ...$row,
            'school_naam' => $this->decrypt((string) $row['school_naam_encrypted']),
            'groep_naam' => $this->decrypt((string) $row['groep_naam_encrypted']),
        ], $stmt->fetchAll());
    }

    public function groups(): array
    {
        $stmt = $this->db->query("
            SELECT sg.*, COUNT(s.id) AS scholen_count
            FROM scholengroepen sg
            LEFT JOIN scholen s ON s.scholengroep_id = sg.id
            GROUP BY sg.id
            ORDER BY sg.created_at DESC
        ");

        return array_map(fn (array $row): array => [
            ...$row,
            'naam' => $this->decrypt((string) $row['naam_encrypted']),
        ], $stmt->fetchAll());
    }

    public function createCustomer(string $groupName, string $schoolName, ?string $adminName, ?string $adminEmail, ?string $adminPassword): array
    {
        $groupName = trim($groupName);
        $schoolName = trim($schoolName);

        if ($groupName === '' || $schoolName === '') {
            throw new \InvalidArgumentException('Vul scholengroep en schoolnaam in.');
        }

        $schools = new SchoolCreator($this->db, $this->encryptor);
        $groupId = $schools->createScholengroep($groupName);
        $schoolId = $schools->createSchool($groupId, $schoolName);
        $adminId = null;

        if ($adminEmail !== null && trim($adminEmail) !== '') {
            if ($adminName === null || trim($adminName) === '') {
                throw new \InvalidArgumentException('Vul een naam in voor de school-admin.');
            }

            if ($adminPassword === null || strlen($adminPassword) < 8) {
                throw new \InvalidArgumentException('Het wachtwoord voor de school-admin moet minimaal 8 tekens zijn.');
            }

            $creator = new UserCreator($this->db, $this->encryptor);
            $adminId = $creator->create([
                'name' => $adminName,
                'email' => mb_strtolower(trim($adminEmail)),
                'password' => $adminPassword,
                'role' => 'school_admin',
                'school_id' => $schoolId,
                'scholengroep_id' => null,
            ]);

            $this->ensureSchoolAdmin($adminId, $schoolId, $adminName);
        }

        return [
            'scholengroep_id' => $groupId,
            'school_id' => $schoolId,
            'admin_id' => $adminId,
        ];
    }

    public function createSchoolAdmin(string $schoolId, string $name, string $email, string $password): string
    {
        if (!$this->schoolExists($schoolId)) {
            throw new \InvalidArgumentException('School niet gevonden.');
        }

        if (trim($name) === '' || trim($email) === '' || strlen($password) < 8) {
            throw new \InvalidArgumentException('Vul naam, e-mail en een wachtwoord van minimaal 8 tekens in.');
        }

        $creator = new UserCreator($this->db, $this->encryptor);
        $userId = $creator->create([
            'name' => $name,
            'email' => mb_strtolower(trim($email)),
            'password' => $password,
            'role' => 'school_admin',
            'school_id' => $schoolId,
            'scholengroep_id' => null,
        ]);

        $this->ensureSchoolAdmin($userId, $schoolId, $name);

        return $userId;
    }

    public function archiveCustomer(string $schoolId): void
    {
        if (!$this->schoolExists($schoolId)) {
            throw new \InvalidArgumentException('School niet gevonden.');
        }

        $stmt = $this->db->prepare("
            UPDATE scholen
            SET active = 0,
                archived_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $schoolId]);
    }

    public function restoreCustomer(string $schoolId): void
    {
        if (!$this->schoolExists($schoolId, false)) {
            throw new \InvalidArgumentException('School niet gevonden.');
        }

        $stmt = $this->db->prepare("
            UPDATE scholen
            SET active = 1,
                archived_at = NULL,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $schoolId]);
    }

    private function ensureSchoolAdmin(string $userId, string $schoolId, string $name): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET naam_encrypted = :naam_encrypted,
                naam_search_hash = :naam_search_hash,
                role = 'school_admin',
                school_id = :school_id,
                scholengroep_id = NULL,
                active = 1,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $userId,
            'school_id' => $schoolId,
            'naam_encrypted' => $this->encryptor->encrypt($name),
            'naam_search_hash' => Str::searchHash($name),
        ]);

        $grants = new PermissionGrantRepository($this->db);
        foreach (array_unique([
            ...RoleDefaults::basePermissions('school_admin'),
            PermissionRegistry::ROSTER_GENERATE,
            PermissionRegistry::ROSTER_EDIT,
            PermissionRegistry::ABSENCE_MANAGE,
            PermissionRegistry::TEST_PLANNING_MANAGE,
        ]) as $permission) {
            $grants->grant($userId, $permission, 'school', $schoolId);
        }
    }

    private function schoolExists(string $schoolId, bool $activeOnly = true): bool
    {
        $sql = "SELECT 1 FROM scholen WHERE id = :id";
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }

        $stmt = $this->db->prepare($sql . " LIMIT 1");
        $stmt->execute(['id' => $schoolId]);

        return (bool) $stmt->fetchColumn();
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
