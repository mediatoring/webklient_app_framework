<?php

declare(strict_types=1);

namespace WebklientApp\Core\Database;

class Migrator
{
    private Connection $db;
    private string $migrationsPath;
    private string $table = 'migrations';

    public function __construct(Connection $db, string $migrationsPath)
    {
        $this->db = $db;
        $this->migrationsPath = $migrationsPath;
    }

    public function ensureMigrationsTable(): void
    {
        $this->db->execute("
            CREATE TABLE IF NOT EXISTS `{$this->table}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL,
                `batch` INT UNSIGNED NOT NULL,
                `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function migrate(): array
    {
        $this->ensureMigrationsTable();

        $ran = $this->getRanMigrations();
        $pending = $this->getPendingMigrations($ran);

        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatch();
        $executed = [];

        foreach ($pending as $file) {
            $name = basename($file, '.php');
            $migration = $this->resolve($file);

            $migration->up($this->db);

            $this->db->execute(
                "INSERT INTO `{$this->table}` (`migration`, `batch`) VALUES (?, ?)",
                [$name, $batch]
            );

            $executed[] = $name;
        }

        return $executed;
    }

    public function rollback(): array
    {
        $this->ensureMigrationsTable();

        $batch = $this->getLastBatch();
        if ($batch === 0) {
            return [];
        }

        $migrations = $this->db->fetchAll(
            "SELECT `migration` FROM `{$this->table}` WHERE `batch` = ? ORDER BY `id` DESC",
            [$batch]
        );

        $rolledBack = [];

        foreach ($migrations as $row) {
            $file = $this->migrationsPath . '/' . $row['migration'] . '.php';
            if (file_exists($file)) {
                $migration = $this->resolve($file);
                $migration->down($this->db);
            }

            $this->db->execute(
                "DELETE FROM `{$this->table}` WHERE `migration` = ?",
                [$row['migration']]
            );

            $rolledBack[] = $row['migration'];
        }

        return $rolledBack;
    }

    public function status(): array
    {
        $this->ensureMigrationsTable();
        $ran = $this->getRanMigrations();
        $all = $this->getAllMigrationFiles();

        $status = [];
        foreach ($all as $file) {
            $name = basename($file, '.php');
            $status[] = [
                'migration' => $name,
                'status' => in_array($name, $ran) ? 'ran' : 'pending',
            ];
        }

        return $status;
    }

    private function getRanMigrations(): array
    {
        $rows = $this->db->fetchAll("SELECT `migration` FROM `{$this->table}` ORDER BY `id`");
        return array_column($rows, 'migration');
    }

    private function getAllMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);
        return $files;
    }

    private function getPendingMigrations(array $ran): array
    {
        $all = $this->getAllMigrationFiles();
        return array_filter($all, fn($file) => !in_array(basename($file, '.php'), $ran));
    }

    private function getNextBatch(): int
    {
        return $this->getLastBatch() + 1;
    }

    private function getLastBatch(): int
    {
        $result = $this->db->fetchOne("SELECT MAX(`batch`) as max_batch FROM `{$this->table}`");
        return (int) ($result['max_batch'] ?? 0);
    }

    private function resolve(string $file): Migration
    {
        require_once $file;

        // Extract class name from filename: 2025_01_01_000001_create_users_table -> CreateUsersTable
        $name = basename($file, '.php');
        $parts = explode('_', $name);
        // Remove timestamp prefix (first 4 parts: year_month_day_number)
        $classParts = array_slice($parts, 4);
        $className = implode('', array_map('ucfirst', $classParts));

        $fqcn = "WebklientApp\\Database\\Migrations\\{$className}";

        if (!class_exists($fqcn)) {
            throw new \RuntimeException("Migration class not found: {$fqcn} in {$file}");
        }

        return new $fqcn();
    }
}
