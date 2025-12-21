<?php

declare(strict_types=1);

namespace App\Cms\Entity;

/**
 * EntityQuery - Fluent query builder for entities
 * 
 * Provides a chainable API for building database queries:
 * ```php
 * $query->where('status', 'published')
 *       ->where('type', 'article')
 *       ->orderBy('created_at', 'DESC')
 *       ->limit(10)
 *       ->get();
 * ```
 */
class EntityQuery
{
    private \PDO $db;
    private string $entityClass;
    private string $table;
    private string $primaryKey;

    /** @var array<array{column: string, operator: string, value: mixed, boolean: string}> */
    private array $wheres = [];

    /** @var array<array{column: string, direction: string}> */
    private array $orders = [];

    /** @var array<string> */
    private array $selects = ['*'];

    private ?int $limit = null;
    private ?int $offset = null;

    /** @var array<string> */
    private array $groups = [];

    /** @var array<array{type: string, table: string, first: string, operator: string, second: string}> */
    private array $joins = [];

    /** @var array<array{column: string, operator: string, value: mixed, boolean: string}> */
    private array $havings = [];

    private bool $withTrashed = false;
    private bool $onlyTrashed = false;

    /** @var array<string, mixed> */
    private array $bindings = [];

    private int $bindingIndex = 0;

    public function __construct(\PDO $db, string $entityClass)
    {
        $this->db = $db;
        $this->entityClass = $entityClass;
        $this->table = $entityClass::getTableName();
        $this->primaryKey = $entityClass::getPrimaryKey();
    }

    // =========================================================================
    // Select
    // =========================================================================

    /**
     * Set columns to select
     * 
     * @param string|array<string> $columns
     */
    public function select(string|array $columns = ['*']): static
    {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Add columns to select
     */
    public function addSelect(string ...$columns): static
    {
        $this->selects = array_merge($this->selects, $columns);
        return $this;
    }

    // =========================================================================
    // Where Clauses
    // =========================================================================

    /**
     * Add a basic where clause
     */
    public function where(string $column, mixed $operatorOrValue = null, mixed $value = null, string $boolean = 'AND'): static
    {
        // Handle where('column', 'value') syntax
        if ($value === null && $operatorOrValue !== null) {
            $value = $operatorOrValue;
            $operatorOrValue = '=';
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operatorOrValue,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add an OR where clause
     */
    public function orWhere(string $column, mixed $operatorOrValue = null, mixed $value = null): static
    {
        return $this->where($column, $operatorOrValue, $value, 'OR');
    }

    /**
     * Add a where IN clause
     * 
     * @param array<mixed> $values
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IN',
            'value' => $values,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a where NOT IN clause
     * 
     * @param array<mixed> $values
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'NOT IN',
            'value' => $values,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a where NULL clause
     */
    public function whereNull(string $column, string $boolean = 'AND'): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IS NULL',
            'value' => null,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a where NOT NULL clause
     */
    public function whereNotNull(string $column, string $boolean = 'AND'): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IS NOT NULL',
            'value' => null,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a where BETWEEN clause
     */
    public function whereBetween(string $column, mixed $min, mixed $max, string $boolean = 'AND'): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'BETWEEN',
            'value' => [$min, $max],
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a where LIKE clause
     */
    public function whereLike(string $column, string $pattern, string $boolean = 'AND'): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'LIKE',
            'value' => $pattern,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a raw where clause
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): static
    {
        $this->wheres[] = [
            'column' => $sql,
            'operator' => 'RAW',
            'value' => $bindings,
            'boolean' => $boolean,
        ];

        return $this;
    }

    // =========================================================================
    // Ordering
    // =========================================================================

    /**
     * Add an order by clause
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction),
        ];

        return $this;
    }

    /**
     * Order by descending
     */
    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Order by latest (created_at DESC)
     */
    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Order by oldest (created_at ASC)
     */
    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'ASC');
    }

    // =========================================================================
    // Limiting
    // =========================================================================

    /**
     * Limit results
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Alias for limit
     */
    public function take(int $limit): static
    {
        return $this->limit($limit);
    }

    /**
     * Skip/offset results
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Alias for offset
     */
    public function skip(int $offset): static
    {
        return $this->offset($offset);
    }

    /**
     * Paginate results
     */
    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    // =========================================================================
    // Grouping & Having
    // =========================================================================

    /**
     * Group by columns
     */
    public function groupBy(string ...$columns): static
    {
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    /**
     * Add having clause
     */
    public function having(string $column, mixed $operatorOrValue = null, mixed $value = null, string $boolean = 'AND'): static
    {
        if ($value === null && $operatorOrValue !== null) {
            $value = $operatorOrValue;
            $operatorOrValue = '=';
        }

        $this->havings[] = [
            'column' => $column,
            'operator' => $operatorOrValue,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    // =========================================================================
    // Joins
    // =========================================================================

    /**
     * Add an inner join
     */
    public function join(string $table, string $first, string $operator, string $second): static
    {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * Add a left join
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * Add a right join
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        $this->joins[] = [
            'type' => 'RIGHT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    // =========================================================================
    // Soft Deletes
    // =========================================================================

    /**
     * Include soft deleted records
     */
    public function withTrashed(): static
    {
        $this->withTrashed = true;
        return $this;
    }

    /**
     * Only get soft deleted records
     */
    public function onlyTrashed(): static
    {
        $this->onlyTrashed = true;
        return $this;
    }

    // =========================================================================
    // Execution
    // =========================================================================

    /**
     * Execute query and get all results
     * 
     * @return array<EntityInterface>
     */
    public function get(): array
    {
        $sql = $this->toSql();
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->bindings);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $entities = [];

        foreach ($rows as $row) {
            $entities[] = ($this->entityClass)::fromDatabase($row);
        }

        return $entities;
    }

    /**
     * Get first result
     */
    public function first(): ?EntityInterface
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Get first result or throw exception
     * 
     * @throws EntityNotFoundException
     */
    public function firstOrFail(): EntityInterface
    {
        $result = $this->first();
        
        if ($result === null) {
            throw new EntityNotFoundException("Entity not found: {$this->entityClass}");
        }

        return $result;
    }

    /**
     * Find by primary key
     */
    public function find(int $id): ?EntityInterface
    {
        return $this->where($this->primaryKey, $id)->first();
    }

    /**
     * Find by primary key or throw
     * 
     * @throws EntityNotFoundException
     */
    public function findOrFail(int $id): EntityInterface
    {
        $result = $this->find($id);
        
        if ($result === null) {
            throw new EntityNotFoundException("Entity not found: {$this->entityClass} #{$id}");
        }

        return $result;
    }

    /**
     * Get count of results
     */
    public function count(): int
    {
        $this->selects = ['COUNT(*) as count'];
        $sql = $this->toSql();
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->bindings);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) ($row['count'] ?? 0);
    }

    /**
     * Check if any results exist
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Get sum of column
     */
    public function sum(string $column): float
    {
        $this->selects = ["SUM({$column}) as sum"];
        $sql = $this->toSql();
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->bindings);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (float) ($row['sum'] ?? 0);
    }

    /**
     * Get average of column
     */
    public function avg(string $column): float
    {
        $this->selects = ["AVG({$column}) as avg"];
        $sql = $this->toSql();
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->bindings);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (float) ($row['avg'] ?? 0);
    }

    /**
     * Get max of column
     */
    public function max(string $column): mixed
    {
        $this->selects = ["MAX({$column}) as max"];
        $sql = $this->toSql();
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->bindings);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['max'] ?? null;
    }

    /**
     * Get min of column
     */
    public function min(string $column): mixed
    {
        $this->selects = ["MIN({$column}) as min"];
        $sql = $this->toSql();
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->bindings);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['min'] ?? null;
    }

    /**
     * Get paginated results
     * 
     * @return array{data: array<EntityInterface>, total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        // Get total count (need to clone to avoid modifying original)
        $countQuery = clone $this;
        $total = $countQuery->count();

        // Get page results
        $results = $this->forPage($page, $perPage)->get();

        return [
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Chunk results for memory efficiency
     */
    public function chunk(int $size, callable $callback): void
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $size)->get();
            $count = count($results);

            if ($count === 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                break;
            }

            $page++;
        } while ($count === $size);
    }

    /**
     * Get column values as array
     * 
     * @return array<mixed>
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $this->selects = $key ? [$key, $column] : [$column];
        $sql = $this->toSql();
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->bindings);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];

        foreach ($rows as $row) {
            if ($key) {
                $result[$row[$key]] = $row[$column];
            } else {
                $result[] = $row[$column];
            }
        }

        return $result;
    }

    // =========================================================================
    // SQL Building
    // =========================================================================

    /**
     * Build SQL query
     */
    public function toSql(): string
    {
        $this->bindings = [];
        $this->bindingIndex = 0;

        $sql = "SELECT " . implode(', ', $this->selects);
        $sql .= " FROM {$this->table}";

        // Joins
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // Where clauses
        $sql .= $this->buildWheres();

        // Group by
        if (!empty($this->groups)) {
            $sql .= " GROUP BY " . implode(', ', $this->groups);
        }

        // Having
        if (!empty($this->havings)) {
            $sql .= $this->buildHavings();
        }

        // Order by
        if (!empty($this->orders)) {
            $orderParts = [];
            foreach ($this->orders as $order) {
                $orderParts[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderParts);
        }

        // Limit & Offset
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    /**
     * Build WHERE clause
     */
    private function buildWheres(): string
    {
        $wheres = $this->wheres;

        // Handle soft deletes
        if (!$this->withTrashed && !$this->onlyTrashed) {
            if (is_subclass_of($this->entityClass, SoftDeleteInterface::class)) {
                $wheres[] = [
                    'column' => 'deleted_at',
                    'operator' => 'IS NULL',
                    'value' => null,
                    'boolean' => 'AND',
                ];
            }
        } elseif ($this->onlyTrashed) {
            $wheres[] = [
                'column' => 'deleted_at',
                'operator' => 'IS NOT NULL',
                'value' => null,
                'boolean' => 'AND',
            ];
        }

        if (empty($wheres)) {
            return '';
        }

        $sql = ' WHERE ';
        $parts = [];

        foreach ($wheres as $i => $where) {
            $part = '';

            if ($i > 0) {
                $part .= " {$where['boolean']} ";
            }

            $part .= $this->buildWhereClause($where);
            $parts[] = $part;
        }

        return $sql . implode('', $parts);
    }

    /**
     * Build a single WHERE clause
     */
    private function buildWhereClause(array $where): string
    {
        $column = $where['column'];
        $operator = $where['operator'];
        $value = $where['value'];

        if ($operator === 'RAW') {
            foreach ($value as $binding) {
                $this->addBinding($binding);
            }
            return "({$column})";
        }

        if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
            return "{$column} {$operator}";
        }

        if ($operator === 'IN' || $operator === 'NOT IN') {
            $placeholders = [];
            foreach ($value as $v) {
                $placeholders[] = $this->addBinding($v);
            }
            return "{$column} {$operator} (" . implode(', ', $placeholders) . ")";
        }

        if ($operator === 'BETWEEN') {
            $min = $this->addBinding($value[0]);
            $max = $this->addBinding($value[1]);
            return "{$column} BETWEEN {$min} AND {$max}";
        }

        $placeholder = $this->addBinding($value);
        return "{$column} {$operator} {$placeholder}";
    }

    /**
     * Build HAVING clause
     */
    private function buildHavings(): string
    {
        if (empty($this->havings)) {
            return '';
        }

        $sql = ' HAVING ';
        $parts = [];

        foreach ($this->havings as $i => $having) {
            $part = '';

            if ($i > 0) {
                $part .= " {$having['boolean']} ";
            }

            $placeholder = $this->addBinding($having['value']);
            $part .= "{$having['column']} {$having['operator']} {$placeholder}";
            $parts[] = $part;
        }

        return $sql . implode('', $parts);
    }

    /**
     * Add a binding and return placeholder
     */
    private function addBinding(mixed $value): string
    {
        $key = ':p' . $this->bindingIndex++;
        $this->bindings[$key] = $value;
        return $key;
    }

    /**
     * Get bindings
     * 
     * @return array<string, mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Clone query
     */
    public function __clone(): void
    {
        // Reset bindings on clone
        $this->bindings = [];
        $this->bindingIndex = 0;
    }
}

/**
 * EntityNotFoundException - Thrown when entity is not found
 */
class EntityNotFoundException extends \Exception
{
}
