<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

/**
 * ValidationResult - Result of a validation check
 */
final class ValidationResult
{
    private function __construct(
        private readonly bool $valid,
        private readonly array $errors,
    ) {}

    public static function success(): self
    {
        return new self(true, []);
    }

    public static function failure(string|array $errors): self
    {
        return new self(false, is_array($errors) ? $errors : [$errors]);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function merge(ValidationResult $other): self
    {
        if ($this->valid && $other->valid) {
            return self::success();
        }
        
        return self::failure(array_merge($this->errors, $other->errors));
    }
}
