<?php

declare(strict_types=1);

namespace App\Cms\Entity;

/**
 * EntityRepositoryInterface - Contract for entity repositories
 *
 * @template T of EntityInterface
 */
interface EntityRepositoryInterface
{
    /**
     * Find entity by ID
     *
     * @return T|null
     */
    public function find(int $id): ?EntityInterface;

    /**
     * Find entity by ID or throw
     *
     * @return T
     * @throws EntityNotFoundException
     */
    public function findOrFail(int $id): EntityInterface;

    /**
     * Get all entities
     *
     * @return T[]
     */
    public function all(): array;

    /**
     * Find by criteria
     *
     * @param array<string, mixed> $criteria
     * @return T[]
     */
    public function findBy(array $criteria): array;

    /**
     * Find one by criteria
     *
     * @param array<string, mixed> $criteria
     * @return T|null
     */
    public function findOneBy(array $criteria): ?EntityInterface;

    /**
     * Save entity
     *
     * @param T $entity
     */
    public function save(EntityInterface $entity): void;

    /**
     * Delete entity
     *
     * @param T $entity
     */
    public function delete(EntityInterface $entity): void;

    /**
     * Create a query builder
     */
    public function createQuery(): EntityQuery;

    /**
     * Count entities
     *
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int;
}
