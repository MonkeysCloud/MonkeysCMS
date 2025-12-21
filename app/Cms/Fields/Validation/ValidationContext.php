<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * ValidationContext - Context for validation
 */
final class ValidationContext
{
    public function __construct(
        public readonly string $fieldName,
        public readonly string $fieldLabel,
        public readonly string $fieldType,
        public readonly array $allValues = [],
    ) {
    }
}
