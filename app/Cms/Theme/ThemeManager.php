<?php

declare(strict_types=1);

namespace App\Cms\Theme;

/**
 * ThemeManager — Discovers, loads, and manages CMS themes.
 *
 * Features:
 *   - Tiered theme hierarchy: core → contrib → custom
 *   - Full inheritance chain: child theme → parent → grandparent → base
 *   - Global libraries: shared CSS/JS defined in config/libraries.mlc
 *   - Asset aggregation: merges libraries + parent assets + theme assets in order
 *   - View/component resolution through the full inheritance chain
 *   - Installable theme structure: drop a theme folder + theme.mlc → auto-discovered
 *
 * Theme tiers (resolution: custom overrides contrib overrides core):
 *   themes/core/    — Built-in (shipped with CMS)
 *   themes/contrib/ — Community/third-party (installable)
 *   themes/custom/  — User-created
 */
final class ThemeManager
{
    /** @var array<string, ThemeInfo> */
    private array $themes = [];

    /** @var array<string, ThemeLibrary> */
    private array $libraries = [];

    private ?ThemeInfo $activeTheme = null;
    private ?ThemeInfo $adminTheme = null;

    public function __construct(
        private readonly string $basePath,
        private readonly string $activeThemeName = 'front',
        private readonly string $adminThemeName = 'admin',
    ) {
        $this->loadLibraries();
        $this->discover();
    }

    // ── Library System ──────────────────────────────────────────────────

    /**
     * Load global libraries from config/libraries.mlc
     */
    private function loadLibraries(): void
    {
        $path = $this->basePath . '/config/libraries.mlc';
        if (!file_exists($path)) return;

        $content = file_get_contents($path);

        // Strip comments
        $content = preg_replace('/^\s*#.*$/m', '', $content);

        // Parse library blocks
        preg_match_all(
            '/library\s+"([^"]+)"\s*\{((?:[^{}]|\{[^{}]*\})*)\}/s',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $id = $match[1];
            $body = $match[2];

            $this->libraries[$id] = new ThemeLibrary(
                id: $id,
                description: $this->extractMlcValue($body, 'description') ?? '',
                css: $this->extractMlcArray($body, 'css'),
                js: $this->extractMlcArray($body, 'js'),
                weight: (int) ($this->extractMlcValue($body, 'weight') ?? '0'),
                required: ($this->extractMlcValue($body, 'required') ?? 'false') === 'true',
                module: ($this->extractMlcValue($body, 'module') ?? 'false') === 'true',
            );
        }
    }

    /**
     * Get a library by ID
     */
    public function getLibrary(string $id): ?ThemeLibrary
    {
        return $this->libraries[$id] ?? null;
    }

    /**
     * Get all libraries that are required (always loaded)
     *
     * @return ThemeLibrary[]
     */
    public function getRequiredLibraries(): array
    {
        $required = array_filter($this->libraries, fn(ThemeLibrary $l) => $l->required);
        uasort($required, fn(ThemeLibrary $a, ThemeLibrary $b) => $a->weight <=> $b->weight);
        return $required;
    }

    /**
     * Get all registered libraries
     *
     * @return array<string, ThemeLibrary>
     */
    public function getAllLibraries(): array
    {
        return $this->libraries;
    }

    // ── Theme Discovery ─────────────────────────────────────────────────

    /**
     * Discover all themes across all tiers
     */
    private function discover(): void
    {
        $tiers = ['core', 'contrib', 'custom'];

        foreach ($tiers as $tier) {
            $tierPath = $this->basePath . '/themes/' . $tier;
            if (!is_dir($tierPath)) {
                continue;
            }

            $dirs = scandir($tierPath);
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') continue;

                $themePath = $tierPath . '/' . $dir;
                $mlcFile = $themePath . '/theme.mlc';

                if (!is_dir($themePath) || !file_exists($mlcFile)) continue;

                $info = $this->parseThemeMlc($mlcFile, $themePath, $tier);
                if ($info) {
                    // Higher tier overrides lower (custom > contrib > core)
                    $this->themes[$info->name] = $info;
                }
            }
        }

        // Set active themes
        $this->activeTheme = $this->themes[$this->activeThemeName] ?? null;
        $this->adminTheme = $this->themes[$this->adminThemeName] ?? null;
    }

    /**
     * Parse a theme.mlc file into a ThemeInfo
     */
    private function parseThemeMlc(string $path, string $basePath, string $tier): ?ThemeInfo
    {
        $content = file_get_contents($path);
        if (!$content) return null;

        // Strip comments
        $content = preg_replace('/^\s*#.*$/m', '', $content);

        $name = $this->extractMlcValue($content, 'name') ?? basename($basePath);
        $label = $this->extractMlcValue($content, 'label') ?? ucfirst($name);
        $description = $this->extractMlcValue($content, 'description') ?? '';
        $version = $this->extractMlcValue($content, 'version') ?? '1.0.0';
        $type = $this->extractMlcValue($content, 'type') ?? 'frontend';
        $parent = $this->extractMlcValue($content, 'base_theme')
            ?? $this->extractMlcValue($content, 'parent');
        $regions = $this->extractMlcArray($content, 'regions');
        $cssList = $this->extractMlcArray($content, 'css');
        $jsList = $this->extractMlcArray($content, 'js');
        $libraries = $this->extractMlcArray($content, 'libraries');

        return new ThemeInfo(
            name: $name,
            label: $label,
            description: $description,
            version: $version,
            type: $type,
            parent: ($parent === 'null' || $parent === null) ? null : $parent,
            regions: $regions,
            assets: ['css' => $cssList, 'js' => $jsList],
            basePath: $basePath,
            tier: $tier,
            libraries: $libraries,
        );
    }

    // ── Public API ──────────────────────────────────────────────────────

    public function getActiveTheme(): ?ThemeInfo
    {
        return $this->activeTheme;
    }

    public function getAdminTheme(): ?ThemeInfo
    {
        return $this->adminTheme;
    }

    public function getTheme(string $name): ?ThemeInfo
    {
        return $this->themes[$name] ?? null;
    }

    /** @return array<string, ThemeInfo> */
    public function getAllThemes(): array
    {
        return $this->themes;
    }

    /** @return ThemeInfo[] */
    public function getFrontendThemes(): array
    {
        return array_filter($this->themes, fn(ThemeInfo $t) => $t->type === 'frontend');
    }

    /** @return ThemeInfo[] */
    public function getAdminThemes(): array
    {
        return array_filter($this->themes, fn(ThemeInfo $t) => $t->type === 'admin');
    }

    // ── Inheritance Chain ───────────────────────────────────────────────

    /**
     * Get the full inheritance chain for a theme (child → parent → grandparent...)
     *
     * @return ThemeInfo[]
     */
    public function getInheritanceChain(ThemeInfo $theme): array
    {
        $chain = [$theme];
        $visited = [$theme->name];

        $current = $theme;
        while ($current->parent && !in_array($current->parent, $visited, true)) {
            $parent = $this->themes[$current->parent] ?? null;
            if (!$parent) break;

            $chain[] = $parent;
            $visited[] = $parent->name;
            $current = $parent;
        }

        return $chain;
    }

    // ── View Resolution ─────────────────────────────────────────────────

    /**
     * Resolve a view file path through the full inheritance chain.
     *
     * Checks: active theme → parent → grandparent → ... → resources/views
     */
    public function resolveView(string $viewName, bool $isAdmin = false): ?string
    {
        $theme = $isAdmin ? $this->adminTheme : $this->activeTheme;
        $dotToSlash = str_replace('.', '/', $viewName) . '.ml.php';

        if ($theme) {
            $chain = $this->getInheritanceChain($theme);
            foreach ($chain as $t) {
                $path = $t->getViewsPath() . '/' . $dotToSlash;
                if (file_exists($path)) return $path;
            }
        }

        // Fallback to resources/views
        $fallback = $this->basePath . '/resources/views/' . $dotToSlash;
        if (file_exists($fallback)) return $fallback;

        return null;
    }

    /**
     * Resolve a component path through the full inheritance chain
     */
    public function resolveComponent(string $componentName, bool $isAdmin = false): ?string
    {
        $theme = $isAdmin ? $this->adminTheme : $this->activeTheme;
        $dotToSlash = str_replace('.', '/', $componentName) . '.ml.php';

        if ($theme) {
            $chain = $this->getInheritanceChain($theme);
            foreach ($chain as $t) {
                $path = $t->getComponentsPath() . '/' . $dotToSlash;
                if (file_exists($path)) return $path;
            }
        }

        $fallback = $this->basePath . '/resources/views/components/' . $dotToSlash;
        if (file_exists($fallback)) return $fallback;

        return null;
    }

    // ── Asset Aggregation ───────────────────────────────────────────────

    /**
     * Get ALL assets for a theme — aggregated from:
     *   1. Required global libraries (lowest weight first)
     *   2. Theme-declared libraries
     *   3. Parent theme's own CSS/JS (bottom of chain first)
     *   4. Active theme's own CSS/JS (last — highest priority)
     *
     * @return array{css: string[], js: string[], modules: string[]}
     */
    public function getAggregatedAssets(bool $isAdmin = false): array
    {
        $theme = $isAdmin ? $this->adminTheme : $this->activeTheme;
        $css = [];
        $js = [];
        $modules = [];

        // 1. Required libraries (always loaded)
        foreach ($this->getRequiredLibraries() as $lib) {
            $css = array_merge($css, $this->resolveLibraryCss($lib));
            if ($lib->module) {
                $modules = array_merge($modules, $this->resolveLibraryJs($lib));
            } else {
                $js = array_merge($js, $this->resolveLibraryJs($lib));
            }
        }

        if (!$theme) {
            return ['css' => $css, 'js' => $js, 'modules' => $modules];
        }

        $chain = $this->getInheritanceChain($theme);

        // 2. Collect libraries from all themes in the chain (parent first)
        $themeLibraries = [];
        foreach (array_reverse($chain) as $t) {
            foreach ($t->libraries as $libId) {
                if (!isset($themeLibraries[$libId])) {
                    $lib = $this->libraries[$libId] ?? null;
                    if ($lib && !$lib->required) { // Skip already-included required ones
                        $themeLibraries[$libId] = $lib;
                    }
                }
            }
        }

        // Sort by weight
        uasort($themeLibraries, fn(ThemeLibrary $a, ThemeLibrary $b) => $a->weight <=> $b->weight);

        foreach ($themeLibraries as $lib) {
            $css = array_merge($css, $this->resolveLibraryCss($lib));
            if ($lib->module) {
                $modules = array_merge($modules, $this->resolveLibraryJs($lib));
            } else {
                $js = array_merge($js, $this->resolveLibraryJs($lib));
            }
        }

        // 3. Theme CSS/JS from the chain (parent first, child last)
        foreach (array_reverse($chain) as $t) {
            $prefix = '/themes/' . $t->tier . '/' . $t->name;
            foreach ($t->getCssFiles() as $file) {
                $css[] = $prefix . '/' . $file;
            }
            foreach ($t->getJsFiles() as $file) {
                $js[] = $prefix . '/' . $file;
            }
        }

        // Deduplicate while preserving order
        return [
            'css' => array_values(array_unique($css)),
            'js' => array_values(array_unique($js)),
            'modules' => array_values(array_unique($modules)),
        ];
    }

    /**
     * Legacy compat: get just the theme's own assets (no libraries)
     */
    public function getThemeAssets(bool $isAdmin = false): array
    {
        $theme = $isAdmin ? $this->adminTheme : $this->activeTheme;
        if (!$theme) return ['css' => [], 'js' => []];

        $prefix = '/themes/' . $theme->tier . '/' . $theme->name;

        return [
            'css' => array_map(fn(string $f) => $prefix . '/' . $f, $theme->getCssFiles()),
            'js' => array_map(fn(string $f) => $prefix . '/' . $f, $theme->getJsFiles()),
        ];
    }

    /**
     * Get regions — inherits from parent if current theme doesn't define them
     */
    public function getRegions(bool $isAdmin = false): array
    {
        $theme = $isAdmin ? $this->adminTheme : $this->activeTheme;
        if (!$theme) return [];

        if (!empty($theme->regions)) return $theme->regions;

        // Inherit from parent chain
        $chain = $this->getInheritanceChain($theme);
        foreach ($chain as $t) {
            if (!empty($t->regions)) return $t->regions;
        }

        return [];
    }

    // ── Library Path Resolution ─────────────────────────────────────────

    private function resolveLibraryCss(ThemeLibrary $lib): array
    {
        return array_map(fn(string $f) => '/' . ltrim($f, '/'), $lib->css);
    }

    private function resolveLibraryJs(ThemeLibrary $lib): array
    {
        return array_map(fn(string $f) => '/' . ltrim($f, '/'), $lib->js);
    }

    // ── MLC Helpers ─────────────────────────────────────────────────────

    private function extractMlcValue(string $content, string $key): ?string
    {
        if (preg_match('/^\s*' . preg_quote($key) . '\s*=\s*"?([^"\n]+)"?\s*$/m', $content, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractMlcArray(string $content, string $key): array
    {
        if (preg_match('/\b' . preg_quote($key) . '\s*=\s*\[(.*?)\]/s', $content, $m)) {
            preg_match_all('/"([^"]+)"/', $m[1], $items);
            return $items[1] ?? [];
        }
        return [];
    }
}
