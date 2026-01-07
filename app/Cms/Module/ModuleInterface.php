<?php

declare(strict_types=1);

namespace App\Cms\Module;

/**
 * ModuleInterface - Contract for CMS modules
 *
 * Modules can provide:
 * - Custom widgets for field rendering
 * - Custom field types
 * - Controllers, services, and other components
 *
 * @example
 * ```php
 * class ExampleModule implements ModuleInterface
 * {
 *     public function getName(): string { return 'example'; }
 *     public function getWidgets(): array { return [new RatingWidget()]; }
 *     public function getFieldTypes(): array { return []; }
 * }
 * ```
 */
interface ModuleInterface
{
    /**
     * Get the unique module identifier
     */
    public function getName(): string;

    /**
     * Get module label for display
     */
    public function getLabel(): string;

    /**
     * Get module description
     */
    public function getDescription(): string;

    /**
     * Get widgets provided by this module
     *
     * @return \App\Cms\Fields\Widget\WidgetInterface[]
     */
    public function getWidgets(): array;

    /**
     * Get custom field types provided by this module
     *
     * @return array<string, array{label: string, icon?: string, category?: string}>
     */
    public function getFieldTypes(): array;

    /**
     * Boot the module (called during application bootstrap)
     */
    public function boot(): void;

    /**
     * Check if module is enabled
     */
    public function isEnabled(): bool;
}
