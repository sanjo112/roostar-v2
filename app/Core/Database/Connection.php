<?php

declare(strict_types=1);

namespace Roostar\Core\Database;

use PDO;
use RuntimeException;

final class Connection
{
    private static array $config = [];
    private static ?PDO $pdo = null;

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (self::$config === [] || empty(self::$config['database'])) {
            throw new RuntimeException('Database is not configured.');
        }

        $charset = self::$config['charset'] ?? 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            self::$config['host'] ?? 'localhost',
            self::$config['port'] ?? '3306',
            self::$config['database'],
            $charset,
        );

        self::$pdo = new PDO(
            $dsn,
            self::$config['username'] ?? '',
            self::$config['password'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        return self::$pdo;
    }

    public static function server(): PDO
    {
        if (self::$config === []) {
            throw new RuntimeException('Database is not configured.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s',
            self::$config['host'] ?? 'localhost',
            self::$config['port'] ?? '3306',
        );

        return new PDO(
            $dsn,
            self::$config['username'] ?? '',
            self::$config['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    public static function databaseName(): string
    {
        if (empty(self::$config['database'])) {
            throw new RuntimeException('Database name is not configured.');
        }

        return (string) self::$config['database'];
    }
}
