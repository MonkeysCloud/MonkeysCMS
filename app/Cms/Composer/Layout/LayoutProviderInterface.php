<?php

declare(strict_types=1);

namespace App\Cms\Composer\Layout;

/**
 * LayoutProviderInterface - For modules to register custom row layouts
 * 
 * Modules implement this interface to add custom column layouts
 * that appear in the composer row layout selector.
 */
interface LayoutProviderInterface
{
    /**
     * Get custom layouts provided by this module
     * 
     * @return array<array{id: string, label: string, widths: array<float>, icon?: string}>
     */
    public function getLayouts(): array;

    /**
     * Get the provider ID (usually module name)
     */
    public function getProviderId(): string;
}
