<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * FloatTransformer - Handles float/decimal values
 */
final class FloatTransformer implements ValueTransformerInterface
{
    public function __construct(
        private readonly int $decimals = 2,
        private readonly string $decimalSeparator = '.',
        private readonly string $thousandsSeparator = ',',
    ) {
    }

    public function toForm(FieldValue $value): FieldValue
    {
        if ($value->isEmpty()) {
            return FieldValue::string('');
        }
        return FieldValue::string((string) $value->asFloat());
    }

    public function toStorage(FieldValue $value): FieldValue
    {
        if ($value->isEmpty()) {
            return FieldValue::null('float');
        }
        return FieldValue::float(round($value->asFloat(), $this->decimals));
    }

    public function toDisplay(FieldValue $value): string
    {
        if ($value->isEmpty()) {
            return '';
        }
        return number_format(
            $value->asFloat(),
            $this->decimals,
            $this->decimalSeparator,
            $this->thousandsSeparator
        );
    }
}
