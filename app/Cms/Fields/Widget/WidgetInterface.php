<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

use App\Cms\Fields\Definition\Field;
use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
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
     * Whether this widget renders a single labelable input element.
     * 
     * When true, the field's label will use a `for` attribute pointing to the input.
     * When false (for widgets with multiple inputs), the label won't have a `for` attribute.
     */
    public function usesLabelableInput(): bool;

    /**
     * Render the widget for editing (new Field-based API)
     */
    public function render(Field $field, FieldValue $value, RenderContext $context): WidgetOutput;

    /**
     * Render the widget for display (new Field-based API)
     */
    public function display(Field $field, FieldValue $value, RenderContext $context): WidgetOutput;

    /**
     * Render the widget for editing (legacy FieldDefinition-based API)
     */
    public function renderField(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult;

    /**
     * Render the widget for display (legacy FieldDefinition-based API)
     */
    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult;

    /**
     * Get the widget's settings schema
     */
    public function getSettingsSchema(): array;
    
    /**
     * Validate a field value
     */
    public function validate(FieldDefinition $field, mixed $value): ValidationResult;

    /**
     * Prepare value for storage
     */
    public function prepareValue(FieldDefinition $field, mixed $value): mixed;

    /**
     * Format value for form display
     */
    public function formatValue(FieldDefinition $field, mixed $value): mixed;
}
