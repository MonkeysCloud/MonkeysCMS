<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Service\CacheSettingsService;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use PDO;

/**
 * CacheController — Admin UI for cache management.
 *
 * Settings are persisted in the `settings` table under group = 'cache'.
 * The CacheSettingsService reads these at boot to configure the PSR-16 store.
 */
#[RoutePrefix('/admin/cache')]
final class CacheController
{
    /** Default cache settings (used when no DB row exists) */
    private const DEFAULTS = [
        'enabled'        => '0',
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

    /** Maps setting keys to their DB `type` column value */
    private const TYPES = [
        'enabled'        => 'boolean',
        'driver'         => 'string',
        'ttl'            => 'integer',
        'page_cache'     => 'boolean',
        'query_cache'    => 'boolean',
        'view_cache'     => 'boolean',
        'asset_cache'    => 'boolean',
        'redis_host'     => 'string',
        'redis_port'     => 'integer',
        'redis_password' => 'string',
        'redis_database' => 'integer',
    ];

    public function __construct(
        private readonly Renderer $renderer,
        private readonly ConnectionInterface $db,
        private readonly CacheInterface $cache,
        private readonly CacheSettingsService $cacheSettings,
    ) {}

    // ── GET /admin/cache ────────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::cache.index')]
    public function index(ServerRequestInterface $request): Response
    {
        // Invalidate cache when returning from a save/clear action
        $query = $request->getQueryParams();
        if (isset($query['saved']) || isset($query['cleared'])) {
            $this->cache->delete('cms.cache_settings');
            $this->cache->delete('cms.cache_stats');
        }

        $settings = $this->cache->get('cms.cache_settings');
        if ($settings === null) {
            $settings = $this->loadSettings();
            $this->cache->set('cms.cache_settings', $settings, 300);
        }

        $stats = $this->cache->get('cms.cache_stats');
        if ($stats === null) {
            $stats = $this->getCacheStats();
            $this->cache->set('cms.cache_stats', $stats, 60);
        }

        return Response::html($this->renderer->render('admin::cache.index', [
            'title'    => 'Cache Management',
            'settings' => $settings,
            'stats'    => $stats,
        ]));
    }

    // ── POST /admin/cache/settings ──────────────────────────────────────

    #[Route('POST', '/settings', name: 'admin::cache.save')]
    public function save(ServerRequestInterface $request): Response
    {
        $body = $request->getParsedBody() ?? [];

        // Normalise checkbox/toggle values (unchecked fields aren't sent)
        $booleanKeys = ['enabled', 'page_cache', 'query_cache', 'view_cache', 'asset_cache'];

        foreach (self::DEFAULTS as $key => $default) {
            $value = $body[$key] ?? (in_array($key, $booleanKeys) ? '0' : $default);
            $type  = self::TYPES[$key];
            $this->upsertSetting($key, (string) $value, $type);
        }

        // Invalidate the in-memory settings cache so next boot reads fresh values
        $this->cacheSettings->invalidate();

        // Also invalidate the PSR-16 cache immediately
        $this->cache->delete('cms.cache_settings');
        $this->cache->delete('cms.cache_stats');

        // Redirect back with success flash
        return Response::redirect('/admin/cache?saved=1');
    }

    // ── POST /admin/cache/clear ─────────────────────────────────────────

    #[Route('POST', '/clear', name: 'admin::cache.clear')]
    public function clear(ServerRequestInterface $request): Response
    {
        $body   = $request->getParsedBody() ?? [];
        $target = $body['target'] ?? 'all';
        $basePath = dirname(__DIR__, 4); // project root

        $cleared = [];

        if ($target === 'views' || $target === 'all') {
            $this->clearDirectory($basePath . '/var/cache/views');
            $cleared[] = 'views';
        }

        if ($target === 'config' || $target === 'all') {
            $this->clearDirectory($basePath . '/var/cache/config');
            $cleared[] = 'config';
        }

        if ($target === 'data' || $target === 'all') {
            $this->clearDirectory($basePath . '/var/cache/data');
            // Also flush the PSR-16 cache store (covers Redis, Database, etc.)
            $this->cache->clear();
            $cleared[] = 'data';
        }

        if ($target === 'all') {
            // Also clear any other cache dirs
            $this->clearDirectory($basePath . '/var/cache/routes');
            // Flush PSR-16 store in case 'data' wasn't individually targeted
            $this->cache->clear();
            $cleared[] = 'routes';
        }

        $label = $target === 'all' ? 'all' : implode(', ', $cleared);

        return Response::redirect('/admin/cache?cleared=' . urlencode($label));
    }

    // ── Private helpers ─────────────────────────────────────────────────

    /** Load all cache settings, filling in defaults for missing keys. */
    private function loadSettings(): array
    {
        $stmt = $this->db->query(
            "SELECT `key`, `value` FROM settings WHERE `group` = 'cache'"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $settings = [];
        foreach (self::DEFAULTS as $key => $default) {
            $settings[$key] = $rows[$key] ?? $default;
        }

        return $settings;
    }

    /** Insert or update a single cache setting. */
    private function upsertSetting(string $key, string $value, string $type): void
    {
        // Check if row exists
        $stmt = $this->db->query(
            "SELECT id FROM settings WHERE `group` = 'cache' AND `key` = ? LIMIT 1",
            [$key]
        );

        if ($stmt->fetchColumn()) {
            $this->db->execute(
                "UPDATE settings SET `value` = ?, `type` = ? WHERE `group` = 'cache' AND `key` = ?",
                [$value, $type, $key]
            );
        } else {
            $this->db->execute(
                "INSERT INTO settings (`group`, `key`, `value`, `type`, `autoload`) VALUES ('cache', ?, ?, ?, 1)",
                [$key, $value, $type]
            );
        }
    }

    /** Recursively delete all files in a directory (keep the directory itself). */
    private function clearDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }
    }

    /** Get cache directory statistics. */
    private function getCacheStats(): array
    {
        $basePath  = dirname(__DIR__, 4) . '/var/cache';
        $stats     = ['total_size' => 0, 'total_files' => 0, 'dirs' => []];

        $cacheTypes = ['views', 'config', 'data', 'routes'];

        foreach ($cacheTypes as $type) {
            $dir = $basePath . '/' . $type;
            $info = ['size' => 0, 'files' => 0, 'exists' => is_dir($dir)];

            if ($info['exists']) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $info['files']++;
                        $info['size'] += $file->getSize();
                    }
                }
            }

            $stats['total_size']  += $info['size'];
            $stats['total_files'] += $info['files'];
            $stats['dirs'][$type]  = $info;
        }

        return $stats;
    }
}
