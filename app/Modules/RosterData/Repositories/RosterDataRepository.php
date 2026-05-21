<?php

declare(strict_types=1);

namespace Roostar\Modules\RosterData\Repositories;

use PDO;
use Roostar\Core\Auth\UserContext;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;

final class RosterDataRepository
{
    private const ENCRYPTED_TABLES = ['klassen', 'vakken', 'lokalen', 'opleidingen'];

    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function schoolYearsFor(UserContext $user): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT sj.*, s.naam_encrypted AS school_naam_encrypted
            FROM schooljaren sj
            INNER JOIN scholen s ON s.id = sj.school_id
            WHERE {$scopeSql}
            ORDER BY sj.startdatum DESC, sj.naam
        ");
        $stmt->execute($params);

        return array_map(fn (array $row): array => [
            ...$row,
            'school_naam' => $this->decrypt((string) $row['school_naam_encrypted']),
        ], $stmt->fetchAll());
    }

    public function classesFor(UserContext $user): array
    {
        return $this->encryptedRowsFor($user, 'klassen', 'k', "
            SELECT
                k.*,
                sj.naam AS schooljaar_naam,
                o.naam_encrypted AS opleiding_naam_encrypted,
                o.code AS opleiding_code,
                s.naam_encrypted AS school_naam_encrypted
            FROM klassen k
            INNER JOIN scholen s ON s.id = k.school_id
            LEFT JOIN schooljaren sj ON sj.id = k.schooljaar_id
            LEFT JOIN opleidingen o ON o.id = k.opleiding_id
        ", "ORDER BY sj.startdatum DESC, k.created_at DESC");
    }

    public function programsFor(UserContext $user): array
    {
        $programs = $this->encryptedRowsFor($user, 'opleidingen', 'o', "
            SELECT o.*, s.naam_encrypted AS school_naam_encrypted
            FROM opleidingen o
            INNER JOIN scholen s ON s.id = o.school_id
        ", "ORDER BY o.created_at DESC");

        $subjectsByProgram = $this->subjectsByProgram(array_column($programs, 'id'));

        return array_map(static function (array $program) use ($subjectsByProgram): array {
            $program['subjects'] = $subjectsByProgram[(string) $program['id']] ?? [];
            return $program;
        }, $programs);
    }

    public function subjectsFor(UserContext $user): array
    {
        return $this->encryptedRowsFor($user, 'vakken', 'v', "
            SELECT v.*, s.naam_encrypted AS school_naam_encrypted
            FROM vakken v
            INNER JOIN scholen s ON s.id = v.school_id
        ", "ORDER BY v.code IS NULL, v.code, v.created_at DESC");
    }

    public function roomsFor(UserContext $user): array
    {
        return $this->encryptedRowsFor($user, 'lokalen', 'l', "
            SELECT l.*, s.naam_encrypted AS school_naam_encrypted
            FROM lokalen l
            INNER JOIN scholen s ON s.id = l.school_id
        ", "ORDER BY l.created_at DESC");
    }

    public function teachersFor(UserContext $user): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.naam_encrypted, u.active, s.naam_encrypted AS school_naam_encrypted
            FROM users u
            INNER JOIN scholen s ON s.id = u.school_id
            WHERE {$scopeSql}
              AND u.role = 'leraar'
            ORDER BY u.active DESC, u.email
        ");
        $stmt->execute($params);

        return array_map(fn (array $row): array => [
            ...$row,
            'naam' => $this->decrypt((string) $row['naam_encrypted']),
            'school_naam' => $this->decrypt((string) $row['school_naam_encrypted']),
        ], $stmt->fetchAll());
    }

    public function createSchoolYear(string $schoolId, string $name, string $startDate, string $endDate): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO schooljaren (id, school_id, naam, startdatum, einddatum, active, created_at, updated_at)
            VALUES (:id, :school_id, :naam, :startdatum, :einddatum, 1, NOW(), NOW())
        ");
        $stmt->execute([
            'id' => Str::uuid(),
            'school_id' => $schoolId,
            'naam' => $name,
            'startdatum' => $startDate,
            'einddatum' => $endDate,
        ]);
    }

    public function updateSchoolYear(string $schoolYearId, string $schoolId, string $name, string $startDate, string $endDate, bool $active): void
    {
        if (!$this->schoolYearBelongsToSchool($schoolYearId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een schooljaar van dezelfde school.');
        }

        $stmt = $this->db->prepare("
            UPDATE schooljaren
            SET naam = :naam,
                startdatum = :startdatum,
                einddatum = :einddatum,
                active = :active,
                updated_at = NOW()
            WHERE id = :id
              AND school_id = :school_id
        ");
        $stmt->execute([
            'id' => $schoolYearId,
            'school_id' => $schoolId,
            'naam' => $name,
            'startdatum' => $startDate,
            'einddatum' => $endDate,
            'active' => $active ? 1 : 0,
        ]);
    }

    public function createClass(string $schoolId, string $name, ?string $schoolYearId, ?string $programId, ?int $yearLevel): void
    {
        if ($schoolYearId !== null && !$this->schoolYearBelongsToSchool($schoolYearId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een schooljaar van dezelfde school.');
        }

        if ($programId !== null && !$this->programBelongsToSchool($programId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een opleiding van dezelfde school.');
        }

        $this->createEncrypted('klassen', [
            'school_id' => $schoolId,
            'schooljaar_id' => $schoolYearId,
            'opleiding_id' => $programId,
            'naam' => $name,
            'leerjaar' => $yearLevel,
        ]);
    }

    public function updateClass(string $classId, string $schoolId, string $name, ?string $schoolYearId, ?string $programId, ?int $yearLevel, bool $active): void
    {
        if (!$this->encryptedRowBelongsToSchool('klassen', $classId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een klas van dezelfde school.');
        }

        if ($schoolYearId !== null && !$this->schoolYearBelongsToSchool($schoolYearId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een schooljaar van dezelfde school.');
        }

        if ($programId !== null && !$this->programBelongsToSchool($programId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een opleiding van dezelfde school.');
        }

        $this->updateEncrypted('klassen', $classId, $schoolId, [
            'schooljaar_id' => $schoolYearId,
            'opleiding_id' => $programId,
            'naam' => $name,
            'leerjaar' => $yearLevel,
            'active' => $active ? 1 : 0,
        ]);
    }

    public function schoolYearBelongsToSchool(string $schoolYearId, string $schoolId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM schooljaren
            WHERE id = :id
              AND school_id = :school_id
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $schoolYearId,
            'school_id' => $schoolId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function createSubject(string $schoolId, string $name, string $code): void
    {
        $this->createEncrypted('vakken', [
            'school_id' => $schoolId,
            'naam' => $name,
            'code' => $code !== '' ? strtoupper($code) : null,
        ]);
    }

    public function updateSubject(string $subjectId, string $schoolId, string $name, string $code, bool $active): void
    {
        if (!$this->encryptedRowBelongsToSchool('vakken', $subjectId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een vak van dezelfde school.');
        }

        $this->updateEncrypted('vakken', $subjectId, $schoolId, [
            'naam' => $name,
            'code' => $code !== '' ? strtoupper($code) : null,
            'active' => $active ? 1 : 0,
        ]);
    }

    public function createProgram(string $schoolId, string $name, string $code, string $level, array $subjectIds): void
    {
        $programId = $this->createEncrypted('opleidingen', [
            'school_id' => $schoolId,
            'naam' => $name,
            'code' => $code !== '' ? strtoupper($code) : null,
            'niveau' => $level !== '' ? $level : null,
        ]);

        $this->syncProgramSubjects($programId, $schoolId, $subjectIds);
    }

    public function updateProgram(string $programId, string $schoolId, string $name, string $code, string $level, array $subjectIds, bool $active): void
    {
        if (!$this->programBelongsToSchool($programId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een opleiding van dezelfde school.');
        }

        $stmt = $this->db->prepare("
            UPDATE opleidingen
            SET naam_encrypted = :naam_encrypted,
                naam_search_hash = :naam_search_hash,
                code = :code,
                niveau = :niveau,
                active = :active,
                updated_at = NOW()
            WHERE id = :id
              AND school_id = :school_id
        ");
        $stmt->execute([
            'id' => $programId,
            'school_id' => $schoolId,
            'naam_encrypted' => $this->encryptor->encrypt($name),
            'naam_search_hash' => hash('sha256', strtolower(trim($name))),
            'code' => $code !== '' ? strtoupper($code) : null,
            'niveau' => $level !== '' ? $level : null,
            'active' => $active ? 1 : 0,
        ]);

        $stmt = $this->db->prepare("DELETE FROM opleiding_vakken WHERE opleiding_id = :opleiding_id");
        $stmt->execute(['opleiding_id' => $programId]);
        $this->syncProgramSubjects($programId, $schoolId, $subjectIds);
    }

    public function createRoom(string $schoolId, string $name, ?int $capacity): void
    {
        $this->createEncrypted('lokalen', [
            'school_id' => $schoolId,
            'naam' => $name,
            'capaciteit' => $capacity,
        ]);
    }

    public function updateRoom(string $roomId, string $schoolId, string $name, ?int $capacity, bool $active): void
    {
        if (!$this->encryptedRowBelongsToSchool('lokalen', $roomId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een lokaal van dezelfde school.');
        }

        $this->updateEncrypted('lokalen', $roomId, $schoolId, [
            'naam' => $name,
            'capaciteit' => $capacity,
            'active' => $active ? 1 : 0,
        ]);
    }

    private function encryptedRowsFor(UserContext $user, string $table, string $alias, string $select, string $order): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            {$select}
            WHERE {$scopeSql}
            {$order}
        ");
        $stmt->execute($params);

        return array_map(function (array $row): array {
            $row['naam'] = $this->decrypt((string) $row['naam_encrypted']);
            $row['school_naam'] = $this->decrypt((string) $row['school_naam_encrypted']);

            if (!empty($row['opleiding_naam_encrypted'])) {
                $row['opleiding_naam'] = $this->decrypt((string) $row['opleiding_naam_encrypted']);
            }

            return $row;
        }, $stmt->fetchAll());
    }

    private function createEncrypted(string $table, array $values): string
    {
        if (!in_array($table, self::ENCRYPTED_TABLES, true)) {
            throw new \InvalidArgumentException('Unsupported roster data table.');
        }

        $values['id'] = Str::uuid();
        $values['naam_encrypted'] = $this->encryptor->encrypt((string) $values['naam']);
        $values['naam_search_hash'] = hash('sha256', strtolower(trim((string) $values['naam'])));
        unset($values['naam']);
        $values['active'] = 1;
        $values['created_at'] = date('Y-m-d H:i:s');
        $values['updated_at'] = date('Y-m-d H:i:s');

        $columns = array_keys($values);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $stmt = $this->db->prepare("
            INSERT INTO {$table} (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($values);

        return (string) $values['id'];
    }

    private function updateEncrypted(string $table, string $id, string $schoolId, array $values): void
    {
        if (!in_array($table, self::ENCRYPTED_TABLES, true)) {
            throw new \InvalidArgumentException('Unsupported roster data table.');
        }

        if (array_key_exists('naam', $values)) {
            $name = (string) $values['naam'];
            $values['naam_encrypted'] = $this->encryptor->encrypt($name);
            $values['naam_search_hash'] = hash('sha256', strtolower(trim($name)));
            unset($values['naam']);
        }

        $values['updated_at'] = date('Y-m-d H:i:s');

        $assignments = array_map(static fn (string $column): string => "{$column} = :{$column}", array_keys($values));
        $values['id'] = $id;
        $values['school_id'] = $schoolId;

        $stmt = $this->db->prepare("
            UPDATE {$table}
            SET " . implode(', ', $assignments) . "
            WHERE id = :id
              AND school_id = :school_id
        ");
        $stmt->execute($values);
    }

    private function syncProgramSubjects(string $programId, string $schoolId, array $subjectIds): void
    {
        $subjectIds = array_values(array_unique(array_filter($subjectIds, static fn (mixed $id): bool => is_string($id) && $id !== '')));

        if ($subjectIds === []) {
            return;
        }

        $validSubjectIds = $this->subjectIdsForSchool($schoolId, $subjectIds);
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO opleiding_vakken (opleiding_id, vak_id, created_at)
            VALUES (:opleiding_id, :vak_id, NOW())
        ");

        foreach ($validSubjectIds as $subjectId) {
            $stmt->execute([
                'opleiding_id' => $programId,
                'vak_id' => $subjectId,
            ]);
        }
    }

    private function subjectIdsForSchool(string $schoolId, array $subjectIds): array
    {
        if ($subjectIds === []) {
            return [];
        }

        $placeholders = [];
        $params = ['school_id' => $schoolId];

        foreach ($subjectIds as $index => $subjectId) {
            $key = 'subject_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $subjectId;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM vakken
            WHERE school_id = :school_id
              AND id IN (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($params);

        return array_column($stmt->fetchAll(), 'id');
    }

    private function subjectsByProgram(array $programIds): array
    {
        $programIds = array_values(array_filter($programIds, static fn (mixed $id): bool => is_string($id) && $id !== ''));

        if ($programIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($programIds as $index => $programId) {
            $key = 'program_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $programId;
        }

        $stmt = $this->db->prepare("
            SELECT ov.opleiding_id, v.id, v.naam_encrypted, v.code
            FROM opleiding_vakken ov
            INNER JOIN vakken v ON v.id = ov.vak_id
            WHERE ov.opleiding_id IN (" . implode(', ', $placeholders) . ")
            ORDER BY v.code IS NULL, v.code, v.created_at
        ");
        $stmt->execute($params);
        $subjects = [];

        foreach ($stmt->fetchAll() as $row) {
            $subjects[(string) $row['opleiding_id']][] = [
                'id' => $row['id'],
                'naam' => $this->decrypt((string) $row['naam_encrypted']),
                'code' => $row['code'],
            ];
        }

        return $subjects;
    }

    private function programBelongsToSchool(string $programId, string $schoolId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM opleidingen
            WHERE id = :id
              AND school_id = :school_id
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $programId,
            'school_id' => $schoolId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function encryptedRowBelongsToSchool(string $table, string $id, string $schoolId): bool
    {
        if (!in_array($table, self::ENCRYPTED_TABLES, true)) {
            throw new \InvalidArgumentException('Unsupported roster data table.');
        }

        $stmt = $this->db->prepare("
            SELECT 1
            FROM {$table}
            WHERE id = :id
              AND school_id = :school_id
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $id,
            'school_id' => $schoolId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function schoolScopeSql(UserContext $user, string $schoolAlias): array
    {
        if ($user->role === 'roostar_admin') {
            return ['1 = 1', []];
        }

        if ($user->schoolId) {
            return [
                "{$schoolAlias}.id = :school_id",
                ['school_id' => $user->schoolId],
            ];
        }

        if ($user->scholengroepId) {
            return [
                "{$schoolAlias}.scholengroep_id = :scholengroep_id",
                ['scholengroep_id' => $user->scholengroepId],
            ];
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
}
