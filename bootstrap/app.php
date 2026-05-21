<?php

declare(strict_types=1);

use Roostar\Core\Support\Env;

require __DIR__ . '/autoload.php';

Env::load(__DIR__ . '/../.env');

date_default_timezone_set('Europe/Amsterdam');

return [
    'base_path' => dirname(__DIR__),
    'config' => [
        'app' => require __DIR__ . '/../config/app.php',
        'database' => require __DIR__ . '/../config/database.php',
        'security' => require __DIR__ . '/../config/security.php',
    ],
];

