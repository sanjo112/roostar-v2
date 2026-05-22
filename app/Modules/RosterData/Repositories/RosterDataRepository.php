<?php

declare(strict_types=1);

namespace Roostar\Modules\RosterData\Repositories;

use PDO;
use Roostar\Core\Auth\UserContext;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;

final class RosterDataRepository
{
    private const ENCRYPTED_TABLES = ['klassen', 'vakken', 'lokalen', 'opleidingen', 'locaties'];

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

    public function periodsFor(UserContext $user): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT
                sp.*,
                sj.naam AS schooljaar_naam,
                sj.startdatum AS schooljaar_startdatum,
                sj.einddatum AS schooljaar_einddatum,
                sj.school_id,
                s.naam_encrypted AS school_naam_encrypted
            FROM schooljaar_periodes sp
            INNER JOIN schooljaren sj ON sj.id = sp.schooljaar_id
            INNER JOIN scholen s ON s.id = sj.school_id
            WHERE {$scopeSql}
            ORDER BY sj.startdatum DESC, COALESCE(sp.week_van_jaar, YEAR(sj.startdatum)), sp.week_van, sp.naam
        ");
        $stmt->execute($params);

        return array_map(fn (array $row): array => [
            ...$row,
            'school_naam' => $this->decrypt((string) $row['school_naam_encrypted']),
        ], $stmt->fetchAll());
    }

    public function schoolYearBreaksFor(UserContext $user): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT
                svd.*,
                sj.naam AS schooljaar_naam,
                sj.school_id,
                s.naam_encrypted AS school_naam_encrypted
            FROM schooljaar_vrije_dagen svd
            INNER JOIN schooljaren sj ON sj.id = svd.schooljaar_id
            INNER JOIN scholen s ON s.id = sj.school_id
            WHERE {$scopeSql}
            ORDER BY sj.startdatum DESC, svd.startdatum, svd.naam
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

    public function locationsFor(UserContext $user): array
    {
        $locations = $this->encryptedRowsFor($user, 'locaties', 'loc', "
            SELECT loc.*, s.naam_encrypted AS school_naam_encrypted
            FROM locaties loc
            INNER JOIN scholen s ON s.id = loc.school_id
        ", "ORDER BY loc.active DESC, loc.extern, loc.created_at DESC");

        return array_map(static function (array $location): array {
            $availability = $location['beschikbaarheid_json'] !== null
                ? json_decode((string) $location['beschikbaarheid_json'], true)
                : null;
            $location['available_slots'] = is_array($availability) ? array_values(array_filter($availability, 'is_string')) : null;

            return $location;
        }, $locations);
    }

    public function roomsFor(UserContext $user): array
    {
        $rooms = $this->encryptedRowsFor($user, 'lokalen', 'l', "
            SELECT
                l.*,
                s.naam_encrypted AS school_naam_encrypted,
                loc.naam_encrypted AS locatie_naam_encrypted,
                loc.extern AS locatie_extern
            FROM lokalen l
            INNER JOIN scholen s ON s.id = l.school_id
            LEFT JOIN locaties loc ON loc.id = l.locatie_id
        ", "ORDER BY l.created_at DESC");

        $subjectsByRoom = $this->subjectsByRoom(array_column($rooms, 'id'));

        return array_map(function (array $room) use ($subjectsByRoom): array {
            $room['subjects'] = $subjectsByRoom[(string) $room['id']] ?? [];
            $room['locatie_naam'] = !empty($room['locatie_naam_encrypted'])
                ? $this->decrypt((string) $room['locatie_naam_encrypted'])
                : $room['school_naam'];
            $room['locatie_extern'] = (int) ($room['locatie_extern'] ?? 0) === 1;
            $availability = $room['beschikbaarheid_json'] !== null
                ? json_decode((string) $room['beschikbaarheid_json'], true)
                : null;
            $room['available_slots'] = is_array($availability) ? array_values(array_filter($availability, 'is_string')) : null;
            return $room;
        }, $rooms);
    }

    public function teachersFor(UserContext $user): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.email,
                u.naam_encrypted,
                u.active,
                u.school_id,
                lp.max_uren_per_week,
                lp.max_uren_per_dag,
                lp.beschikbaarheid_json,
                s.naam_encrypted AS school_naam_encrypted
            FROM users u
            INNER JOIN scholen s ON s.id = u.school_id
            LEFT JOIN leraar_profielen lp ON lp.user_id = u.id
            WHERE {$scopeSql}
              AND u.role = 'leraar'
            ORDER BY u.active DESC, u.email
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $subjectsByTeacher = $this->subjectsByTeacher(array_column($rows, 'id'));

        return array_map(function (array $row) use ($subjectsByTeacher): array {
            $availability = $row['beschikbaarheid_json'] !== null
                ? json_decode((string) $row['beschikbaarheid_json'], true)
                : null;

            return [
                ...$row,
                'naam' => $this->decrypt((string) $row['naam_encrypted']),
                'school_naam' => $this->decrypt((string) $row['school_naam_encrypted']),
                'max_uren_per_week' => (int) ($row['max_uren_per_week'] ?? 24),
                'max_uren_per_dag' => (int) ($row['max_uren_per_dag'] ?? 6),
                'available_slots' => is_array($availability) ? array_values(array_filter($availability, 'is_string')) : null,
                'subjects' => $subjectsByTeacher[(string) $row['id']] ?? [],
            ];
        }, $rows);
    }

    public function syncTeacherProfile(string $teacherId, string $schoolId, int $maxHoursPerWeek, int $maxHoursPerDay, array $availableSlots, array $subjectIds): void
    {
        if (!$this->teacherBelongsToSchool($teacherId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een leraar van dezelfde school.');
        }

        $maxHoursPerWeek = max(1, min(45, $maxHoursPerWeek));
        $maxHoursPerDay = max(1, min(9, $maxHoursPerDay));
        $availableSlots = $this->normalizeAvailabilitySlots($availableSlots);

        $stmt = $this->db->prepare("
            INSERT INTO leraar_profielen (user_id, max_uren_per_week, max_uren_per_dag, beschikbaarheid_json, created_at, updated_at)
            VALUES (:user_id, :max_uren_per_week, :max_uren_per_dag, :beschikbaarheid_json, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                max_uren_per_week = VALUES(max_uren_per_week),
                max_uren_per_dag = VALUES(max_uren_per_dag),
                beschikbaarheid_json = VALUES(beschikbaarheid_json),
                updated_at = NOW()
        ");
        $stmt->execute([
            'user_id' => $teacherId,
            'max_uren_per_week' => $maxHoursPerWeek,
            'max_uren_per_dag' => $maxHoursPerDay,
            'beschikbaarheid_json' => json_encode($availableSlots, JSON_THROW_ON_ERROR),
        ]);

        $stmt = $this->db->prepare("DELETE FROM leraar_vakken WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $teacherId]);
        $this->syncTeacherSubjects($teacherId, $schoolId, $subjectIds);
    }

    public function updateTeacher(string $teacherId, string $schoolId, string $name, string $email, bool $active): void
    {
        if (!$this->teacherBelongsToSchool($teacherId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een leraar van dezelfde school.');
        }

        $email = mb_strtolower(trim($email));
        if ($this->emailBelongsToAnotherUser($email, $teacherId)) {
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
              AND role = 'leraar'
        ");
        $stmt->execute([
            'id' => $teacherId,
            'school_id' => $schoolId,
            'email' => $email,
            'naam_encrypted' => $this->encryptor->encrypt($name),
            'naam_search_hash' => Str::searchHash($name),
            'active' => $active ? 1 : 0,
        ]);
    }

    public function deactivateTeacher(string $teacherId, string $schoolId): void
    {
        if (!$this->teacherBelongsToSchool($teacherId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een leraar van dezelfde school.');
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET active = 0,
                updated_at = NOW()
            WHERE id = :id
              AND school_id = :school_id
              AND role = 'leraar'
        ");
        $stmt->execute([
            'id' => $teacherId,
            'school_id' => $schoolId,
        ]);
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

    public function createPeriod(string $schoolYearId, string $schoolId, string $name, int $weekFrom, int $weekTo, ?int $weekFromYear = null, ?int $weekToYear = null): void
    {
        if (!$this->schoolYearBelongsToSchool($schoolYearId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een schooljaar van dezelfde school.');
        }

        [$weekFromYear, $weekToYear] = $this->normalizePeriodWeekYears($schoolYearId, $weekFrom, $weekTo, $weekFromYear, $weekToYear);

        $columns = "id, schooljaar_id, naam, week_van, week_tot, active, created_at, updated_at";
        $values = ":id, :schooljaar_id, :naam, :week_van, :week_tot, 1, NOW(), NOW()";
        $params = [
            'id' => Str::uuid(),
            'schooljaar_id' => $schoolYearId,
            'naam' => $name,
            'week_van' => $weekFrom,
            'week_tot' => $weekTo,
        ];

        if ($this->hasPeriodWeekYearColumns()) {
            $columns = "id, schooljaar_id, naam, week_van, week_van_jaar, week_tot, week_tot_jaar, active, created_at, updated_at";
            $values = ":id, :schooljaar_id, :naam, :week_van, :week_van_jaar, :week_tot, :week_tot_jaar, 1, NOW(), NOW()";
            $params['week_van_jaar'] = $weekFromYear;
            $params['week_tot_jaar'] = $weekToYear;
        }

        $stmt = $this->db->prepare("
            INSERT INTO schooljaar_periodes ({$columns})
            VALUES ({$values})
        ");
        $stmt->execute($params);
    }

    public function updatePeriod(string $periodId, string $schoolYearId, string $schoolId, string $name, int $weekFrom, int $weekTo, bool $active, ?int $weekFromYear = null, ?int $weekToYear = null): void
    {
        if (!$this->periodBelongsToSchoolYear($periodId, $schoolYearId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een periode van hetzelfde schooljaar.');
        }

        [$weekFromYear, $weekToYear] = $this->normalizePeriodWeekYears($schoolYearId, $weekFrom, $weekTo, $weekFromYear, $weekToYear);
        $yearSql = $this->hasPeriodWeekYearColumns() ? ",
                week_van_jaar = :week_van_jaar,
                week_tot_jaar = :week_tot_jaar" : '';
        $params = [
            'id' => $periodId,
            'schooljaar_id' => $schoolYearId,
            'naam' => $name,
            'week_van' => $weekFrom,
            'week_tot' => $weekTo,
            'active' => $active ? 1 : 0,
        ];

        if ($this->hasPeriodWeekYearColumns()) {
            $params['week_van_jaar'] = $weekFromYear;
            $params['week_tot_jaar'] = $weekToYear;
        }

        $stmt = $this->db->prepare("
            UPDATE schooljaar_periodes
            SET naam = :naam,
                week_van = :week_van,
                week_tot = :week_tot
                {$yearSql},
                active = :active,
                updated_at = NOW()
            WHERE id = :id
              AND schooljaar_id = :schooljaar_id
        ");
        $stmt->execute($params);
    }

    public function deletePeriod(string $periodId, string $schoolYearId, string $schoolId): void
    {
        if (!$this->periodBelongsToSchoolYear($periodId, $schoolYearId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een periode van hetzelfde schooljaar.');
        }

        $stmt = $this->db->prepare("
            DELETE FROM schooljaar_periodes
            WHERE id = :id
              AND schooljaar_id = :schooljaar_id
        ");
        $stmt->execute([
            'id' => $periodId,
            'schooljaar_id' => $schoolYearId,
        ]);
    }

    public function createSchoolYearBreak(string $schoolYearId, string $schoolId, string $name, string $type, string $startDate, string $endDate): void
    {
        if (!$this->schoolYearBelongsToSchool($schoolYearId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een schooljaar van dezelfde school.');
        }

        $type = $this->normalizeBreakType($type);
        $this->assertValidBreakDates($schoolYearId, $startDate, $endDate);

        $stmt = $this->db->prepare("
            INSERT INTO schooljaar_vrije_dagen (id, schooljaar_id, naam, type, startdatum, einddatum, active, created_at, updated_at)
            VALUES (:id, :schooljaar_id, :naam, :type, :startdatum, :einddatum, 1, NOW(), NOW())
        ");
        $stmt->execute([
            'id' => Str::uuid(),
            'schooljaar_id' => $schoolYearId,
            'naam' => $name,
            'type' => $type,
            'startdatum' => $startDate,
            'einddatum' => $endDate,
        ]);
    }

    public function updateSchoolYearBreak(string $breakId, string $schoolYearId, string $schoolId, string $name, string $type, string $startDate, string $endDate, bool $active): void
    {
        if (!$this->schoolYearBreakBelongsToSchoolYear($breakId, $schoolYearId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een vrije dag van hetzelfde schooljaar.');
        }

        $type = $this->normalizeBreakType($type);
        $this->assertValidBreakDates($schoolYearId, $startDate, $endDate);

        $stmt = $this->db->prepare("
            UPDATE schooljaar_vrije_dagen
            SET naam = :naam,
                type = :type,
                startdatum = :startdatum,
                einddatum = :einddatum,
                active = :active,
                updated_at = NOW()
            WHERE id = :id
              AND schooljaar_id = :schooljaar_id
        ");
        $stmt->execute([
            'id' => $breakId,
            'schooljaar_id' => $schoolYearId,
            'naam' => $name,
            'type' => $type,
            'startdatum' => $startDate,
            'einddatum' => $endDate,
            'active' => $active ? 1 : 0,
        ]);
    }

    public function deleteSchoolYearBreak(string $breakId, string $schoolYearId, string $schoolId): void
    {
        if (!$this->schoolYearBreakBelongsToSchoolYear($breakId, $schoolYearId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een vrije dag van hetzelfde schooljaar.');
        }

        $stmt = $this->db->prepare("
            DELETE FROM schooljaar_vrije_dagen
            WHERE id = :id
              AND schooljaar_id = :schooljaar_id
        ");
        $stmt->execute([
            'id' => $breakId,
            'schooljaar_id' => $schoolYearId,
        ]);
    }

    public function createClass(string $schoolId, string $name, ?string $schoolYearId, ?string $programId, ?int $yearLevel): string
    {
        if ($schoolYearId !== null && !$this->schoolYearBelongsToSchool($schoolYearId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een schooljaar van dezelfde school.');
        }

        if ($programId !== null && !$this->programBelongsToSchool($programId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een opleiding van dezelfde school.');
        }

        return $this->createEncrypted('klassen', [
            'school_id' => $schoolId,
            'schooljaar_id' => $schoolYearId,
            'opleiding_id' => $programId,
            'naam' => $name,
            'leerjaar' => $yearLevel,
        ]);
    }

    public function schoolYearIdByName(string $schoolId, string $name): ?string
    {
        if (trim($name) === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM schooljaren
            WHERE school_id = :school_id
              AND naam = :naam
            LIMIT 1
        ");
        $stmt->execute([
            'school_id' => $schoolId,
            'naam' => trim($name),
        ]);
        $id = $stmt->fetchColumn();

        return is_string($id) ? $id : null;
    }

    public function programIdByCodeOrName(string $schoolId, string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id, code, naam_encrypted
            FROM opleidingen
            WHERE school_id = :school_id
              AND active = 1
            ORDER BY code = :code DESC, created_at DESC
        ");
        $stmt->execute([
            'school_id' => $schoolId,
            'code' => strtoupper($value),
        ]);

        foreach ($stmt->fetchAll() as $row) {
            if (strtoupper((string) ($row['code'] ?? '')) === strtoupper($value)) {
                return (string) $row['id'];
            }

            if ($this->decrypt((string) $row['naam_encrypted']) === $value) {
                return (string) $row['id'];
            }
        }

        return null;
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

    public function periodBelongsToSchoolYear(string $periodId, string $schoolYearId, string $schoolId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM schooljaar_periodes sp
            INNER JOIN schooljaren sj ON sj.id = sp.schooljaar_id
            WHERE sp.id = :id
              AND sp.schooljaar_id = :schooljaar_id
              AND sj.school_id = :school_id
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $periodId,
            'schooljaar_id' => $schoolYearId,
            'school_id' => $schoolId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function schoolYearBreakBelongsToSchoolYear(string $breakId, string $schoolYearId, string $schoolId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM schooljaar_vrije_dagen svd
            INNER JOIN schooljaren sj ON sj.id = svd.schooljaar_id
            WHERE svd.id = :id
              AND svd.schooljaar_id = :schooljaar_id
              AND sj.school_id = :school_id
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $breakId,
            'schooljaar_id' => $schoolYearId,
            'school_id' => $schoolId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function createSubject(string $schoolId, string $name, string $code): string
    {
        return $this->createEncrypted('vakken', [
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

    public function subjectIdsByCodes(string $schoolId, array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $code): string => strtoupper(trim((string) $code)),
            $codes,
        ))));

        if ($codes === []) {
            return [];
        }

        $placeholders = [];
        $params = ['school_id' => $schoolId];
        foreach ($codes as $index => $code) {
            $key = 'code_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $code;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM vakken
            WHERE school_id = :school_id
              AND code IN (" . implode(', ', $placeholders) . ")
              AND active = 1
        ");
        $stmt->execute($params);

        return array_column($stmt->fetchAll(), 'id');
    }

    public function createProgram(string $schoolId, string $name, string $code, string $level, array $subjectIds, array $electiveSubjectIds = [], array $subjectHours = []): void
    {
        $programId = $this->createEncrypted('opleidingen', [
            'school_id' => $schoolId,
            'naam' => $name,
            'code' => $code !== '' ? strtoupper($code) : null,
            'niveau' => $level !== '' ? $level : null,
        ]);

        $this->syncProgramSubjects($programId, $schoolId, $subjectIds, $electiveSubjectIds, $subjectHours);
    }

    public function updateProgram(string $programId, string $schoolId, string $name, string $code, string $level, array $subjectIds, array $electiveSubjectIds, array $subjectHours, bool $active): void
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
        $stmt = $this->db->prepare("DELETE FROM opleiding_vak_periode_uren WHERE opleiding_id = :opleiding_id");
        $stmt->execute(['opleiding_id' => $programId]);
        $this->syncProgramSubjects($programId, $schoolId, $subjectIds, $electiveSubjectIds, $subjectHours);
    }

    public function createLocation(string $schoolId, string $name, bool $external): void
    {
        $this->createEncrypted('locaties', [
            'school_id' => $schoolId,
            'naam' => $name,
            'extern' => $external ? 1 : 0,
        ]);
    }

    public function createRoom(string $schoolId, string $locationId, string $name, ?int $capacity, array $availableSlots, array $subjectIds): void
    {
        if (!$this->locationBelongsToSchool($locationId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een locatie van dezelfde school.');
        }

        $locationIsExternal = $this->locationIsExternal($locationId, $schoolId);
        $roomId = $this->createEncrypted('lokalen', [
            'school_id' => $schoolId,
            'locatie_id' => $locationId,
            'naam' => $name,
            'capaciteit' => $capacity,
            'beschikbaarheid_json' => $locationIsExternal ? json_encode($this->normalizeAvailabilitySlots($availableSlots), JSON_THROW_ON_ERROR) : null,
        ]);

        $this->syncRoomSubjects($roomId, $schoolId, $subjectIds);
    }

    public function updateRoom(string $roomId, string $schoolId, string $locationId, string $name, ?int $capacity, array $availableSlots, array $subjectIds, bool $active): void
    {
        if (!$this->encryptedRowBelongsToSchool('lokalen', $roomId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een lokaal van dezelfde school.');
        }

        if (!$this->locationBelongsToSchool($locationId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een locatie van dezelfde school.');
        }

        $locationIsExternal = $this->locationIsExternal($locationId, $schoolId);
        $this->updateEncrypted('lokalen', $roomId, $schoolId, [
            'locatie_id' => $locationId,
            'naam' => $name,
            'capaciteit' => $capacity,
            'beschikbaarheid_json' => $locationIsExternal ? json_encode($this->normalizeAvailabilitySlots($availableSlots), JSON_THROW_ON_ERROR) : null,
            'active' => $active ? 1 : 0,
        ]);

        $stmt = $this->db->prepare("DELETE FROM lokaal_vakken WHERE lokaal_id = :lokaal_id");
        $stmt->execute(['lokaal_id' => $roomId]);
        $this->syncRoomSubjects($roomId, $schoolId, $subjectIds);
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

    private function syncProgramSubjects(string $programId, string $schoolId, array $subjectIds, array $electiveSubjectIds = [], array $subjectHours = []): void
    {
        $subjectIds = array_values(array_unique(array_filter($subjectIds, static fn (mixed $id): bool => is_string($id) && $id !== '')));
        $electiveSubjectIds = array_values(array_unique(array_filter($electiveSubjectIds, static fn (mixed $id): bool => is_string($id) && $id !== '')));

        if ($subjectIds === []) {
            return;
        }

        $validSubjectIds = $this->subjectIdsForSchool($schoolId, $subjectIds);
        $electiveSubjectIds = array_values(array_intersect($validSubjectIds, $electiveSubjectIds));
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO opleiding_vakken (opleiding_id, vak_id, uren_per_week, keuzevak, created_at)
            VALUES (:opleiding_id, :vak_id, :uren_per_week, :keuzevak, NOW())
        ");

        foreach ($validSubjectIds as $subjectId) {
            $hoursByPeriod = $this->normalHoursForSubject($subjectHours[$subjectId] ?? []);
            $stmt->execute([
                'opleiding_id' => $programId,
                'vak_id' => $subjectId,
                'uren_per_week' => $this->defaultHoursForSubject($hoursByPeriod),
                'keuzevak' => in_array($subjectId, $electiveSubjectIds, true) ? 1 : 0,
            ]);
        }

        $periodIds = $this->periodIdsForSchool($schoolId, $this->periodIdsFromHours($subjectHours));
        if ($periodIds === []) {
            return;
        }

        $periodHoursStmt = $this->db->prepare("
            INSERT INTO opleiding_vak_periode_uren (opleiding_id, vak_id, periode_id, uren_per_week, created_at, updated_at)
            VALUES (:opleiding_id, :vak_id, :periode_id, :uren_per_week, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                uren_per_week = VALUES(uren_per_week),
                updated_at = NOW()
        ");

        foreach ($validSubjectIds as $subjectId) {
            $hoursByPeriod = $this->normalHoursForSubject($subjectHours[$subjectId] ?? []);
            foreach ($periodIds as $periodId) {
                $periodHoursStmt->execute([
                    'opleiding_id' => $programId,
                    'vak_id' => $subjectId,
                    'periode_id' => $periodId,
                    'uren_per_week' => $hoursByPeriod[$periodId] ?? 0,
                ]);
            }
        }
    }

    private function syncRoomSubjects(string $roomId, string $schoolId, array $subjectIds): void
    {
        $subjectIds = array_values(array_unique(array_filter($subjectIds, static fn (mixed $id): bool => is_string($id) && $id !== '')));

        if ($subjectIds === []) {
            return;
        }

        $validSubjectIds = $this->subjectIdsForSchool($schoolId, $subjectIds);
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO lokaal_vakken (lokaal_id, vak_id, created_at)
            VALUES (:lokaal_id, :vak_id, NOW())
        ");

        foreach ($validSubjectIds as $subjectId) {
            $stmt->execute([
                'lokaal_id' => $roomId,
                'vak_id' => $subjectId,
            ]);
        }
    }

    private function syncTeacherSubjects(string $teacherId, string $schoolId, array $subjectIds): void
    {
        $subjectIds = array_values(array_unique(array_filter($subjectIds, static fn (mixed $id): bool => is_string($id) && $id !== '')));

        if ($subjectIds === []) {
            return;
        }

        $validSubjectIds = $this->subjectIdsForSchool($schoolId, $subjectIds);
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO leraar_vakken (user_id, vak_id, created_at)
            VALUES (:user_id, :vak_id, NOW())
        ");

        foreach ($validSubjectIds as $subjectId) {
            $stmt->execute([
                'user_id' => $teacherId,
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

    private function periodIdsForSchool(string $schoolId, array $periodIds): array
    {
        $periodIds = array_values(array_unique(array_filter($periodIds, static fn (mixed $id): bool => is_string($id) && $id !== '')));

        if ($periodIds === []) {
            return [];
        }

        $placeholders = [];
        $params = ['school_id' => $schoolId];

        foreach ($periodIds as $index => $periodId) {
            $key = 'period_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $periodId;
        }

        $stmt = $this->db->prepare("
            SELECT sp.id
            FROM schooljaar_periodes sp
            INNER JOIN schooljaren sj ON sj.id = sp.schooljaar_id
            WHERE sj.school_id = :school_id
              AND sp.id IN (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($params);

        return array_column($stmt->fetchAll(), 'id');
    }

    private function periodIdsFromHours(array $subjectHours): array
    {
        $periodIds = [];
        foreach ($subjectHours as $hoursByPeriod) {
            if (is_array($hoursByPeriod)) {
                $periodIds = array_merge($periodIds, array_keys($hoursByPeriod));
            }
        }

        return array_values(array_unique(array_filter($periodIds, static fn (mixed $id): bool => is_string($id) && $id !== '')));
    }

    private function normalHoursForSubject(mixed $hoursByPeriod): array
    {
        if (!is_array($hoursByPeriod)) {
            return [];
        }

        $normalized = [];
        foreach ($hoursByPeriod as $periodId => $hours) {
            if (!is_string($periodId) || $periodId === '') {
                continue;
            }

            $normalized[$periodId] = max(0, min(40, (int) $hours));
        }

        return $normalized;
    }

    private function defaultHoursForSubject(array $hoursByPeriod): int
    {
        $positiveHours = array_values(array_filter($hoursByPeriod, static fn (int $hours): bool => $hours > 0));

        return $positiveHours !== [] ? (int) $positiveHours[0] : 0;
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
            SELECT
                ov.opleiding_id,
                v.id,
                v.naam_encrypted,
                v.code,
                ov.uren_per_week,
                ov.keuzevak,
                GROUP_CONCAT(CONCAT(oph.periode_id, ':', oph.uren_per_week) SEPARATOR ',') AS periode_uren
            FROM opleiding_vakken ov
            INNER JOIN vakken v ON v.id = ov.vak_id
            LEFT JOIN opleiding_vak_periode_uren oph ON oph.opleiding_id = ov.opleiding_id AND oph.vak_id = ov.vak_id
            WHERE ov.opleiding_id IN (" . implode(', ', $placeholders) . ")
            GROUP BY ov.opleiding_id, v.id, v.naam_encrypted, v.code, ov.uren_per_week, ov.keuzevak
            ORDER BY v.code IS NULL, v.code, v.created_at
        ");
        $stmt->execute($params);
        $subjects = [];

        foreach ($stmt->fetchAll() as $row) {
            $subjects[(string) $row['opleiding_id']][] = [
                'id' => $row['id'],
                'naam' => $this->decrypt((string) $row['naam_encrypted']),
                'code' => $row['code'],
                'uren_per_week' => (int) ($row['uren_per_week'] ?? 0),
                'keuzevak' => (int) ($row['keuzevak'] ?? 0) === 1,
                'periode_uren' => $this->parsePeriodHours((string) ($row['periode_uren'] ?? '')),
            ];
        }

        return $subjects;
    }

    private function parsePeriodHours(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $hours = [];
        foreach (explode(',', $value) as $entry) {
            [$periodId, $amount] = array_pad(explode(':', $entry, 2), 2, null);
            if (is_string($periodId) && $periodId !== '' && is_numeric($amount)) {
                $hours[$periodId] = (int) $amount;
            }
        }

        return $hours;
    }

    private function subjectsByRoom(array $roomIds): array
    {
        $roomIds = array_values(array_filter($roomIds, static fn (mixed $id): bool => is_string($id) && $id !== ''));

        if ($roomIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($roomIds as $index => $roomId) {
            $key = 'room_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $roomId;
        }

        $stmt = $this->db->prepare("
            SELECT lv.lokaal_id, v.id, v.naam_encrypted, v.code
            FROM lokaal_vakken lv
            INNER JOIN vakken v ON v.id = lv.vak_id
            WHERE lv.lokaal_id IN (" . implode(', ', $placeholders) . ")
            ORDER BY v.code IS NULL, v.code, v.created_at
        ");
        $stmt->execute($params);
        $subjects = [];

        foreach ($stmt->fetchAll() as $row) {
            $subjects[(string) $row['lokaal_id']][] = [
                'id' => $row['id'],
                'naam' => $this->decrypt((string) $row['naam_encrypted']),
                'code' => $row['code'],
            ];
        }

        return $subjects;
    }

    private function subjectsByTeacher(array $teacherIds): array
    {
        $teacherIds = array_values(array_filter($teacherIds, static fn (mixed $id): bool => is_string($id) && $id !== ''));

        if ($teacherIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($teacherIds as $index => $teacherId) {
            $key = 'teacher_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $teacherId;
        }

        $stmt = $this->db->prepare("
            SELECT lv.user_id, v.id, v.naam_encrypted, v.code
            FROM leraar_vakken lv
            INNER JOIN vakken v ON v.id = lv.vak_id
            WHERE lv.user_id IN (" . implode(', ', $placeholders) . ")
            ORDER BY v.code IS NULL, v.code, v.created_at
        ");
        $stmt->execute($params);
        $subjects = [];

        foreach ($stmt->fetchAll() as $row) {
            $subjects[(string) $row['user_id']][] = [
                'id' => $row['id'],
                'naam' => $this->decrypt((string) $row['naam_encrypted']),
                'code' => $row['code'],
            ];
        }

        return $subjects;
    }

    private function teacherBelongsToSchool(string $teacherId, string $schoolId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM users
            WHERE id = :id
              AND school_id = :school_id
              AND role = 'leraar'
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $teacherId,
            'school_id' => $schoolId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function locationBelongsToSchool(string $locationId, string $schoolId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM locaties
            WHERE id = :id
              AND school_id = :school_id
              AND active = 1
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $locationId,
            'school_id' => $schoolId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function locationIsExternal(string $locationId, string $schoolId): bool
    {
        $stmt = $this->db->prepare("
            SELECT extern
            FROM locaties
            WHERE id = :id
              AND school_id = :school_id
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $locationId,
            'school_id' => $schoolId,
        ]);

        return (int) $stmt->fetchColumn() === 1;
    }

    private function emailBelongsToAnotherUser(string $email, string $teacherId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM users
            WHERE email = :email
              AND id <> :id
            LIMIT 1
        ");
        $stmt->execute([
            'email' => $email,
            'id' => $teacherId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function normalizeAvailabilitySlots(array $availableSlots): array
    {
        $allowed = [];
        foreach (['ma', 'di', 'wo', 'do', 'vr'] as $day) {
            for ($period = 1; $period <= 9; $period++) {
                $allowed[] = $day . '-' . $period;
            }
        }

        return array_values(array_intersect(
            $allowed,
            array_values(array_unique(array_filter($availableSlots, static fn (mixed $slot): bool => is_string($slot) && $slot !== ''))),
        ));
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

    private function assertValidPeriodWeeks(int $weekFrom, int $weekTo): void
    {
        if ($weekFrom < 1 || $weekFrom > 53 || $weekTo < 1 || $weekTo > 53) {
            throw new \InvalidArgumentException('Weeknummers moeten tussen 1 en 53 liggen.');
        }

    }

    private function normalizeBreakType(string $type): string
    {
        return in_array($type, ['vrije_dag', 'vakantie'], true) ? $type : 'vrije_dag';
    }

    private function assertValidBreakDates(string $schoolYearId, string $startDate, string $endDate): void
    {
        if (!$this->validDate($startDate) || !$this->validDate($endDate)) {
            throw new \InvalidArgumentException('Gebruik geldige datums.');
        }

        if ($endDate < $startDate) {
            throw new \InvalidArgumentException('De einddatum moet na de startdatum liggen.');
        }

        $schoolYear = $this->schoolYearDates($schoolYearId);
        if ($startDate < (string) $schoolYear['startdatum'] || $endDate > (string) $schoolYear['einddatum']) {
            throw new \InvalidArgumentException('Vrije dagen moeten binnen het schooljaar vallen.');
        }
    }

    private function validDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed instanceof \DateTimeImmutable && $parsed->format('Y-m-d') === $date;
    }

    private function normalizePeriodWeekYears(string $schoolYearId, int $weekFrom, int $weekTo, ?int $weekFromYear, ?int $weekToYear): array
    {
        $this->assertValidPeriodWeeks($weekFrom, $weekTo);
        $options = $this->weekIndexMapForSchoolYear($schoolYearId);

        if ($weekFromYear === null || $weekToYear === null) {
            $years = $this->inferPeriodYears($schoolYearId, $weekFrom, $weekTo);
            $weekFromYear ??= $years[0];
            $weekToYear ??= $years[1];
        }

        $fromKey = sprintf('%04d-%02d', $weekFromYear, $weekFrom);
        $toKey = sprintf('%04d-%02d', $weekToYear, $weekTo);

        if (!isset($options[$fromKey], $options[$toKey])) {
            throw new \InvalidArgumentException('Kies weken die binnen het schooljaar vallen.');
        }

        if ($options[$fromKey] > $options[$toKey]) {
            throw new \InvalidArgumentException('De eindweek moet na de startweek liggen binnen het schooljaar.');
        }

        return [$weekFromYear, $weekToYear];
    }

    private function inferPeriodYears(string $schoolYearId, int $weekFrom, int $weekTo): array
    {
        $schoolYear = $this->schoolYearDates($schoolYearId);
        $startWeek = (int) (new \DateTimeImmutable((string) $schoolYear['startdatum']))->format('W');
        $startYear = (int) (new \DateTimeImmutable((string) $schoolYear['startdatum']))->format('o');
        $endYear = (int) (new \DateTimeImmutable((string) $schoolYear['einddatum']))->format('o');

        return [
            $weekFrom >= $startWeek ? $startYear : $endYear,
            $weekTo >= $startWeek ? $startYear : $endYear,
        ];
    }

    private function weekIndexMapForSchoolYear(string $schoolYearId): array
    {
        $schoolYear = $this->schoolYearDates($schoolYearId);
        $start = new \DateTimeImmutable((string) $schoolYear['startdatum']);
        $end = new \DateTimeImmutable((string) $schoolYear['einddatum']);
        $cursor = $start->setISODate((int) $start->format('o'), (int) $start->format('W'), 1);
        $last = $end->setISODate((int) $end->format('o'), (int) $end->format('W'), 1);
        $weeks = [];
        $index = 0;

        while ($cursor <= $last) {
            $weeks[$cursor->format('o-W')] = $index++;
            $cursor = $cursor->modify('+1 week');
        }

        return $weeks;
    }

    private function schoolYearDates(string $schoolYearId): array
    {
        $stmt = $this->db->prepare("SELECT startdatum, einddatum FROM schooljaren WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $schoolYearId]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            throw new \InvalidArgumentException('Schooljaar niet gevonden.');
        }

        return $row;
    }

    private function hasPeriodWeekYearColumns(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM schooljaar_periodes LIKE 'week_van_jaar'");
            $exists = (bool) $stmt->fetch();
        } catch (\Throwable) {
            $exists = false;
        }

        return $exists;
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
