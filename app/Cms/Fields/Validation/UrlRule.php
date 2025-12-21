<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * UrlRule - Validates URL format
 */
final class UrlRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'url';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return ValidationResult::failure("{$context->fieldLabel} must be a valid URL");
        }

        return ValidationResult::success();
    }
}
