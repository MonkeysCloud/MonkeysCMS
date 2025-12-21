<?php

declare(strict_types=1);

namespace App\Cli\Command;

use MonkeysLegion\Cache\CacheManager;
use MonkeysLegion\Cli\Attribute\Command;
use MonkeysLegion\Cli\Attribute\Option;
use MonkeysLegion\Cli\IO\Input;
use MonkeysLegion\Cli\IO\Output;

/**
 * CacheStatsCommand - Display cache statistics
 *
 * Usage:
 *   php monkeys cache:stats              # Show stats for default store
 *   php monkeys cache:stats --store=redis  # Show stats for specific store
 */
#[Command(
    name: 'cache:stats',
    description: 'Display cache statistics'
)]
class CacheStatsCommand
{
    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {
    }

    #[Option(name: 'store', shortcut: 's', description: 'Cache store to check')]
    public ?string $store = null;

    public function __invoke(Input $input, Output $output): int
    {
        $storeName = $this->store ?? $this->cacheManager->getDefaultDriver();

        $output->writeln("<info>Cache Statistics</info>");
        $output->writeln("================");
        $output->writeln("");
        $output->writeln("Default Store: <comment>{$this->cacheManager->getDefaultDriver()}</comment>");
        $output->writeln("Current Store: <comment>{$storeName}</comment>");
        $output->writeln("");

        try {
            $store = $this->store
                ? $this->cacheManager->store($this->store)
                : $this->cacheManager->store();

            // Check if store supports stats
            if (method_exists($store, 'getStats')) {
                $stats = $store->getStats();
                $output->writeln("<info>Store Statistics:</info>");
                foreach ($stats as $key => $value) {
                    $output->writeln("  {$key}: <comment>{$value}</comment>");
                }
            }

            // Redis-specific stats
            if (method_exists($store, 'getRedis')) {
                $this->displayRedisStats($store, $output);
            }

            // Memcached-specific stats
            if (method_exists($store, 'getMemcached')) {
                $this->displayMemcachedStats($store, $output);
            }

            // File-specific stats
            if (method_exists($store, 'getPath')) {
                $this->displayFileStats($store, $output);
            }

            $output->writeln("");
            $output->writeln("<success>✓ Stats retrieved successfully</success>");

            return 0;
        } catch (\Exception $e) {
            $output->writeln("<error>✗ Failed to get stats: " . $e->getMessage() . "</error>");
            return 1;
        }
    }

    private function displayRedisStats($store, Output $output): void
    {
        try {
            $redis = $store->getRedis();
            $info = $redis->info();

            $output->writeln("");
            $output->writeln("<info>Redis Statistics:</info>");
            $output->writeln("  Connected Clients: <comment>" . ($info['connected_clients'] ?? 'N/A') . "</comment>");
            $output->writeln("  Used Memory: <comment>" . ($info['used_memory_human'] ?? 'N/A') . "</comment>");
            $output->writeln("  Peak Memory: <comment>" . ($info['used_memory_peak_human'] ?? 'N/A') . "</comment>");
            $output->writeln("  Uptime: <comment>" . $this->formatUptime($info['uptime_in_seconds'] ?? 0) . "</comment>");
            $output->writeln("  Total Connections: <comment>" . ($info['total_connections_received'] ?? 'N/A') . "</comment>");
            $output->writeln("  Keyspace Hits: <comment>" . ($info['keyspace_hits'] ?? 'N/A') . "</comment>");
            $output->writeln("  Keyspace Misses: <comment>" . ($info['keyspace_misses'] ?? 'N/A') . "</comment>");

            // Calculate hit rate
            $hits = (int) ($info['keyspace_hits'] ?? 0);
            $misses = (int) ($info['keyspace_misses'] ?? 0);
            $total = $hits + $misses;
            $hitRate = $total > 0 ? round(($hits / $total) * 100, 2) : 0;
            $output->writeln("  Hit Rate: <comment>{$hitRate}%</comment>");

            // Show database key counts
            foreach ($info as $key => $value) {
                if (str_starts_with($key, 'db')) {
                    $output->writeln("  {$key}: <comment>{$value}</comment>");
                }
            }
        } catch (\Exception $e) {
            $output->writeln("  <warning>Could not retrieve Redis stats: " . $e->getMessage() . "</warning>");
        }
    }

    private function displayMemcachedStats($store, Output $output): void
    {
        try {
            $memcached = $store->getMemcached();
            $stats = $memcached->getStats();

            $output->writeln("");
            $output->writeln("<info>Memcached Statistics:</info>");

            foreach ($stats as $server => $serverStats) {
                $output->writeln("  Server: <comment>{$server}</comment>");
                $output->writeln("    Uptime: <comment>" . $this->formatUptime($serverStats['uptime'] ?? 0) . "</comment>");
                $output->writeln("    Current Items: <comment>" . ($serverStats['curr_items'] ?? 'N/A') . "</comment>");
                $output->writeln("    Total Items: <comment>" . ($serverStats['total_items'] ?? 'N/A') . "</comment>");
                $output->writeln("    Bytes Used: <comment>" . $this->formatBytes($serverStats['bytes'] ?? 0) . "</comment>");
                $output->writeln("    Get Hits: <comment>" . ($serverStats['get_hits'] ?? 'N/A') . "</comment>");
                $output->writeln("    Get Misses: <comment>" . ($serverStats['get_misses'] ?? 'N/A') . "</comment>");

                $hits = (int) ($serverStats['get_hits'] ?? 0);
                $misses = (int) ($serverStats['get_misses'] ?? 0);
                $total = $hits + $misses;
                $hitRate = $total > 0 ? round(($hits / $total) * 100, 2) : 0;
                $output->writeln("    Hit Rate: <comment>{$hitRate}%</comment>");
            }
        } catch (\Exception $e) {
            $output->writeln("  <warning>Could not retrieve Memcached stats: " . $e->getMessage() . "</warning>");
        }
    }

    private function displayFileStats($store, Output $output): void
    {
        try {
            $path = $store->getPath();

            $output->writeln("");
            $output->writeln("<info>File Cache Statistics:</info>");
            $output->writeln("  Path: <comment>{$path}</comment>");

            if (is_dir($path)) {
                $files = 0;
                $totalSize = 0;

                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $files++;
                        $totalSize += $file->getSize();
                    }
                }

                $output->writeln("  Cache Files: <comment>{$files}</comment>");
                $output->writeln("  Total Size: <comment>" . $this->formatBytes($totalSize) . "</comment>");
                $output->writeln("  Writable: <comment>" . (is_writable($path) ? 'Yes' : 'No') . "</comment>");
            } else {
                $output->writeln("  <warning>Cache directory does not exist</warning>");
            }
        } catch (\Exception $e) {
            $output->writeln("  <warning>Could not retrieve file stats: " . $e->getMessage() . "</warning>");
        }
    }

    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }

        return implode(' ', $parts) ?: '< 1m';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }
}
