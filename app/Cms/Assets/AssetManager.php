<?php

declare(strict_types=1);

namespace App\Cms\Assets;

use App\Cms\Fields\Rendering\AssetCollection;

/**
 * AssetManager - Centralized asset management
 * 
 * Manages library definitions, dependencies, and rendering.
 * Supports local fallback vs CDN.
 */
class AssetManager
{
    private AssetCollection $collection;
    private array $libraries;
    private array $config;
    private string $publicPath;

    public function __construct(array $config, AssetCollection $collection)
    {
        $this->config = $config;
        $this->libraries = $config['libraries'] ?? [];
        $this->publicPath = $config['public_path'] ?? 'public/js';
        $this->collection = $collection;
    }

    /**
     * Check if a library is defined
     */
    public function hasLibrary(string $name): bool
    {
        return isset($this->libraries[$name]);
    }

    /**
     * Attach a library by name
     */
    public function attach(string $name): self
    {
        if (!isset($this->libraries[$name])) {
            return $this;
        }

        $lib = $this->libraries[$name];

        // 1. Attach Dependencies First
        if (!empty($lib['dependencies'])) {
            foreach ($lib['dependencies'] as $dep) {
                $this->attach($dep);
            }
        }

        // 2. Resolve URL/Path
        $path = $this->resolvePath($name, $lib);

        // 3. Add to collection
        if (str_ends_with($path, '.css')) {
            $this->collection->addCss($path);
        } else {
            $this->collection->addJs($path);
        }

        return $this;
    }

    /**
     * Resolve the actual URL/Path for a library
     */
    private function resolvePath(string $name, array $lib): string
    {
        $useCdn = $this->config['use_cdn'] ?? true;
        
        // If local fallback is requested and file exists
        if (!$useCdn) {
            $localFilename = $lib['filename'] ?? $name . '.js';
            // Assuming public_path is relative to webroot (e.g. 'js/') or full path?
            // In config it is 'public/js'. For web URL we want '/js/...'
            
            // Map 'public/js' -> '/js' logic or just trust config?
            // Config 'public_path' => 'public/js' is filesystem path usually.
            // We need WEB path. 
            // Let's assume a convention: 'web_path' in config or derive from public_path.
            // If public_path is 'public/js', web path is '/js'.
            
            $webRoot = '/js'; // Default convention if not configured
            if (isset($this->config['web_path'])) {
                $webRoot = $this->config['web_path'];
            }

            return rtrim($webRoot, '/') . '/' . $localFilename;
        }

        // Use CDN / Remote URL
        if (isset($lib['url'])) {
            return str_replace('{version}', $lib['version'], $lib['url']);
        }
        
        // Fallback to local if no URL
        return '/js/' . ($lib['filename'] ?? $name . '.js');
    }

    /**
     * Add raw file path (bypass library system)
     */
    public function addFile(string $path): self
    {
        if (str_ends_with($path, '.css')) {
            $this->collection->addCss($path);
        } else {
            $this->collection->addJs($path);
        }
        return $this;
    }

    /**
     * Merge an external asset collection
     */
    public function mergeCollection(AssetCollection $collection): self
    {
        $this->collection->merge($collection);
        return $this;
    }

    public function renderCss(): string
    {
        return $this->collection->renderCssTags() . $this->collection->renderInlineStyles();
    }

    public function renderJs(): string
    {
        return $this->collection->renderJsTags() . $this->collection->renderInitScripts();
    }
}
