<?php

declare(strict_types=1);

namespace App\Cms\Themes;

/**
 * ThemeInfo - Data class representing theme metadata
 */
final class ThemeInfo
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string $description,
        public readonly string $author,
        public readonly string $path,
        public string $source, // 'contrib' or 'custom'
        public readonly ?string $parent,
        public readonly array $regions,
        public readonly array $config,
        public readonly array $screenshots = [],
        public readonly string $type = 'frontend', // 'frontend' or 'admin'
        public readonly array $supports = [],
        public readonly array $assets = [],
        public readonly array $menus = [],
    ) {}
    
    /**
     * Check if this theme has a parent theme
     */
    public function hasParent(): bool
    {
        return $this->parent !== null;
    }
    
    /**
     * Check if this is an admin theme
     */
    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }
    
    /**
     * Check if theme supports a feature
     */
    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supports, true);
    }
    
    /**
     * Get the views path for this theme
     */
    public function getViewsPath(): string
    {
        return $this->path . '/views';
    }
    
    /**
     * Get the components path for this theme
     */
    public function getComponentsPath(): string
    {
        return $this->path . '/components';
    }
    
    /**
     * Get the assets path for this theme
     */
    public function getAssetsPath(): string
    {
        return $this->path . '/assets';
    }
    
    /**
     * Get CSS asset files
     */
    public function getCssAssets(): array
    {
        return $this->assets['css'] ?? [];
    }
    
    /**
     * Get JS asset files
     */
    public function getJsAssets(): array
    {
        return $this->assets['js'] ?? [];
    }
    
    /**
     * Get a config value
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Get available regions
     * 
     * @return array<string, string> Region ID => Label
     */
    public function getRegions(): array
    {
        return $this->regions;
    }
    
    /**
     * Check if theme has a specific region
     */
    public function hasRegion(string $regionId): bool
    {
        return isset($this->regions[$regionId]);
    }
    
    /**
     * Get menu locations
     */
    public function getMenuLocations(): array
    {
        return $this->menus;
    }
    
    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'source' => $this->source,
            'type' => $this->type,
            'parent' => $this->parent,
            'regions' => $this->regions,
            'config' => $this->config,
            'screenshots' => $this->screenshots,
            'supports' => $this->supports,
            'assets' => $this->assets,
            'menus' => $this->menus,
            'paths' => [
                'views' => $this->getViewsPath(),
                'components' => $this->getComponentsPath(),
                'assets' => $this->getAssetsPath(),
            ],
        ];
    }
}
