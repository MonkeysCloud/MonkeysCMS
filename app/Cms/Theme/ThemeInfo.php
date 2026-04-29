<?php

declare(strict_types=1);

namespace App\Cms\Theme;

/**
 * ThemeInfo — Parsed theme metadata from theme.mlc.
 *
 * A theme can declare:
 *   - base_theme: parent theme to inherit from (views, components, assets, regions)
 *   - libraries: list of global library IDs to attach (from config/libraries.mlc)
 *   - regions: layout regions available for blocks
 *   - assets: CSS/JS files specific to this theme
 *
 * Installation:
 *   1. Place theme folder in themes/contrib/ or themes/custom/
 *   2. Include a theme.mlc config file
 *   3. The ThemeManager auto-discovers it on next request
 */
final class ThemeInfo
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $description,
        public readonly string $version,
        public readonly string $type,       // 'admin' or 'frontend'
        public readonly ?string $parent,    // base_theme name (null = root base theme)
        public readonly array $regions,
        public readonly array $assets,      // ['css' => [...], 'js' => [...]]
        public readonly string $basePath,
        public readonly string $tier,       // 'core', 'contrib', 'custom'
        public readonly array $libraries,   // Global library IDs to attach
    ) {}

    public function getViewsPath(): string
    {
        return $this->basePath . '/views';
    }

    public function getComponentsPath(): string
    {
        return $this->basePath . '/components';
    }

    public function getTemplatesPath(): string
    {
        return $this->basePath . '/templates';
    }

    public function getCssFiles(): array
    {
        return $this->assets['css'] ?? [];
    }

    public function getJsFiles(): array
    {
        return $this->assets['js'] ?? [];
    }

    public function hasRegion(string $region): bool
    {
        return in_array($region, $this->regions, true);
    }

    public function isBaseTheme(): bool
    {
        return $this->parent === null;
    }

    public function isChildTheme(): bool
    {
        return $this->parent !== null;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'version' => $this->version,
            'type' => $this->type,
            'parent' => $this->parent,
            'regions' => $this->regions,
            'assets' => $this->assets,
            'tier' => $this->tier,
            'base_path' => $this->basePath,
            'libraries' => $this->libraries,
            'is_base' => $this->isBaseTheme(),
        ];
    }
}
