<?php

declare(strict_types=1);

namespace App\Cms\Service;

use PDO;

/**
 * SettingsService — Read/write CMS settings from the `settings` table.
 *
 * Settings are stored as key-value pairs grouped by category.
 * Autoload settings are cached in memory after first load.
 */
final class SettingsService
{
    /** @var array<string, string|null> */
    private array $cache = [];
    private bool $loaded = false;

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * Get a single setting value.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $this->loadAutoloaded();

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        // Try loading from DB (non-autoloaded setting)
        $stmt = $this->pdo->prepare('SELECT `value` FROM settings WHERE `key` = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->cache[$key] = $row['value'];
            return $row['value'];
        }

        return $default;
    }

    /**
     * Get all settings as an associative array.
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT `key`, `value` FROM settings ORDER BY `group`, `key`');
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }

    /**
     * Set a single setting.
     */
    public function set(string $key, ?string $value, string $group = 'general', string $type = 'string', bool $autoload = true): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (`group`, `key`, `value`, `type`, `autoload`)
             VALUES (:group, :key, :value, :type, :autoload)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group` = VALUES(`group`), `type` = VALUES(`type`), `autoload` = VALUES(`autoload`)'
        );

        $stmt->execute([
            'group'    => $group,
            'key'      => $key,
            'value'    => $value,
            'type'     => $type,
            'autoload' => (int) $autoload,
        ]);

        $this->cache[$key] = $value;
    }

    /**
     * Set many settings at once.
     *
     * @param array<string, string|null> $settings
     */
    public function setMany(array $settings, string $group = 'general'): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value, $group);
        }
    }

    /**
     * Delete a setting.
     */
    public function forget(string $key): void
    {
        $this->pdo->prepare('DELETE FROM settings WHERE `key` = :key')->execute(['key' => $key]);
        unset($this->cache[$key]);
    }

    /**
     * Load autoloaded settings into cache.
     */
    private function loadAutoloaded(): void
    {
        if ($this->loaded) {
            return;
        }

        try {
            $stmt = $this->pdo->query('SELECT `key`, `value` FROM settings WHERE autoload = 1');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $this->cache[$row['key']] = $row['value'];
            }
        } catch (\Throwable) {
            // Table may not exist during install
        }

        $this->loaded = true;
    }
}
