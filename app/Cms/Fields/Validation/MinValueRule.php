<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * MinValueRule - Validates minimum numeric value
 */
final class MinValueRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'min';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!is_numeric($value)) {
            return ValidationResult::success();
        }

        $min = (float) $parameter;
        if ((float) $value < $min) {
            return ValidationResult::failure("{$context->fieldLabel} must be at least {$min}");
        }

        return ValidationResult::success();
    }
}
