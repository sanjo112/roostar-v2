<?php

declare(strict_types=1);

use Roostar\Core\Database\Connection;
use Roostar\Core\Database\MigrationRunner;

$app = require __DIR__ . '/../bootstrap/app.php';

Connection::configure($app['config']['database']);

$runner = new MigrationRunner(
    Connection::get(),
    $app['base_path'] . '/database/migrations',
);

$ran = $runner->run();

if ($ran === []) {
    echo "No migrations to run." . PHP_EOL;
    exit(0);
}

foreach ($ran as $migration) {
    echo "Migrated: {$migration}" . PHP_EOL;
}

