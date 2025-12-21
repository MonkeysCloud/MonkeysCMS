<?php

declare(strict_types=1);

namespace App\Cms\Fields\Settings;

/**
 * FieldSettings - Immutable value object for field settings
 *
 * Provides type-safe access to field configuration with
 * default values and validation.
 */
final class FieldSettings
{
    private array $settings;
    private array $schema;

    private function __construct(array $settings, array $schema = [])
    {
        $this->schema = $schema;
        $this->settings = $this->applyDefaults($settings, $schema);
    }

    /**
     * Create from raw settings array
     */
    public static function fromArray(array $settings, array $schema = []): self
    {
        return new self($settings, $schema);
    }

    /**
     * Create empty settings
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Apply schema defaults to settings
     */
    private function applyDefaults(array $settings, array $schema): array
    {
        foreach ($schema as $key => $definition) {
            if (!isset($settings[$key]) && isset($definition['default'])) {
                $settings[$key] = $definition['default'];
            }
        }
        return $settings;
    }

    // Type-safe getters
    public function getString(string $key, string $default = ''): string
    {
        $value = $this->settings[$key] ?? $default;
        return is_string($value) ? $value : (string) $value;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->settings[$key] ?? $default;
        return is_int($value) ? $value : (int) $value;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $value = $this->settings[$key] ?? $default;
        return is_float($value) ? $value : (float) $value;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->settings[$key] ?? $default;
        return is_bool($value) ? $value : (bool) $value;
    }

    public function getArray(string $key, array $default = []): array
    {
        $value = $this->settings[$key] ?? $default;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : $default;
        }
        return is_array($value) ? $value : $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->settings[$key]);
    }

    public function all(): array
    {
        return $this->settings;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    // Immutable transformations
    public function with(string $key, mixed $value): self
    {
        $settings = $this->settings;
        $settings[$key] = $value;
        return new self($settings, $this->schema);
    }

    public function without(string $key): self
    {
        $settings = $this->settings;
        unset($settings[$key]);
        return new self($settings, $this->schema);
    }

    public function merge(array $settings): self
    {
        return new self(array_merge($this->settings, $settings), $this->schema);
    }

    public function withSchema(array $schema): self
    {
        return new self($this->settings, $schema);
    }

    /**
     * Validate settings against schema
     * @return array<string, string> Errors by setting key
     */
    public function validate(): array
    {
        $errors = [];

        foreach ($this->schema as $key => $definition) {
            $value = $this->settings[$key] ?? null;
            $required = $definition['required'] ?? false;
            $type = $definition['type'] ?? 'string';

            // Check required
            if ($required && $value === null) {
                $errors[$key] = "Setting '{$key}' is required";
                continue;
            }

            if ($value === null) {
                continue;
            }

            // Type validation
            $typeError = $this->validateType($key, $value, $type);
            if ($typeError) {
                $errors[$key] = $typeError;
                continue;
            }

            // Constraints
            if (isset($definition['min']) && is_numeric($value) && $value < $definition['min']) {
                $errors[$key] = "Setting '{$key}' must be at least {$definition['min']}";
            }

            if (isset($definition['max']) && is_numeric($value) && $value > $definition['max']) {
                $errors[$key] = "Setting '{$key}' must be at most {$definition['max']}";
            }

            if (isset($definition['options']) && !isset($definition['options'][$value])) {
                $errors[$key] = "Setting '{$key}' must be one of: " . implode(', ', array_keys($definition['options']));
            }
        }

        return $errors;
    }

    private function validateType(string $key, mixed $value, string $type): ?string
    {
        return match ($type) {
            'string' => is_string($value) ? null : "Setting '{$key}' must be a string",
            'integer', 'int' => is_int($value) || (is_string($value) && ctype_digit($value)) ? null : "Setting '{$key}' must be an integer",
            'float', 'number' => is_numeric($value) ? null : "Setting '{$key}' must be a number",
            'boolean', 'bool' => is_bool($value) || in_array($value, [0, 1, '0', '1'], true) ? null : "Setting '{$key}' must be a boolean",
            'array' => is_array($value) ? null : "Setting '{$key}' must be an array",
            'json' => is_array($value) || (is_string($value) && json_decode($value) !== null) ? null : "Setting '{$key}' must be valid JSON",
            default => null,
        };
    }

    public function toJson(): string
    {
        return json_encode($this->settings, JSON_THROW_ON_ERROR);
    }
}
