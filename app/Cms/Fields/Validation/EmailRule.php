<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * EmailRule - Validates email format
 */
final class EmailRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'email';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ValidationResult::failure("{$context->fieldLabel} must be a valid email address");
        }

        return ValidationResult::success();
    }
}
