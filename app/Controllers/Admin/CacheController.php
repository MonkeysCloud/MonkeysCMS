<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use MonkeysLegion\Cache\CacheManager;
use MonkeysLegion\Cache\Cache;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CacheController - Admin cache management
 *
 * Uses MonkeysLegion-Cache for:
 * - Viewing cache statistics
 * - Clearing cache by store or tags
 * - Managing cache entries
 */
#[Route('/admin/cache', name: 'admin.cache', middleware: ['admin'])]
final class CacheController
{
    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {
    }

    /**
     * Get cache statistics
     */
    #[Route('GET', '/stats', name: 'admin.cache.stats')]
    public function stats(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $storeName = $params['store'] ?? null;

        $store = $storeName ? $this->cacheManager->store($storeName) : $this->cacheManager->store();

        $stats = [
            'default_store' => $this->cacheManager->getDefaultDriver(),
            'current_store' => $storeName ?? 'default',
            'available_stores' => $this->getAvailableStores(),
        ];

        // Try to get store-specific stats (if supported)
        if (method_exists($store, 'getStats')) {
            /** @phpstan-ignore-next-line */
            $stats['store_stats'] = $store->getStats();
        }

        // For Redis, get additional info
        if (method_exists($store, 'getRedis')) {
            try {
                /** @phpstan-ignore-next-line */
                $redis = $store->getRedis();
                $info = $redis->info();
                $stats['redis'] = [
                    'connected_clients' => $info['connected_clients'] ?? null,
                    'used_memory_human' => $info['used_memory_human'] ?? null,
                    'uptime_in_seconds' => $info['uptime_in_seconds'] ?? null,
                    'total_connections_received' => $info['total_connections_received'] ?? null,
                    'keyspace_hits' => $info['keyspace_hits'] ?? null,
                    'keyspace_misses' => $info['keyspace_misses'] ?? null,
                ];
            } catch (\Exception) {
                // Redis not available
            }
        }

        return json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Clear all cache
     */
    #[Route('POST', '/clear', name: 'admin.cache.clear')]
    public function clear(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);
        $storeName = $data['store'] ?? null;
        $tags = $data['tags'] ?? null;

        try {
            if ($tags) {
                // Clear by tags
                if (!is_array($tags)) {
                    $tags = [$tags];
                }

                $store = $storeName ? $this->cacheManager->store($storeName) : $this->cacheManager->store();

                if (method_exists($this->cacheManager, 'tags')) {
                    $this->cacheManager->tags($tags)->clear();
                    $message = 'Cache cleared for tags: ' . implode(', ', $tags);
                } else {
                    return json([
                        'success' => false,
                        'error' => 'Current cache store does not support tags',
                    ], 400);
                }
            } else {
                // Clear entire store
                $store = $storeName ? $this->cacheManager->store($storeName) : $this->cacheManager->store();
                $store->clear();
                $message = $storeName
                    ? "Cache store '{$storeName}' cleared successfully"
                    : 'Default cache store cleared successfully';
            }

            return json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a cache value
     */
    #[Route('GET', '/get/{key}', name: 'admin.cache.get')]
    public function get(ServerRequestInterface $request, string $key): ResponseInterface
    {
        $params = $request->getQueryParams();
        $storeName = $params['store'] ?? null;

        $store = $storeName ? $this->cacheManager->store($storeName) : $this->cacheManager->store();

        $value = $store->get($key);

        if ($value === null) {
            return json([
                'success' => false,
                'error' => 'Key not found',
            ], 404);
        }

        return json([
            'success' => true,
            'data' => [
                'key' => $key,
                'value' => $value,
                'type' => gettype($value),
            ],
        ]);
    }

    /**
     * Set a cache value
     */
    #[Route('POST', '/set', name: 'admin.cache.set')]
    public function set(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);

        $key = $data['key'] ?? null;
        $value = $data['value'] ?? null;
        $ttl = $data['ttl'] ?? 3600;
        $storeName = $data['store'] ?? null;
        $tags = $data['tags'] ?? null;

        if (!$key) {
            return json([
                'success' => false,
                'error' => 'Key is required',
            ], 400);
        }

        try {
            if ($tags && is_array($tags)) {
                $this->cacheManager->tags($tags)->set($key, $value, $ttl);
            } else {
                $store = $storeName ? $this->cacheManager->store($storeName) : $this->cacheManager->store();
                $store->set($key, $value, $ttl);
            }

            return json([
                'success' => true,
                'message' => "Cache key '{$key}' set successfully",
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a cache key
     */
    #[Route('DELETE', '/delete/{key}', name: 'admin.cache.delete')]
    public function delete(ServerRequestInterface $request, string $key): ResponseInterface
    {
        $params = $request->getQueryParams();
        $storeName = $params['store'] ?? null;

        $store = $storeName ? $this->cacheManager->store($storeName) : $this->cacheManager->store();

        try {
            $store->delete($key);

            return json([
                'success' => true,
                'message' => "Cache key '{$key}' deleted successfully",
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a key exists
     */
    #[Route('GET', '/has/{key}', name: 'admin.cache.has')]
    public function has(ServerRequestInterface $request, string $key): ResponseInterface
    {
        $params = $request->getQueryParams();
        $storeName = $params['store'] ?? null;

        $store = $storeName ? $this->cacheManager->store($storeName) : $this->cacheManager->store();

        return json([
            'success' => true,
            'data' => [
                'key' => $key,
                'exists' => $store->has($key),
            ],
        ]);
    }

    /**
     * Increment a numeric value
     */
    #[Route('POST', '/increment/{key}', name: 'admin.cache.increment')]
    public function increment(ServerRequestInterface $request, string $key): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);
        $amount = (int) ($data['amount'] ?? 1);
        $storeName = $data['store'] ?? null;

        $store = $storeName ? $this->cacheManager->store($storeName) : $this->cacheManager->store();

        try {
            if (method_exists($store, 'increment')) {
                $newValue = $store->increment($key, $amount);

                return json([
                    'success' => true,
                    'data' => [
                        'key' => $key,
                        'value' => $newValue,
                    ],
                ]);
            }

            return json([
                'success' => false,
                'error' => 'Current cache store does not support increment',
            ], 400);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Decrement a numeric value
     */
    #[Route('POST', '/decrement/{key}', name: 'admin.cache.decrement')]
    public function decrement(ServerRequestInterface $request, string $key): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);
        $amount = (int) ($data['amount'] ?? 1);
        $storeName = $data['store'] ?? null;

        $store = $storeName ? $this->cacheManager->store($storeName) : $this->cacheManager->store();

        try {
            if (method_exists($store, 'decrement')) {
                $newValue = $store->decrement($key, $amount);

                return json([
                    'success' => true,
                    'data' => [
                        'key' => $key,
                        'value' => $newValue,
                    ],
                ]);
            }

            return json([
                'success' => false,
                'error' => 'Current cache store does not support decrement',
            ], 400);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Flush cache by tag
     */
    #[Route('POST', '/flush-tags', name: 'admin.cache.flush_tags')]
    public function flushTags(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);
        $tags = $data['tags'] ?? [];

        if (empty($tags)) {
            return json([
                'success' => false,
                'error' => 'Tags are required',
            ], 400);
        }

        if (!is_array($tags)) {
            $tags = [$tags];
        }

        try {
            $this->cacheManager->tags($tags)->clear();

            return json([
                'success' => true,
                'message' => 'Cache cleared for tags: ' . implode(', ', $tags),
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remember pattern - get or compute
     */
    #[Route('POST', '/remember', name: 'admin.cache.remember')]
    public function remember(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);

        $key = $data['key'] ?? null;
        $ttl = $data['ttl'] ?? 3600;

        if (!$key) {
            return json([
                'success' => false,
                'error' => 'Key is required',
            ], 400);
        }

        // For the remember pattern demo, we'll just return the current value or null
        $value = Cache::remember($key, $ttl, function () use ($data) {
            return $data['default'] ?? null;
        });

        return json([
            'success' => true,
            'data' => [
                'key' => $key,
                'value' => $value,
            ],
        ]);
    }

    /**
     * Get available cache stores
     */
    private function getAvailableStores(): array
    {
        return ['file', 'redis', 'memcached', 'array'];
    }
}
