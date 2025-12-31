<?php

declare(strict_types=1);

namespace App\Cms\Theme;

use MonkeysLegion\Template\Loader;

/**
 * CascadingLoader extends the default Loader to support multiple view paths.
 * It checks paths in priority order (Theme -> Core).
 */
class CascadingLoader extends Loader
{
    private array $paths = [];

    /**
     * @param array $paths Priority ordered list of absolute paths to view directories
     * @param string $cachePath Path to cache directory
     * @param string $extension Template extension (default: ml.php)
     */
    public function __construct(array $paths, string $cachePath, string $extension = 'ml.php')
    {
        $this->paths = $paths;
        // Pass the first path as default source path to satisfy parent constructor
        $defaultPath = $paths[0] ?? '';
        parent::__construct($defaultPath, $cachePath, $extension);
    }

    /**
     * Get the absolute path to the source template file.
     * Iterates through registered paths to find the file.
     */
    public function getSourcePath(string $view): string
    {
        // View might omit extension
        if (!str_ends_with($view, '.' . $this->getTemplateExtension())) {
            $view .= '.' . $this->getTemplateExtension();
        }

        // Support dot notation: admin.dashboard -> admin/dashboard.ml.php
        // We only replace dots in the name part, preserving the extension
        $extensionLength = strlen('.' . $this->getTemplateExtension());
        $namePart = substr($view, 0, -$extensionLength);
        $namePart = str_replace('.', '/', $namePart);
        $view = $namePart . '.' . $this->getTemplateExtension();

        foreach ($this->paths as $path) {
            $file = rtrim($path, '/') . '/' . ltrim($view, '/');
            if (file_exists($file)) {
                return $file;
            }
        }

        // If not found, throw exception
        throw new \RuntimeException("Template not found: {$view} (searched in: " . implode(', ', $this->paths) . ")");
    }

    /**
     * Check if a template exists.
     */
    public function exists(string $view): bool
    {
        try {
            $this->getSourcePath($view);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Helper to expose extension since property might be private in parent
     */
    private function getTemplateExtension(): string
    {
        return 'ml.php'; 
    }
}
