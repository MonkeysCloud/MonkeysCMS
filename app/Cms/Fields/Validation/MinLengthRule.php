<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * MinLengthRule - Validates minimum string length
 */
final class MinLengthRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'minLength';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!is_string($value)) {
            return ValidationResult::success();
        }

        $minLength = (int) $parameter;
        if (mb_strlen($value) < $minLength) {
            return ValidationResult::failure("{$context->fieldLabel} must be at least {$minLength} characters");
        }

        return ValidationResult::success();
    }
}
