<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * IntegerTransformer - Handles integer values
 */
final class IntegerTransformer implements ValueTransformerInterface
{
    public function toForm(FieldValue $value): FieldValue
    {
        if ($value->isEmpty()) {
            return FieldValue::string('');
        }
        return FieldValue::string((string) $value->asInt());
    }

    public function toStorage(FieldValue $value): FieldValue
    {
        if ($value->isEmpty()) {
            return FieldValue::null('integer');
        }
        return FieldValue::integer($value->asInt());
    }

    public function toDisplay(FieldValue $value): string
    {
        if ($value->isEmpty()) {
            return '';
        }
        return number_format($value->asInt());
    }
}
