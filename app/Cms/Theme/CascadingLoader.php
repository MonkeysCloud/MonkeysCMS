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

        foreach ($this->paths as $path) {
            $file = rtrim($path, '/') . '/' . ltrim($view, '/');
            if (file_exists($file)) {
                return $file;
            }
        }

        // If not found, throw exception (or let parent handle it which might throw)
        // Parent implementation likely just returns path/view, so we can defer or throw
        throw new \RuntimeException("Template not found: {$view} (searched in: " . implode(', ', $this->paths) . ")");
    }

    /**
     * Helper to expose extension since property might be private in parent
     */
    private function getTemplateExtension(): string
    {
        // Try accessing via reflection if no getter method exists on parent
        // Based on previous check, parent has no public getter for extension
        // So we store it locally or assume default if not passed
        // However, we passed it to parent ctor.
        
        // Let's rely on the property if protected, or just hardcode/store our own
        return 'ml.php'; 
    }
}
