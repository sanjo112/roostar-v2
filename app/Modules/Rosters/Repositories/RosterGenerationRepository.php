<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Repositories;

use PDO;
use Roostar\Core\Auth\UserContext;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;

final class RosterGenerationRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function constraintsForSchoolYear(UserContext $user, string $schoolYearId, ?string $periodId = null): array
    {
        $classes = $this->classesForSchoolYear($user, $schoolYearId);

        if ($classes === []) {
            throw new \InvalidArgumentException('Er zijn geen actieve klassen binnen dit schooljaar.');
        }

        $schoolIds = array_values(array_unique(array_map(static fn (array $class): string => (string) $class['school_id'], $classes)));

        if (count($schoolIds) !== 1) {
            throw new \InvalidArgumentException('Genereer per school. Dit schooljaar bevat klassen uit meerdere scholen.');
        }

        $lessonGroups = [];
        foreach ($classes as $class) {
            $lessonGroups = array_merge($lessonGroups, $this->lessonGroupsForClass($class, $periodId));
        }

        return [
            'schoolYear' => $this->schoolYear($schoolYearId),
            'classes' => $classes,
            'schoolId' => $schoolIds[0],
            'lessonGroups' => $lessonGroups,
            'teachers' => $this->teachersForSchool($schoolIds[0]),
            'rooms' => $this->roomsForSchool($schoolIds[0]),
            'conflictMatrix' => [],
        ];
    }

    public function constraintsForPeriod(UserContext $user, string $periodId): array
    {
        $period = $this->periodForUser($user, $periodId);

        if ($period === null) {
            throw new \InvalidArgumentException('Kies een geldige periode.');
        }

        $constraints = $this->constraintsForSchoolYear($user, (string) $period['schooljaar_id'], (string) $period['id']);
        $constraints['period'] = $period;

        return $constraints;
    }

    public function saveGeneratedRosters(array $constraints, array $result, string $userId): array
    {
        $classById = [];
        foreach ($constraints['classes'] ?? [] as $class) {
            $classById[(string) $class['id']] = $class;
        }
        $lessonsByClass = [];
        foreach ($result['lessons'] ?? [] as $lesson) {
            $lessonsByClass[(string) $lesson['lessonGroup']['classId']][] = $lesson;
        }

        $stmt = $this->db->prepare("
            INSERT INTO roosters (
                id, school_id, schooljaar_id, periode_id, klas_id, status, generated_by, opmerkingen_json, created_at, updated_at
            ) VALUES (
                :id, :school_id, :schooljaar_id, :periode_id, :klas_id, 'concept', :generated_by, :opmerkingen_json, NOW(), NOW()
            )
        ");

        $insert = $this->db->prepare("
            INSERT INTO rooster_lessen (
                id, rooster_id, klas_id, vak_id, leraar_id, lokaal_id, dag, lesuur, starttijd, eindtijd, created_at
            ) VALUES (
                :id, :rooster_id, :klas_id, :vak_id, :leraar_id, :lokaal_id, :dag, :lesuur, :starttijd, :eindtijd, NOW()
            )
        ");
        $rosterIds = [];

        foreach ($classById as $classId => $class) {
            $classLessons = $lessonsByClass[$classId] ?? [];

            if ($classLessons === []) {
                continue;
            }

            $roosterId = Str::uuid();
            $stmt->execute([
                'id' => $roosterId,
                'school_id' => $class['school_id'],
                'schooljaar_id' => $class['schooljaar_id'],
                'periode_id' => $constraints['period']['id'] ?? null,
                'klas_id' => $class['id'],
                'generated_by' => $userId,
                'opmerkingen_json' => json_encode($result['issues'] ?? [], JSON_THROW_ON_ERROR),
            ]);

            foreach ($classLessons as $lesson) {
                $insert->execute([
                    'id' => Str::uuid(),
                    'rooster_id' => $roosterId,
                    'klas_id' => $class['id'],
                    'vak_id' => $lesson['lessonGroup']['subject']['id'],
                    'leraar_id' => $lesson['teacher']['id'],
                    'lokaal_id' => $lesson['room']['id'],
                    'dag' => $lesson['slot']['day'],
                    'lesuur' => $lesson['slot']['period'],
                    'starttijd' => $lesson['slot']['start'],
                    'eindtijd' => $lesson['slot']['end'],
                ]);
            }

            $rosterIds[$classId] = $roosterId;
        }

        return $rosterIds;
    }

    public function latestSavedRosterForPeriod(UserContext $user, string $periodId): ?array
    {
        $constraints = $this->constraintsForPeriod($user, $periodId);
        $classIds = array_map(static fn (array $class): string => (string) $class['id'], $constraints['classes'] ?? []);

        if ($classIds === []) {
            return null;
        }

        $placeholders = [];
        $params = ['periode_id' => $periodId];

        foreach ($classIds as $index => $classId) {
            $key = 'class_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $classId;
        }

        $rosterStmt = $this->db->prepare("
            SELECT r.*
            FROM roosters r
            WHERE r.periode_id = :periode_id
              AND r.klas_id IN (" . implode(', ', $placeholders) . ")
              AND r.created_at = (
                  SELECT MAX(r2.created_at)
                  FROM roosters r2
                  WHERE r2.periode_id = r.periode_id
                    AND r2.klas_id = r.klas_id
              )
            ORDER BY r.created_at DESC
        ");
        $rosterStmt->execute($params);
        $rosters = $rosterStmt->fetchAll();

        if ($rosters === []) {
            return null;
        }

        $rosterIds = [];
        $issues = [];
        foreach ($rosters as $roster) {
            $rosterIds[(string) $roster['klas_id']] = (string) $roster['id'];
            $decoded = json_decode((string) ($roster['opmerkingen_json'] ?? '[]'), true);
            if (is_array($decoded)) {
                $issues = array_merge($issues, $decoded);
            }
        }

        $lessonPlaceholders = [];
        $lessonParams = [];
        foreach (array_values($rosterIds) as $index => $rosterId) {
            $key = 'roster_' . $index;
            $lessonPlaceholders[] = ':' . $key;
            $lessonParams[$key] = $rosterId;
        }

        $lessonStmt = $this->db->prepare("
            SELECT
                rl.*,
                v.naam_encrypted AS vak_naam_encrypted,
                v.code AS vak_code,
                u.naam_encrypted AS leraar_naam_encrypted,
                l.naam_encrypted AS lokaal_naam_encrypted,
                l.capaciteit AS lokaal_capaciteit
            FROM rooster_lessen rl
            INNER JOIN vakken v ON v.id = rl.vak_id
            INNER JOIN users u ON u.id = rl.leraar_id
            INNER JOIN lokalen l ON l.id = rl.lokaal_id
            WHERE rl.rooster_id IN (" . implode(', ', $lessonPlaceholders) . ")
            ORDER BY rl.dag, rl.lesuur
        ");
        $lessonStmt->execute($lessonParams);

        $classById = [];
        foreach ($constraints['classes'] as $class) {
            $classById[(string) $class['id']] = $class;
        }

        $lessons = array_map(function (array $row) use ($classById): array {
            $classId = (string) $row['klas_id'];
            $subjectName = $this->decrypt((string) $row['vak_naam_encrypted']);

            return [
                'id' => (string) $row['id'],
                'lessonGroup' => [
                    'id' => $classId . ':' . (string) $row['vak_id'],
                    'classId' => $classId,
                    'className' => (string) ($classById[$classId]['naam'] ?? ''),
                    'subject' => [
                        'id' => (string) $row['vak_id'],
                        'name' => $subjectName,
                        'code' => $row['vak_code'] ?: $subjectName,
                    ],
                ],
                'teacher' => [
                    'id' => (string) $row['leraar_id'],
                    'name' => $this->decrypt((string) $row['leraar_naam_encrypted']),
                ],
                'room' => [
                    'id' => (string) $row['lokaal_id'],
                    'name' => $this->decrypt((string) $row['lokaal_naam_encrypted']),
                    'capacity' => (int) ($row['lokaal_capaciteit'] ?? 0),
                ],
                'slot' => [
                    'day' => (string) $row['dag'],
                    'period' => (int) $row['lesuur'],
                    'start' => (string) $row['starttijd'],
                    'end' => (string) $row['eindtijd'],
                ],
            ];
        }, $lessonStmt->fetchAll());

        return [
            'constraints' => $constraints,
            'result' => [
                'success' => true,
                'lessons' => $lessons,
                'issues' => array_values(array_unique($issues)),
                'stats' => [
                    'lessons' => count($lessons),
                    'storedRosters' => count($rosterIds),
                ],
            ],
            'validation' => ['success' => true, 'errors' => []],
            'rosterIds' => $rosterIds,
        ];
    }

    public function moveLesson(UserContext $user, string $lessonId, string $day, int $period, string $startTime, string $endTime): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT
                rl.*,
                r.periode_id,
                r.school_id
            FROM rooster_lessen rl
            INNER JOIN roosters r ON r.id = rl.rooster_id
            INNER JOIN scholen s ON s.id = r.school_id
            WHERE {$scopeSql}
              AND rl.id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ...$params,
            'id' => $lessonId,
        ]);
        $lesson = $stmt->fetch();

        if (!is_array($lesson)) {
            return ['success' => false, 'error' => 'Les niet gevonden.'];
        }

        if ((string) $lesson['dag'] === $day && (int) $lesson['lesuur'] === $period) {
            return ['success' => true];
        }

        $conflictStmt = $this->db->prepare("
            SELECT rl.id, rl.klas_id, rl.leraar_id, rl.lokaal_id
            FROM rooster_lessen rl
            INNER JOIN roosters r ON r.id = rl.rooster_id
            WHERE r.periode_id <=> :periode_id
              AND rl.id <> :lesson_id
              AND rl.dag = :dag
              AND rl.lesuur = :lesuur
              AND r.created_at = (
                  SELECT MAX(r2.created_at)
                  FROM roosters r2
                  WHERE r2.periode_id <=> r.periode_id
                    AND r2.klas_id = r.klas_id
              )
              AND (
                  rl.klas_id = :klas_id
                  OR rl.leraar_id = :leraar_id
                  OR rl.lokaal_id = :lokaal_id
              )
            LIMIT 1
        ");
        $conflictStmt->execute([
            'periode_id' => $lesson['periode_id'],
            'lesson_id' => $lessonId,
            'dag' => $day,
            'lesuur' => $period,
            'klas_id' => $lesson['klas_id'],
            'leraar_id' => $lesson['leraar_id'],
            'lokaal_id' => $lesson['lokaal_id'],
        ]);

        if ($conflictStmt->fetch()) {
            return ['success' => false, 'error' => 'Deze verplaatsing botst met klas, leraar of lokaal.'];
        }

        $update = $this->db->prepare("
            UPDATE rooster_lessen
            SET dag = :dag,
                lesuur = :lesuur,
                starttijd = :starttijd,
                eindtijd = :eindtijd
            WHERE id = :id
        ");
        $update->execute([
            'id' => $lessonId,
            'dag' => $day,
            'lesuur' => $period,
            'starttijd' => $startTime,
            'eindtijd' => $endTime,
        ]);

        return ['success' => true];
    }

    private function classesForSchoolYear(UserContext $user, string $schoolYearId): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT
                k.*,
                s.naam_encrypted AS school_naam_encrypted,
                o.naam_encrypted AS opleiding_naam_encrypted,
                o.code AS opleiding_code
            FROM klassen k
            INNER JOIN scholen s ON s.id = k.school_id
            LEFT JOIN opleidingen o ON o.id = k.opleiding_id
            WHERE {$scopeSql}
              AND k.schooljaar_id = :schooljaar_id
              AND k.active = 1
            ORDER BY k.naam_search_hash, k.created_at
        ");
        $stmt->execute([
            ...$params,
            'schooljaar_id' => $schoolYearId,
        ]);

        return array_map(function (array $row): array {
            $row['naam'] = $this->decrypt((string) $row['naam_encrypted']);
            $row['school_naam'] = $this->decrypt((string) $row['school_naam_encrypted']);
            $row['opleiding_naam'] = !empty($row['opleiding_naam_encrypted'])
                ? $this->decrypt((string) $row['opleiding_naam_encrypted'])
                : null;

            return $row;
        }, $stmt->fetchAll());
    }

    private function schoolYear(string $schoolYearId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM schooljaren WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $schoolYearId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function periodForUser(UserContext $user, string $periodId): ?array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT
                sp.*,
                sj.naam AS schooljaar_naam,
                sj.school_id,
                s.naam_encrypted AS school_naam_encrypted
            FROM schooljaar_periodes sp
            INNER JOIN schooljaren sj ON sj.id = sp.schooljaar_id
            INNER JOIN scholen s ON s.id = sj.school_id
            WHERE {$scopeSql}
              AND sp.id = :id
              AND sp.active = 1
            LIMIT 1
        ");
        $stmt->execute([
            ...$params,
            'id' => $periodId,
        ]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        $row['school_naam'] = $this->decrypt((string) $row['school_naam_encrypted']);

        return $row;
    }

    private function lessonGroupsForClass(array $class, ?string $periodId): array
    {
        if (empty($class['opleiding_id'])) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT v.id, v.naam_encrypted, v.code, COALESCE(oph.uren_per_week, ov.uren_per_week) AS uren_per_week
            FROM opleiding_vakken ov
            INNER JOIN vakken v ON v.id = ov.vak_id
            LEFT JOIN opleiding_vak_periode_uren oph
              ON oph.opleiding_id = ov.opleiding_id
             AND oph.vak_id = ov.vak_id
             AND oph.periode_id = :periode_id
            WHERE ov.opleiding_id = :opleiding_id
              AND ov.keuzevak = 0
              AND v.school_id = :school_id
              AND v.active = 1
            ORDER BY v.code IS NULL, v.code, v.created_at
        ");
        $stmt->execute([
            'opleiding_id' => $class['opleiding_id'],
            'school_id' => $class['school_id'],
            'periode_id' => $periodId,
        ]);

        return array_map(function (array $row) use ($class): array {
            return [
                'id' => (string) $class['id'] . ':' . (string) $row['id'],
                'classId' => (string) $class['id'],
                'className' => (string) $class['naam'],
                'subject' => [
                    'id' => (string) $row['id'],
                    'name' => $this->decrypt((string) $row['naam_encrypted']),
                    'code' => $row['code'] ?: $this->decrypt((string) $row['naam_encrypted']),
                ],
                'hoursPerWeek' => max(0, (int) ($row['uren_per_week'] ?? 0)),
                'studentCount' => 24 + ((int) ($class['leerjaar'] ?? 1) * 2),
            ];
        }, $stmt->fetchAll());
    }

    private function teachersForSchool(string $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.naam_encrypted,
                u.email,
                lp.max_uren_per_week,
                lp.max_uren_per_dag,
                lp.beschikbaarheid_json,
                GROUP_CONCAT(lv.vak_id) AS subject_ids
            FROM users u
            LEFT JOIN leraar_vakken lv ON lv.user_id = u.id
            LEFT JOIN leraar_profielen lp ON lp.user_id = u.id
            WHERE u.school_id = :school_id
              AND u.role = 'leraar'
              AND u.active = 1
            GROUP BY u.id, u.naam_encrypted, u.email, lp.max_uren_per_week, lp.max_uren_per_dag, lp.beschikbaarheid_json
            ORDER BY u.email
        ");
        $stmt->execute(['school_id' => $schoolId]);

        return array_map(function (array $row): array {
            $availability = $row['beschikbaarheid_json'] !== null
                ? json_decode((string) $row['beschikbaarheid_json'], true)
                : null;

            return [
                'id' => (string) $row['id'],
                'name' => $this->decrypt((string) $row['naam_encrypted']),
                'email' => (string) $row['email'],
                'subjectIds' => $row['subject_ids'] ? explode(',', (string) $row['subject_ids']) : [],
                'availableSlots' => is_array($availability) ? array_values(array_filter($availability, 'is_string')) : null,
                'maxHoursPerDay' => (int) ($row['max_uren_per_dag'] ?? 6),
                'maxHoursPerWeek' => (int) ($row['max_uren_per_week'] ?? 24),
            ];
        }, $stmt->fetchAll());
    }

    private function roomsForSchool(string $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                l.id,
                l.naam_encrypted,
                l.capaciteit,
                l.beschikbaarheid_json,
                loc.extern AS locatie_extern,
                GROUP_CONCAT(lv.vak_id) AS subject_ids
            FROM lokalen l
            LEFT JOIN lokaal_vakken lv ON lv.lokaal_id = l.id
            LEFT JOIN locaties loc ON loc.id = l.locatie_id
            WHERE l.school_id = :school_id
              AND l.active = 1
            GROUP BY l.id, l.naam_encrypted, l.capaciteit, l.beschikbaarheid_json, loc.extern
            ORDER BY l.created_at
        ");
        $stmt->execute(['school_id' => $schoolId]);

        return array_map(function (array $row): array {
            $availability = $row['beschikbaarheid_json'] !== null
                ? json_decode((string) $row['beschikbaarheid_json'], true)
                : null;

            return [
                'id' => (string) $row['id'],
                'name' => $this->decrypt((string) $row['naam_encrypted']),
                'capacity' => (int) ($row['capaciteit'] ?? 0),
                'subjectIds' => $row['subject_ids'] ? explode(',', (string) $row['subject_ids']) : [],
                'externalLocation' => (int) ($row['locatie_extern'] ?? 0) === 1,
                'availableSlots' => is_array($availability) ? array_values(array_filter($availability, 'is_string')) : null,
            ];
        }, $stmt->fetchAll());
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
