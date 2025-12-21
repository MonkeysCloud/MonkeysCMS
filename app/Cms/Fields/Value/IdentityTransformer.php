<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * IdentityTransformer - Returns value unchanged
 */
final class IdentityTransformer implements ValueTransformerInterface
{
    public function toForm(FieldValue $value): FieldValue
    {
        return $value;
    }

    public function toStorage(FieldValue $value): FieldValue
    {
        return $value;
    }

    public function toDisplay(FieldValue $value): string
    {
        return $value->asString();
    }
}
