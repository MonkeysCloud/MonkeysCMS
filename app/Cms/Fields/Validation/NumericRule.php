<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * NumericRule - Validates numeric format
 */
final class NumericRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'numeric';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!is_numeric($value)) {
            return ValidationResult::failure("{$context->fieldLabel} must be a number");
        }

        return ValidationResult::success();
    }
}
