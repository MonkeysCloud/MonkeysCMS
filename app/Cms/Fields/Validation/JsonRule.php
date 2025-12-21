<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * JsonRule - Validates JSON format
 */
final class JsonRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'json';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '' || is_array($value)) {
            return ValidationResult::success();
        }

        if (!is_string($value)) {
            return ValidationResult::failure("{$context->fieldLabel} must be valid JSON");
        }

        json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ValidationResult::failure("{$context->fieldLabel} must be valid JSON: " . json_last_error_msg());
        }

        return ValidationResult::success();
    }
}
