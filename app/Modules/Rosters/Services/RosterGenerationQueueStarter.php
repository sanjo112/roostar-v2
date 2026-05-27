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

        $this->ensureLogDirectory($log);

        $command = escapeshellarg($this->phpBinary()) . ' ' . escapeshellarg($worker)
            . ' >> ' . escapeshellarg($log) . ' 2>&1 &';

        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    public function startAndDeferFallback(callable $worker): bool
    {
        $started = $this->start();

        register_shutdown_function(static function () use ($worker): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }

            $worker();
        });

        return $started;
    }

    private function phpBinary(): string
    {
        $configured = trim((string) ($_ENV['PHP_CLI_BINARY'] ?? ''));

        if ($configured !== '') {
            return $configured;
        }

        $binary = PHP_BINARY;
        $name = strtolower(basename($binary));

        if ($binary === '' || str_contains($name, 'php-fpm')) {
            return 'php';
        }

        return $binary;
    }

    private function ensureLogDirectory(string $log): void
    {
        $directory = dirname($log);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }
}
