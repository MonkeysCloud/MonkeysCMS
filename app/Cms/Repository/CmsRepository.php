<?php

declare(strict_types=1);

namespace App\Cms\Repository;

use App\Cms\Attributes\ContentType;
use App\Cms\Core\BaseEntity;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Query\QueryBuilder;
use PDO;
use ReflectionClass;

/**
 * CmsRepository - Generic repository for CMS content entities
 *
 * This repository provides CRUD operations for any CMS entity without
 * requiring entity-specific repository classes.
 *
 * Key features:
 * - Generic save/find/delete for any entity
 * - Query builder integration for complex queries
 * - Automatic hydration and serialization
 * - Pagination support
 * - Search across multiple fields
 *
 * Unlike Drupal's entity API (complex, hook-based), this is simple
 * and predictable - just call save($entity).
 *
 * Unlike WordPress (everything through wp_insert_post), this works
 * with proper typed entities and any content type.
 *
 * @example
 * ```php
 * $repo = new CmsRepository($database, $queryBuilder);
 *
 * $product = new Product();
 * $product->name = 'Widget';
 * $repo->save($product);
 *
 * $found = $repo->find(Product::class, $product->id);
 * ```
 */
final class CmsRepository
{
    /**
     * @param ConnectionInterface $connection Database connection
     * @param QueryBuilder $qb Query builder instance
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly QueryBuilder $qb,
    ) {
    }

    /**
     * Save an entity (insert or update)
     *
     * @template T of BaseEntity
     * @param T $entity The entity to save
     * @return T The saved entity with ID populated
     */
    public function save(BaseEntity $entity): BaseEntity
    {
        $entity->prePersist();
        $tableName = $entity::getTableName();

        if ($entity->isNew()) {
            return $this->insert($entity, $tableName);
        }

        return $this->update($entity, $tableName);
    }

    /**
     * Find an entity by ID
     *
     * @template T of BaseEntity
     * @param class-string<T> $entityClass Fully qualified class name
     * @param int|string $id Primary key value
     * @return T|null The entity or null if not found
     */
    public function find(string $entityClass, int|string $id): ?BaseEntity
    {
        $tableName = $this->getTableName($entityClass);

        // Reset query builder state to prevent accumulation from previous queries
        $this->qb->reset();

        $row = $this->qb
            ->from($tableName)
            ->where('id', '=', $id)
            ->first();

        if ($row === null) {
            return null;
        }

        /** @var T $entity */
        $entity = new $entityClass();
        return $entity->hydrate($row);
    }

    /**
     * Find entities by criteria
     *
     * @template T of BaseEntity
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $criteria Column => value pairs
     * @param array<string, string> $orderBy Column => direction (ASC/DESC)
     * @param int|null $limit
     * @param int|null $offset
     * @return array<T>
     */
    public function findBy(
        string $entityClass,
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        $tableName = $this->getTableName($entityClass);

        // Reset query builder state to prevent accumulation from previous queries
        $this->qb->reset();

        $query = $this->qb->from($tableName);

        foreach ($criteria as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } elseif ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, '=', $value);
            }
        }

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        $rows = $query->fetchAllAssoc();

        return array_map(
            fn(array $row) => (new $entityClass())->hydrate($row),
            $rows
        );
    }

    /**
     * Find a single entity by criteria
     *
     * @template T of BaseEntity
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $criteria
     * @return T|null
     */
    public function findOneBy(string $entityClass, array $criteria): ?BaseEntity
    {
        $results = $this->findBy($entityClass, $criteria, [], 1);
        return $results[0] ?? null;
    }

    /**
     * Find all entities of a type
     *
     * @template T of BaseEntity
     * @param class-string<T> $entityClass
     * @param array<string, string> $orderBy
     * @return array<T>
     */
    public function findAll(string $entityClass, array $orderBy = ['id' => 'ASC']): array
    {
        return $this->findBy($entityClass, [], $orderBy);
    }

    /**
     * Delete an entity
     *
     * @param BaseEntity $entity
     * @return bool True if deleted successfully
     */
    public function delete(BaseEntity $entity): bool
    {
        if ($entity->getId() === null) {
            return false;
        }

        $tableName = $entity::getTableName();

        $affected = $this->qb
            ->from($tableName)
            ->where('id', '=', $entity->getId())
            ->delete($tableName)
            ->execute();

        return $affected > 0;
    }

    /**
     * Delete entities by criteria
     *
     * @param class-string<BaseEntity> $entityClass
     * @param array<string, mixed> $criteria
     * @return int Number of deleted rows
     */
    public function deleteBy(string $entityClass, array $criteria): int
    {
        $tableName = $this->getTableName($entityClass);

        $query = $this->qb->from($tableName);

        foreach ($criteria as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } elseif ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, '=', $value);
            }
        }

        return $query->delete($tableName)->execute();
    }

    /**
     * Count entities
     *
     * @param class-string<BaseEntity> $entityClass
     * @param array<string, mixed> $criteria
     * @return int
     */
    public function count(string $entityClass, array $criteria = []): int
    {
        $tableName = $this->getTableName($entityClass);

        $query = $this->qb->from($tableName);

        foreach ($criteria as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } elseif ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, '=', $value);
            }
        }

        return $query->count();
    }

    /**
     * Paginated query results
     *
     * @template T of BaseEntity
     * @param class-string<T> $entityClass
     * @param int $page Current page (1-indexed)
     * @param int $perPage Items per page
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return array{data: array<T>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function paginate(
        string $entityClass,
        int $page = 1,
        int $perPage = 20,
        array $criteria = [],
        array $orderBy = ['id' => 'DESC'],
    ): array {
        $total = $this->count($entityClass, $criteria);
        $totalPages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $data = $this->findBy($entityClass, $criteria, $orderBy, $perPage, $offset);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Full-text search across searchable fields
     *
     * @template T of BaseEntity
     * @param class-string<T> $entityClass
     * @param string $query Search query
     * @param array<string> $fields Fields to search (or auto-detect from entity)
     * @param int $limit
     * @return array<T>
     */
    public function search(
        string $entityClass,
        string $query,
        array $fields = [],
        int $limit = 50,
    ): array {
        $tableName = $this->getTableName($entityClass);

        // If no fields specified, find searchable fields from entity
        if (empty($fields)) {
            $fields = $this->getSearchableFields($entityClass);
        }

        if (empty($fields)) {
            return [];
        }

        // Build LIKE query for each field
        $whereParts = [];
        $params = [];
        $searchTerm = '%' . $query . '%';

        foreach ($fields as $field) {
            $whereParts[] = "`{$field}` LIKE ?";
            $params[] = $searchTerm;
        }

        $whereClause = implode(' OR ', $whereParts);
        $sql = "SELECT * FROM `{$tableName}` WHERE ({$whereClause}) LIMIT {$limit}";

        return $this->rawQuery($entityClass, $sql, $params);
    }

    /**
     * Execute a raw query and hydrate results
     *
     * @template T of BaseEntity
     * @param class-string<T> $entityClass
     * @param string $sql Raw SQL query
     * @param array<mixed> $params Query parameters
     * @return array<T>
     */
    public function rawQuery(string $entityClass, string $sql, array $params = []): array
    {
        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            fn(array $row) => (new $entityClass())->hydrate($row),
            $rows
        );
    }

    /**
     * Get the query builder for custom queries
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->qb;
    }

    /**
     * Insert a new entity
     */
    private function insert(BaseEntity $entity, string $tableName): BaseEntity
    {
        $data = $entity->toArray();

        // Remove ID if null (let auto-increment handle it)
        if (isset($data['id']) && $data['id'] === null) {
            unset($data['id']);
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $tableName,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute(array_values($data));

        $entity->id = (int) $this->connection->pdo()->lastInsertId();
        $entity->markPersisted();

        return $entity;
    }

    /**
     * Update an existing entity
     */
    private function update(BaseEntity $entity, string $tableName): BaseEntity
    {
        $dirty = $entity->getDirtyFields();

        if (empty($dirty)) {
            return $entity; // Nothing to update
        }

        // Always update updated_at
        $dirty['updated_at'] = date('Y-m-d H:i:s');

        $setParts = array_map(
            fn(string $col) => "`{$col}` = ?",
            array_keys($dirty)
        );

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `id` = ?',
            $tableName,
            implode(', ', $setParts)
        );

        $params = array_values($dirty);
        $params[] = $entity->getId();

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($params);

        $entity->markPersisted();

        return $entity;
    }

    /**
     * Get table name from entity class
     */
    private function getTableName(string $entityClass): string
    {
        if (method_exists($entityClass, 'getTableName')) {
            return $entityClass::getTableName();
        }

        $reflection = new ReflectionClass($entityClass);
        $attrs = $reflection->getAttributes(ContentType::class);

        if (!empty($attrs)) {
            return $attrs[0]->newInstance()->tableName;
        }

        // Fallback: convert class name to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $reflection->getShortName()) ?? '');
    }

    /**
     * Get searchable fields from entity definition
     *
     * @return array<string>
     */
    private function getSearchableFields(string $entityClass): array
    {
        $reflection = new ReflectionClass($entityClass);
        $fields = [];

        foreach ($reflection->getProperties() as $property) {
            $attrs = $property->getAttributes(\App\Cms\Attributes\Field::class);

            if (!empty($attrs)) {
                $field = $attrs[0]->newInstance();
                if ($field->searchable) {
                    $fields[] = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $property->getName()) ?? '');
                }
            }
        }

        return $fields;
    }
}
