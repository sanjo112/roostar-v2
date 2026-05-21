<?php

declare(strict_types=1);

namespace Roostar\Modules\Absence\Repositories;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;
use Roostar\Core\Auth\UserContext;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;

final class AbsenceRepository
{
    private const DAY_BY_ISO = [
        1 => 'Maandag',
        2 => 'Dinsdag',
        3 => 'Woensdag',
        4 => 'Donderdag',
        5 => 'Vrijdag',
    ];

    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function teachersFor(UserContext $user): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.naam_encrypted, u.school_id, s.naam_encrypted AS school_naam_encrypted
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
            'school_naam' => $this->decrypt((string) $row['school_naam_encrypted']),
        ], $stmt->fetchAll());
    }

    public function activeAbsences(UserContext $user): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT z.*, u.naam_encrypted AS leraar_naam_encrypted, u.email
            FROM ziekteperiodes z
            INNER JOIN users u ON u.id = z.leraar_id
            INNER JOIN scholen s ON s.id = z.school_id
            WHERE {$scopeSql}
              AND z.status = 'open'
            ORDER BY z.datum_van DESC, z.created_at DESC
        ");
        $stmt->execute($params);

        return array_map(function (array $row) use ($user): array {
            $impact = $this->impactFor($user, (string) $row['id']);

            return [
                ...$row,
                'leraar_naam' => $this->decrypt((string) $row['leraar_naam_encrypted']),
                'impact' => $impact['summary'],
            ];
        }, $stmt->fetchAll());
    }

    public function create(UserContext $user, string $schoolId, string $teacherId, string $dateFrom, ?string $dateTo, ?string $note): string
    {
        if (!$this->teacherBelongsToSchool($teacherId, $schoolId)) {
            throw new \InvalidArgumentException('Kies een leraar van dezelfde school.');
        }

        $id = Str::uuid();
        $stmt = $this->db->prepare("
            INSERT INTO ziekteperiodes (
                id, school_id, leraar_id, datum_van, datum_tot, opmerking, status, ingevoerd_door, created_at, updated_at
            ) VALUES (
                :id, :school_id, :leraar_id, :datum_van, :datum_tot, :opmerking, 'open', :ingevoerd_door, NOW(), NOW()
            )
        ");
        $stmt->execute([
            'id' => $id,
            'school_id' => $schoolId,
            'leraar_id' => $teacherId,
            'datum_van' => $dateFrom,
            'datum_tot' => $dateTo,
            'opmerking' => $note,
            'ingevoerd_door' => $user->id,
        ]);

        return $id;
    }

    public function resolve(UserContext $user, string $absenceId, string $dateTo): void
    {
        if (!$this->absenceForUser($user, $absenceId)) {
            throw new \InvalidArgumentException('Ziekmelding niet gevonden.');
        }

        $stmt = $this->db->prepare("
            UPDATE ziekteperiodes
            SET status = 'gesloten',
                datum_tot = :datum_tot,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $absenceId,
            'datum_tot' => $dateTo,
        ]);
    }

    public function impactFor(UserContext $user, string $absenceId): array
    {
        $absence = $this->absenceForUser($user, $absenceId);

        if ($absence === null) {
            return ['absence' => null, 'lessons' => [], 'summary' => $this->summary([])];
        }

        $dateMap = $this->dateMap((string) $absence['datum_van'], $absence['datum_tot'] ? (string) $absence['datum_tot'] : null);
        $lessons = [];

        foreach ($dateMap as $date => $dateInfo) {
            $period = $this->periodForWeek((string) $absence['school_id'], (int) $dateInfo['week']);

            if ($period === null) {
                continue;
            }

            foreach ($this->lessonsForTeacherPeriodDay((string) $absence['leraar_id'], (string) $period['id'], (string) $dateInfo['day']) as $lesson) {
                $replacement = $this->replacementFor($absenceId, (string) $lesson['id'], $date);
                $lesson['datum'] = $date;
                $lesson['week'] = (int) $dateInfo['week'];
                $lesson['periode'] = $period['naam'];
                $lesson['vervanger_id'] = $replacement['vervanger_id'] ?? null;
                $lesson['oplossing'] = $replacement['oplossing'] ?? null;
                $lesson['vervanger_naam'] = isset($replacement['vervanger_naam_encrypted'])
                    ? $this->decrypt((string) $replacement['vervanger_naam_encrypted'])
                    : null;
                $lesson['vervangers'] = $this->replacementTeachers($user, $lesson, (string) $absence['leraar_id'], $date, $absenceId);
                $lessons[] = $lesson;
            }
        }

        usort($lessons, static fn (array $a, array $b): int => [$a['datum'], $a['lesuur'], $a['vak']] <=> [$b['datum'], $b['lesuur'], $b['vak']]);

        return [
            'absence' => [
                ...$absence,
                'leraar_naam' => $this->decrypt((string) $absence['leraar_naam_encrypted']),
            ],
            'lessons' => $lessons,
            'summary' => $this->summary($lessons),
        ];
    }

    public function replaceLesson(UserContext $user, string $absenceId, string $lessonId, string $date, string $replacementTeacherId): void
    {
        $impact = $this->impactFor($user, $absenceId);
        $lesson = null;

        foreach ($impact['lessons'] as $impactLesson) {
            if ((string) $impactLesson['id'] === $lessonId && (string) $impactLesson['datum'] === $date) {
                $lesson = $impactLesson;
                break;
            }
        }

        if ($lesson === null) {
            throw new \InvalidArgumentException('Deze les hoort niet bij deze ziekmelding.');
        }

        $candidates = array_column($lesson['vervangers'], 'id');
        if (!in_array($replacementTeacherId, $candidates, true)) {
            throw new \InvalidArgumentException('Deze vervanger is niet beschikbaar voor dit uur.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO ziekte_les_wijzigingen (
                id, ziekteperiode_id, rooster_les_id, datum, week_nummer, oplossing, vervanger_id, created_at, updated_at
            ) VALUES (
                :id, :ziekteperiode_id, :rooster_les_id, :datum, :week_nummer, 'vervangen', :vervanger_id, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                oplossing = 'vervangen',
                vervanger_id = VALUES(vervanger_id),
                updated_at = NOW()
        ");
        $stmt->execute([
            'id' => Str::uuid(),
            'ziekteperiode_id' => $absenceId,
            'rooster_les_id' => $lessonId,
            'datum' => $date,
            'week_nummer' => (int) $lesson['week'],
            'vervanger_id' => $replacementTeacherId,
        ]);
    }

    public function cancelLesson(UserContext $user, string $absenceId, string $lessonId, string $date): void
    {
        $impact = $this->impactFor($user, $absenceId);
        $lesson = null;

        foreach ($impact['lessons'] as $impactLesson) {
            if ((string) $impactLesson['id'] === $lessonId && (string) $impactLesson['datum'] === $date) {
                $lesson = $impactLesson;
                break;
            }
        }

        if ($lesson === null) {
            throw new \InvalidArgumentException('Deze les hoort niet bij deze ziekmelding.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO ziekte_les_wijzigingen (
                id, ziekteperiode_id, rooster_les_id, datum, week_nummer, oplossing, vervanger_id, created_at, updated_at
            ) VALUES (
                :id, :ziekteperiode_id, :rooster_les_id, :datum, :week_nummer, 'uitgeroosterd', NULL, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                oplossing = 'uitgeroosterd',
                vervanger_id = NULL,
                updated_at = NOW()
        ");
        $stmt->execute([
            'id' => Str::uuid(),
            'ziekteperiode_id' => $absenceId,
            'rooster_les_id' => $lessonId,
            'datum' => $date,
            'week_nummer' => (int) $lesson['week'],
        ]);
    }

    public function replaceRange(UserContext $user, string $absenceId, string $replacementTeacherId, string $dateFrom, ?string $dateTo, array $hours): array
    {
        $hours = array_values(array_unique(array_filter(array_map('intval', $hours), static fn (int $hour): bool => $hour >= 1 && $hour <= 9)));
        if ($hours === []) {
            throw new \InvalidArgumentException('Kies minimaal één lesuur.');
        }

        if ($dateTo === null || $dateTo === '') {
            $dateTo = $dateFrom;
        }

        if ($dateTo < $dateFrom) {
            throw new \InvalidArgumentException('Einddatum moet na de startdatum liggen.');
        }

        $impact = $this->impactFor($user, $absenceId);
        $applied = 0;
        $skipped = [];

        foreach ($impact['lessons'] as $lesson) {
            if ((string) $lesson['datum'] < $dateFrom || (string) $lesson['datum'] > $dateTo) {
                continue;
            }

            if (!in_array((int) $lesson['lesuur'], $hours, true)) {
                continue;
            }

            try {
                $this->replaceLesson($user, $absenceId, (string) $lesson['id'], (string) $lesson['datum'], $replacementTeacherId);
                $applied++;
            } catch (\InvalidArgumentException $error) {
                $skipped[] = $lesson['vak'] . ' ' . $lesson['dag'] . ' uur ' . $lesson['lesuur'] . ': ' . $error->getMessage();
            }
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    public function clearReplacement(UserContext $user, string $absenceId, string $lessonId, string $date): void
    {
        if (!$this->absenceForUser($user, $absenceId)) {
            throw new \InvalidArgumentException('Ziekmelding niet gevonden.');
        }

        $stmt = $this->db->prepare("
            DELETE FROM ziekte_les_wijzigingen
            WHERE ziekteperiode_id = :ziekteperiode_id
              AND rooster_les_id = :rooster_les_id
              AND datum = :datum
        ");
        $stmt->execute([
            'ziekteperiode_id' => $absenceId,
            'rooster_les_id' => $lessonId,
            'datum' => $date,
        ]);
    }

    private function lessonsForTeacherPeriodDay(string $teacherId, string $periodId, string $day): array
    {
        $stmt = $this->db->prepare("
            SELECT
                rl.*,
                r.periode_id,
                k.naam_encrypted AS klas_naam_encrypted,
                v.naam_encrypted AS vak_naam_encrypted,
                v.code AS vak_code,
                l.naam_encrypted AS lokaal_naam_encrypted
            FROM rooster_lessen rl
            INNER JOIN roosters r ON r.id = rl.rooster_id
            INNER JOIN klassen k ON k.id = rl.klas_id
            INNER JOIN vakken v ON v.id = rl.vak_id
            INNER JOIN lokalen l ON l.id = rl.lokaal_id
            WHERE r.periode_id = :periode_id
              AND rl.leraar_id = :leraar_id
              AND rl.dag = :dag
              AND r.created_at = (
                  SELECT MAX(r2.created_at)
                  FROM roosters r2
                  WHERE r2.periode_id = r.periode_id
                    AND r2.klas_id = r.klas_id
              )
            ORDER BY rl.lesuur, v.code
        ");
        $stmt->execute([
            'periode_id' => $periodId,
            'leraar_id' => $teacherId,
            'dag' => $day,
        ]);

        return array_map(fn (array $row): array => [
            ...$row,
            'klas' => $this->decrypt((string) $row['klas_naam_encrypted']),
            'vak' => $row['vak_code'] ?: $this->decrypt((string) $row['vak_naam_encrypted']),
            'lokaal' => $this->decrypt((string) $row['lokaal_naam_encrypted']),
        ], $stmt->fetchAll());
    }

    private function replacementTeachers(UserContext $user, array $lesson, string $sickTeacherId, string $date, string $absenceId): array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT u.id, u.naam_encrypted, u.email, lp.beschikbaarheid_json
            FROM users u
            INNER JOIN scholen s ON s.id = u.school_id
            INNER JOIN leraar_vakken lv ON lv.user_id = u.id AND lv.vak_id = :vak_id
            LEFT JOIN leraar_profielen lp ON lp.user_id = u.id
            WHERE {$scopeSql}
              AND u.role = 'leraar'
              AND u.active = 1
              AND u.id <> :sick_teacher_id
            ORDER BY u.naam_search_hash, u.email
        ");
        $stmt->execute([
            ...$params,
            'vak_id' => $lesson['vak_id'],
            'sick_teacher_id' => $sickTeacherId,
        ]);

        $candidates = [];
        foreach ($stmt->fetchAll() as $candidate) {
            $candidateId = (string) $candidate['id'];

            if ($this->teacherIsSick($candidateId, $date, $absenceId)) {
                continue;
            }

            if ($this->teacherHasRosterConflict($candidateId, (string) $lesson['periode_id'], (string) $lesson['dag'], (int) $lesson['lesuur'])) {
                continue;
            }

            if ($this->teacherHasReplacementConflict($candidateId, $date, (int) $lesson['lesuur'])) {
                continue;
            }

            if (!$this->teacherAvailableForLesson($candidate, (string) $lesson['dag'], (int) $lesson['lesuur'])) {
                continue;
            }

            $candidates[] = [
                'id' => $candidateId,
                'naam' => $this->decrypt((string) $candidate['naam_encrypted']),
                'email' => (string) $candidate['email'],
            ];
        }

        return $candidates;
    }

    private function teacherAvailableForLesson(array $teacher, string $day, int $period): bool
    {
        if ($teacher['beschikbaarheid_json'] === null) {
            return true;
        }

        $availableSlots = json_decode((string) $teacher['beschikbaarheid_json'], true);

        if (!is_array($availableSlots)) {
            return false;
        }

        $dayKey = $this->dayKey($day);

        return $dayKey !== null && in_array($dayKey . '-' . $period, $availableSlots, true);
    }

    private function dayKey(string $day): ?string
    {
        return match (mb_strtolower(trim($day))) {
            'ma', 'maandag' => 'ma',
            'di', 'dinsdag' => 'di',
            'wo', 'woensdag' => 'wo',
            'do', 'donderdag' => 'do',
            'vr', 'vrijdag' => 'vr',
            default => null,
        };
    }

    private function replacementFor(string $absenceId, string $lessonId, string $date): ?array
    {
        $stmt = $this->db->prepare("
            SELECT zw.*, u.naam_encrypted AS vervanger_naam_encrypted
            FROM ziekte_les_wijzigingen zw
            LEFT JOIN users u ON u.id = zw.vervanger_id
            WHERE zw.ziekteperiode_id = :ziekteperiode_id
              AND zw.rooster_les_id = :rooster_les_id
              AND zw.datum = :datum
            LIMIT 1
        ");
        $stmt->execute([
            'ziekteperiode_id' => $absenceId,
            'rooster_les_id' => $lessonId,
            'datum' => $date,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function absenceForUser(UserContext $user, string $absenceId): ?array
    {
        [$scopeSql, $params] = $this->schoolScopeSql($user, 's');
        $stmt = $this->db->prepare("
            SELECT z.*, u.naam_encrypted AS leraar_naam_encrypted
            FROM ziekteperiodes z
            INNER JOIN users u ON u.id = z.leraar_id
            INNER JOIN scholen s ON s.id = z.school_id
            WHERE {$scopeSql}
              AND z.id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ...$params,
            'id' => $absenceId,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function periodForWeek(string $schoolId, int $week): ?array
    {
        $stmt = $this->db->prepare("
            SELECT sp.*
            FROM schooljaar_periodes sp
            INNER JOIN schooljaren sj ON sj.id = sp.schooljaar_id
            WHERE sj.school_id = :school_id
              AND sp.active = 1
              AND (
                  (sp.week_van <= sp.week_tot AND :week_between BETWEEN sp.week_van AND sp.week_tot)
                  OR (sp.week_van > sp.week_tot AND (:week_after >= sp.week_van OR :week_before <= sp.week_tot))
              )
            ORDER BY sp.week_van
            LIMIT 1
        ");
        $stmt->execute([
            'school_id' => $schoolId,
            'week_between' => $week,
            'week_after' => $week,
            'week_before' => $week,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function dateMap(string $dateFrom, ?string $dateTo): array
    {
        $start = new DateTimeImmutable($dateFrom);
        $end = $dateTo ? new DateTimeImmutable($dateTo) : $start->modify('+14 days');

        if ($end < $start) {
            $end = $start;
        }

        $map = [];
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));

        foreach ($period as $date) {
            $isoDay = (int) $date->format('N');
            if (!isset(self::DAY_BY_ISO[$isoDay])) {
                continue;
            }

            $map[$date->format('Y-m-d')] = [
                'week' => (int) $date->format('W'),
                'day' => self::DAY_BY_ISO[$isoDay],
            ];
        }

        return $map;
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

    private function teacherIsSick(string $teacherId, string $date, string $excludeAbsenceId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM ziekteperiodes
            WHERE leraar_id = :leraar_id
              AND status = 'open'
              AND id <> :exclude_id
              AND datum_van <= :datum_van
              AND (datum_tot IS NULL OR datum_tot >= :datum_tot)
            LIMIT 1
        ");
        $stmt->execute([
            'leraar_id' => $teacherId,
            'exclude_id' => $excludeAbsenceId,
            'datum_van' => $date,
            'datum_tot' => $date,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function teacherHasRosterConflict(string $teacherId, string $periodId, string $day, int $lessonHour): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM rooster_lessen rl
            INNER JOIN roosters r ON r.id = rl.rooster_id
            WHERE r.periode_id = :periode_id
              AND rl.leraar_id = :leraar_id
              AND rl.dag = :dag
              AND rl.lesuur = :lesuur
              AND r.created_at = (
                  SELECT MAX(r2.created_at)
                  FROM roosters r2
                  WHERE r2.periode_id = r.periode_id
                    AND r2.klas_id = r.klas_id
              )
            LIMIT 1
        ");
        $stmt->execute([
            'periode_id' => $periodId,
            'leraar_id' => $teacherId,
            'dag' => $day,
            'lesuur' => $lessonHour,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function teacherHasReplacementConflict(string $teacherId, string $date, int $lessonHour): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM ziekte_les_wijzigingen zw
            INNER JOIN rooster_lessen rl ON rl.id = zw.rooster_les_id
            WHERE zw.vervanger_id = :vervanger_id
              AND zw.datum = :datum
              AND rl.lesuur = :lesuur
            LIMIT 1
        ");
        $stmt->execute([
            'vervanger_id' => $teacherId,
            'datum' => $date,
            'lesuur' => $lessonHour,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function summary(array $lessons): array
    {
        return [
            'lessen' => count($lessons),
            'opgevangen' => count(array_filter($lessons, static fn (array $lesson): bool => !empty($lesson['vervanger_id']))),
            'uitgeroosterd' => count(array_filter($lessons, static fn (array $lesson): bool => ($lesson['oplossing'] ?? null) === 'uitgeroosterd')),
            'open' => count(array_filter($lessons, static fn (array $lesson): bool => empty($lesson['vervanger_id']) && ($lesson['oplossing'] ?? null) !== 'uitgeroosterd')),
            'klassen' => count(array_unique(array_column($lessons, 'klas'))),
            'dagen' => count(array_unique(array_column($lessons, 'datum'))),
        ];
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
