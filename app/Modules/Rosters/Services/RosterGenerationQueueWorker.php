<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Services;

use PDO;
use Roostar\Core\Access\PermissionRegistry;
use Roostar\Core\Auth\UserContext;
use Roostar\Core\Security\Encryptor;
use Roostar\Modules\Rosters\Repositories\RosterGenerationQueueRepository;
use Roostar\Modules\Rosters\Repositories\RosterGenerationRepository;

final class RosterGenerationQueueWorker
{
    public function __construct(
        private readonly PDO $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function processAvailable(?int $maxJobs = null): array
    {
        $queue = new RosterGenerationQueueRepository($this->db, $this->encryptor);
        $queue->resetStaleRunning();

        $processed = [];
        $limit = $maxJobs ?? $queue->maxConcurrent();

        for ($index = 0; $index < $limit; $index++) {
            $job = $queue->claimNext();

            if ($job === null) {
                break;
            }

            $processed[] = $this->processJob($job);
        }

        return $processed;
    }

    public function processJob(array $job): array
    {
        $queue = new RosterGenerationQueueRepository($this->db, $this->encryptor);
        $generation = new RosterGenerationRepository($this->db, $this->encryptor);

        try {
            $queue->markProgress((string) $job['id'], 15);
            $user = new UserContext(
                (string) $job['requested_by'],
                'school_admin',
                null,
                (string) $job['school_id'],
                false,
                [[
                    'permission' => PermissionRegistry::ROSTER_GENERATE,
                    'scope_type' => 'school',
                    'scope_id' => (string) $job['school_id'],
                ]],
            );

            $constraints = $generation->constraintsForPeriod($user, (string) $job['periode_id']);
            $queue->markProgress((string) $job['id'], 35);

            $result = (new EngineRosterGenerator())->generate($constraints);
            $validation = (new RosterValidator())->validate($constraints, $result);
            $queue->markProgress((string) $job['id'], 75);

            if (!$validation['success']) {
                $result['issues'] = array_values(array_unique(array_merge($result['issues'] ?? [], $validation['errors'])));
            }

            $this->db->beginTransaction();
            $generation->saveGeneratedRosters($constraints, $result, (string) $job['requested_by']);
            $this->db->commit();

            $queue->complete((string) $job['id'], $result, $validation);

            return [
                'id' => (string) $job['id'],
                'status' => 'completed',
                'result_percent' => $this->resultPercent($result),
            ];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $queue->fail((string) $job['id'], $error->getMessage());

            return [
                'id' => (string) $job['id'],
                'status' => 'failed',
                'error' => $error->getMessage(),
            ];
        }
    }

    private function resultPercent(array $result): int
    {
        $stats = $result['stats'] ?? [];
        $lessonCount = (int) ($stats['lessons'] ?? count($result['lessons'] ?? []));
        $requestCount = max(1, (int) ($stats['lessonRequests'] ?? $lessonCount));

        return max(0, min(100, (int) round(($lessonCount / $requestCount) * 100)));
    }
}
