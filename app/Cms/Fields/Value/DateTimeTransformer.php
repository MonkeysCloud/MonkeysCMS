<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * DateTimeTransformer - Handles datetime values
 */
final class DateTimeTransformer implements ValueTransformerInterface
{
    public function __construct(
        private readonly string $storageFormat = 'Y-m-d H:i:s',
        private readonly string $formFormat = 'Y-m-d\TH:i',
        private readonly string $displayFormat = 'F j, Y g:i A',
    ) {}

    public function toForm(FieldValue $value): FieldValue
    {
        $date = $value->asDate($this->formFormat);
        return FieldValue::string($date ?? '');
    }

    public function toStorage(FieldValue $value): FieldValue
    {
        if ($value->isEmpty()) {
            return FieldValue::null('datetime');
        }
        
        $date = $value->asDate($this->storageFormat);
        return FieldValue::string($date ?? '');
    }

    public function toDisplay(FieldValue $value): string
    {
        return $value->asDate($this->displayFormat) ?? '';
    }
}
