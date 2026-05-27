<?php

declare(strict_types=1);

use Roostar\Core\Database\Connection;
use Roostar\Core\Security\Encryptor;
use Roostar\Modules\Rosters\Services\RosterGenerationQueueWorker;

$app = require __DIR__ . '/../bootstrap/app.php';

Connection::configure($app['config']['database']);

$maxJobs = isset($argv[1]) ? max(1, (int) $argv[1]) : null;
$worker = new RosterGenerationQueueWorker(
    Connection::get(),
    new Encryptor($_ENV['ENCRYPTION_KEY'] ?? ''),
);

$results = $worker->processAvailable($maxJobs);

if ($results === []) {
    echo "Geen roosterjobs om te verwerken." . PHP_EOL;
    exit(0);
}

foreach ($results as $result) {
    echo $result['id'] . ' ' . $result['status'];

    if (isset($result['result_percent'])) {
        echo ' ' . $result['result_percent'] . '%';
    }

    if (isset($result['error'])) {
        echo ' - ' . $result['error'];
    }

    echo PHP_EOL;
}
