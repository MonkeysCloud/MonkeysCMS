<?php

/**
 * MonkeysCMS Cache Helper Functions
 *
 * Global helper functions for cache operations using MonkeysLegion-Cache.
 * These functions provide convenient shortcuts for common cache operations.
 *
 * @see https://github.com/MonkeysCloud/MonkeysLegion-Cache
 */

declare(strict_types=1);



use MonkeysLegion\Cache\Cache;

if (!function_exists('cache')) {
    /**
     * Get/Set cache value
     *
     * @param string|array|null $key Key to get, array to set multiple, null to get manager
     * @param mixed $value Value to set (when $key is string)
     * @param int $ttl TTL in seconds (when setting)
     * @return mixed
     *
     * @example
     * cache('key');                           // Get value
     * cache('key', 'value');                  // Set value with default TTL
     * cache('key', 'value', 3600);            // Set value with 1 hour TTL
     * cache(['key1' => 'val1', 'key2' => 'val2']); // Set multiple
     */
    function cache(string|array|null $key = null, mixed $value = null, int $ttl = 3600): mixed
    {
        // Return manager if no arguments
        if ($key === null) {
            return Cache::getInstance();
        }

        // Set multiple values
        if (is_array($key)) {
            return Cache::putMany($key, $ttl);
        }

        // Get value
        if ($value === null) {
            return Cache::get($key);
        }

        // Set value
        return Cache::set($key, $value, $ttl);
    }
}

if (!function_exists('cache_get')) {
    /**
     * Get a value from cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function cache_get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $default);
    }
}

if (!function_exists('cache_set')) {
    /**
     * Set a value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int $ttl TTL in seconds
     * @return bool
     */
    function cache_set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return Cache::set($key, $value, $ttl);
    }
}

if (!function_exists('cache_remember')) {
    /**
     * Get from cache or compute and store
     *
     * @param string $key Cache key
     * @param int $ttl TTL in seconds
     * @param callable $callback Function to compute value if not cached
     * @return mixed
     *
     * @example
     * $users = cache_remember('users', 3600, fn() => User::all());
     */
    function cache_remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }
}

if (!function_exists('cache_forever')) {
    /**
     * Store value forever (or until manually deleted)
     *
     * @param string $key Cache key
     * @param mixed $valueOrCallback Value or callable to compute value
     * @return mixed
     */
    function cache_forever(string $key, mixed $valueOrCallback): mixed
    {
        if (is_callable($valueOrCallback)) {
            return Cache::rememberForever($key, $valueOrCallback);
        }

        Cache::forever($key, $valueOrCallback);
        return $valueOrCallback;
    }
}

if (!function_exists('cache_forget')) {
    /**
     * Delete a key from cache
     *
     * @param string $key Cache key
     * @return bool
     */
    function cache_forget(string $key): bool
    {
        return Cache::delete($key);
    }
}

if (!function_exists('cache_flush')) {
    /**
     * Clear all cache
     *
     * @return bool
     */
    function cache_flush(): bool
    {
        return Cache::clear();
    }
}

if (!function_exists('cache_has')) {
    /**
     * Check if key exists in cache
     *
     * @param string $key Cache key
     * @return bool
     */
    function cache_has(string $key): bool
    {
        return Cache::has($key);
    }
}

if (!function_exists('cache_pull')) {
    /**
     * Get and delete a value from cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function cache_pull(string $key, mixed $default = null): mixed
    {
        return Cache::pull($key, $default);
    }
}

if (!function_exists('cache_add')) {
    /**
     * Store value only if key doesn't exist
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int $ttl TTL in seconds
     * @return bool True if added, false if key exists
     */
    function cache_add(string $key, mixed $value, int $ttl = 3600): bool
    {
        return Cache::add($key, $value, $ttl);
    }
}

if (!function_exists('cache_increment')) {
    /**
     * Increment a numeric value
     *
     * @param string $key Cache key
     * @param int $amount Amount to increment
     * @return int|false New value or false on failure
     */
    function cache_increment(string $key, int $amount = 1): int|false
    {
        return Cache::increment($key, $amount);
    }
}

if (!function_exists('cache_decrement')) {
    /**
     * Decrement a numeric value
     *
     * @param string $key Cache key
     * @param int $amount Amount to decrement
     * @return int|false New value or false on failure
     */
    function cache_decrement(string $key, int $amount = 1): int|false
    {
        return Cache::decrement($key, $amount);
    }
}

if (!function_exists('cache_tags')) {
    /**
     * Get tagged cache instance
     *
     * @param array $tags Tags to apply
     * @return mixed Tagged cache instance
     *
     * @example
     * cache_tags(['users', 'premium'])->set('user:1', $user, 3600);
     * cache_tags(['users'])->clear(); // Flush all user caches
     */
    function cache_tags(array $tags): mixed
    {
        return Cache::tags($tags);
    }
}

if (!function_exists('cache_store')) {
    /**
     * Get specific cache store
     *
     * @param string $name Store name (file, redis, memcached, array)
     * @return mixed Cache store instance
     *
     * @example
     * cache_store('redis')->set('key', 'value');
     */
    function cache_store(string $name): mixed
    {
        return Cache::store($name);
    }
}

if (!function_exists('cache_many')) {
    /**
     * Get multiple values from cache
     *
     * @param array $keys Keys to retrieve
     * @param mixed $default Default value for missing keys
     * @return array Key-value pairs
     */
    function cache_many(array $keys, mixed $default = null): array
    {
        return Cache::getMultiple($keys, $default);
    }
}

if (!function_exists('cache_put_many')) {
    /**
     * Store multiple values in cache
     *
     * @param array $values Key-value pairs to store
     * @param int $ttl TTL in seconds
     * @return bool
     */
    function cache_put_many(array $values, int $ttl = 3600): bool
    {
        return Cache::putMany($values, $ttl);
    }
}
