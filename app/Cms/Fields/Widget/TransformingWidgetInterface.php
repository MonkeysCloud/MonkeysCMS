<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

use App\Cms\Fields\Definition\Field;
use App\Cms\Fields\Value\FieldValue;

/**
 * TransformingWidgetInterface - Widget with value transformation
 */
interface TransformingWidgetInterface extends WidgetInterface
{
    /**
     * Transform value before storage
     */
    public function prepareForStorage(Field $field, FieldValue $value): FieldValue;

    /**
     * Transform value for form display
     */
    public function prepareForForm(Field $field, FieldValue $value): FieldValue;
}
