<?php

declare(strict_types=1);

namespace App\Cms\Module;

use App\Cms\Fields\Widget\WidgetInterface;

/**
 * AbstractModule - Base class for CMS modules
 *
 * Provides default implementations and auto-discovery of widgets.
 *
 * @example
 * ```php
 * class ExampleModule extends AbstractModule
 * {
 *     protected string $name = 'example';
 *     protected string $label = 'Example Module';
 *     protected string $description = 'An example module demonstrating custom widgets.';
 *
 *     public function getWidgets(): array
 *     {
 *         return [
 *             new RatingWidget(),
 *             new IconPickerWidget(),
 *         ];
 *     }
 * }
 * ```
 */
abstract class AbstractModule implements ModuleInterface
{
    protected string $name = '';
    protected string $label = '';
    protected string $description = '';
    protected bool $enabled = true;

    public function getName(): string
    {
        if (empty($this->name)) {
            // Derive from class name: ExampleModule -> example
            $className = (new \ReflectionClass($this))->getShortName();
            $this->name = strtolower(str_replace('Module', '', $className));
        }
        return $this->name;
    }

    public function getLabel(): string
    {
        if (empty($this->label)) {
            return ucwords(str_replace('_', ' ', $this->getName()));
        }
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Override this method to provide widgets
     *
     * @return WidgetInterface[]
     */
    public function getWidgets(): array
    {
        return [];
    }

    /**
     * Override this method to provide custom field types
     *
     * @return array<string, array{label: string, icon?: string, category?: string}>
     */
    public function getFieldTypes(): array
    {
        return [];
    }

    /**
     * Boot the module - override for custom initialization
     */
    public function boot(): void
    {
        // Default: no-op
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the module's base path
     */
    protected function getPath(): string
    {
        $reflection = new \ReflectionClass($this);
        return dirname($reflection->getFileName());
    }

    /**
     * Auto-discover widgets in the Widgets subdirectory
     *
     * @return WidgetInterface[]
     */
    protected function discoverWidgets(): array
    {
        $widgets = [];
        $widgetsPath = $this->getPath() . '/Widgets';

        if (!is_dir($widgetsPath)) {
            return $widgets;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($widgetsPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->getClassFromPath($file->getPathname());

            if (!$className) {
                continue;
            }

            try {
                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new \ReflectionClass($className);

                if (!$reflection->isAbstract() && $reflection->implementsInterface(WidgetInterface::class)) {
                    $widgets[] = $reflection->newInstance();
                }
            } catch (\Throwable $e) {
                // Log but don't crash
                error_log("Failed to load widget {$className}: " . $e->getMessage());
            }
        }

        return $widgets;
    }

    /**
     * Extract class name from file path
     */
    private function getClassFromPath(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        if (!$content) {
            return null;
        }

        $namespace = '';
        $class = '';

        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $class = $matches[1];
        }

        if ($namespace && $class) {
            return $namespace . '\\' . $class;
        }

        return null;
    }
}
