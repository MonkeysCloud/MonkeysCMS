<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * BooleanTransformer - Handles boolean values
 */
final class BooleanTransformer implements ValueTransformerInterface
{
    public function __construct(
        private readonly string $trueLabel = 'Yes',
        private readonly string $falseLabel = 'No',
    ) {
    }

    public function toForm(FieldValue $value): FieldValue
    {
        return FieldValue::string($value->asBool() ? '1' : '0');
    }

    public function toStorage(FieldValue $value): FieldValue
    {
        return FieldValue::boolean($value->asBool());
    }

    public function toDisplay(FieldValue $value): string
    {
        return $value->asBool() ? $this->trueLabel : $this->falseLabel;
    }
}
