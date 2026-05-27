<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Repositories;

use PDO;
use Roostar\Core\Security\Encryptor;
use Roostar\Core\Support\Str;

final class RosterGenerationQueueRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function maxConcurrent(): int
    {
        $this->ensureDefaultSettings();

        return max(1, min(10, (int) $this->db
            ->query("SELECT max_concurrent FROM roster_generation_settings WHERE id = 1")
            ->fetchColumn()));
    }

    public function updateMaxConcurrent(int $value): void
    {
        $this->ensureDefaultSettings();
        $value = max(1, min(10, $value));

        $stmt = $this->db->prepare("
            UPDATE roster_generation_settings
            SET max_concurrent = :max_concurrent,
                updated_at = NOW()
            WHERE id = 1
        ");
        $stmt->execute(['max_concurrent' => $value]);
    }

    public function enqueue(string $schoolId, string $schoolYearId, string $periodId, string $requestedBy): array
    {
        $existing = $this->activeJobForPeriod($schoolId, $periodId);
        if ($existing !== null) {
            return $existing;
        }

        $id = Str::uuid();
        $stmt = $this->db->prepare("
            INSERT INTO roster_generation_jobs (
                id, school_id, schooljaar_id, periode_id, requested_by, status, created_at, updated_at
            ) VALUES (
                :id, :school_id, :schooljaar_id, :periode_id, :requested_by, 'queued', NOW(), NOW()
            )
        ");
        $stmt->execute([
            'id' => $id,
            'school_id' => $schoolId,
            'schooljaar_id' => $schoolYearId,
            'periode_id' => $periodId,
            'requested_by' => $requestedBy,
        ]);

        return $this->find($id) ?? ['id' => $id, 'status' => 'queued'];
    }

    public function activeJobForPeriod(string $schoolId, string $periodId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM roster_generation_jobs
            WHERE school_id = :school_id
              AND periode_id = :periode_id
              AND status IN ('queued', 'running')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            'school_id' => $schoolId,
            'periode_id' => $periodId,
        ]);

        $job = $stmt->fetch();

        return is_array($job) ? $this->normalizeJob($job) : null;
    }

    public function recentForPeriod(string $schoolId, string $periodId, int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM roster_generation_jobs
            WHERE school_id = :school_id
              AND periode_id = :periode_id
            ORDER BY created_at DESC
            LIMIT " . max(1, min(20, $limit)) . "
        ");
        $stmt->execute([
            'school_id' => $schoolId,
            'periode_id' => $periodId,
        ]);

        return array_map(fn (array $row): array => $this->normalizeJob($row), $stmt->fetchAll());
    }

    public function dashboardJobs(int $limit = 50): array
    {
        $stmt = $this->db->query("
            SELECT
                j.*,
                s.naam_encrypted AS school_naam_encrypted,
                sy.naam AS schooljaar_naam,
                p.naam AS periode_naam
            FROM roster_generation_jobs j
            LEFT JOIN scholen s ON s.id = j.school_id
            LEFT JOIN schooljaren sy ON sy.id = j.schooljaar_id
            LEFT JOIN schooljaar_periodes p ON p.id = j.periode_id
            ORDER BY
                FIELD(j.status, 'running', 'queued', 'failed', 'completed'),
                j.created_at DESC
            LIMIT " . max(1, min(200, $limit)) . "
        ");

        return array_map(function (array $row): array {
            $job = $this->normalizeJob($row);
            $job['school_naam'] = !empty($row['school_naam_encrypted'])
                ? $this->decrypt((string) $row['school_naam_encrypted'])
                : 'Onbekende school';
            $job['schooljaar_naam'] = (string) ($row['schooljaar_naam'] ?? 'Onbekend schooljaar');
            $job['periode_naam'] = (string) ($row['periode_naam'] ?? 'Onbekende periode');

            return $job;
        }, $stmt->fetchAll());
    }

    public function queueStats(): array
    {
        $rows = $this->db
            ->query("SELECT status, COUNT(*) AS total FROM roster_generation_jobs GROUP BY status")
            ->fetchAll();
        $stats = ['queued' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0];

        foreach ($rows as $row) {
            $stats[(string) $row['status']] = (int) $row['total'];
        }

        return $stats;
    }

    public function runningCount(): int
    {
        return (int) $this->db
            ->query("SELECT COUNT(*) FROM roster_generation_jobs WHERE status = 'running'")
            ->fetchColumn();
    }

    public function claimNext(): ?array
    {
        $this->db->beginTransaction();

        try {
            $maxConcurrent = $this->maxConcurrent();
            $running = $this->runningCount();

            if ($running >= $maxConcurrent) {
                $this->db->commit();
                return null;
            }

            $stmt = $this->db->query("
                SELECT *
                FROM roster_generation_jobs
                WHERE status = 'queued'
                ORDER BY created_at
                LIMIT 1
                FOR UPDATE
            ");
            $job = $stmt->fetch();

            if (!is_array($job)) {
                $this->db->commit();
                return null;
            }

            $update = $this->db->prepare("
                UPDATE roster_generation_jobs
                SET status = 'running',
                    attempts = attempts + 1,
                    progress_percent = 5,
                    started_at = COALESCE(started_at, NOW()),
                    updated_at = NOW()
                WHERE id = :id
                  AND status = 'queued'
            ");
            $update->execute(['id' => $job['id']]);
            $this->db->commit();

            return $this->find((string) $job['id']);
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $error;
        }
    }

    public function markProgress(string $id, int $progressPercent): void
    {
        $stmt = $this->db->prepare("
            UPDATE roster_generation_jobs
            SET progress_percent = :progress_percent,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'progress_percent' => max(0, min(99, $progressPercent)),
        ]);
    }

    public function complete(string $id, array $result, array $validation): void
    {
        $stats = $result['stats'] ?? [];
        $lessonCount = (int) ($stats['lessons'] ?? count($result['lessons'] ?? []));
        $requestCount = max(1, (int) ($stats['lessonRequests'] ?? $lessonCount));
        $resultPercent = max(0, min(100, (int) round(($lessonCount / $requestCount) * 100)));

        $stmt = $this->db->prepare("
            UPDATE roster_generation_jobs
            SET status = 'completed',
                progress_percent = 100,
                result_percent = :result_percent,
                lesson_count = :lesson_count,
                lesson_request_count = :lesson_request_count,
                hard_violations = :hard_violations,
                soft_violations = :soft_violations,
                stats_json = :stats_json,
                error_message = NULL,
                finished_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'result_percent' => $resultPercent,
            'lesson_count' => $lessonCount,
            'lesson_request_count' => $requestCount,
            'hard_violations' => (int) ($stats['hardViolations'] ?? 0),
            'soft_violations' => (int) ($stats['softViolations'] ?? 0),
            'stats_json' => json_encode([
                'result' => $stats,
                'validation' => $validation,
                'issues' => $result['issues'] ?? [],
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    public function fail(string $id, string $message): void
    {
        $stmt = $this->db->prepare("
            UPDATE roster_generation_jobs
            SET status = 'failed',
                progress_percent = 100,
                error_message = :error_message,
                finished_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'error_message' => substr($message, 0, 2000),
        ]);
    }

    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM roster_generation_jobs WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $job = $stmt->fetch();

        return is_array($job) ? $this->normalizeJob($job) : null;
    }

    public function resetStaleRunning(int $minutes = 120): int
    {
        $stmt = $this->db->prepare("
            UPDATE roster_generation_jobs
            SET status = 'failed',
                progress_percent = 100,
                error_message = 'Job is afgebroken omdat deze te lang bleef draaien.',
                finished_at = NOW(),
                updated_at = NOW()
            WHERE status = 'running'
              AND updated_at < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->bindValue('minutes', max(15, $minutes), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function ensureDefaultSettings(): void
    {
        $this->db->exec("
            INSERT IGNORE INTO roster_generation_settings (id, max_concurrent, updated_at)
            VALUES (1, 1, NOW())
        ");
    }

    private function normalizeJob(array $row): array
    {
        return [
            ...$row,
            'progress_percent' => (int) ($row['progress_percent'] ?? 0),
            'result_percent' => $row['result_percent'] === null ? null : (int) $row['result_percent'],
            'lesson_count' => (int) ($row['lesson_count'] ?? 0),
            'lesson_request_count' => (int) ($row['lesson_request_count'] ?? 0),
            'hard_violations' => (int) ($row['hard_violations'] ?? 0),
            'soft_violations' => (int) ($row['soft_violations'] ?? 0),
            'stats' => $row['stats_json'] ? (json_decode((string) $row['stats_json'], true) ?: []) : [],
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
}
