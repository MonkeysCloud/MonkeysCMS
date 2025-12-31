<?php

declare(strict_types=1);

namespace App\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;

/**
 * AssetsUpdateCommand - Download and update JS/CSS libraries
 *
 * Usage:
 *   php cms assets:update           # Update all enabled assets
 *   php cms assets:update htmx      # Update specific library
 *   php cms assets:update --list    # List all configured assets
 *   php cms assets:update --check   # Check for newer versions
 */
#[CommandAttr('assets:update', 'Download and update JS/CSS libraries')]
final class AssetsUpdateCommand extends Command
{
    protected function handle(): int
    {
        $args = $this->getArguments();
        $library = $args[0] ?? null;
        
        $list = in_array('--list', $args) || in_array('-l', $args);
        $check = in_array('--check', $args) || in_array('-c', $args);
        $force = in_array('--force', $args) || in_array('-f', $args);

        $configPath = $this->basePath('config/assets.php');
        
        if (!file_exists($configPath)) {
            $this->error('Config file not found: config/assets.php');
            return self::FAILURE;
        }

        $config = require $configPath;
        $libraries = $config['libraries'] ?? [];
        $publicPath = $this->basePath($config['public_path'] ?? 'public/js');

        // Ensure directory exists
        if (!is_dir($publicPath)) {
            mkdir($publicPath, 0755, true);
        }

        // List mode
        if ($list) {
            return $this->listAssets($libraries, $publicPath);
        }

        // Check mode
        if ($check) {
            return $this->checkVersions($libraries, $publicPath);
        }

        // Filter to specific library if provided
        if ($library && !str_starts_with($library, '-')) {
            if (!isset($libraries[$library])) {
                $this->error("Unknown library: {$library}");
                $this->info("Available: " . implode(', ', array_keys($libraries)));
                return self::FAILURE;
            }
            $libraries = [$library => $libraries[$library]];
        }

        // Download/update libraries
        $this->info('Updating assets...');
        $this->newLine();

        $updated = 0;
        $skipped = 0;

        foreach ($libraries as $name => $lib) {
            // Skip disabled libraries
            if (isset($lib['enabled']) && $lib['enabled'] === false) {
                $this->comment("  ○ {$name} (disabled)");
                $skipped++;
                continue;
            }

            $version = $lib['version'];
            $url = str_replace('{version}', $version, $lib['url']);
            $filename = $lib['filename'];
            $targetPath = $publicPath . '/' . $filename;

            // Check if file exists and not forcing
            if (file_exists($targetPath) && !$force) {
                // Check if version matches
                $currentVersion = $this->detectVersion($targetPath, $name);
                if ($currentVersion === $version) {
                    $this->success("  ✓ {$name} v{$version} (current)");
                    $skipped++;
                    continue;
                }
            }

            // Download
            $this->info("  ↓ {$name} v{$version} downloading...");
            
            $content = $this->download($url);
            if ($content === false) {
                $this->error("    ✗ Failed to download from {$url}");
                continue;
            }

            // Add version header
            $header = "/* {$name} v{$version} | Downloaded by MonkeysCMS assets:update */\n";
            file_put_contents($targetPath, $header . $content);

            $size = $this->formatBytes(strlen($content));
            $this->success("    ✓ Saved ({$size})");
            $updated++;
        }

        $this->newLine();
        $this->success("Done! Updated: {$updated}, Skipped: {$skipped}");

        return self::SUCCESS;
    }

    private function listAssets(array $libraries, string $publicPath): int
    {
        $this->info('Configured Assets:');
        $this->newLine();

        foreach ($libraries as $name => $lib) {
            $version = $lib['version'];
            $filename = $lib['filename'];
            $targetPath = $publicPath . '/' . $filename;
            $enabled = !isset($lib['enabled']) || $lib['enabled'] === true;
            
            $status = $enabled ? '✓ enabled' : '○ disabled';
            $exists = file_exists($targetPath) ? '✓' : '✗';
            
            $this->line("  {$exists} {$name} v{$version} [{$status}]");
            if (isset($lib['description'])) {
                $this->comment("      {$lib['description']}");
            }
        }

        $this->newLine();
        return self::SUCCESS;
    }

    private function checkVersions(array $libraries, string $publicPath): int
    {
        $this->info('Checking asset versions...');
        $this->newLine();

        foreach ($libraries as $name => $lib) {
            $filename = $lib['filename'];
            $targetPath = $publicPath . '/' . $filename;
            $configVersion = $lib['version'];

            if (!file_exists($targetPath)) {
                $this->error("  ✗ {$name} - Not installed");
                continue;
            }

            $currentVersion = $this->detectVersion($targetPath, $name);
            
            if ($currentVersion === $configVersion) {
                $this->success("  ✓ {$name} v{$configVersion} (up to date)");
            } elseif ($currentVersion) {
                $this->warning("  ↑ {$name} v{$currentVersion} → v{$configVersion} (update available)");
            } else {
                $this->comment("  ? {$name} version unknown, config: v{$configVersion}");
            }
        }

        $this->newLine();
        return self::SUCCESS;
    }

    private function detectVersion(string $path, string $name): ?string
    {
        $content = file_get_contents($path, false, null, 0, 500);
        
        // Try to extract version from our header
        if (preg_match('/\* ' . preg_quote($name) . ' v([\d.]+)/', $content, $matches)) {
            return $matches[1];
        }
        
        // Try generic version patterns
        if (preg_match('/v([\d.]+)/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function download(string $url): string|false
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'user_agent' => 'MonkeysCMS/1.0'
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        return @file_get_contents($url, false, $context);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }

    private function basePath(string $path = ''): string
    {
        $base = dirname(__DIR__, 3);
        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }

    private function getArguments(): array
    {
        global $argv;
        return array_slice($argv, 2);
    }

    private function success(string $message): void
    {
        $this->line("<fg=green>{$message}</>");
    }

    private function warning(string $message): void
    {
        $this->line("<fg=yellow>{$message}</>");
    }

    private function comment(string $message): void
    {
        $this->line("<fg=gray>{$message}</>");
    }

    private function newLine(): void
    {
        $this->line('');
    }
}
