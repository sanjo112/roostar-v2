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

foreach ($runner->status() as $migration) {
    echo ($migration['applied'] ? '[x] ' : '[ ] ') . $migration['migration'] . PHP_EOL;
}

