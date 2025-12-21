<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * ColorRule - Validates hex color format
 */
final class ColorRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'color';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            return ValidationResult::failure("{$context->fieldLabel} must be a valid hex color (e.g., #FF0000)");
        }

        return ValidationResult::success();
    }
}
