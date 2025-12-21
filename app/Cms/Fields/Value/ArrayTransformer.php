<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * ArrayTransformer - Handles array/JSON values
 */
final class ArrayTransformer implements ValueTransformerInterface
{
    public function __construct(
        private readonly string $separator = ', ',
    ) {}

    public function toForm(FieldValue $value): FieldValue
    {
        return $value;
    }

    public function toStorage(FieldValue $value): FieldValue
    {
        return FieldValue::array($value->asArray());
    }

    public function toDisplay(FieldValue $value): string
    {
        $array = $value->asArray();
        return implode($this->separator, $array);
    }
}
