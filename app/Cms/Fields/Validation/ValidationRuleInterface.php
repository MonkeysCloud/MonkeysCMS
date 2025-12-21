<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * ValidationRuleInterface - Contract for field validation rules
 */
interface ValidationRuleInterface
{
    /**
     * Get the rule identifier
     */
    public function getName(): string;

    /**
     * Validate a value
     *
     * @param mixed $value The value to validate
     * @param mixed $parameter Rule-specific parameter (e.g., max length, pattern)
     * @param ValidationContext $context Additional context
     * @return ValidationResult
     */
    public function validate(mixed $value, mixed $parameter, ValidationContext $context): ValidationResult;
}
