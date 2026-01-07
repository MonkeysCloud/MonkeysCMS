<?php

declare(strict_types=1);

namespace App\Modules\Example;

use App\Cms\Module\AbstractModule;

/**
 * ExampleModule - Demonstration module with custom widgets
 *
 * This module serves as a reference implementation showing how to create
 * custom modules that provide widgets and field types.
 */
class ExampleModule extends AbstractModule
{
    protected string $name = 'example';
    protected string $label = 'Example Module';
    protected string $description = 'Demonstration module with example widgets like Rating and Icon Picker.';

    /**
     * Get widgets provided by this module
     *
     * You can either:
     * 1. Manually return widget instances (explicit control)
     * 2. Use discoverWidgets() for auto-discovery from Widgets/ subdirectory
     *
     * @return \App\Cms\Fields\Widget\WidgetInterface[]
     */
    public function getWidgets(): array
    {
        // Option 1: Explicit registration (safer, more control)
        // return [
        //     new Widgets\RatingWidget(),
        //     new Widgets\IconPickerWidget(),
        // ];

        // Option 2: Auto-discover widgets from Widgets/ subdirectory
        return $this->discoverWidgets();
    }

    /**
     * Custom field types provided by this module
     */
    public function getFieldTypes(): array
    {
        return [
            'rating' => [
                'label' => 'Star Rating',
                'icon' => '⭐',
                'category' => 'Custom',
            ],
            'icon' => [
                'label' => 'Icon',
                'icon' => '🎨',
                'category' => 'Custom',
            ],
        ];
    }

    /**
     * Boot the module
     */
    public function boot(): void
    {
        // Register event listeners, initialize services, etc.
    }
}
