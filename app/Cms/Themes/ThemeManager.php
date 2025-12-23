<?php

declare(strict_types=1);

namespace App\Cms\Themes;

use MonkeysLegion\Mlc\Parser;
use RuntimeException;

/**
 * ThemeManager - Manages custom and contrib themes for MonkeysCMS
 *
 * Themes are organized in two directories:
 * - themes/contrib: Community/third-party themes
 * - themes/custom: Site-specific custom themes
 *
 * Each theme contains:
 * - theme.mlc: Theme metadata (preferred) or theme.json (legacy)
 * - views/: Template files (.ml.php)
 * - components/: Reusable components
 * - assets/: CSS, JS, images
 * - layouts/: Base layouts
 */
final class ThemeManager
{
    private array $discoveredThemes = [];
    private ?ThemeInfo $activeThemeInfo = null;
    private ?ThemeInfo $adminThemeInfo = null;

    public function __construct(
        private readonly string $contribPath,
        private readonly string $customPath,
        private readonly string $activeTheme,
        private readonly string $adminTheme,
        private readonly string $cachePath,
        private readonly bool $cacheEnabled = true,
    ) {
        $this->ensureDirectories();
    }

    /**
     * Get active frontend theme info
     */
    public function getActiveTheme(): ThemeInfo
    {
        if ($this->activeThemeInfo === null) {
            $this->activeThemeInfo = $this->loadTheme($this->activeTheme);
        }
        return $this->activeThemeInfo;
    }

    /**
     * Get admin theme info
     */
    public function getAdminTheme(): ThemeInfo
    {
        if ($this->adminThemeInfo === null) {
            $this->adminThemeInfo = $this->loadTheme($this->adminTheme);
        }
        return $this->adminThemeInfo;
    }

    /**
     * Set active theme
     */
    public function setActiveTheme(string $themeName): void
    {
        $theme = $this->loadTheme($themeName);
        $this->activeThemeInfo = $theme;
    }

    /**
     * Discover all available themes
     *
     * @return array<string, ThemeInfo>
     */
    public function discoverThemes(): array
    {
        if (!empty($this->discoveredThemes)) {
            return $this->discoveredThemes;
        }

        // Discover contrib themes
        foreach ($this->scanThemeDirectory($this->contribPath) as $name => $info) {
            $info->source = 'contrib';
            $this->discoveredThemes[$name] = $info;
        }

        // Discover custom themes (override contrib if same name)
        foreach ($this->scanThemeDirectory($this->customPath) as $name => $info) {
            $info->source = 'custom';
            $this->discoveredThemes[$name] = $info;
        }

        return $this->discoveredThemes;
    }

    /**
     * Get all contrib themes
     *
     * @return array<string, ThemeInfo>
     */
    public function getContribThemes(): array
    {
        return array_filter(
            $this->discoverThemes(),
            fn(ThemeInfo $t) => $t->source === 'contrib'
        );
    }

    /**
     * Get all custom themes
     *
     * @return array<string, ThemeInfo>
     */
    public function getCustomThemes(): array
    {
        return array_filter(
            $this->discoverThemes(),
            fn(ThemeInfo $t) => $t->source === 'custom'
        );
    }

    /**
     * Check if theme exists
     */
    public function themeExists(string $themeName): bool
    {
        return $this->findThemePath($themeName) !== null;
    }

    /**
     * Get view paths for the active theme (for Renderer)
     *
     * Returns paths in priority order:
     * 1. Custom theme views
     * 2. Contrib theme views
     * 3. Fallback app views
     *
     * @return string[]
     */
    public function getViewPaths(): array
    {
        $paths = [];
        $theme = $this->getActiveTheme();

        // Theme views
        $paths[] = $theme->path . '/views';

        // Theme layouts
        if (is_dir($theme->path . '/layouts')) {
            $paths[] = $theme->path . '/layouts';
        }

        // Parent theme if exists
        if ($theme->parent !== null) {
            $parentTheme = $this->loadTheme($theme->parent);
            $paths[] = $parentTheme->path . '/views';
            if (is_dir($parentTheme->path . '/layouts')) {
                $paths[] = $parentTheme->path . '/layouts';
            }
        }

        return $paths;
    }

    /**
     * Get component paths for the active theme
     *
     * @return string[]
     */
    public function getComponentPaths(): array
    {
        $paths = [];
        $theme = $this->getActiveTheme();

        // Theme components
        if (is_dir($theme->path . '/components')) {
            $paths[] = $theme->path . '/components';
        }

        // Parent theme components
        if ($theme->parent !== null) {
            $parentTheme = $this->loadTheme($theme->parent);
            if (is_dir($parentTheme->path . '/components')) {
                $paths[] = $parentTheme->path . '/components';
            }
        }

        return $paths;
    }

    /**
     * Get asset URL for theme
     */
    public function getAssetUrl(string $asset, ?string $themeName = null): string
    {
        $theme = $themeName ? $this->loadTheme($themeName) : $this->getActiveTheme();
        return '/themes/' . $theme->source . '/' . $theme->name . '/assets/' . ltrim($asset, '/');
    }

    /**
     * Get theme configuration value
     */
    public function getThemeConfig(string $key, mixed $default = null): mixed
    {
        $theme = $this->getActiveTheme();
        return $theme->config[$key] ?? $default;
    }

    /**
     * Clear theme cache
     */
    public function clearCache(): void
    {
        if (!is_dir($this->cachePath)) {
            return;
        }

        $files = glob($this->cachePath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        // Reset discovered themes
        $this->discoveredThemes = [];
        $this->activeThemeInfo = null;
        $this->adminThemeInfo = null;
    }

    /**
     * Validate theme structure
     *
     * @return array<string> List of validation errors
     */
    public function validateTheme(string $themeName): array
    {
        $errors = [];
        $path = $this->findThemePath($themeName);

        if ($path === null) {
            return ["Theme '{$themeName}' not found"];
        }

        // Check for theme config file
        // Priority: 1. [theme].theme.mlc, 2. theme.mlc, 3. theme.json
        $namedMlcFile = $path . '/' . $themeName . '.theme.mlc';
        $mlcFile = $path . '/theme.mlc';
        $jsonFile = $path . '/theme.json';

        if (file_exists($namedMlcFile)) {
             try {
                $data = $this->parseMlcFile($namedMlcFile);
                if (empty($data['name'])) $errors[] = "{$themeName}.theme.mlc missing 'name' field";
                if (empty($data['version'])) $errors[] = "{$themeName}.theme.mlc missing 'version' field";
            } catch (\Exception $e) {
                $errors[] = "Invalid {$themeName}.theme.mlc: " . $e->getMessage();
            }
        } elseif (file_exists($mlcFile)) {
            try {
                $data = $this->parseMlcFile($mlcFile);
                if (empty($data['name'])) {
                    $errors[] = "theme.mlc missing 'name' field";
                }
                if (empty($data['version'])) {
                    $errors[] = "theme.mlc missing 'version' field";
                }
            } catch (\Exception $e) {
                $errors[] = "Invalid theme.mlc: " . $e->getMessage();
            }
        } elseif (file_exists($jsonFile)) {
            $json = file_get_contents($jsonFile);
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "Invalid theme.json: " . json_last_error_msg();
            } else {
                if (empty($data['name'])) {
                    $errors[] = "theme.json missing 'name' field";
                }
                if (empty($data['version'])) {
                    $errors[] = "theme.json missing 'version' field";
                }
            }
        } else {
            $errors[] = "Missing {$themeName}.theme.mlc, theme.mlc or theme.json";
        }

        // Check views directory
        if (!is_dir($path . '/views')) {
            $errors[] = "Missing views/ directory";
        }

        return $errors;
    }

    /**
     * Create a new theme scaffold
     */
    public function createTheme(
        string $name,
        string $source = 'custom',
        ?string $parentTheme = null,
        array $metadata = []
    ): ThemeInfo {
        $basePath = $source === 'custom' ? $this->customPath : $this->contribPath;
        $themePath = $basePath . '/' . $name;

        if (is_dir($themePath)) {
            throw new RuntimeException("Theme '{$name}' already exists at {$themePath}");
        }

        // Create directory structure
        $directories = [
            '',
            '/views',
            '/views/layouts',
            '/views/partials',
            '/components',
            '/assets',
            '/assets/css',
            '/assets/js',
            '/assets/images',
        ];

        foreach ($directories as $dir) {
            mkdir($themePath . $dir, 0755, true);
        }

        // Create theme.mlc
        $this->createThemeMlc($themePath, $name, $parentTheme, $metadata);

        // Create base layout
        $this->createDefaultLayout($themePath, $name, $parentTheme);

        // Create basic CSS
        $this->createDefaultStyles($themePath, $name);

        // Clear cache
        $this->discoveredThemes = [];

        return $this->loadTheme($name);
    }

    /**
     * Create theme.mlc configuration file
     */
    private function createThemeMlc(string $themePath, string $name, ?string $parentTheme, array $metadata): void
    {
        $description = $metadata['description'] ?? "Custom theme: {$name}";
        $author = $metadata['author'] ?? '';
        $type = $metadata['type'] ?? 'frontend';

        $mlc = <<<MLC
# {$name} Theme
# Generated by MonkeysCMS

name = "{$name}"
version = "1.0.0"
description = "{$description}"
author = "{$author}"
type = "{$type}"

# Parent theme (null for base themes)
parent = {$this->formatMlcValue($parentTheme)}

# Theme regions for block placement
[regions]
header = "Header"
content = "Main Content"
sidebar = "Sidebar"
footer = "Footer"

# Theme configuration options
[config]
primary_color = "#3b82f6"
secondary_color = "#64748b"

# Asset paths
[assets]
css = ["assets/css/theme.css"]
js = ["assets/js/theme.js"]

# Screenshots for theme preview
screenshots = []
MLC;

        file_put_contents($themePath . '/theme.mlc', $mlc);
    }

    /**
     * Format value for MLC file
     */
    private function formatMlcValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            return '"' . addslashes($value) . '"';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        return 'null';
    }

    // ─────────────────────────────────────────────────────────────
    // Private Methods
    // ─────────────────────────────────────────────────────────────

    private function ensureDirectories(): void
    {
        foreach ([$this->contribPath, $this->customPath, $this->cachePath] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    private function loadTheme(string $themeName): ThemeInfo
    {
        // Check cache first
        if ($this->cacheEnabled) {
            $cached = $this->loadFromCache($themeName);
            if ($cached !== null) {
                return $cached;
            }
        }

        $path = $this->findThemePath($themeName);

        if ($path === null) {
            throw new RuntimeException("Theme '{$themeName}' not found in contrib or custom directories");
        }

        // Try named .mlc first, then theme.mlc, then .json
        $namedMlcFile = $path . '/' . $themeName . '.theme.mlc';
        $mlcFile = $path . '/theme.mlc';
        $jsonFile = $path . '/theme.json';

        if (file_exists($namedMlcFile)) {
            $data = $this->parseMlcFile($namedMlcFile);
        } elseif (file_exists($mlcFile)) {
            $data = $this->parseMlcFile($mlcFile);
        } elseif (file_exists($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                    "Invalid theme.json for '{$themeName}': " . json_last_error_msg()
                );
            }
        } else {
            throw new RuntimeException("Theme '{$themeName}' missing configuration file at {$path}");
        }

        $info = new ThemeInfo(
            name: $data['name'] ?? $themeName,
            version: $data['version'] ?? '0.0.0',
            description: $data['description'] ?? '',
            author: $data['author'] ?? '',
            path: $path,
            source: str_contains($path, '/custom/') ? 'custom' : 'contrib',
            parent: $data['parent'] ?? null,
            regions: $data['regions'] ?? [],
            config: $data['config'] ?? [],
            screenshots: $data['screenshots'] ?? [],
            type: $data['type'] ?? 'frontend',
            supports: $data['supports'] ?? [],
            assets: $data['assets'] ?? [],
            menus: $data['menus'] ?? [],
        );

        // Cache the theme info
        if ($this->cacheEnabled) {
            $this->saveToCache($themeName, $info);
        }

        return $info;
    }

    /**
     * Parse MLC configuration file
     */
    private function parseMlcFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $parser = new Parser();
        return $parser->parseContent($content);
    }

    public function findThemePath(string $themeName): ?string
    {
        // Check custom first (higher priority)
        $customPath = $this->customPath . '/' . $themeName;
        if (is_dir($customPath)) {
            return $customPath;
        }

        // Check contrib
        $contribPath = $this->contribPath . '/' . $themeName;
        if (is_dir($contribPath)) {
            return $contribPath;
        }

        return null;
    }

    /**
     * @return array<string, ThemeInfo>
     */
    private function scanThemeDirectory(string $directory): array
    {
        $themes = [];

        if (!is_dir($directory)) {
            return $themes;
        }

        $dirs = glob($directory . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $themeName = basename($dir);
            $namedMlcFile = $dir . '/' . $themeName . '.theme.mlc';
            $mlcFile = $dir . '/theme.mlc';
            $jsonFile = $dir . '/theme.json';

            // Skip if no config file exists
            if (!file_exists($namedMlcFile) && !file_exists($mlcFile) && !file_exists($jsonFile)) {
                continue;
            }

            try {
                $themes[$themeName] = $this->loadTheme($themeName);
            } catch (RuntimeException) {
                // Skip invalid themes
                continue;
            }
        }

        return $themes;
    }

    private function loadFromCache(string $themeName): ?ThemeInfo
    {
        $cacheFile = $this->cachePath . '/' . md5($themeName) . '.cache';

        if (!file_exists($cacheFile)) {
            return null;
        }

        $data = unserialize(file_get_contents($cacheFile));

        if (!$data instanceof ThemeInfo) {
            return null;
        }

        // Validate cache - check if theme config was modified
        // Check both potential config files
        $namedMlcFile = $data->path . '/' . $themeName . '.theme.mlc';
        $mlcFile = $data->path . '/theme.mlc';
        $jsonFile = $data->path . '/theme.json';

        $configFile = null;
        if (file_exists($namedMlcFile)) $configFile = $namedMlcFile;
        elseif (file_exists($mlcFile)) $configFile = $mlcFile;
        elseif (file_exists($jsonFile)) $configFile = $jsonFile;

        if ($configFile && file_exists($configFile) && filemtime($configFile) > filemtime($cacheFile)) {
            return null; // Cache invalidated
        }

        return $data;
    }

    private function saveToCache(string $themeName, ThemeInfo $info): void
    {
        $cacheFile = $this->cachePath . '/' . md5($themeName) . '.cache';
        file_put_contents($cacheFile, serialize($info));
    }

    private function createDefaultLayout(string $themePath, string $themeName, ?string $parent): void
    {
        if ($parent !== null) {
            $content = <<<'ML'
@extends('layouts.base')

@section('content')
    {{ $slot }}
@endsection
ML;
        } else {
            $content = <<<ML
<!DOCTYPE html>
<html lang="{{ \$locale ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '{$themeName}')</title>
    
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    @stack('styles')
</head>
<body class="@yield('body-class')">
    <header class="site-header">
        @yield('header')
    </header>
    
    <main class="site-content">
        @yield('content')
        {{ \$slot }}
    </main>
    
    <aside class="site-sidebar">
        @yield('sidebar')
    </aside>
    
    <footer class="site-footer">
        @yield('footer')
    </footer>
    
    <script src="{{ asset('js/theme.js') }}"></script>
    @stack('scripts')
</body>
</html>
ML;
        }

        file_put_contents($themePath . '/views/layouts/base.ml.php', $content);
    }

    private function createDefaultStyles(string $themePath, string $themeName): void
    {
        $css = <<<CSS
/**
 * Theme: {$themeName}
 * Generated by MonkeysCMS
 */

:root {
    --color-primary: #3b82f6;
    --color-secondary: #64748b;
    --color-background: #ffffff;
    --color-text: #1e293b;
    --font-family: system-ui, -apple-system, sans-serif;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: var(--font-family);
    color: var(--color-text);
    background: var(--color-background);
    line-height: 1.6;
}

.site-header {
    padding: 1rem 2rem;
    border-bottom: 1px solid #e2e8f0;
}

.site-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.site-footer {
    padding: 2rem;
    text-align: center;
    border-top: 1px solid #e2e8f0;
    margin-top: 2rem;
}
CSS;

        file_put_contents($themePath . '/assets/css/theme.css', $css);

        // Empty JS file
        file_put_contents($themePath . '/assets/js/theme.js', "// Theme JavaScript\n");
    }
}
