<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Repositories;

use DateTimeImmutable;
use PDO;
use Roostar\Core\Auth\UserContext;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;

final class RosterWeekRepository
{
    private const DAYS = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag'];

    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function defaultWeek(UserContext $user): int
    {
        $week = (int) date('W');
        $year = (int) date('o');

        if ($this->periodForWeek($user, $week, $year) !== null) {
            return $week;
        }

        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT sp.week_van
            FROM schooljaar_periodes sp
            INNER JOIN schooljaren sj ON sj.id = sp.schooljaar_id
            INNER JOIN scholen s ON s.id = sj.school_id
            INNER JOIN roosters r ON r.periode_id = sp.id
            WHERE {$scopeSql}
            ORDER BY COALESCE(sp.week_van_jaar, YEAR(sj.startdatum)), sp.week_van
            LIMIT 1
        ");
        $stmt->execute($params);
        $storedWeek = $stmt->fetchColumn();

        return is_numeric($storedWeek) ? (int) $storedWeek : $week;
    }

    public function weekOverview(UserContext $user, int $week, int $year): array
    {
        $period = $this->periodForWeek($user, $week, $year);
        $dates = $this->weekDates($week, $year);

        if ($period === null) {
            return [
                'period' => null,
                'week' => $week,
                'year' => $year,
                'dates' => $dates,
                'days' => self::DAYS,
                'views' => ['class' => [], 'teacher' => [], 'room' => []],
                'issues' => ['Geen periode gevonden voor week ' . $week . '.'],
            ];
        }

        $lessons = $this->lessonsForPeriod((string) $period['id'], $week);
        $absences = $this->absencesForWeek((string) $period['school_id'], $dates);
        $replacements = $this->replacementsForWeek($dates);
        $views = ['class' => [], 'teacher' => [], 'room' => []];
        $issues = [];

        foreach ($lessons as $index => $lesson) {
            $date = $dates[(string) $lesson['dag']] ?? null;
            $absence = $date ? $this->absenceForLesson($absences, (string) $lesson['leraar_id'], $date) : null;
            $replacement = $date ? ($replacements[$lesson['id'] . '|' . $date] ?? null) : null;
            $isCancelled = $replacement !== null && (string) ($replacement['oplossing'] ?? '') === 'uitgeroosterd';
            $isReplaced = $replacement !== null && !$isCancelled && !empty($replacement['vervanger_id']);
            $effectiveTeacherId = $isReplaced ? (string) $replacement['vervanger_id'] : (string) $lesson['leraar_id'];
            $effectiveTeacherName = $isReplaced && isset($replacement['vervanger_naam_encrypted'])
                ? $this->decrypt((string) $replacement['vervanger_naam_encrypted'])
                : (string) $lesson['leraar_naam'];
            $status = 'normal';

            if ($absence !== null) {
                $status = $isCancelled ? 'cancelled' : ($isReplaced ? 'replaced' : 'sick');
            }

            if ($absence !== null && $replacement === null) {
                $issues[] = $lesson['leraar_naam'] . ' ziek: ' . $lesson['vak_code'] . ' ' . $lesson['klas_naam'] . ' op ' . $lesson['dag'] . ' uur ' . $lesson['lesuur'];
            }

            $viewLesson = [
                'id' => (string) $lesson['id'],
                'classId' => (string) $lesson['klas_id'],
                'teacherId' => (string) $effectiveTeacherId,
                'roomId' => (string) $lesson['lokaal_id'],
                'periodIndex' => (int) $lesson['lesuur'] - 1,
                'dayIndex' => array_search((string) $lesson['dag'], self::DAYS, true),
                'status' => $status,
                'date' => $date,
                'subject' => [
                    'naam' => (string) $lesson['vak_naam'],
                    'code' => (string) $lesson['vak_code'],
                ],
                'class' => ['naam' => (string) $lesson['klas_naam']],
                'teacher' => [
                    'naam' => $effectiveTeacherName,
                    'origineel' => (string) $lesson['leraar_naam'],
                ],
                'room' => [
                    'naam' => (string) $lesson['lokaal_naam'],
                    'capaciteit' => (int) ($lesson['lokaal_capaciteit'] ?? 0),
                ],
                'color' => in_array($status, ['sick', 'cancelled'], true) ? 'lesson-coral' : ($status === 'replaced' ? 'lesson-green' : $this->colorFor($index)),
            ];

            $this->ensureView($views['class'], (string) $lesson['klas_id'], (string) $lesson['klas_naam'], (string) ($lesson['opleiding_naam'] ?? ''));
            $this->ensureView($views['teacher'], (string) $effectiveTeacherId, $effectiveTeacherName, $status === 'replaced' ? 'Vervanger' : 'Leraar');
            $this->ensureView($views['room'], (string) $lesson['lokaal_id'], (string) $lesson['lokaal_naam'], (string) $lesson['lokaal_capaciteit'] . ' plaatsen');

            $views['class'][(string) $lesson['klas_id']]['lessons'][$viewLesson['periodIndex']][$viewLesson['dayIndex']][] = $viewLesson;
            $views['teacher'][(string) $effectiveTeacherId]['lessons'][$viewLesson['periodIndex']][$viewLesson['dayIndex']][] = $viewLesson;
            $views['room'][(string) $lesson['lokaal_id']]['lessons'][$viewLesson['periodIndex']][$viewLesson['dayIndex']][] = $viewLesson;
        }

        return [
            'period' => $period,
            'week' => $week,
            'year' => $year,
            'dates' => $dates,
            'days' => self::DAYS,
            'views' => array_map(static fn (array $items): array => array_values($items), $views),
            'issues' => array_values(array_unique($issues)),
        ];
    }

    private function periodForWeek(UserContext $user, int $week, int $year): ?array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT sp.*, sj.naam AS schooljaar_naam, sj.school_id, s.naam_encrypted AS school_naam_encrypted
            FROM schooljaar_periodes sp
            INNER JOIN schooljaren sj ON sj.id = sp.schooljaar_id
            INNER JOIN scholen s ON s.id = sj.school_id
            WHERE {$scopeSql}
              AND sp.active = 1
              AND ((COALESCE(sp.week_van_jaar, YEAR(sj.startdatum)) * 100) + sp.week_van) <= :week_key_from
              AND ((COALESCE(sp.week_tot_jaar, YEAR(sj.einddatum)) * 100) + sp.week_tot) >= :week_key_to
            ORDER BY COALESCE(sp.week_van_jaar, YEAR(sj.startdatum)), sp.week_van
            LIMIT 1
        ");
        $weekKey = ($year * 100) + $week;
        $stmt->execute([
            ...$params,
            'week_key_from' => $weekKey,
            'week_key_to' => $weekKey,
        ]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        $row['school_naam'] = $this->decrypt((string) $row['school_naam_encrypted']);

        return $row;
    }

    public function moveWeekLesson(UserContext $user, string $lessonId, int $week, string $day, int $period, string $startTime, string $endTime): array
    {
        $context = $this->lessonContext($user, $lessonId);

        if ($context === null) {
            return ['success' => false, 'error' => 'Les niet gevonden.'];
        }

        $lessons = $this->lessonsForPeriod((string) $context['periode_id'], $week);
        foreach ($lessons as $lesson) {
            if ((string) $lesson['id'] === $lessonId) {
                continue;
            }

            if ((string) $lesson['dag'] !== $day || (int) $lesson['lesuur'] !== $period) {
                continue;
            }

            if (
                (string) $lesson['klas_id'] === (string) $context['klas_id']
                || (string) $lesson['leraar_id'] === (string) $context['leraar_id']
                || (string) $lesson['lokaal_id'] === (string) $context['lokaal_id']
            ) {
                return ['success' => false, 'error' => 'Deze weekverplaatsing botst met klas, leraar of lokaal.'];
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO rooster_week_wijzigingen (
                id, rooster_les_id, week_nummer, dag, lesuur, starttijd, eindtijd, created_by, created_at, updated_at
            ) VALUES (
                :id, :rooster_les_id, :week_nummer, :dag, :lesuur, :starttijd, :eindtijd, :created_by, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                dag = VALUES(dag),
                lesuur = VALUES(lesuur),
                starttijd = VALUES(starttijd),
                eindtijd = VALUES(eindtijd),
                updated_at = NOW()
        ");
        $stmt->execute([
            'id' => Str::uuid(),
            'rooster_les_id' => $lessonId,
            'week_nummer' => $week,
            'dag' => $day,
            'lesuur' => $period,
            'starttijd' => $startTime,
            'eindtijd' => $endTime,
            'created_by' => $user->id,
        ]);

        return ['success' => true];
    }

    private function lessonsForPeriod(string $periodId, int $week): array
    {
        $stmt = $this->db->prepare("
            SELECT
                rl.*,
                COALESCE(rw.dag, rl.dag) AS effectieve_dag,
                COALESCE(rw.lesuur, rl.lesuur) AS effectieve_lesuur,
                COALESCE(rw.starttijd, rl.starttijd) AS effectieve_starttijd,
                COALESCE(rw.eindtijd, rl.eindtijd) AS effectieve_eindtijd,
                k.naam_encrypted AS klas_naam_encrypted,
                o.naam_encrypted AS opleiding_naam_encrypted,
                v.naam_encrypted AS vak_naam_encrypted,
                v.code AS vak_code,
                u.naam_encrypted AS leraar_naam_encrypted,
                l.naam_encrypted AS lokaal_naam_encrypted,
                l.capaciteit AS lokaal_capaciteit
            FROM rooster_lessen rl
            INNER JOIN roosters r ON r.id = rl.rooster_id
            LEFT JOIN rooster_week_wijzigingen rw ON rw.rooster_les_id = rl.id AND rw.week_nummer = :week_nummer
            INNER JOIN klassen k ON k.id = rl.klas_id
            LEFT JOIN opleidingen o ON o.id = k.opleiding_id
            INNER JOIN vakken v ON v.id = rl.vak_id
            INNER JOIN users u ON u.id = rl.leraar_id
            INNER JOIN lokalen l ON l.id = rl.lokaal_id
            WHERE r.periode_id = :periode_id
              AND r.created_at = (
                  SELECT MAX(r2.created_at)
                  FROM roosters r2
                  WHERE r2.periode_id = r.periode_id
                    AND r2.klas_id = r.klas_id
              )
            ORDER BY effectieve_dag, effectieve_lesuur
        ");
        $stmt->execute([
            'periode_id' => $periodId,
            'week_nummer' => $week,
        ]);

        return array_map(fn (array $row): array => [
            ...$row,
            'dag' => $row['effectieve_dag'],
            'lesuur' => $row['effectieve_lesuur'],
            'starttijd' => $row['effectieve_starttijd'],
            'eindtijd' => $row['effectieve_eindtijd'],
            'klas_naam' => $this->decrypt((string) $row['klas_naam_encrypted']),
            'opleiding_naam' => !empty($row['opleiding_naam_encrypted']) ? $this->decrypt((string) $row['opleiding_naam_encrypted']) : '',
            'vak_naam' => $this->decrypt((string) $row['vak_naam_encrypted']),
            'vak_code' => $row['vak_code'] ?: $this->decrypt((string) $row['vak_naam_encrypted']),
            'leraar_naam' => $this->decrypt((string) $row['leraar_naam_encrypted']),
            'lokaal_naam' => $this->decrypt((string) $row['lokaal_naam_encrypted']),
        ], $stmt->fetchAll());
    }

    private function lessonContext(UserContext $user, string $lessonId): ?array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT rl.*, r.periode_id, r.school_id
            FROM rooster_lessen rl
            INNER JOIN roosters r ON r.id = rl.rooster_id
            INNER JOIN scholen s ON s.id = r.school_id
            WHERE {$scopeSql}
              AND rl.id = :id
            LIMIT 1
        ");
        $stmt->execute([...$params, 'id' => $lessonId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function absencesForWeek(string $schoolId, array $dates): array
    {
        $stmt = $this->db->prepare("
            SELECT z.*, u.naam_encrypted AS leraar_naam_encrypted
            FROM ziekteperiodes z
            INNER JOIN users u ON u.id = z.leraar_id
            WHERE z.school_id = :school_id
              AND z.status = 'open'
              AND z.datum_van <= :week_end
              AND (z.datum_tot IS NULL OR z.datum_tot >= :week_start)
        ");
        $stmt->execute([
            'school_id' => $schoolId,
            'week_start' => min($dates),
            'week_end' => max($dates),
        ]);

        return $stmt->fetchAll();
    }

    private function replacementsForWeek(array $dates): array
    {
        $stmt = $this->db->prepare("
            SELECT zw.*, u.naam_encrypted AS vervanger_naam_encrypted
            FROM ziekte_les_wijzigingen zw
            LEFT JOIN users u ON u.id = zw.vervanger_id
            WHERE zw.datum BETWEEN :week_start AND :week_end
        ");
        $stmt->execute([
            'week_start' => min($dates),
            'week_end' => max($dates),
        ]);
        $items = [];

        foreach ($stmt->fetchAll() as $row) {
            $items[(string) $row['rooster_les_id'] . '|' . (string) $row['datum']] = $row;
        }

        return $items;
    }

    private function absenceForLesson(array $absences, string $teacherId, string $date): ?array
    {
        foreach ($absences as $absence) {
            if ((string) $absence['leraar_id'] !== $teacherId) {
                continue;
            }

            if ((string) $absence['datum_van'] <= $date && (empty($absence['datum_tot']) || (string) $absence['datum_tot'] >= $date)) {
                return $absence;
            }
        }

        return null;
    }

    private function ensureView(array &$views, string $id, string $label, string $sub): void
    {
        if (isset($views[$id])) {
            return;
        }

        $views[$id] = [
            'id' => $id,
            'label' => $label,
            'sub' => $sub,
            'lessons' => [],
        ];
    }

    private function weekDates(int $week, int $year): array
    {
        $start = (new DateTimeImmutable())->setISODate($year, $week, 1);
        $dates = [];

        foreach (self::DAYS as $index => $day) {
            $dates[$day] = $start->modify('+' . $index . ' days')->format('Y-m-d');
        }

        return $dates;
    }

    private function colorFor(int $index): string
    {
        return ['lesson-blue', 'lesson-green', 'lesson-teal', 'lesson-yellow', 'lesson-purple'][$index % 5];
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
