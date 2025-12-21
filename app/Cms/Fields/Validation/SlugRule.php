<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * SlugRule - Validates URL slug format
 */
final class SlugRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'slug';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            return ValidationResult::failure("{$context->fieldLabel} must be a valid slug (lowercase letters, numbers, and hyphens)");
        }

        return ValidationResult::success();
    }
}
