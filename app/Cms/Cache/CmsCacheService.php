<?php

declare(strict_types=1);

namespace App\Cms\Cache;

use MonkeysLegion\Cache\CacheManager;
use MonkeysLegion\Cache\Cache;
use MonkeysLegion\Cache\Contracts\Store;

/**
 * CmsCacheService - CMS-specific caching layer
 * 
 * Wraps MonkeysLegion-Cache with CMS-specific functionality:
 * - Automatic cache tagging for entities
 * - Content cache invalidation
 * - Query result caching
 * - Template/view caching
 * - Menu/navigation caching
 * 
 * @example
 * ```php
 * // Cache entity data with automatic tagging
 * $cache->entity('user', 123, fn() => $this->loadUser(123));
 * 
 * // Cache query results
 * $cache->query('articles', ['status' => 'published'], fn() => $this->getArticles());
 * 
 * // Invalidate all caches for an entity type
 * $cache->invalidateEntity('user');
 * ```
 */
final class CmsCacheService
{
    // Cache tag prefixes
    private const TAG_ENTITY = 'entity';
    private const TAG_QUERY = 'query';
    private const TAG_CONTENT = 'content';
    private const TAG_MENU = 'menu';
    private const TAG_SETTINGS = 'settings';
    private const TAG_THEME = 'theme';
    private const TAG_MODULE = 'module';
    private const TAG_TAXONOMY = 'taxonomy';
    private const TAG_MEDIA = 'media';
    
    // Default TTLs
    private const TTL_SHORT = 300;      // 5 minutes
    private const TTL_MEDIUM = 3600;    // 1 hour
    private const TTL_LONG = 86400;     // 24 hours
    private const TTL_FOREVER = 0;      // Never expires

    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {
        // Set up the static facade
        Cache::setInstance($this->cacheManager);
    }

    // =========================================================================
    // Entity Caching
    // =========================================================================

    /**
     * Cache an entity by type and ID
     * 
     * @param string $entityType Entity type (user, article, etc.)
     * @param int|string $id Entity ID
     * @param callable $callback Function to load entity if not cached
     * @param int $ttl Cache TTL in seconds
     * @return mixed Cached or computed value
     */
    public function entity(string $entityType, int|string $id, callable $callback, int $ttl = self::TTL_MEDIUM): mixed
    {
        $key = $this->entityKey($entityType, $id);
        $tags = [self::TAG_ENTITY, "{$entityType}"];
        
        return $this->rememberWithTags($key, $tags, $ttl, $callback);
    }

    /**
     * Cache multiple entities
     */
    public function entities(string $entityType, array $ids, callable $callback, int $ttl = self::TTL_MEDIUM): array
    {
        $result = [];
        $missing = [];
        
        foreach ($ids as $id) {
            $key = $this->entityKey($entityType, $id);
            $cached = $this->cacheManager->store()->get($key);
            
            if ($cached !== null) {
                $result[$id] = $cached;
            } else {
                $missing[] = $id;
            }
        }
        
        if (!empty($missing)) {
            $loaded = $callback($missing);
            $tags = [self::TAG_ENTITY, "{$entityType}"];
            
            foreach ($loaded as $id => $entity) {
                $key = $this->entityKey($entityType, $id);
                $this->setWithTags($key, $entity, $tags, $ttl);
                $result[$id] = $entity;
            }
        }
        
        return $result;
    }

    /**
     * Invalidate entity cache
     */
    public function invalidateEntity(string $entityType, int|string|null $id = null): void
    {
        if ($id !== null) {
            $this->cacheManager->store()->delete($this->entityKey($entityType, $id));
        } else {
            // Invalidate all entities of this type
            $this->flushTags(["{$entityType}"]);
        }
    }

    /**
     * Get entity cache key
     */
    private function entityKey(string $entityType, int|string $id): string
    {
        return "entity:{$entityType}:{$id}";
    }

    // =========================================================================
    // Query Caching
    // =========================================================================

    /**
     * Cache a database query result
     * 
     * @param string $name Query identifier
     * @param array $params Query parameters (used in cache key)
     * @param callable $callback Function to execute query
     * @param int $ttl Cache TTL
     * @return mixed Query result
     */
    public function query(string $name, array $params, callable $callback, int $ttl = self::TTL_MEDIUM): mixed
    {
        $key = $this->queryKey($name, $params);
        $tags = [self::TAG_QUERY, "query:{$name}"];
        
        return $this->rememberWithTags($key, $tags, $ttl, $callback);
    }

    /**
     * Invalidate query cache
     */
    public function invalidateQuery(string $name): void
    {
        $this->flushTags(["query:{$name}"]);
    }

    /**
     * Invalidate all query caches
     */
    public function invalidateAllQueries(): void
    {
        $this->flushTags([self::TAG_QUERY]);
    }

    /**
     * Get query cache key
     */
    private function queryKey(string $name, array $params): string
    {
        $hash = md5(serialize($params));
        return "query:{$name}:{$hash}";
    }

    // =========================================================================
    // Content Caching
    // =========================================================================

    /**
     * Cache rendered content
     */
    public function content(string $contentType, int|string $id, callable $callback, int $ttl = self::TTL_LONG): mixed
    {
        $key = "content:{$contentType}:{$id}";
        $tags = [self::TAG_CONTENT, "content:{$contentType}"];
        
        return $this->rememberWithTags($key, $tags, $ttl, $callback);
    }

    /**
     * Cache a page by URL
     */
    public function page(string $url, callable $callback, int $ttl = self::TTL_MEDIUM): mixed
    {
        $key = "page:" . md5($url);
        $tags = [self::TAG_CONTENT, 'pages'];
        
        return $this->rememberWithTags($key, $tags, $ttl, $callback);
    }

    /**
     * Invalidate content cache
     */
    public function invalidateContent(string $contentType, int|string|null $id = null): void
    {
        if ($id !== null) {
            $this->cacheManager->store()->delete("content:{$contentType}:{$id}");
        } else {
            $this->flushTags(["content:{$contentType}"]);
        }
    }

    /**
     * Invalidate all pages cache
     */
    public function invalidatePages(): void
    {
        $this->flushTags(['pages']);
    }

    // =========================================================================
    // Menu Caching
    // =========================================================================

    /**
     * Cache menu tree
     */
    public function menu(string $menuName, callable $callback, int $ttl = self::TTL_LONG): mixed
    {
        $key = "menu:{$menuName}";
        $tags = [self::TAG_MENU, "menu:{$menuName}"];
        
        return $this->rememberWithTags($key, $tags, $ttl, $callback);
    }

    /**
     * Invalidate menu cache
     */
    public function invalidateMenu(string $menuName): void
    {
        $this->cacheManager->store()->delete("menu:{$menuName}");
        $this->flushTags(["menu:{$menuName}"]);
    }

    /**
     * Invalidate all menus
     */
    public function invalidateAllMenus(): void
    {
        $this->flushTags([self::TAG_MENU]);
    }

    // =========================================================================
    // Taxonomy Caching
    // =========================================================================

    /**
     * Cache taxonomy terms
     */
    public function taxonomy(string $vocabulary, callable $callback, int $ttl = self::TTL_LONG): mixed
    {
        $key = "taxonomy:{$vocabulary}";
        $tags = [self::TAG_TAXONOMY, "taxonomy:{$vocabulary}"];
        
        return $this->rememberWithTags($key, $tags, $ttl, $callback);
    }

    /**
     * Invalidate taxonomy cache
     */
    public function invalidateTaxonomy(string $vocabulary): void
    {
        $this->cacheManager->store()->delete("taxonomy:{$vocabulary}");
        $this->flushTags(["taxonomy:{$vocabulary}"]);
    }

    // =========================================================================
    // Settings Caching
    // =========================================================================

    /**
     * Cache settings
     */
    public function settings(string $group, callable $callback, int $ttl = self::TTL_LONG): mixed
    {
        $key = "settings:{$group}";
        $tags = [self::TAG_SETTINGS];
        
        return $this->rememberWithTags($key, $tags, $ttl, $callback);
    }

    /**
     * Invalidate settings cache
     */
    public function invalidateSettings(): void
    {
        $this->flushTags([self::TAG_SETTINGS]);
    }

    // =========================================================================
    // Theme Caching
    // =========================================================================

    /**
     * Cache theme data
     */
    public function theme(string $themeName, callable $callback, int $ttl = self::TTL_LONG): mixed
    {
        $key = "theme:{$themeName}";
        $tags = [self::TAG_THEME];
        
        return $this->rememberWithTags($key, $tags, $ttl, $callback);
    }

    /**
     * Invalidate theme cache
     */
    public function invalidateTheme(string $themeName): void
    {
        $this->cacheManager->store()->delete("theme:{$themeName}");
    }

    /**
     * Invalidate all theme caches
     */
    public function invalidateAllThemes(): void
    {
        $this->flushTags([self::TAG_THEME]);
    }

    // =========================================================================
    // Media Caching
    // =========================================================================

    /**
     * Cache media metadata
     */
    public function media(int|string $id, callable $callback, int $ttl = self::TTL_LONG): mixed
    {
        $key = "media:{$id}";
        $tags = [self::TAG_MEDIA];
        
        return $this->rememberWithTags($key, $tags, $ttl, $callback);
    }

    /**
     * Invalidate media cache
     */
    public function invalidateMedia(int|string|null $id = null): void
    {
        if ($id !== null) {
            $this->cacheManager->store()->delete("media:{$id}");
        } else {
            $this->flushTags([self::TAG_MEDIA]);
        }
    }

    // =========================================================================
    // Module Caching
    // =========================================================================

    /**
     * Cache module data
     */
    public function module(string $moduleName, callable $callback, int $ttl = self::TTL_LONG): mixed
    {
        $key = "module:{$moduleName}";
        $tags = [self::TAG_MODULE];
        
        return $this->rememberWithTags($key, $tags, $ttl, $callback);
    }

    /**
     * Invalidate module cache
     */
    public function invalidateModule(string $moduleName): void
    {
        $this->cacheManager->store()->delete("module:{$moduleName}");
    }

    // =========================================================================
    // Generic Cache Operations
    // =========================================================================

    /**
     * Get a value from cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cacheManager->store()->get($key, $default);
    }

    /**
     * Set a value in cache
     */
    public function set(string $key, mixed $value, int $ttl = self::TTL_MEDIUM): bool
    {
        return $this->cacheManager->store()->set($key, $value, $ttl);
    }

    /**
     * Delete a value from cache
     */
    public function delete(string $key): bool
    {
        return $this->cacheManager->store()->delete($key);
    }

    /**
     * Check if key exists
     */
    public function has(string $key): bool
    {
        return $this->cacheManager->store()->has($key);
    }

    /**
     * Remember pattern - get or compute and cache
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Remember forever
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return Cache::rememberForever($key, $callback);
    }

    /**
     * Increment a value
     */
    public function increment(string $key, int $amount = 1): int|false
    {
        return Cache::increment($key, $amount);
    }

    /**
     * Decrement a value
     */
    public function decrement(string $key, int $amount = 1): int|false
    {
        return Cache::decrement($key, $amount);
    }

    /**
     * Get and delete
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        return Cache::pull($key, $default);
    }

    /**
     * Store if key doesn't exist
     */
    public function add(string $key, mixed $value, int $ttl = self::TTL_MEDIUM): bool
    {
        return Cache::add($key, $value, $ttl);
    }

    /**
     * Store forever
     */
    public function forever(string $key, mixed $value): bool
    {
        return Cache::forever($key, $value);
    }

    // =========================================================================
    // Tag Operations
    // =========================================================================

    /**
     * Set with tags
     */
    public function setWithTags(string $key, mixed $value, array $tags, int $ttl = self::TTL_MEDIUM): bool
    {
        try {
            return $this->cacheManager->tags($tags)->set($key, $value, $ttl);
        } catch (\Exception) {
            // If tags not supported, fall back to regular set
            return $this->cacheManager->store()->set($key, $value, $ttl);
        }
    }

    /**
     * Get with tags
     */
    public function getWithTags(string $key, array $tags, mixed $default = null): mixed
    {
        try {
            return $this->cacheManager->tags($tags)->get($key, $default);
        } catch (\Exception) {
            return $this->cacheManager->store()->get($key, $default);
        }
    }

    /**
     * Remember with tags
     */
    public function rememberWithTags(string $key, array $tags, int $ttl, callable $callback): mixed
    {
        try {
            $cached = $this->cacheManager->tags($tags)->get($key);
            
            if ($cached !== null) {
                return $cached;
            }
            
            $value = $callback();
            $this->cacheManager->tags($tags)->set($key, $value, $ttl);
            
            return $value;
        } catch (\Exception) {
            // Fall back to regular remember
            return Cache::remember($key, $ttl, $callback);
        }
    }

    /**
     * Flush cache by tags
     */
    public function flushTags(array $tags): void
    {
        try {
            $this->cacheManager->tags($tags)->clear();
        } catch (\Exception) {
            // Tags not supported, can't flush by tag
        }
    }

    // =========================================================================
    // Store Operations
    // =========================================================================

    /**
     * Use specific cache store
     */
    public function store(string $name): Store
    {
        return $this->cacheManager->store($name);
    }

    /**
     * Clear entire cache
     */
    public function flush(): bool
    {
        return $this->cacheManager->store()->clear();
    }

    /**
     * Clear specific store
     */
    public function flushStore(string $name): bool
    {
        return $this->cacheManager->store($name)->clear();
    }

    /**
     * Invalidate all CMS caches
     */
    public function invalidateAll(): void
    {
        $this->flushTags([
            self::TAG_ENTITY,
            self::TAG_QUERY,
            self::TAG_CONTENT,
            self::TAG_MENU,
            self::TAG_SETTINGS,
            self::TAG_THEME,
            self::TAG_MODULE,
            self::TAG_TAXONOMY,
            self::TAG_MEDIA,
        ]);
    }

    /**
     * Get the underlying cache manager
     */
    public function getManager(): CacheManager
    {
        return $this->cacheManager;
    }

    // =========================================================================
    // Cache Warming
    // =========================================================================

    /**
     * Warm up critical caches
     */
    public function warmUp(array $warmers): void
    {
        foreach ($warmers as $warmer) {
            if (is_callable($warmer)) {
                $warmer($this);
            }
        }
    }

    /**
     * Pre-cache common data
     */
    public function preCache(string $type, callable $dataProvider, int $ttl = self::TTL_LONG): void
    {
        $data = $dataProvider();
        
        foreach ($data as $key => $value) {
            $this->set("{$type}:{$key}", $value, $ttl);
        }
    }
}
