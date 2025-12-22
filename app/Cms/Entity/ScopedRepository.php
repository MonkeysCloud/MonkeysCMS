<?php

declare(strict_types=1);

namespace App\Cms\Entity;

/**
 * ScopedRepository - Repository with predefined scopes
 *
 * @template T of EntityInterface
 * @extends EntityRepository<T>
 */
abstract class ScopedRepository extends EntityRepository
{
    /**
     * Get defined scopes
     *
     * @return array<string, callable(EntityQuery): EntityQuery>
     */
    abstract protected function scopes(): array;

    /**
     * Apply a scope
     */
    public function scope(string $name, EntityQuery $query): EntityQuery
    {
        $scopes = $this->scopes();

        if (!isset($scopes[$name])) {
            throw new \InvalidArgumentException("Scope '{$name}' not defined");
        }

        return $scopes[$name]($query);
    }

    /**
     * Create query with scope
     */
    public function withScope(string $name): EntityQuery
    {
        return $this->scope($name, $this->createQuery());
    }
}
