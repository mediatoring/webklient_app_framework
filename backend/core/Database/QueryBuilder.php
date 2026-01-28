<?php

declare(strict_types=1);

namespace WebklientApp\Core\Database;

class QueryBuilder
{
    private Connection $db;
    private string $table = '';
    private array $select = ['*'];
    private array $where = [];
    private array $bindings = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $joins = [];
    private ?string $groupBy = null;
    private array $having = [];

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function table(string $table): self
    {
        $clone = clone $this;
        $clone->table = $table;
        return $clone;
    }

    public function select(string ...$columns): self
    {
        $clone = clone $this;
        $clone->select = $columns;
        return $clone;
    }

    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $clone = clone $this;
        $clone->where[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'boolean' => 'AND',
        ];
        $clone->bindings[] = $value;
        return $clone;
    }

    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $clone = clone $this;
        $clone->where[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'boolean' => 'OR',
        ];
        $clone->bindings[] = $value;
        return $clone;
    }

    public function whereIn(string $column, array $values): self
    {
        $clone = clone $this;
        $clone->where[] = [
            'type' => 'in',
            'column' => $column,
            'count' => count($values),
            'boolean' => 'AND',
        ];
        $clone->bindings = array_merge($clone->bindings, array_values($values));
        return $clone;
    }

    public function whereNull(string $column): self
    {
        $clone = clone $this;
        $clone->where[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'AND',
        ];
        return $clone;
    }

    public function whereNotNull(string $column): self
    {
        $clone = clone $this;
        $clone->where[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'AND',
        ];
        return $clone;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $clone = clone $this;
        $clone->where[] = [
            'type' => 'between',
            'column' => $column,
            'boolean' => 'AND',
        ];
        $clone->bindings[] = $min;
        $clone->bindings[] = $max;
        return $clone;
    }

    public function whereLike(string $column, string $pattern): self
    {
        return $this->where($column, 'LIKE', $pattern);
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        $clone = clone $this;
        $clone->joins[] = "JOIN {$table} ON {$first} {$operator} {$second}";
        return $clone;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $clone = clone $this;
        $clone->joins[] = "LEFT JOIN {$table} ON {$first} {$operator} {$second}";
        return $clone;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $clone = clone $this;
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $clone->orderBy[] = "{$column} {$direction}";
        return $clone;
    }

    public function limit(int $limit): self
    {
        $clone = clone $this;
        $clone->limit = $limit;
        return $clone;
    }

    public function offset(int $offset): self
    {
        $clone = clone $this;
        $clone->offset = $offset;
        return $clone;
    }

    public function groupBy(string $column): self
    {
        $clone = clone $this;
        $clone->groupBy = $column;
        return $clone;
    }

    // --- Execute ---

    public function get(): array
    {
        [$sql, $bindings] = $this->buildSelect();
        return $this->db->fetchAll($sql, $bindings);
    }

    public function first(): ?array
    {
        $clone = $this->limit(1);
        [$sql, $bindings] = $clone->buildSelect();
        return $this->db->fetchOne($sql, $bindings);
    }

    public function count(): int
    {
        $clone = clone $this;
        $clone->select = ['COUNT(*) as cnt'];
        $clone->orderBy = [];
        $clone->limit = null;
        $clone->offset = null;
        [$sql, $bindings] = $clone->buildSelect();
        $row = $this->db->fetchOne($sql, $bindings);
        return (int) ($row['cnt'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $total = $this->count();
        $items = $this->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function insert(array $data): string
    {
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));

        $sql = "INSERT INTO `{$this->table}` ({$columnList}) VALUES ({$placeholders})";
        $this->db->execute($sql, array_values($data));
        return $this->db->lastInsertId();
    }

    public function insertBatch(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $columns = array_keys($rows[0]);
        $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $placeholders = implode(', ', array_fill(0, count($rows), $rowPlaceholder));

        $bindings = [];
        foreach ($rows as $row) {
            foreach ($columns as $col) {
                $bindings[] = $row[$col] ?? null;
            }
        }

        $sql = "INSERT INTO `{$this->table}` ({$columnList}) VALUES {$placeholders}";
        return $this->db->execute($sql, $bindings);
    }

    public function update(array $data): int
    {
        $sets = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            $sets[] = "`{$column}` = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $sets);
        $sql .= $this->buildWhere();
        $bindings = array_merge($bindings, $this->bindings);

        return $this->db->execute($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM `{$this->table}`" . $this->buildWhere();
        return $this->db->execute($sql, $this->bindings);
    }

    // --- SQL Building ---

    private function buildSelect(): array
    {
        $sql = "SELECT " . implode(', ', $this->select) . " FROM `{$this->table}`";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->buildWhere();

        if ($this->groupBy !== null) {
            $sql .= " GROUP BY {$this->groupBy}";
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return [$sql, $this->bindings];
    }

    private function buildWhere(): string
    {
        if (empty($this->where)) {
            return '';
        }

        $parts = [];
        foreach ($this->where as $i => $clause) {
            $prefix = $i === 0 ? '' : " {$clause['boolean']} ";

            switch ($clause['type']) {
                case 'basic':
                    $parts[] = $prefix . "`{$clause['column']}` {$clause['operator']} ?";
                    break;
                case 'in':
                    $placeholders = implode(', ', array_fill(0, $clause['count'], '?'));
                    $parts[] = $prefix . "`{$clause['column']}` IN ({$placeholders})";
                    break;
                case 'null':
                    $parts[] = $prefix . "`{$clause['column']}` IS NULL";
                    break;
                case 'not_null':
                    $parts[] = $prefix . "`{$clause['column']}` IS NOT NULL";
                    break;
                case 'between':
                    $parts[] = $prefix . "`{$clause['column']}` BETWEEN ? AND ?";
                    break;
            }
        }

        return " WHERE " . implode('', $parts);
    }
}
