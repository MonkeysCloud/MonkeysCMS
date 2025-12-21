<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * StringTransformer - Handles string values
 */
final class StringTransformer implements ValueTransformerInterface
{
    public function __construct(
        private readonly bool $trim = true,
        private readonly bool $nullifyEmpty = true,
    ) {
    }

    public function toForm(FieldValue $value): FieldValue
    {
        return $value;
    }

    public function toStorage(FieldValue $value): FieldValue
    {
        $string = $value->asString();

        if ($this->trim) {
            $string = trim($string);
        }

        if ($this->nullifyEmpty && $string === '') {
            return FieldValue::null('string');
        }

        return FieldValue::string($string);
    }

    public function toDisplay(FieldValue $value): string
    {
        return htmlspecialchars($value->asString(), ENT_QUOTES | ENT_HTML5);
    }
}
