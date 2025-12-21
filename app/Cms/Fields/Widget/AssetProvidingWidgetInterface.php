<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

use App\Cms\Fields\Definition\Field;

/**
 * AssetProvidingWidgetInterface - Widget with CSS/JS assets
 */
interface AssetProvidingWidgetInterface extends WidgetInterface
{
    /**
     * Get required assets
     */
    public function getAssets(): WidgetAssets;

    /**
     * Get initialization script for a specific field instance
     */
    public function getInitScript(Field $field, string $elementId): ?string;
}
