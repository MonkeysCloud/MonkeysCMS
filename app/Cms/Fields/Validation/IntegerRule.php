<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * IntegerRule - Validates integer format
 */
final class IntegerRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'integer';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!is_numeric($value) || (int) $value != $value) {
            return ValidationResult::failure("{$context->fieldLabel} must be a whole number");
        }

        return ValidationResult::success();
    }
}
