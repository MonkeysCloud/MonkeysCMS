<?php

declare(strict_types=1);

namespace App\Cms\Service;

use MonkeysLegion\Mlc\Config as MlcConfig;
use PDO;

/**
 * CacheSettingsService — Single source of truth for cache configuration.
 *
 * Merges admin DB settings (from `settings` table, group='cache') with
 * .mlc file defaults. Admin settings take precedence when present.
 */
final class CacheSettingsService
{
    /** Default settings when neither DB nor .mlc provides a value */
    private const DEFAULTS = [
        'enabled'        => '1',
        'driver'         => 'file',
        'ttl'            => '3600',
        'page_cache'     => '0',
        'query_cache'    => '0',
        'view_cache'     => '1',
        'asset_cache'    => '0',
        'redis_host'     => '127.0.0.1',
        'redis_port'     => '6379',
        'redis_password' => '',
        'redis_database' => '0',
    ];

    private ?array $settings = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly MlcConfig $mlc,
    ) {}

    // ── Core accessors ────────────────────────────────────────

    public function isEnabled(): bool
    {
        return $this->get('enabled') === '1';
    }

    public function getDriver(): string
    {
        return $this->get('driver');
    }

    public function getTtl(): int
    {
        return max(60, (int) $this->get('ttl'));
    }

    public function isLayerEnabled(string $layer): bool
    {
        return $this->get($layer) === '1';
    }

    // ── Redis configuration ───────────────────────────────────

    public function getRedisConfig(): array
    {
        return [
            'host'     => $this->get('redis_host'),
            'port'     => (int) $this->get('redis_port'),
            'password' => $this->get('redis_password') ?: null,
            'database' => (int) $this->get('redis_database'),
        ];
    }

    // ── All settings ──────────────────────────────────────────

    public function all(): array
    {
        $this->load();
        return $this->settings;
    }

    // ── Cache invalidation ────────────────────────────────────

    public function invalidate(): void
    {
        $this->settings = null;
    }

    // ── Private ───────────────────────────────────────────────

    private function get(string $key): string
    {
        $this->load();
        return $this->settings[$key] ?? self::DEFAULTS[$key] ?? '';
    }

    private function load(): void
    {
        if ($this->settings !== null) {
            return;
        }

        // Start with hardcoded defaults
        $this->settings = self::DEFAULTS;

        // Layer 1: .mlc config overrides
        $mlcDriver = $this->mlc->getString('cache.default');
        if ($mlcDriver !== null && $mlcDriver !== '') {
            $this->settings['driver'] = $mlcDriver;
        }

        $mlcRedisHost = $this->mlc->getString('cache.stores.redis.host');
        if ($mlcRedisHost !== null) {
            $this->settings['redis_host'] = $mlcRedisHost;
        }

        $mlcRedisPort = $this->mlc->getString('cache.stores.redis.port');
        if ($mlcRedisPort !== null) {
            $this->settings['redis_port'] = $mlcRedisPort;
        }

        $mlcViewCache = $this->mlc->get('cache.views.enabled');
        if ($mlcViewCache !== null) {
            $this->settings['view_cache'] = ($mlcViewCache === true || $mlcViewCache === 'true' || $mlcViewCache === '1') ? '1' : '0';
        }

        // Layer 2: Admin DB settings override everything
        try {
            $stmt = $this->pdo->prepare(
                "SELECT `key`, `value` FROM settings WHERE `group` = 'cache'"
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            foreach ($rows as $key => $value) {
                if (array_key_exists($key, $this->settings)) {
                    $this->settings[$key] = $value;
                }
            }
        } catch (\Throwable) {
            // Table may not exist during install — use defaults
        }
    }
}
