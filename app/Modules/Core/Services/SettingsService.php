<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Entities\Setting;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Cache\CacheManager;

/**
 * SettingsService - Manages site settings with caching
 *
 * Uses MonkeysLegion-Cache for persistent caching of settings
 * with cache tagging support for easy invalidation.
 */
final class SettingsService
{
    private const CACHE_KEY = 'cms:settings';
    private const CACHE_TTL = 86400; // 24 hours
    private const CACHE_TAG = 'settings';

    private array $localCache = [];
    private bool $loaded = false;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CacheManager $cache,
    ) {
    }

    /**
     * Get a setting value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->loadAutoloadSettings();

        if (isset($this->localCache[$key])) {
            return $this->localCache[$key];
        }

        // Try loading from database
        $stmt = $this->connection->prepare(
            "SELECT value, type FROM settings WHERE `key` = :key"
        );
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return $default;
        }

        $value = $this->castValue($row['value'], $row['type']);
        $this->localCache[$key] = $value;

        return $value;
    }

    /**
     * Set a setting value
     */
    public function set(string $key, mixed $value, ?string $type = null): void
    {
        // Check if setting exists
        $stmt = $this->connection->prepare(
            "SELECT id, type FROM settings WHERE `key` = :key"
        );
        $stmt->execute(['key' => $key]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        $type = $type ?? $existing['type'] ?? $this->detectType($value);
        $stringValue = $this->valueToString($value, $type);

        if ($existing) {
            $stmt = $this->connection->prepare(
                "UPDATE settings SET value = :value, type = :type, updated_at = :updated_at WHERE `key` = :key"
            );
            $stmt->execute([
                'key' => $key,
                'value' => $stringValue,
                'type' => $type,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } else {
            $stmt = $this->connection->prepare("
                INSERT INTO settings (`key`, value, type, `group`, autoload, created_at, updated_at)
                VALUES (:key, :value, :type, :group, :autoload, :created_at, :updated_at)
            ");
            $stmt->execute([
                'key' => $key,
                'value' => $stringValue,
                'type' => $type,
                'group' => $this->extractGroup($key),
                'autoload' => 1,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        $this->localCache[$key] = $value;
        $this->invalidateCache();
    }

    /**
     * Delete a setting
     */
    public function delete(string $key): void
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM settings WHERE `key` = :key AND is_system = 0"
        );
        $stmt->execute(['key' => $key]);

        unset($this->localCache[$key]);
        $this->invalidateCache();
    }

    /**
     * Check if setting exists
     */
    public function has(string $key): bool
    {
        $this->loadAutoloadSettings();

        if (isset($this->localCache[$key])) {
            return true;
        }

        $stmt = $this->connection->prepare(
            "SELECT id FROM settings WHERE `key` = :key"
        );
        $stmt->execute(['key' => $key]);

        return (bool) $stmt->fetch();
    }

    /**
     * Get all settings in a group
     */
    public function getGroup(string $group): array
    {
        // Try cache first
        $cacheKey = self::CACHE_KEY . ':group:' . $group;
        $cached = $this->cache->store()->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $stmt = $this->connection->prepare(
            "SELECT `key`, value, type FROM settings WHERE `group` = :group ORDER BY `key`"
        );
        $stmt->execute(['group' => $group]);

        $settings = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $this->castValue($row['value'], $row['type']);
            $this->localCache[$row['key']] = $settings[$row['key']];
        }

        // Cache the group
        $this->cache->tags([self::CACHE_TAG])->set($cacheKey, $settings, self::CACHE_TTL);

        return $settings;
    }

    /**
     * Get all settings grouped by group
     */
    public function getAllGrouped(): array
    {
        $stmt = $this->connection->query(
            "SELECT * FROM settings ORDER BY `group`, `key`"
        );

        $grouped = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $setting = new Setting();
            $setting->hydrate($row);
            $grouped[$setting->group][] = $setting;
        }

        return $grouped;
    }

    /**
     * Get setting as entity (for forms)
     */
    public function getSetting(string $key): ?Setting
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM settings WHERE `key` = :key"
        );
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $setting = new Setting();
        $setting->hydrate($row);

        return $setting;
    }

    /**
     * Save a setting entity
     */
    public function saveSetting(Setting $setting): void
    {
        $setting->prePersist();

        if ($setting->isNew()) {
            $stmt = $this->connection->prepare("
                INSERT INTO settings (`key`, value, type, `group`, label, description, is_system, autoload, created_at, updated_at)
                VALUES (:key, :value, :type, :group, :label, :description, :is_system, :autoload, :created_at, :updated_at)
            ");
            $stmt->execute([
                'key' => $setting->key,
                'value' => $setting->value,
                'type' => $setting->type,
                'group' => $setting->group,
                'label' => $setting->label,
                'description' => $setting->description,
                'is_system' => $setting->is_system ? 1 : 0,
                'autoload' => $setting->autoload ? 1 : 0,
                'created_at' => $setting->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $setting->updated_at->format('Y-m-d H:i:s'),
            ]);
            $setting->id = (int) $this->connection->lastInsertId();
        } else {
            $stmt = $this->connection->prepare("
                UPDATE settings SET
                    value = :value,
                    type = :type,
                    `group` = :group,
                    label = :label,
                    description = :description,
                    autoload = :autoload,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $setting->id,
                'value' => $setting->value,
                'type' => $setting->type,
                'group' => $setting->group,
                'label' => $setting->label,
                'description' => $setting->description,
                'autoload' => $setting->autoload ? 1 : 0,
                'updated_at' => $setting->updated_at->format('Y-m-d H:i:s'),
            ]);
        }

        // Update cache
        $this->localCache[$setting->key] = $setting->getTypedValue();
        $this->invalidateCache();
    }

    /**
     * Clear the settings cache
     */
    public function clearCache(): void
    {
        $this->localCache = [];
        $this->loaded = false;
        $this->invalidateCache();
    }

    /**
     * Invalidate persistent cache
     */
    private function invalidateCache(): void
    {
        // Clear all settings-related cache using tags
        try {
            $this->cache->tags([self::CACHE_TAG])->clear();
        } catch (\Exception) {
            // If tags not supported, delete specific key
            $this->cache->store()->delete(self::CACHE_KEY);
        }
    }

    /**
     * Seed default settings
     */
    public function seedDefaults(): void
    {
        $defaults = Setting::getDefaults();

        foreach ($defaults as $data) {
            if ($this->has($data['key'])) {
                continue;
            }

            $setting = new Setting();
            $setting->key = $data['key'];
            $setting->value = $data['value'];
            $setting->type = $data['type'];
            $setting->group = $data['group'];
            $setting->label = $data['label'] ?? null;
            $setting->description = $data['description'] ?? null;
            $setting->is_system = $data['is_system'] ?? false;
            $setting->autoload = $data['autoload'] ?? true;

            $this->saveSetting($setting);
        }
    }

    private function loadAutoloadSettings(): void
    {
        if ($this->loaded) {
            return;
        }

        // Try to get from cache first
        $cached = $this->cache->store()->get(self::CACHE_KEY);

        if ($cached !== null) {
            $this->localCache = $cached;
            $this->loaded = true;
            return;
        }

        // Load from database
        $stmt = $this->connection->query(
            "SELECT `key`, value, type FROM settings WHERE autoload = 1"
        );

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->localCache[$row['key']] = $this->castValue($row['value'], $row['type']);
        }

        // Store in persistent cache with tag
        try {
            $this->cache->tags([self::CACHE_TAG])->set(self::CACHE_KEY, $this->localCache, self::CACHE_TTL);
        } catch (\Exception) {
            // If tags not supported, use regular set
            $this->cache->store()->set(self::CACHE_KEY, $this->localCache, self::CACHE_TTL);
        }

        $this->loaded = true;
    }

    private function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    private function valueToString(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool' => $value ? '1' : '0',
            'json', 'array' => json_encode($value),
            default => (string) $value,
        };
    }

    private function detectType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'bool';
        }
        if (is_int($value)) {
            return 'int';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_array($value)) {
            return 'json';
        }
        return 'string';
    }

    private function extractGroup(string $key): string
    {
        $parts = explode('.', $key);
        return $parts[0] ?? 'general';
    }
}
