<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * DateTransformer - Handles date values
 */
final class DateTransformer implements ValueTransformerInterface
{
    public function __construct(
        private readonly string $storageFormat = 'Y-m-d',
        private readonly string $formFormat = 'Y-m-d',
        private readonly string $displayFormat = 'F j, Y',
    ) {}

    public function toForm(FieldValue $value): FieldValue
    {
        $date = $value->asDate($this->formFormat);
        return FieldValue::string($date ?? '');
    }

    public function toStorage(FieldValue $value): FieldValue
    {
        if ($value->isEmpty()) {
            return FieldValue::null('date');
        }
        
        $date = $value->asDate($this->storageFormat);
        return FieldValue::string($date ?? '');
    }

    public function toDisplay(FieldValue $value): string
    {
        return $value->asDate($this->displayFormat) ?? '';
    }
}
