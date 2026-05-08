<?php

declare(strict_types=1);

namespace App\Cms\Url;

/**
 * UrlResolverInterface — Contract for entities that can resolve URLs.
 *
 * Any subsystem (content, taxonomy, media, etc.) can register a resolver
 * to provide URL generation and path resolution for its entities.
 *
 * PHP 8.4+
 */
interface UrlResolverInterface
{
    /**
     * Get the entity type this resolver handles (e.g., 'node', 'taxonomy', 'media').
     */
    public function getEntityType(): string;

    /**
     * Generate a frontend URL for an entity.
     *
     * @param object|int $entity Entity object or ID
     * @param array<string, mixed> $context Additional context for URL generation
     */
    public function frontendUrl(object|int $entity, array $context = []): ?string;

    /**
     * Generate an admin edit URL for an entity.
     *
     * @param object|int $entity Entity object or ID
     */
    public function adminUrl(object|int $entity): ?string;

    /**
     * Resolve a path to an entity.
     *
     * @param string $path The URL path to resolve
     * @return array{entity: object, type: string, params: array<string, string>}|null
     */
    public function resolve(string $path): ?array;

    /**
     * Check if this resolver can handle the given path.
     */
    public function supports(string $path): bool;
}
