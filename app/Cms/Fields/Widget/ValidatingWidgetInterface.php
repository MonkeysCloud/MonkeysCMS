<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

use App\Cms\Fields\Definition\Field;
use App\Cms\Fields\Value\FieldValue;

/**
 * ValidatingWidgetInterface - Widget with custom validation
 */
interface ValidatingWidgetInterface extends WidgetInterface
{
    /**
     * Validate a value
     * 
     * @return string[] Error messages
     */
    public function validate(Field $field, FieldValue $value): array;
}
