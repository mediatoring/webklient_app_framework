<?php

declare(strict_types=1);

namespace WebklientApp\Core\Database;

class Connection
{
    private ?\PDO $pdo = null;
    private array $config;
    private bool $queryLogging = false;
    private array $queryLog = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->queryLogging = ($config['query_logging'] ?? false)
            || (getenv('APP_ENV') === 'development');
    }

    public function getPdo(): \PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    private function connect(): void
    {
        $driver = $this->config['driver'] ?? 'mysql';
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 3306;
        $database = $this->config['database'] ?? '';
        $charset = $this->config['charset'] ?? 'utf8mb4';

        $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";

        $this->pdo = new \PDO(
            $dsn,
            $this->config['username'] ?? 'root',
            $this->config['password'] ?? '',
            $this->config['options'] ?? []
        );
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $start = microtime(true);

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);

        if ($this->queryLogging) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        }

        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        return $this->query($sql, $params)->fetchColumn($column);
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->getPdo()->lastInsertId();
    }

    public function transaction(callable $callback): mixed
    {
        $this->getPdo()->beginTransaction();
        try {
            $result = $callback($this);
            $this->getPdo()->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->getPdo()->rollBack();
            throw $e;
        }
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function tableExists(string $table): bool
    {
        $stmt = $this->query("SHOW TABLES LIKE ?", [$table]);
        return $stmt->rowCount() > 0;
    }
}
