<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * MaxLengthRule - Validates maximum string length
 */
final class MaxLengthRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'maxLength';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!is_string($value)) {
            return ValidationResult::success();
        }

        $maxLength = (int) $parameter;
        if (mb_strlen($value) > $maxLength) {
            return ValidationResult::failure("{$context->fieldLabel} must be at most {$maxLength} characters");
        }

        return ValidationResult::success();
    }
}
