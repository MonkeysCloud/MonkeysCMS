<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use MonkeysLegion\Cache\CacheManager;
use MonkeysLegion\Cache\Cache;
use MonkeysLegion\Di\ContainerInterface;

/**
 * CacheServiceProvider - Bootstraps MonkeysLegion-Cache
 * 
 * This provider initializes the cache system and sets up:
 * - CacheManager with configured stores
 * - Cache facade for static access
 * - Cache warming for frequently accessed data
 */
final class CacheServiceProvider
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Boot the cache service
     */
    public function boot(): void
    {
        // Get the cache manager from container
        $cacheManager = $this->container->get(CacheManager::class);
        
        // Initialize the Cache facade
        Cache::setInstance($cacheManager);
        
        // Optionally warm up cache with frequently accessed data
        $this->warmUpCache($cacheManager);
    }

    /**
     * Warm up cache with frequently accessed data
     */
    private function warmUpCache(CacheManager $cacheManager): void
    {
        // This can be extended to pre-cache common data
        // For example:
        // - Site settings
        // - Navigation menus
        // - User permissions
        // - System configuration
        
        // Only warm up in production
        $environment = $_ENV['APP_ENV'] ?? 'production';
        
        if ($environment !== 'production') {
            return;
        }
        
        // Cache warming logic can be added here
        // Example:
        // $cacheManager->store()->set('app:booted', time(), 86400);
    }

    /**
     * Get cache configuration from environment
     */
    public static function getConfiguration(string $basePath): array
    {
        return [
            'default' => $_ENV['CACHE_DRIVER'] ?? 'file',
            'prefix' => $_ENV['CACHE_PREFIX'] ?? 'monkeyscms',
            
            'stores' => [
                'file' => [
                    'driver' => 'file',
                    'path' => $basePath . '/storage/cache',
                    'prefix' => $_ENV['CACHE_PREFIX'] ?? 'ml_cache',
                ],
                
                'redis' => [
                    'driver' => 'redis',
                    'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                    'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
                    'password' => $_ENV['REDIS_PASSWORD'] ?? null,
                    'database' => (int) ($_ENV['REDIS_CACHE_DB'] ?? 1),
                    'prefix' => $_ENV['CACHE_PREFIX'] ?? 'ml_cache',
                    'timeout' => 2.5,
                ],
                
                'memcached' => [
                    'driver' => 'memcached',
                    'persistent_id' => $_ENV['MEMCACHED_PERSISTENT_ID'] ?? 'monkeyscms',
                    'prefix' => $_ENV['CACHE_PREFIX'] ?? 'ml_cache',
                    'servers' => [
                        [
                            'host' => $_ENV['MEMCACHED_HOST'] ?? '127.0.0.1',
                            'port' => (int) ($_ENV['MEMCACHED_PORT'] ?? 11211),
                            'weight' => 100,
                        ],
                    ],
                ],
                
                'array' => [
                    'driver' => 'array',
                    'prefix' => $_ENV['CACHE_PREFIX'] ?? 'ml_cache',
                ],
            ],
        ];
    }
}
