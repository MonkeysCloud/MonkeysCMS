<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

/**
 * ConfigurableWidgetInterface - Widget with configurable settings
 */
interface ConfigurableWidgetInterface extends WidgetInterface
{
    /**
     * Get the settings schema
     */
    public function getSettingsSchema(): WidgetSettingsSchema;
}
