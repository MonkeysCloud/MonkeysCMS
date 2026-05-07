<?php

declare(strict_types=1);

namespace App\Cms\Url;

use App\Cms\I18n\LanguageService;
use MonkeysLegion\DI\Attributes\Singleton;

/**
 * UrlManager — Global URL registry and generator.
 *
 * Drupal-inspired centralized URL management. Subsystems register
 * UrlResolverInterface implementations, and the manager provides
 * a unified API for URL generation and path resolution.
 *
 * Usage:
 *   $url = $urlManager->url($node);            // Frontend URL
 *   $url = $urlManager->editUrl($node);         // Admin edit URL
 *   $url = $urlManager->resolve('/article/foo'); // Resolve path to entity
 *
 * PHP 8.4+
 */
#[Singleton]
final class UrlManager
{
    /** @var array<string, UrlResolverInterface> */
    private array $resolvers = [];

    /** @var array<string, string> Entity class → entity type mapping */
    private array $classMap = [];

    private ?LanguageService $languageService = null;

    /**
     * Inject language service for locale-prefixed URL generation.
     */
    public function setLanguageService(LanguageService $service): void
    {
        $this->languageService = $service;
    }

    /**
     * Register a URL resolver for an entity type.
     */
    public function register(UrlResolverInterface $resolver): self
    {
        $this->resolvers[$resolver->getEntityType()] = $resolver;
        return $this;
    }

    /**
     * Map an entity class to an entity type.
     *
     * This allows `url($entity)` to automatically find the correct resolver
     * based on the entity's class name.
     *
     * @param class-string $class The entity class (e.g., ContentEntity::class)
     * @param string $type The entity type key (e.g., 'node')
     */
    public function mapClass(string $class, string $type): self
    {
        $this->classMap[$class] = $type;
        return $this;
    }

    /**
     * Generate a frontend URL for any entity.
     *
     * @param object|int $entity Entity object or ID
     * @param string|null $type Entity type (auto-detected from class if null)
     * @param array<string, mixed> $context Additional context
     */
    public function url(object|int $entity, ?string $type = null, array $context = []): string
    {
        $resolver = $this->resolverFor($entity, $type);

        if (!$resolver) {
            return '#';
        }

        return $resolver->frontendUrl($entity, $context) ?? '#';
    }

    /**
     * Generate an admin edit URL for any entity.
     *
     * @param object|int $entity Entity object or ID
     * @param string|null $type Entity type (auto-detected from class if null)
     */
    public function editUrl(object|int $entity, ?string $type = null): string
    {
        $resolver = $this->resolverFor($entity, $type);

        if (!$resolver) {
            return '#';
        }

        return $resolver->adminUrl($entity) ?? '#';
    }

    /**
     * Resolve a URL path to an entity.
     *
     * Tries each resolver until one matches. Returns null if no resolver
     * handles the path.
     *
     * @return array{entity: object, type: string, resolver: string, params: array<string, string>}|null
     */
    public function resolve(string $path): ?array
    {
        $path = '/' . trim($path, '/');

        foreach ($this->resolvers as $type => $resolver) {
            if ($resolver->supports($path)) {
                $result = $resolver->resolve($path);
                if ($result !== null) {
                    $result['resolver'] = $type;
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Check if any resolver can handle the given path.
     */
    public function canResolve(string $path): bool
    {
        $path = '/' . trim($path, '/');

        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all registered entity types.
     *
     * @return list<string>
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->resolvers);
    }

    /**
     * Get the resolver for an entity type.
     */
    public function getResolver(string $type): ?UrlResolverInterface
    {
        return $this->resolvers[$type] ?? null;
    }

    // ── Private ─────────────────────────────────────────────────────────

    /**
     * Find the correct resolver for an entity, using explicit type or class map.
     */
    private function resolverFor(object|int $entity, ?string $type): ?UrlResolverInterface
    {
        if ($type !== null) {
            return $this->resolvers[$type] ?? null;
        }

        if (is_int($entity)) {
            return null; // Can't auto-detect type from int ID
        }

        // Try class map
        $class = $entity::class;
        if (isset($this->classMap[$class])) {
            return $this->resolvers[$this->classMap[$class]] ?? null;
        }

        // Try parent classes
        foreach (class_parents($entity) as $parent) {
            if (isset($this->classMap[$parent])) {
                return $this->resolvers[$this->classMap[$parent]] ?? null;
            }
        }

        return null;
    }

    // ── Locale-Prefixed URLs ────────────────────────────────────────────

    /**
     * Generate a locale-prefixed frontend URL for a content entity.
     *
     * Default language → no prefix (/blog/my-article)
     * Non-default     → prefix    (/es/blog/mi-articulo)
     *
     * @param object|int  $entity Entity object or ID
     * @param string|null $lang   Target language (null = default)
     * @param string|null $type   Entity type (auto-detected if null)
     */
    public function localizedUrl(object|int $entity, ?string $lang = null, ?string $type = null): string
    {
        $baseUrl = $this->url($entity, $type);

        if ($baseUrl === '#') {
            return '#';
        }

        return $this->prefixLocale($baseUrl, $lang);
    }

    /**
     * Prefix any path with a locale segment (when non-default language).
     *
     * @param string      $path URL path (e.g. /blog/my-article)
     * @param string|null $lang Language code (null = default = no prefix)
     */
    public function prefixLocale(string $path, ?string $lang = null): string
    {
        if ($lang === null || $this->languageService === null) {
            return $path;
        }

        // Default language: no prefix
        if ($lang === $this->languageService->getDefaultCode()) {
            return $path;
        }

        // Ensure path starts with /
        $path = '/' . ltrim($path, '/');

        return '/' . $lang . $path;
    }
}
