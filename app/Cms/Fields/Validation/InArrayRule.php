<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * InArrayRule - Validates value is in allowed list
 */
final class InArrayRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'in';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        $allowed = is_array($parameter) ? $parameter : explode(',', (string) $parameter);
        
        if (!in_array($value, $allowed, true)) {
            return ValidationResult::failure("{$context->fieldLabel} must be one of: " . implode(', ', $allowed));
        }

        return ValidationResult::success();
    }
}
