<?php

declare(strict_types=1);

namespace App\Cms\Entity;

use App\Cms\Cache\CmsCacheService;

/**
 * EntityManager - Central CRUD operations for all CMS entities
 * 
 * The EntityManager provides a unified interface for working with all entities:
 * - Create, read, update, delete operations
 * - Query building via EntityQuery
 * - Event dispatching for entity lifecycle
 * - Caching integration
 * - Transaction support
 * 
 * Usage:
 * ```php
 * $em = new EntityManager($pdo);
 * 
 * // Create
 * $node = new Node(['title' => 'Hello']);
 * $em->save($node);
 * 
 * // Query
 * $nodes = $em->query(Node::class)
 *     ->where('status', 'published')
 *     ->orderBy('created_at', 'DESC')
 *     ->get();
 * 
 * // Find
 * $node = $em->find(Node::class, 1);
 * 
 * // Update
 * $node->title = 'Updated';
 * $em->save($node);
 * 
 * // Delete
 * $em->delete($node);
 * ```
 */
class EntityManager
{
    private \PDO $db;
    private ?CmsCacheService $cache;
    private EntityEventDispatcher $events;

    /** @var array<string, EntityRepositoryInterface> */
    private array $repositories = [];

    public function __construct(\PDO $db, ?CmsCacheService $cache = null)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->events = new EntityEventDispatcher();
    }

    // =========================================================================
    // Query Building
    // =========================================================================

    /**
     * Create a query builder for an entity
     * 
     * @template T of EntityInterface
     * @param class-string<T> $entityClass
     * @return EntityQuery
     */
    public function query(string $entityClass): EntityQuery
    {
        return new EntityQuery($this->db, $entityClass);
    }

    /**
     * Find entity by ID
     * 
     * @template T of EntityInterface
     * @param class-string<T> $entityClass
     * @return T|null
     */
    public function find(string $entityClass, int $id): ?EntityInterface
    {
        // Check cache first
        $cacheKey = $this->getCacheKey($entityClass, $id);
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        $entity = $this->query($entityClass)->find($id);

        // Cache result
        if ($entity && $this->cache) {
            $this->cache->set($cacheKey, $entity, 3600);
        }

        return $entity;
    }

    /**
     * Find entity by ID or throw exception
     * 
     * @template T of EntityInterface
     * @param class-string<T> $entityClass
     * @return T
     * @throws EntityNotFoundException
     */
    public function findOrFail(string $entityClass, int $id): EntityInterface
    {
        $entity = $this->find($entityClass, $id);
        
        if ($entity === null) {
            throw new EntityNotFoundException("Entity not found: {$entityClass} #{$id}");
        }

        return $entity;
    }

    /**
     * Find entities by IDs
     * 
     * @template T of EntityInterface
     * @param class-string<T> $entityClass
     * @param int[] $ids
     * @return T[]
     */
    public function findMany(string $entityClass, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->query($entityClass)->whereIn('id', $ids)->get();
    }

    /**
     * Get all entities
     * 
     * @template T of EntityInterface
     * @param class-string<T> $entityClass
     * @return T[]
     */
    public function all(string $entityClass): array
    {
        return $this->query($entityClass)->get();
    }

    /**
     * Find entity by criteria
     * 
     * @template T of EntityInterface
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $criteria
     * @return T|null
     */
    public function findOneBy(string $entityClass, array $criteria): ?EntityInterface
    {
        $query = $this->query($entityClass);

        foreach ($criteria as $column => $value) {
            $query->where($column, $value);
        }

        return $query->first();
    }

    /**
     * Find entities by criteria
     * 
     * @template T of EntityInterface
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return T[]
     */
    public function findBy(string $entityClass, array $criteria, array $orderBy = [], ?int $limit = null): array
    {
        $query = $this->query($entityClass);

        foreach ($criteria as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    // =========================================================================
    // CRUD Operations
    // =========================================================================

    /**
     * Save an entity (insert or update)
     */
    public function save(EntityInterface $entity): void
    {
        if ($entity->exists()) {
            $this->update($entity);
        } else {
            $this->insert($entity);
        }
    }

    /**
     * Insert a new entity
     */
    public function insert(EntityInterface $entity): void
    {
        // Dispatch pre-save event
        $this->events->dispatch(new EntityEvent('preSave', $entity));
        $this->events->dispatch(new EntityEvent('preInsert', $entity));

        // Set timestamps
        if ($entity instanceof TimestampInterface) {
            $now = new \DateTimeImmutable();
            $entity->setCreatedAt($now);
            $entity->setUpdatedAt($now);
        }

        $table = $entity::getTableName();
        $data = $entity->toDatabase();

        // Remove id for auto-increment
        unset($data['id']);

        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ":{$c}", $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        // Set the generated ID
        $entity->setId((int) $this->db->lastInsertId());
        $entity->syncOriginal();

        // Clear cache
        $this->clearEntityCache($entity);

        // Dispatch post-save event
        $this->events->dispatch(new EntityEvent('postInsert', $entity));
        $this->events->dispatch(new EntityEvent('postSave', $entity));
    }

    /**
     * Update an existing entity
     */
    public function update(EntityInterface $entity): void
    {
        if (!$entity->exists()) {
            throw new \RuntimeException("Cannot update entity that doesn't exist");
        }

        // Dispatch pre-save event
        $this->events->dispatch(new EntityEvent('preSave', $entity));
        $this->events->dispatch(new EntityEvent('preUpdate', $entity));

        // Update timestamp
        if ($entity instanceof TimestampInterface) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
        }

        // Increment revision if supported
        if ($entity instanceof RevisionInterface) {
            $entity->incrementRevision();
        }

        $table = $entity::getTableName();
        $primaryKey = $entity::getPrimaryKey();
        $data = $entity->toDatabase();

        // Get only dirty fields for efficient update
        $dirty = $entity->getDirty();
        if (empty($dirty)) {
            return; // Nothing to update
        }

        // Always include updated_at if present
        if (isset($data['updated_at'])) {
            $dirty['updated_at'] = $data['updated_at'];
        }
        if (isset($data['revision_id'])) {
            $dirty['revision_id'] = $data['revision_id'];
        }

        $sets = [];
        $params = [];
        foreach ($dirty as $column => $value) {
            $sets[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $params[$primaryKey] = $entity->getId();

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = :%s",
            $table,
            implode(', ', $sets),
            $primaryKey,
            $primaryKey
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $entity->syncOriginal();

        // Clear cache
        $this->clearEntityCache($entity);

        // Dispatch post-save event
        $this->events->dispatch(new EntityEvent('postUpdate', $entity));
        $this->events->dispatch(new EntityEvent('postSave', $entity));
    }

    /**
     * Delete an entity
     */
    public function delete(EntityInterface $entity): void
    {
        if (!$entity->exists()) {
            return;
        }

        // Dispatch pre-delete event
        $this->events->dispatch(new EntityEvent('preDelete', $entity));

        // Soft delete if supported
        if ($entity instanceof SoftDeleteInterface) {
            $entity->setDeletedAt(new \DateTimeImmutable());
            $this->update($entity);
            $this->events->dispatch(new EntityEvent('postDelete', $entity));
            return;
        }

        // Hard delete
        $table = $entity::getTableName();
        $primaryKey = $entity::getPrimaryKey();

        $sql = "DELETE FROM {$table} WHERE {$primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $entity->getId()]);

        // Clear cache
        $this->clearEntityCache($entity);

        // Dispatch post-delete event
        $this->events->dispatch(new EntityEvent('postDelete', $entity));
    }

    /**
     * Force delete an entity (bypass soft delete)
     */
    public function forceDelete(EntityInterface $entity): void
    {
        if (!$entity->exists()) {
            return;
        }

        $this->events->dispatch(new EntityEvent('preDelete', $entity));

        $table = $entity::getTableName();
        $primaryKey = $entity::getPrimaryKey();

        $sql = "DELETE FROM {$table} WHERE {$primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $entity->getId()]);

        $this->clearEntityCache($entity);
        $this->events->dispatch(new EntityEvent('postDelete', $entity));
    }

    /**
     * Restore a soft-deleted entity
     */
    public function restore(EntityInterface $entity): void
    {
        if (!$entity instanceof SoftDeleteInterface) {
            throw new \RuntimeException("Entity does not support soft deletes");
        }

        $entity->restore();
        $this->update($entity);
    }

    // =========================================================================
    // Bulk Operations
    // =========================================================================

    /**
     * Insert multiple entities
     * 
     * @param EntityInterface[] $entities
     */
    public function insertMany(array $entities): void
    {
        if (empty($entities)) {
            return;
        }

        $this->transaction(function() use ($entities) {
            foreach ($entities as $entity) {
                $this->insert($entity);
            }
        });
    }

    /**
     * Delete by criteria
     * 
     * @param class-string<EntityInterface> $entityClass
     * @param array<string, mixed> $criteria
     */
    public function deleteBy(string $entityClass, array $criteria): int
    {
        $table = $entityClass::getTableName();
        
        $wheres = [];
        $params = [];
        foreach ($criteria as $column => $value) {
            $wheres[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            $table,
            implode(' AND ', $wheres)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Update by criteria
     * 
     * @param class-string<EntityInterface> $entityClass
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $data
     */
    public function updateBy(string $entityClass, array $criteria, array $data): int
    {
        $table = $entityClass::getTableName();

        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $sets[] = "{$column} = :set_{$column}";
            $params["set_{$column}"] = $value;
        }

        $wheres = [];
        foreach ($criteria as $column => $value) {
            $wheres[] = "{$column} = :where_{$column}";
            $params["where_{$column}"] = $value;
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $sets),
            implode(' AND ', $wheres)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    // =========================================================================
    // Transactions
    // =========================================================================

    /**
     * Begin a transaction
     */
    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): void
    {
        $this->db->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): void
    {
        $this->db->rollBack();
    }

    /**
     * Execute callback within a transaction
     * 
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // =========================================================================
    // Events
    // =========================================================================

    /**
     * Register an event listener
     * 
     * @param string $event Event name (preSave, postSave, preDelete, postDelete, etc.)
     * @param callable $listener
     */
    public function on(string $event, callable $listener): void
    {
        $this->events->addListener($event, $listener);
    }

    /**
     * Get the event dispatcher
     */
    public function getEventDispatcher(): EntityEventDispatcher
    {
        return $this->events;
    }

    // =========================================================================
    // Repositories
    // =========================================================================

    /**
     * Get repository for an entity
     * 
     * @template T of EntityInterface
     * @param class-string<T> $entityClass
     * @return EntityRepository<T>
     */
    public function getRepository(string $entityClass): EntityRepository
    {
        if (!isset($this->repositories[$entityClass])) {
            $this->repositories[$entityClass] = new EntityRepository($this, $entityClass);
        }

        return $this->repositories[$entityClass];
    }

    /**
     * Register a custom repository
     * 
     * @param class-string<EntityInterface> $entityClass
     */
    public function setRepository(string $entityClass, EntityRepositoryInterface $repository): void
    {
        $this->repositories[$entityClass] = $repository;
    }

    // =========================================================================
    // Cache
    // =========================================================================

    /**
     * Get cache key for an entity
     */
    private function getCacheKey(string $entityClass, int $id): string
    {
        $table = $entityClass::getTableName();
        return "entity:{$table}:{$id}";
    }

    /**
     * Clear entity cache
     */
    private function clearEntityCache(EntityInterface $entity): void
    {
        if (!$this->cache || !$entity->getId()) {
            return;
        }

        $cacheKey = $this->getCacheKey(get_class($entity), $entity->getId());
        $this->cache->delete($cacheKey);
    }

    /**
     * Get the database connection
     */
    public function getConnection(): \PDO
    {
        return $this->db;
    }

    /**
     * Get the cache service
     */
    public function getCache(): ?CmsCacheService
    {
        return $this->cache;
    }
}

/**
 * EntityEvent - Event object for entity lifecycle events
 */
class EntityEvent
{
    public function __construct(
        public readonly string $name,
        public readonly EntityInterface $entity,
        public readonly array $data = []
    ) {}
}

/**
 * EntityEventDispatcher - Dispatches entity lifecycle events
 */
class EntityEventDispatcher
{
    /** @var array<string, array<callable>> */
    private array $listeners = [];

    /**
     * Add an event listener
     */
    public function addListener(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Dispatch an event
     */
    public function dispatch(EntityEvent $event): void
    {
        $listeners = $this->listeners[$event->name] ?? [];

        foreach ($listeners as $listener) {
            $listener($event);
        }
    }

    /**
     * Remove all listeners for an event
     */
    public function removeListeners(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * Get all listeners for an event
     * 
     * @return callable[]
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }
}
