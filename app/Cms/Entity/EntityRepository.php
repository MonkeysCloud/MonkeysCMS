<?php

declare(strict_types=1);

namespace App\Cms\Entity;



/**
 * EntityRepository - Generic repository implementation
 *
 * Provides common repository operations for any entity type.
 * Can be extended for entity-specific repositories.
 *
 * @template T of EntityInterface
 * @implements EntityRepositoryInterface<T>
 */
class EntityRepository implements EntityRepositoryInterface
{
    protected EntityManager $em;

    /** @var class-string<T> */
    protected string $entityClass;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(EntityManager $em, string $entityClass)
    {
        $this->em = $em;
        $this->entityClass = $entityClass;
    }

    /**
     * Find entity by ID
     *
     * @return T|null
     */
    public function find(int $id): ?EntityInterface
    {
        return $this->em->find($this->entityClass, $id);
    }

    /**
     * Find entity by ID or throw
     *
     * @return T
     * @throws EntityNotFoundException
     */
    public function findOrFail(int $id): EntityInterface
    {
        return $this->em->findOrFail($this->entityClass, $id);
    }

    /**
     * Get all entities
     *
     * @return T[]
     */
    public function all(): array
    {
        return $this->em->all($this->entityClass);
    }

    /**
     * Find by criteria
     *
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return T[]
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null): array
    {
        return $this->em->findBy($this->entityClass, $criteria, $orderBy, $limit);
    }

    /**
     * Find one by criteria
     *
     * @param array<string, mixed> $criteria
     * @return T|null
     */
    public function findOneBy(array $criteria): ?EntityInterface
    {
        return $this->em->findOneBy($this->entityClass, $criteria);
    }

    /**
     * Save entity
     *
     * @param T $entity
     */
    public function save(EntityInterface $entity): void
    {
        $this->em->save($entity);
    }

    /**
     * Delete entity
     *
     * @param T $entity
     */
    public function delete(EntityInterface $entity): void
    {
        $this->em->delete($entity);
    }

    /**
     * Create a query builder
     */
    public function createQuery(): EntityQuery
    {
        return $this->em->query($this->entityClass);
    }

    /**
     * Count entities
     *
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int
    {
        $query = $this->createQuery();

        foreach ($criteria as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }

        return $query->count();
    }

    /**
     * Check if entity exists
     *
     * @param array<string, mixed> $criteria
     */
    public function exists(array $criteria): bool
    {
        return $this->findOneBy($criteria) !== null;
    }

    /**
     * Get paginated results
     *
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return array{data: T[], total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(int $page = 1, int $perPage = 15, array $criteria = [], array $orderBy = []): array
    {
        $query = $this->createQuery();

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

        return $query->paginate($perPage, $page);
    }

    /**
     * Find by IDs
     *
     * @param int[] $ids
     * @return T[]
     */
    public function findByIds(array $ids): array
    {
        return $this->em->findMany($this->entityClass, $ids);
    }

    /**
     * Get first entity matching criteria
     *
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return T|null
     */
    public function first(array $criteria = [], array $orderBy = []): ?EntityInterface
    {
        return $this->findBy($criteria, $orderBy, 1)[0] ?? null;
    }

    /**
     * Get latest entity
     *
     * @return T|null
     */
    public function latest(string $column = 'created_at'): ?EntityInterface
    {
        return $this->createQuery()->latest($column)->first();
    }

    /**
     * Get oldest entity
     *
     * @return T|null
     */
    public function oldest(string $column = 'created_at'): ?EntityInterface
    {
        return $this->createQuery()->oldest($column)->first();
    }

    /**
     * Create new entity instance
     *
     * @param array<string, mixed> $data
     * @return T
     */
    public function create(array $data = []): EntityInterface
    {
        return new ($this->entityClass)($data);
    }

    /**
     * Create and save entity
     *
     * @param array<string, mixed> $data
     * @return T
     */
    public function createAndSave(array $data): EntityInterface
    {
        $entity = $this->create($data);
        $this->save($entity);
        return $entity;
    }

    /**
     * Update entity by ID
     *
     * @param array<string, mixed> $data
     * @return T|null
     */
    public function update(int $id, array $data): ?EntityInterface
    {
        $entity = $this->find($id);

        if ($entity === null) {
            return null;
        }

        $entity->fill($data);
        $this->save($entity);

        return $entity;
    }

    /**
     * Delete by ID
     */
    public function deleteById(int $id): bool
    {
        $entity = $this->find($id);

        if ($entity === null) {
            return false;
        }

        $this->delete($entity);
        return true;
    }

    /**
     * Get the entity class
     *
     * @return class-string<T>
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * Get the entity manager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->em;
    }

    /**
     * Get the table name
     */
    public function getTableName(): string
    {
        return ($this->entityClass)::getTableName();
    }
}


