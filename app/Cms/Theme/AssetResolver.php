<?php

declare(strict_types=1);

namespace App\Cms\Theme;

/**
 * AssetResolver — Resolves asset URLs from the Vite build manifest
 * and theme asset declarations.
 *
 * In dev mode: proxies through Vite dev server
 * In prod mode: reads the hashed filenames from manifest.json
 */
final class AssetResolver
{
    private ?array $manifest = null;

    public function __construct(
        private readonly string $basePath,
        private readonly bool $isDev = false,
        private readonly string $devServerUrl = 'http://localhost:5173',
    ) {}

    /**
     * Get the URL for a Vite-managed entry point
     */
    public function entry(string $name): string
    {
        if ($this->isDev) {
            return $this->devServerUrl . '/' . $name;
        }

        $manifest = $this->getManifest();
        $entry = $manifest[$name] ?? null;

        return $entry ? '/build/' . ($entry['file'] ?? $name) : '/build/' . $name;
    }

    /**
     * Get CSS URLs for a Vite entry point
     */
    public function entryCss(string $name): array
    {
        if ($this->isDev) return [];

        $manifest = $this->getManifest();
        $entry = $manifest[$name] ?? null;

        if (!$entry || empty($entry['css'])) return [];

        return array_map(fn(string $f) => '/build/' . $f, $entry['css']);
    }

    /**
     * Generate <script> and <link> tags for an entry point
     */
    public function tags(string $name): string
    {
        $html = '';

        if ($this->isDev) {
            // Dev mode: Vite client + module
            $html .= '<script type="module" src="' . $this->devServerUrl . '/@vite/client"></script>' . "\n";
            $html .= '<script type="module" src="' . $this->devServerUrl . '/' . $name . '"></script>' . "\n";
        } else {
            // Prod mode: hashed assets
            foreach ($this->entryCss($name) as $css) {
                $html .= '<link rel="stylesheet" href="' . $css . '">' . "\n";
            }
            $html .= '<script type="module" src="' . $this->entry($name) . '"></script>' . "\n";
        }

        return $html;
    }

    /**
     * Generate CSS <link> tag for a standalone CSS entry
     */
    public function css(string $name): string
    {
        $url = $this->entry($name);
        return '<link rel="stylesheet" href="' . $url . '">';
    }

    /**
     * Resolve a theme-relative asset path to a public URL
     */
    public function themeAsset(string $path, ThemeInfo $theme): string
    {
        return '/themes/' . $theme->tier . '/' . $theme->name . '/' . ltrim($path, '/');
    }

    /**
     * Generate all asset tags for a theme
     */
    public function themeAssetTags(ThemeInfo $theme): string
    {
        $html = '';

        foreach ($theme->getCssFiles() as $css) {
            $html .= '<link rel="stylesheet" href="' . $this->themeAsset($css, $theme) . '">' . "\n";
        }

        foreach ($theme->getJsFiles() as $js) {
            $html .= '<script src="' . $this->themeAsset($js, $theme) . '" defer></script>' . "\n";
        }

        return $html;
    }

    private function getManifest(): array
    {
        if ($this->manifest === null) {
            $path = $this->basePath . '/public/build/.vite/manifest.json';
            if (file_exists($path)) {
                $this->manifest = json_decode(file_get_contents($path), true) ?? [];
            } else {
                $this->manifest = [];
            }
        }

        return $this->manifest;
    }
}
