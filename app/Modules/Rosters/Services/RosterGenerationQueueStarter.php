<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Services;

final class RosterGenerationQueueStarter
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function start(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $worker = rtrim($this->basePath, '/') . '/bin/roster-queue-worker.php';
        $log = rtrim($this->basePath, '/') . '/storage/logs/roster-queue.log';

        if (!is_file($worker)) {
            return false;
        }

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker)
            . ' >> ' . escapeshellarg($log) . ' 2>&1 &';

        exec($command);

        return true;
    }
}
