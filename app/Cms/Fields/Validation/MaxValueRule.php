<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * MaxValueRule - Validates maximum numeric value
 */
final class MaxValueRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'max';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!is_numeric($value)) {
            return ValidationResult::success();
        }

        $max = (float) $parameter;
        if ((float) $value > $max) {
            return ValidationResult::failure("{$context->fieldLabel} must be at most {$max}");
        }

        return ValidationResult::success();
    }
}
