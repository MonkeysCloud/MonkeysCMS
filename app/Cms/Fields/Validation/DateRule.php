<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * DateRule - Validates date format
 */
final class DateRule implements ValidationRuleInterface
{
    public function getName(): string
    {
        return 'date';
    }

    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        try {
            new \DateTimeImmutable($value);
            return ValidationResult::success();
        } catch (\Exception $e) {
            return ValidationResult::failure("{$context->fieldLabel} must be a valid date");
        }
    }
}
