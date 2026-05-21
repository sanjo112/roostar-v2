<?php

declare(strict_types=1);

namespace Roostar\Core\Database;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(
        private readonly PDO $db,
        private readonly string $migrationPath,
    ) {
    }

    public function status(): array
    {
        $this->ensureMigrationTable();
        $applied = array_flip($this->appliedMigrationNames());

        return array_map(
            static fn (string $file): array => [
                'migration' => basename($file),
                'applied' => isset($applied[basename($file)]),
            ],
            $this->migrationFiles(),
        );
    }

    public function run(): array
    {
        $this->ensureMigrationTable();

        $ran = [];
        $applied = array_flip($this->appliedMigrationNames());

        foreach ($this->migrationFiles() as $file) {
            $name = basename($file);

            if (isset($applied[$name])) {
                continue;
            }

            $this->runFile($file, $name);
            $ran[] = $name;
        }

        return $ran;
    }

    private function runFile(string $file, string $name): void
    {
        $sql = file_get_contents($file);

        if ($sql === false) {
            throw new RuntimeException('Could not read migration: ' . $name);
        }

        try {
            foreach ($this->statements($sql) as $statement) {
                $this->db->exec($statement);
            }

            $stmt = $this->db->prepare("
                INSERT INTO schema_migrations (migration, applied_at)
                VALUES (:migration, NOW())
            ");
            $stmt->execute(['migration' => $name]);

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw new RuntimeException('Migration failed: ' . $name . ' - ' . $e->getMessage(), 0, $e);
        }
    }

    private function ensureMigrationTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS schema_migrations (
                migration VARCHAR(190) PRIMARY KEY,
                applied_at DATETIME NOT NULL
            )
        ");
    }

    private function appliedMigrationNames(): array
    {
        return $this->db
            ->query("SELECT migration FROM schema_migrations ORDER BY migration")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    private function migrationFiles(): array
    {
        $files = glob(rtrim($this->migrationPath, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    private function statements(string $sql): array
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

        return array_values(array_filter(
            array_map('trim', explode(';', $withoutComments)),
            static fn (string $statement): bool => $statement !== '',
        ));
    }
}
