<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

use App\Cms\Fields\Definition\Field;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Value\FieldValue;

/**
 * WidgetInterface - Contract for field widgets
 */
interface WidgetInterface
{
    /**
     * Get widget metadata
     */
    public function getMetadata(): WidgetMetadata;
    public function getId(): string;
    public function getLabel(): string;
    public function getCategory(): string;
    public function getIcon(): string;
    public function getPriority(): int;
    public function getSupportedTypes(): array;
    public function supportsMultiple(): bool;

    /**
     * Render the widget for editing
     */
    public function render(Field $field, FieldValue $value, RenderContext $context): WidgetOutput;

    /**
     * Render the widget for display (non-editable)
     */
    public function display(Field $field, FieldValue $value, RenderContext $context): WidgetOutput;
}
