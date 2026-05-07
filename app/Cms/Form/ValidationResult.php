<?php

declare(strict_types=1);

namespace App\Cms\Form;

/**
 * ValidationResult — Holds form validation outcome.
 */
final class ValidationResult
{
    /** @param array<string, string> $errors field name => error message */
    public function __construct(
        private readonly array $errors = [],
    ) {}

    public bool $isValid {
        get => count($this->errors) === 0;
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    public int $errorCount {
        get => count($this->errors);
    }
}
