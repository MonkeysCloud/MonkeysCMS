<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * ValueTransformerInterface - Transforms values between formats
 * 
 * Transformers handle conversion between:
 * - Storage format (database)
 * - Form format (user input)
 * - Display format (view)
 */
interface ValueTransformerInterface
{
    /**
     * Transform value from storage format to form format
     */
    public function toForm(FieldValue $value): FieldValue;

    /**
     * Transform value from form format to storage format
     */
    public function toStorage(FieldValue $value): FieldValue;

    /**
     * Transform value for display
     */
    public function toDisplay(FieldValue $value): string;
}
