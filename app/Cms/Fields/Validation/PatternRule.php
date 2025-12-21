<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * PatternRule - Validates against a regex pattern
 */
final class PatternRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'pattern';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!is_string($value)) {
            return ValidationResult::success();
        }

        $pattern = '/' . $parameter . '/';
        if (!preg_match($pattern, $value)) {
            return ValidationResult::failure("{$context->fieldLabel} format is invalid");
        }

        return ValidationResult::success();
    }
}
