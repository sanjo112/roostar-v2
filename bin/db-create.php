<?php

declare(strict_types=1);

use Roostar\Core\Database\Connection;

$app = require __DIR__ . '/../bootstrap/app.php';

Connection::configure($app['config']['database']);

$database = Connection::databaseName();
$charset = $app['config']['database']['charset'] ?? 'utf8mb4';
$safeDatabase = str_replace('`', '``', $database);
$safeCharset = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $charset);

Connection::server()->exec("
    CREATE DATABASE IF NOT EXISTS `{$safeDatabase}`
    CHARACTER SET {$safeCharset}
    COLLATE {$safeCharset}_unicode_ci
");

echo "Database ready: {$database}" . PHP_EOL;
