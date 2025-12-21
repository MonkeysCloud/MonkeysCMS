<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * RequiredRule - Validates that a value is present
 */
final class RequiredRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'required';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if (!$parameter) {
            return ValidationResult::success();
        }

        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return ValidationResult::failure("{$context->fieldLabel} is required");
        }

        return ValidationResult::success();
    }
}
