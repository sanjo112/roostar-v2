<?php

declare(strict_types=1);

namespace Roostar\Modules\TestPlanning\Repositories;

use PDO;
use Roostar\Core\Auth\UserContext;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;

final class TestPlanningRepository
{
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
            ORDER BY sj.active DESC, sj.startdatum DESC
        ");
        $stmt->execute($params);

        return array_map(fn (array $row): array => [
            ...$row,
            'school_naam' => $this->decrypt((string) $row['school_naam_encrypted']),
        ], $stmt->fetchAll());
    }

    public function testWeeksFor(UserContext $user, ?string $schoolYearId): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $whereSchoolYear = '';
        if ($schoolYearId !== null && $schoolYearId !== '') {
            $whereSchoolYear = 'AND tw.schooljaar_id = :schooljaar_id';
            $params['schooljaar_id'] = $schoolYearId;
        }

        $stmt = $this->db->prepare("
            SELECT
                tw.*,
                sj.naam AS schooljaar_naam,
                sp.naam AS periode_naam,
                s.naam_encrypted AS school_naam_encrypted,
                COUNT(t.id) AS toetsen_count
            FROM toetsweken tw
            INNER JOIN schooljaren sj ON sj.id = tw.schooljaar_id
            INNER JOIN scholen s ON s.id = tw.school_id
            LEFT JOIN schooljaar_periodes sp ON sp.id = tw.periode_id
            LEFT JOIN toetsen t ON t.toetsweek_id = tw.id
            WHERE {$scopeSql}
              {$whereSchoolYear}
            GROUP BY tw.id
            ORDER BY sj.startdatum DESC, tw.week_nummer
        ");
        $stmt->execute($params);

        return array_map(fn (array $row): array => [
            ...$row,
            'school_naam' => $this->decrypt((string) $row['school_naam_encrypted']),
        ], $stmt->fetchAll());
    }

    public function testsFor(UserContext $user, ?string $testWeekId): array
    {
        if ($testWeekId === null || $testWeekId === '' || !$this->testWeekVisible($user, $testWeekId)) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                t.*,
                v.naam_encrypted AS vak_naam_encrypted,
                v.code AS vak_code,
                o.naam_encrypted AS opleiding_naam_encrypted,
                l.naam_encrypted AS lokaal_naam_encrypted,
                GROUP_CONCAT(DISTINCT CONCAT(u.id, '|', u.naam_encrypted, '|', ts.voorstel) ORDER BY u.naam_search_hash SEPARATOR ';;') AS surveillance
            FROM toetsen t
            INNER JOIN vakken v ON v.id = t.vak_id
            LEFT JOIN opleidingen o ON o.id = t.opleiding_id
            LEFT JOIN lokalen l ON l.id = t.lokaal_id
            LEFT JOIN toets_surveillance ts ON ts.toets_id = t.id
            LEFT JOIN users u ON u.id = ts.leraar_id
            WHERE t.toetsweek_id = :toetsweek_id
            GROUP BY t.id
            ORDER BY t.datum IS NULL, t.datum, t.tijdslot, v.code, t.naam
        ");
        $stmt->execute(['toetsweek_id' => $testWeekId]);

        return array_map(function (array $row): array {
            $surveillance = [];
            foreach (array_filter(explode(';;', (string) ($row['surveillance'] ?? ''))) as $item) {
                [$id, $encryptedName, $proposal] = array_pad(explode('|', $item), 3, '');
                if ($id !== '') {
                    $surveillance[] = [
                        'id' => $id,
                        'naam' => $this->decrypt($encryptedName),
                        'voorstel' => $proposal === '1',
                    ];
                }
            }

            return [
                ...$row,
                'vak_naam' => $this->decrypt((string) $row['vak_naam_encrypted']),
                'opleiding_naam' => !empty($row['opleiding_naam_encrypted']) ? $this->decrypt((string) $row['opleiding_naam_encrypted']) : '',
                'lokaal_naam' => !empty($row['lokaal_naam_encrypted']) ? $this->decrypt((string) $row['lokaal_naam_encrypted']) : '',
                'surveillance' => $surveillance,
            ];
        }, $stmt->fetchAll());
    }

    public function metaFor(UserContext $user): array
    {
        return [
            'subjects' => $this->encryptedRows($user, 'vakken', 'v', 'ORDER BY v.code IS NULL, v.code, v.naam_search_hash'),
            'programs' => $this->encryptedRows($user, 'opleidingen', 'o', 'ORDER BY o.naam_search_hash'),
            'rooms' => $this->encryptedRows($user, 'lokalen', 'l', 'ORDER BY l.naam_search_hash'),
            'teachers' => $this->teachersFor($user),
        ];
    }

    public function periodsForSchoolYear(UserContext $user, ?string $schoolYearId): array
    {
        if ($schoolYearId === null || $schoolYearId === '') {
            return [];
        }

        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $params['schooljaar_id'] = $schoolYearId;
        $stmt = $this->db->prepare("
            SELECT sp.*
            FROM schooljaar_periodes sp
            INNER JOIN schooljaren sj ON sj.id = sp.schooljaar_id
            INNER JOIN scholen s ON s.id = sj.school_id
            WHERE {$scopeSql}
              AND sp.schooljaar_id = :schooljaar_id
            ORDER BY COALESCE(sp.week_van_jaar, YEAR(sj.startdatum)), sp.week_van, sp.naam
        ");
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function createTestWeek(UserContext $user, string $schoolId, string $schoolYearId, string $name, int $week, int $lessonPercentage, bool $shortRoster, ?int $lessonsPerDay): string
    {
        if (!$this->schoolYearBelongsToSchool($schoolYearId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een schooljaar van dezelfde school.');
        }

        $this->validateWeek($week);
        $lessonPercentage = max(0, min(100, $lessonPercentage));
        $periodId = $this->periodIdForWeek($schoolYearId, $week);

        if ($this->testWeekExists($schoolId, $schoolYearId, $week)) {
            throw new \InvalidArgumentException('Deze week is al ingericht als toetsweek.');
        }

        $id = Str::uuid();
        $stmt = $this->db->prepare("
            INSERT INTO toetsweken (
                id, school_id, schooljaar_id, periode_id, naam, week_nummer, les_percentage, verkort_rooster, lesuren_per_dag, active, created_at, updated_at
            ) VALUES (
                :id, :school_id, :schooljaar_id, :periode_id, :naam, :week_nummer, :les_percentage, :verkort_rooster, :lesuren_per_dag, 1, NOW(), NOW()
            )
        ");
        $stmt->execute([
            'id' => $id,
            'school_id' => $schoolId,
            'schooljaar_id' => $schoolYearId,
            'periode_id' => $periodId,
            'naam' => $name,
            'week_nummer' => $week,
            'les_percentage' => $lessonPercentage,
            'verkort_rooster' => $shortRoster ? 1 : 0,
            'lesuren_per_dag' => $lessonsPerDay,
        ]);

        return $id;
    }

    public function updateTestWeek(UserContext $user, string $id, string $name, int $lessonPercentage, bool $shortRoster, ?int $lessonsPerDay, bool $active): void
    {
        if (!$this->testWeekVisible($user, $id)) {
            throw new \InvalidArgumentException('Toetsweek niet gevonden.');
        }

        $stmt = $this->db->prepare("
            UPDATE toetsweken
            SET naam = :naam,
                les_percentage = :les_percentage,
                verkort_rooster = :verkort_rooster,
                lesuren_per_dag = :lesuren_per_dag,
                active = :active,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'naam' => $name,
            'les_percentage' => max(0, min(100, $lessonPercentage)),
            'verkort_rooster' => $shortRoster ? 1 : 0,
            'lesuren_per_dag' => $lessonsPerDay,
            'active' => $active ? 1 : 0,
        ]);
    }

    public function deleteTestWeek(UserContext $user, string $id): void
    {
        if (!$this->testWeekVisible($user, $id)) {
            throw new \InvalidArgumentException('Toetsweek niet gevonden.');
        }

        $testIds = array_column($this->testsFor($user, $id), 'id');
        if ($testIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($testIds), '?'));
            $this->db->prepare("DELETE FROM toets_surveillance WHERE toets_id IN ({$placeholders})")->execute($testIds);
            $this->db->prepare("DELETE FROM toetsen WHERE id IN ({$placeholders})")->execute($testIds);
        }

        $stmt = $this->db->prepare("DELETE FROM toetsweken WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function saveTest(UserContext $user, ?string $id, string $testWeekId, string $subjectId, ?string $programId, string $name, ?string $date, string $slot, int $duration, ?string $roomId, int $surveillanceCount): string
    {
        $testWeek = $this->testWeekRow($user, $testWeekId);
        if ($testWeek === null) {
            throw new \InvalidArgumentException('Toetsweek niet gevonden.');
        }

        $schoolId = (string) $testWeek['school_id'];
        if (!$this->recordBelongsToSchool('vakken', $subjectId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een vak van deze school.');
        }

        if ($programId !== null && !$this->recordBelongsToSchool('opleidingen', $programId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een opleiding van deze school.');
        }

        if ($roomId !== null && !$this->recordBelongsToSchool('lokalen', $roomId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een lokaal van deze school.');
        }

        $params = [
            'toetsweek_id' => $testWeekId,
            'vak_id' => $subjectId,
            'opleiding_id' => $programId,
            'naam' => $name,
            'datum' => $date,
            'tijdslot' => $slot,
            'duur_minuten' => max(10, min(240, $duration)),
            'lokaal_id' => $roomId,
            'aantal_surveillance' => max(1, min(10, $surveillanceCount)),
        ];

        if ($id === null || $id === '') {
            $id = Str::uuid();
            $stmt = $this->db->prepare("
                INSERT INTO toetsen (
                    id, toetsweek_id, vak_id, opleiding_id, naam, datum, tijdslot, duur_minuten, lokaal_id, aantal_surveillance, created_at, updated_at
                ) VALUES (
                    :id, :toetsweek_id, :vak_id, :opleiding_id, :naam, :datum, :tijdslot, :duur_minuten, :lokaal_id, :aantal_surveillance, NOW(), NOW()
                )
            ");
            $stmt->execute(['id' => $id, ...$params]);
        } else {
            if (!$this->testVisible($user, $id)) {
                throw new \InvalidArgumentException('Toets niet gevonden.');
            }

            $stmt = $this->db->prepare("
                UPDATE toetsen
                SET toetsweek_id = :toetsweek_id,
                    vak_id = :vak_id,
                    opleiding_id = :opleiding_id,
                    naam = :naam,
                    datum = :datum,
                    tijdslot = :tijdslot,
                    duur_minuten = :duur_minuten,
                    lokaal_id = :lokaal_id,
                    aantal_surveillance = :aantal_surveillance,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id, ...$params]);
        }

        return $id;
    }

    public function deleteTest(UserContext $user, string $id): void
    {
        if (!$this->testVisible($user, $id)) {
            throw new \InvalidArgumentException('Toets niet gevonden.');
        }

        $this->db->prepare("DELETE FROM toets_surveillance WHERE toets_id = :id")->execute(['id' => $id]);
        $this->db->prepare("DELETE FROM toetsen WHERE id = :id")->execute(['id' => $id]);
    }

    public function saveSurveillance(UserContext $user, string $testId, array $teacherIds, bool $proposal = false): void
    {
        $testWeek = $this->testWeekForTest($user, $testId);
        if ($testWeek === null) {
            throw new \InvalidArgumentException('Toets niet gevonden.');
        }

        $schoolId = (string) $testWeek['school_id'];
        $teacherIds = array_values(array_unique(array_filter($teacherIds, static fn (mixed $id): bool => is_string($id) && $id !== '')));

        foreach ($teacherIds as $teacherId) {
            if (!$this->teacherBelongsToSchool($teacherId, $schoolId)) {
                throw new \InvalidArgumentException('Kies surveillanten van deze school.');
            }
        }

        $this->db->prepare("DELETE FROM toets_surveillance WHERE toets_id = :id")->execute(['id' => $testId]);
        $stmt = $this->db->prepare("
            INSERT INTO toets_surveillance (toets_id, leraar_id, voorstel, created_at)
            VALUES (:toets_id, :leraar_id, :voorstel, NOW())
        ");

        foreach ($teacherIds as $teacherId) {
            $stmt->execute([
                'toets_id' => $testId,
                'leraar_id' => $teacherId,
                'voorstel' => $proposal ? 1 : 0,
            ]);
        }
    }

    public function proposeSurveillance(UserContext $user, string $testId): array
    {
        $test = $this->testRow($user, $testId);
        if ($test === null) {
            throw new \InvalidArgumentException('Toets niet gevonden.');
        }

        $busyTeachers = $this->busyTeachers((string) $test['periode_id'], (string) $test['tijdslot']);
        $teachers = $this->teachersForSchool((string) $test['school_id']);
        $proposal = [];

        foreach ($teachers as $teacher) {
            if (isset($busyTeachers[(string) $teacher['id']])) {
                continue;
            }

            if (!$this->teacherAvailable($teacher, (string) $test['tijdslot'])) {
                continue;
            }

            $proposal[] = (string) $teacher['id'];
            if (count($proposal) >= (int) $test['aantal_surveillance']) {
                break;
            }
        }

        $this->saveSurveillance($user, $testId, $proposal, true);

        return $proposal;
    }

    private function teachersFor(UserContext $user): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.naam_encrypted, u.school_id
            FROM users u
            INNER JOIN scholen s ON s.id = u.school_id
            WHERE {$scopeSql}
              AND u.role = 'leraar'
              AND u.active = 1
            ORDER BY u.naam_search_hash, u.email
        ");
        $stmt->execute($params);

        return array_map(fn (array $row): array => [
            ...$row,
            'naam' => $this->decrypt((string) $row['naam_encrypted']),
        ], $stmt->fetchAll());
    }

    private function encryptedRows(UserContext $user, string $table, string $alias, string $order): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT {$alias}.*, s.naam_encrypted AS school_naam_encrypted
            FROM {$table} {$alias}
            INNER JOIN scholen s ON s.id = {$alias}.school_id
            WHERE {$scopeSql}
              AND {$alias}.active = 1
            {$order}
        ");
        $stmt->execute($params);

        return array_map(fn (array $row): array => [
            ...$row,
            'naam' => $this->decrypt((string) $row['naam_encrypted']),
            'school_naam' => $this->decrypt((string) $row['school_naam_encrypted']),
        ], $stmt->fetchAll());
    }

    private function periodIdForWeek(string $schoolYearId, int $week): ?string
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM schooljaar_periodes
            WHERE schooljaar_id = :schooljaar_id
              AND (
                (week_van <= week_tot AND :week_a BETWEEN week_van AND week_tot)
                OR (week_van > week_tot AND (:week_b >= week_van OR :week_c <= week_tot))
              )
            ORDER BY week_van
            LIMIT 1
        ");
        $stmt->execute([
            'schooljaar_id' => $schoolYearId,
            'week_a' => $week,
            'week_b' => $week,
            'week_c' => $week,
        ]);

        $periodId = $stmt->fetchColumn();

        return is_string($periodId) ? $periodId : null;
    }

    private function busyTeachers(string $periodId, string $slot): array
    {
        if ($periodId === '') {
            return [];
        }

        [$day, $period] = array_pad(explode('-', $slot, 2), 2, '');
        $dayMap = ['ma' => 'Maandag', 'di' => 'Dinsdag', 'wo' => 'Woensdag', 'do' => 'Donderdag', 'vr' => 'Vrijdag'];
        $stmt = $this->db->prepare("
            SELECT DISTINCT rl.leraar_id
            FROM rooster_lessen rl
            INNER JOIN roosters r ON r.id = rl.rooster_id
            WHERE r.periode_id = :periode_id
              AND rl.dag = :dag
              AND rl.lesuur = :lesuur
        ");
        $stmt->execute([
            'periode_id' => $periodId,
            'dag' => $dayMap[$day] ?? $day,
            'lesuur' => (int) $period,
        ]);

        return array_flip(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    private function teachersForSchool(string $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.naam_encrypted, lp.beschikbaarheid_json
            FROM users u
            LEFT JOIN leraar_profielen lp ON lp.user_id = u.id
            WHERE u.school_id = :school_id
              AND u.role = 'leraar'
              AND u.active = 1
            ORDER BY u.naam_search_hash, u.email
        ");
        $stmt->execute(['school_id' => $schoolId]);

        return $stmt->fetchAll();
    }

    private function teacherAvailable(array $teacher, string $slot): bool
    {
        $availability = !empty($teacher['beschikbaarheid_json']) ? json_decode((string) $teacher['beschikbaarheid_json'], true) : null;

        return !is_array($availability) || $availability === [] || in_array($slot, $availability, true);
    }

    private function validateWeek(int $week): void
    {
        if ($week < 1 || $week > 53) {
            throw new \InvalidArgumentException('Weeknummer moet tussen 1 en 53 liggen.');
        }
    }

    private function testWeekExists(string $schoolId, string $schoolYearId, int $week): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM toetsweken
            WHERE school_id = :school_id
              AND schooljaar_id = :schooljaar_id
              AND week_nummer = :week
            LIMIT 1
        ");
        $stmt->execute(['school_id' => $schoolId, 'schooljaar_id' => $schoolYearId, 'week' => $week]);

        return (bool) $stmt->fetchColumn();
    }

    private function schoolYearBelongsToSchool(string $schoolYearId, string $schoolId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM schooljaren WHERE id = :id AND school_id = :school_id LIMIT 1");
        $stmt->execute(['id' => $schoolYearId, 'school_id' => $schoolId]);

        return (bool) $stmt->fetchColumn();
    }

    private function recordBelongsToSchool(string $table, ?string $id, string $schoolId): bool
    {
        if ($id === null || $id === '') {
            return true;
        }

        $stmt = $this->db->prepare("SELECT 1 FROM {$table} WHERE id = :id AND school_id = :school_id LIMIT 1");
        $stmt->execute(['id' => $id, 'school_id' => $schoolId]);

        return (bool) $stmt->fetchColumn();
    }

    private function teacherBelongsToSchool(string $id, string $schoolId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE id = :id AND school_id = :school_id AND role = 'leraar' LIMIT 1");
        $stmt->execute(['id' => $id, 'school_id' => $schoolId]);

        return (bool) $stmt->fetchColumn();
    }

    private function testWeekVisible(UserContext $user, string $id): bool
    {
        return $this->testWeekRow($user, $id) !== null;
    }

    private function testVisible(UserContext $user, string $id): bool
    {
        return $this->testWeekForTest($user, $id) !== null;
    }

    private function testWeekRow(UserContext $user, string $id): ?array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $params['id'] = $id;
        $stmt = $this->db->prepare("
            SELECT tw.*
            FROM toetsweken tw
            INNER JOIN scholen s ON s.id = tw.school_id
            WHERE {$scopeSql}
              AND tw.id = :id
            LIMIT 1
        ");
        $stmt->execute($params);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function testWeekForTest(UserContext $user, string $id): ?array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $params['id'] = $id;
        $stmt = $this->db->prepare("
            SELECT tw.*
            FROM toetsen t
            INNER JOIN toetsweken tw ON tw.id = t.toetsweek_id
            INNER JOIN scholen s ON s.id = tw.school_id
            WHERE {$scopeSql}
              AND t.id = :id
            LIMIT 1
        ");
        $stmt->execute($params);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function testRow(UserContext $user, string $id): ?array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $params['id'] = $id;
        $stmt = $this->db->prepare("
            SELECT t.*, tw.school_id, tw.periode_id
            FROM toetsen t
            INNER JOIN toetsweken tw ON tw.id = t.toetsweek_id
            INNER JOIN scholen s ON s.id = tw.school_id
            WHERE {$scopeSql}
              AND t.id = :id
            LIMIT 1
        ");
        $stmt->execute($params);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
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
}
