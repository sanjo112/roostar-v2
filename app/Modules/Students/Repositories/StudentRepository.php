<?php

declare(strict_types=1);

namespace Roostar\Modules\Students\Repositories;

use PDO;
use Roostar\Core\Auth\UserContext;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;

final class StudentRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function listFor(UserContext $user): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.email,
                u.naam_encrypted,
                u.active,
                u.school_id,
                lp.leerlingnummer,
                lp.klas_id,
                k.naam_encrypted AS klas_naam_encrypted,
                k.opleiding_id,
                s.naam_encrypted AS school_naam_encrypted
            FROM users u
            INNER JOIN scholen s ON s.id = u.school_id
            LEFT JOIN leerling_profielen lp ON lp.user_id = u.id
            LEFT JOIN klassen k ON k.id = lp.klas_id
            WHERE {$scopeSql}
              AND u.role = 'leerling'
            ORDER BY u.active DESC, k.naam_search_hash, u.naam_search_hash, u.email
        ");
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        $electivesByStudent = $this->studentElectives(array_column($rows, 'id'));

        return array_map(fn (array $row): array => [
            ...$row,
            'naam' => $this->decrypt((string) $row['naam_encrypted']),
            'school_naam' => $this->decrypt((string) $row['school_naam_encrypted']),
            'klas_naam' => !empty($row['klas_naam_encrypted']) ? $this->decrypt((string) $row['klas_naam_encrypted']) : '',
            'electives' => $electivesByStudent[(string) $row['id']] ?? [],
        ], $rows);
    }

    public function classesFor(UserContext $user): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT
                k.id,
                k.school_id,
                k.opleiding_id,
                k.naam_encrypted,
                k.active,
                sj.naam AS schooljaar_naam,
                s.naam_encrypted AS school_naam_encrypted
            FROM klassen k
            INNER JOIN scholen s ON s.id = k.school_id
            LEFT JOIN schooljaren sj ON sj.id = k.schooljaar_id
            WHERE {$scopeSql}
              AND k.active = 1
            ORDER BY sj.startdatum DESC, k.naam_search_hash
        ");
        $stmt->execute($params);

        return array_map(fn (array $row): array => [
            ...$row,
            'naam' => $this->decrypt((string) $row['naam_encrypted']),
            'school_naam' => $this->decrypt((string) $row['school_naam_encrypted']),
        ], $stmt->fetchAll());
    }

    public function electiveSubjectsByClass(UserContext $user): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT
                k.id AS klas_id,
                v.id AS vak_id,
                v.naam_encrypted,
                v.code
            FROM klassen k
            INNER JOIN scholen s ON s.id = k.school_id
            INNER JOIN opleiding_vakken ov ON ov.opleiding_id = k.opleiding_id AND ov.keuzevak = 1
            INNER JOIN vakken v ON v.id = ov.vak_id
            WHERE {$scopeSql}
              AND k.active = 1
            ORDER BY k.naam_search_hash, v.code IS NULL, v.code, v.naam_search_hash
        ");
        $stmt->execute($params);
        $byClass = [];

        foreach ($stmt->fetchAll() as $row) {
            $byClass[(string) $row['klas_id']][] = [
                'id' => (string) $row['vak_id'],
                'naam' => $this->decrypt((string) $row['naam_encrypted']),
                'code' => $row['code'],
            ];
        }

        return $byClass;
    }

    public function syncProfile(string $studentId, string $schoolId, ?string $classId, string $studentNumber, array $electiveSubjectIds = []): void
    {
        if (!$this->studentBelongsToSchool($studentId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een leerling van dezelfde school.');
        }

        if ($classId !== null && !$this->classBelongsToSchool($classId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een klas van dezelfde school.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO leerling_profielen (user_id, klas_id, leerlingnummer, created_at, updated_at)
            VALUES (:user_id, :klas_id, :leerlingnummer, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                klas_id = VALUES(klas_id),
                leerlingnummer = VALUES(leerlingnummer),
                updated_at = NOW()
        ");
        $stmt->execute([
            'user_id' => $studentId,
            'klas_id' => $classId,
            'leerlingnummer' => $studentNumber !== '' ? $studentNumber : null,
        ]);

        $this->syncStudentElectives($studentId, $classId, $electiveSubjectIds);
    }

    public function studentIdByEmailForSchool(string $schoolId, string $email): ?string
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM users
            WHERE school_id = :school_id
              AND email = :email
              AND role = 'leerling'
            LIMIT 1
        ");
        $stmt->execute([
            'school_id' => $schoolId,
            'email' => mb_strtolower(trim($email)),
        ]);
        $id = $stmt->fetchColumn();

        return is_string($id) ? $id : null;
    }

    public function classIdByName(string $schoolId, string $className): ?string
    {
        $className = trim($className);
        if ($className === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM klassen
            WHERE school_id = :school_id
              AND active = 1
              AND (id = :class_id OR naam_search_hash = :naam_search_hash)
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            'school_id' => $schoolId,
            'class_id' => $className,
            'naam_search_hash' => Str::searchHash($className),
        ]);
        $id = $stmt->fetchColumn();

        if (is_string($id)) {
            return $id;
        }

        $wanted = $this->normalizeClassCode($className);
        if ($wanted === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id, naam_encrypted
            FROM klassen
            WHERE school_id = :school_id
              AND active = 1
            ORDER BY created_at DESC
        ");
        $stmt->execute(['school_id' => $schoolId]);

        $suffixMatch = null;
        foreach ($stmt->fetchAll() as $row) {
            $candidate = $this->normalizeClassCode($this->decrypt((string) $row['naam_encrypted']));

            if ($candidate === $wanted) {
                return (string) $row['id'];
            }

            if ($suffixMatch === null && str_ends_with($candidate, $wanted)) {
                $suffixMatch = (string) $row['id'];
            }
        }

        return $suffixMatch;
    }

    public function electiveSubjectIdsByCodes(string $classId, array $codes): array
    {
        $values = array_values(array_unique(array_filter(array_map(static fn (mixed $code): string => trim((string) $code), $codes), static fn (string $code): bool => $code !== '')));
        if ($values === []) {
            return [];
        }

        $codePlaceholders = [];
        $hashPlaceholders = [];
        $params = ['klas_id' => $classId];
        foreach ($values as $index => $value) {
            $key = 'code_' . $index;
            $hashKey = 'hash_' . $index;
            $codePlaceholders[] = ':' . $key;
            $hashPlaceholders[] = ':' . $hashKey;
            $params[$key] = mb_strtoupper($value);
            $params[$hashKey] = Str::searchHash($value);
        }

        $stmt = $this->db->prepare("
            SELECT v.id
            FROM klassen k
            INNER JOIN opleiding_vakken ov ON ov.opleiding_id = k.opleiding_id AND ov.keuzevak = 1
            INNER JOIN vakken v ON v.id = ov.vak_id
            WHERE k.id = :klas_id
              AND (
                UPPER(v.code) IN (" . implode(', ', $codePlaceholders) . ")
                OR v.naam_search_hash IN (" . implode(', ', $hashPlaceholders) . ")
              )
        ");
        $stmt->execute($params);

        return array_map(static fn (array $row): string => (string) $row['id'], $stmt->fetchAll());
    }

    private function syncStudentElectives(string $studentId, ?string $classId, array $electiveSubjectIds): void
    {
        $stmt = $this->db->prepare("DELETE FROM leerling_keuzevakken WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $studentId]);

        if ($classId === null) {
            return;
        }

        $validSubjectIds = $this->validElectiveSubjectIds($classId, $electiveSubjectIds);
        if ($validSubjectIds === []) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT IGNORE INTO leerling_keuzevakken (user_id, vak_id, created_at)
            VALUES (:user_id, :vak_id, NOW())
        ");

        foreach ($validSubjectIds as $subjectId) {
            $stmt->execute(['user_id' => $studentId, 'vak_id' => $subjectId]);
        }
    }

    private function validElectiveSubjectIds(string $classId, array $subjectIds): array
    {
        $subjectIds = array_values(array_unique(array_filter($subjectIds, static fn (mixed $id): bool => is_string($id) && $id !== '')));

        if ($subjectIds === []) {
            return [];
        }

        $placeholders = [];
        $params = ['klas_id' => $classId];
        foreach ($subjectIds as $index => $subjectId) {
            $key = 'subject_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $subjectId;
        }

        $stmt = $this->db->prepare("
            SELECT ov.vak_id
            FROM klassen k
            INNER JOIN opleiding_vakken ov ON ov.opleiding_id = k.opleiding_id AND ov.keuzevak = 1
            WHERE k.id = :klas_id
              AND ov.vak_id IN (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($params);

        return array_column($stmt->fetchAll(), 'vak_id');
    }

    private function studentElectives(array $studentIds): array
    {
        $studentIds = array_values(array_filter($studentIds, static fn (mixed $id): bool => is_string($id) && $id !== ''));
        if ($studentIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($studentIds as $index => $studentId) {
            $key = 'student_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $studentId;
        }

        $stmt = $this->db->prepare("
            SELECT lkv.user_id, v.id, v.naam_encrypted, v.code
            FROM leerling_keuzevakken lkv
            INNER JOIN vakken v ON v.id = lkv.vak_id
            WHERE lkv.user_id IN (" . implode(', ', $placeholders) . ")
            ORDER BY v.code IS NULL, v.code, v.naam_search_hash
        ");
        $stmt->execute($params);
        $electives = [];

        foreach ($stmt->fetchAll() as $row) {
            $electives[(string) $row['user_id']][] = [
                'id' => (string) $row['id'],
                'naam' => $this->decrypt((string) $row['naam_encrypted']),
                'code' => $row['code'],
            ];
        }

        return $electives;
    }

    public function updateStudent(string $studentId, string $schoolId, string $name, string $email, bool $active): void
    {
        if (!$this->studentBelongsToSchool($studentId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een leerling van dezelfde school.');
        }

        $email = mb_strtolower(trim($email));
        if ($this->emailBelongsToAnotherUser($email, $studentId)) {
            throw new \InvalidArgumentException('Er bestaat al een gebruiker met dit e-mailadres.');
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET email = :email,
                naam_encrypted = :naam_encrypted,
                naam_search_hash = :naam_search_hash,
                active = :active,
                updated_at = NOW()
            WHERE id = :id
              AND school_id = :school_id
              AND role = 'leerling'
        ");
        $stmt->execute([
            'id' => $studentId,
            'school_id' => $schoolId,
            'email' => $email,
            'naam_encrypted' => $this->encryptor->encrypt($name),
            'naam_search_hash' => Str::searchHash($name),
            'active' => $active ? 1 : 0,
        ]);
    }

    public function deactivate(string $studentId, string $schoolId): void
    {
        if (!$this->studentBelongsToSchool($studentId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een leerling van dezelfde school.');
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET active = 0,
                updated_at = NOW()
            WHERE id = :id
              AND school_id = :school_id
              AND role = 'leerling'
        ");
        $stmt->execute(['id' => $studentId, 'school_id' => $schoolId]);
    }

    private function studentBelongsToSchool(string $studentId, string $schoolId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE id = :id AND school_id = :school_id AND role = 'leerling' LIMIT 1");
        $stmt->execute(['id' => $studentId, 'school_id' => $schoolId]);

        return (bool) $stmt->fetchColumn();
    }

    private function classBelongsToSchool(string $classId, string $schoolId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM klassen WHERE id = :id AND school_id = :school_id AND active = 1 LIMIT 1");
        $stmt->execute(['id' => $classId, 'school_id' => $schoolId]);

        return (bool) $stmt->fetchColumn();
    }

    private function emailBelongsToAnotherUser(string $email, string $studentId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE email = :email AND id <> :id LIMIT 1");
        $stmt->execute(['email' => $email, 'id' => $studentId]);

        return (bool) $stmt->fetchColumn();
    }

    private function schoolScopeSql(UserContext $user, string $schoolAlias): array
    {
        if ($user->role === 'roostar_admin') {
            return ['1 = 1', []];
        }

        if ($user->schoolId) {
            return ["{$schoolAlias}.id = :school_id", ['school_id' => $user->schoolId]];
        }

        if ($user->scholengroepId) {
            return ["{$schoolAlias}.scholengroep_id = :scholengroep_id", ['scholengroep_id' => $user->scholengroepId]];
        }

        return ['1 = 0', []];
    }

    private function decrypt(string $value): string
    {
        try {
            return $this->encryptor->decrypt($value);
        } catch (\Throwable) {
            return '[onleesbaar]';
        }
    }

    private function normalizeClassCode(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($value))) ?? '';
    }
}
