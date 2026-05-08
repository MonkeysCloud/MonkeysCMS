<?php

declare(strict_types=1);

namespace App\Cms\Config;

use App\Cms\Plugin\PluginManager;
use App\Cms\Theme\ThemeManager;
use MonkeysLegion\DI\Attributes\Singleton;

/**
 * ConfigManager — Orchestrates config export/import using auto-discovered collectors.
 *
 * Collectors are discovered from:
 *   1. Core built-in collectors (always registered)
 *   2. Plugins implementing ConfigCollectorInterface
 *   3. Themes with config blocks in theme.mlc
 *
 * Export writes one .mlc file per config item to config/sync/.
 * Import reads from config/sync/ or uploaded archive.
 */
#[Singleton]
final class ConfigManager
{
    /** @var array<string, ConfigCollectorInterface> */
    private array $collectors = [];

    private bool $discovered = false;

    public function __construct(
        private readonly string $basePath,
        private readonly ?PluginManager $pluginManager = null,
        private readonly ?ThemeManager $themeManager = null,
    ) {}

    // ── Registration ────────────────────────────────────────────────────

    /**
     * Register a collector (core built-ins use this).
     */
    public function registerCollector(ConfigCollectorInterface $collector): void
    {
        $this->collectors[$collector->getKey()] = $collector;
    }

    /**
     * Auto-discover collectors from plugins and themes.
     */
    public function discoverCollectors(): void
    {
        if ($this->discovered) return;

        // From plugins
        if ($this->pluginManager) {
            foreach ($this->pluginManager->getLoaded() as $plugin) {
                // Plugin itself implements the interface
                if ($plugin instanceof ConfigCollectorInterface) {
                    $this->collectors[$plugin->getKey()] = $plugin;
                }

                // Plugin returns additional collectors
                if (method_exists($plugin, 'getConfigCollectors')) {
                    foreach ($plugin->getConfigCollectors() as $collector) {
                        if ($collector instanceof ConfigCollectorInterface) {
                            $this->collectors[$collector->getKey()] = $collector;
                        }
                    }
                }
            }
        }

        $this->discovered = true;
    }

    /**
     * @return array<string, ConfigCollectorInterface>
     */
    public function getAvailableCollectors(): array
    {
        $this->discoverCollectors();
        return $this->collectors;
    }

    // ── Export ───────────────────────────────────────────────────────────

    /**
     * Export config to the sync directory.
     *
     * @param string[] $sections  Collector keys to export (empty = all)
     * @return list<string>       List of files written
     */
    public function export(array $sections = []): array
    {
        $this->discoverCollectors();
        $syncDir = $this->getSyncDir();

        if (!is_dir($syncDir)) {
            mkdir($syncDir, 0755, true);
        }

        $collectors = empty($sections)
            ? $this->collectors
            : array_intersect_key($this->collectors, array_flip($sections));

        $files = [];

        foreach ($collectors as $collector) {
            $exported = $collector->export();

            foreach ($exported as $id => $data) {
                $filename = $collector->getKey() . '.' . $id . '.mlc';
                $content = MlcWriter::serialize($collector->getKey(), $id, $data);
                file_put_contents($syncDir . '/' . $filename, $content);
                $files[] = $filename;
            }
        }

        // Write _meta.mlc
        $meta = MlcWriter::serialize('meta', 'export', [
            'version'     => '1.0',
            'cms_version' => '2.0.0',
            'exported_at' => date('c'),
            'sections'    => array_keys($collectors),
            'file_count'  => count($files),
        ]);
        file_put_contents($syncDir . '/_meta.mlc', $meta);
        $files[] = '_meta.mlc';

        return $files;
    }

    /**
     * Export as a zip archive.
     *
     * @return string  Path to the created zip file
     */
    public function exportArchive(array $sections = []): string
    {
        $files = $this->export($sections);
        $syncDir = $this->getSyncDir();
        $archivePath = $this->basePath . '/storage/config-export-' . date('Ymd-His') . '.zip';

        $zip = new \ZipArchive();
        $zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($files as $file) {
            $fullPath = $syncDir . '/' . $file;
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, 'config/sync/' . $file);
            }
        }

        $zip->close();
        return $archivePath;
    }

    // ── Import ──────────────────────────────────────────────────────────

    /**
     * Import from the sync directory.
     *
     * @param bool $overwrite  Replace existing config items
     * @param bool $dryRun     Preview without writing
     * @param bool $sync       Full sync: overwrite existing AND delete items not in sync/
     */
    public function import(bool $overwrite = false, bool $dryRun = false, bool $sync = false): ImportResult
    {
        $this->discoverCollectors();
        $syncDir = $this->getSyncDir();
        $result = new ImportResult();

        if (!is_dir($syncDir)) {
            $result->addError("Sync directory not found: {$syncDir}");
            return $result;
        }

        // Sync mode implies overwrite
        if ($sync) {
            $overwrite = true;
        }

        // Read all .mlc files from sync dir
        $filesByCollector = $this->readSyncDirectory($syncDir);

        // Sort collectors by dependencies
        $ordered = $this->sortByDependencies();

        foreach ($ordered as $collectorKey) {
            if (!isset($this->collectors[$collectorKey])) {
                continue;
            }

            $collector = $this->collectors[$collectorKey];
            $syncItems = $filesByCollector[$collectorKey] ?? [];

            if ($dryRun) {
                foreach ($syncItems as $id => $values) {
                    $result->addCreated("[dry-run] {$collectorKey}.{$id}");
                }
                continue;
            }

            if (!empty($syncItems)) {
                $collectorResult = $collector->import($syncItems, $overwrite);
                $result->merge($collectorResult);
            }
        }

        return $result;
    }

    /**
     * Import from a zip archive.
     */
    public function importArchive(string $archivePath, bool $overwrite = false): ImportResult
    {
        $result = new ImportResult();

        if (!file_exists($archivePath)) {
            $result->addError("Archive not found: {$archivePath}");
            return $result;
        }

        $syncDir = $this->getSyncDir();
        $zip = new \ZipArchive();

        if ($zip->open($archivePath) !== true) {
            $result->addError("Failed to open archive.");
            return $result;
        }

        // Extract to sync dir
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Strip prefix like "config/sync/"
            $relative = preg_replace('#^config/sync/#', '', $name);
            if ($relative && str_ends_with($relative, '.mlc')) {
                $content = $zip->getFromIndex($i);
                file_put_contents($syncDir . '/' . $relative, $content);
            }
        }

        $zip->close();

        return $this->import($overwrite);
    }

    // ── Diff ────────────────────────────────────────────────────────────

    /**
     * Compare sync directory with current active config.
     *
     * @return array<string, array{status: string, sync: ?array, active: ?array}>
     */
    public function diff(): array
    {
        $this->discoverCollectors();
        $syncDir = $this->getSyncDir();
        $diff = [];

        if (!is_dir($syncDir)) {
            return $diff;
        }

        $syncData = $this->readSyncDirectory($syncDir);

        foreach ($this->collectors as $key => $collector) {
            $active = $collector->export();
            $sync = $syncData[$key] ?? [];

            // Items only in sync (would be created)
            foreach ($sync as $id => $values) {
                if (!isset($active[$id])) {
                    $diff["{$key}.{$id}"] = [
                        'status' => 'create',
                        'sync'   => $values,
                        'active' => null,
                    ];
                } elseif (self::normalizeForDiff($values) !== self::normalizeForDiff($active[$id])) {
                    $diff["{$key}.{$id}"] = [
                        'status' => 'update',
                        'sync'   => $values,
                        'active' => $active[$id],
                    ];
                }
            }

            // Items only in active (would be orphaned)
            foreach ($active as $id => $values) {
                if (!isset($sync[$id])) {
                    $diff["{$key}.{$id}"] = [
                        'status' => 'orphan',
                        'sync'   => null,
                        'active' => $values,
                    ];
                }
            }
        }

        return $diff;
    }

    // ── Internal ────────────────────────────────────────────────────────

    /**
     * Normalize values for diff comparison.
     * MLC parser returns strings for numbers/bools; this ensures both sides
     * are compared with the same types by JSON-encoding both.
     */
    private static function normalizeForDiff(mixed $value): string
    {
        if (is_array($value)) {
            // Normalize the structure first
            $value = self::normalizeArray($value);
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return self::castToString($value);
    }

    /**
     * Recursively normalize an array for comparison:
     * - Cast all scalars to strings
     * - Wrap single associative arrays inside known list keys (items, terms) into lists
     * - Sort keys for consistent comparison
     */
    private static function normalizeArray(array $arr): array
    {
        // Known keys that should always be lists of objects
        static $listKeys = ['items', 'terms', 'permissions'];

        $result = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                if (in_array($k, $listKeys, true) && !array_is_list($v)) {
                    // Single assoc should be wrapped as a list of one
                    $v = [$v];
                }
                if (array_is_list($v)) {
                    // Normalize each list item
                    $result[$k] = array_map(function ($item) {
                        return is_array($item) ? self::normalizeArray($item) : self::castToString($item);
                    }, $v);
                } else {
                    $result[$k] = self::normalizeArray($v);
                }
            } else {
                $result[$k] = self::castToString($v);
            }
        }
        ksort($result);
        return $result;
    }

    private static function castToString(mixed $v): string
    {
        if (is_bool($v)) return $v ? 'true' : 'false';
        if (is_null($v)) return 'null';
        return (string) $v;
    }


    public function getSyncDir(): string
    {
        return $this->basePath . '/config/sync';
    }

    /**
     * Read all .mlc files from the sync directory, grouped by collector key.
     *
     * @return array<string, array<string, array>>
     */
    private function readSyncDirectory(string $syncDir): array
    {
        $grouped = [];
        $files = glob($syncDir . '/*.mlc') ?: [];

        foreach ($files as $file) {
            $basename = basename($file, '.mlc');

            // Skip meta file
            if ($basename === '_meta') {
                continue;
            }

            // Parse: "collector_key.identifier" → key=collector_key, id=identifier
            $dotPos = strpos($basename, '.');
            if ($dotPos === false) continue;

            $collectorKey = substr($basename, 0, $dotPos);
            $content = file_get_contents($file);
            $parsed = MlcWriter::parse($content);

            foreach ($parsed as $fullKey => $data) {
                $id = $data['_id'] ?? substr($basename, $dotPos + 1);
                unset($data['_type'], $data['_id']);
                $grouped[$collectorKey][$id] = $data;
            }
        }

        return $grouped;
    }

    /**
     * Topological sort of collectors by dependencies.
     *
     * @return string[]
     */
    private function sortByDependencies(): array
    {
        $sorted = [];
        $visited = [];

        $visit = function (string $key) use (&$visit, &$sorted, &$visited): void {
            if (isset($visited[$key])) return;
            $visited[$key] = true;

            $collector = $this->collectors[$key] ?? null;
            if ($collector) {
                foreach ($collector->getDependencies() as $dep) {
                    $visit($dep);
                }
            }

            $sorted[] = $key;
        };

        foreach (array_keys($this->collectors) as $key) {
            $visit($key);
        }

        return $sorted;
    }
}
