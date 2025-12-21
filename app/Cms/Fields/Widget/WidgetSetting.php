<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

/**
 * WidgetSetting - Individual setting definition
 */
final class WidgetSetting
{
    private function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly string $label,
        private readonly mixed $default,
        private readonly array $options,
        private readonly array $constraints,
    ) {
    }

    public static function string(string $name, string $label, ?string $default = null): self
    {
        return new self($name, 'string', $label, $default, [], []);
    }

    public static function integer(string $name, string $label, int $default = 0): self
    {
        return new self($name, 'integer', $label, $default, [], []);
    }

    public static function boolean(string $name, string $label, bool $default = false): self
    {
        return new self($name, 'boolean', $label, $default, [], []);
    }

    public static function select(string $name, string $label, array $options, ?string $default = null): self
    {
        return new self($name, 'select', $label, $default ?? array_key_first($options), $options, []);
    }

    public static function fromArray(string $name, array $definition): self
    {
        return new self(
            name: $name,
            type: $definition['type'] ?? 'string',
            label: $definition['label'] ?? $name,
            default: $definition['default'] ?? null,
            options: $definition['options'] ?? [],
            constraints: array_filter([
                'min' => $definition['min'] ?? null,
                'max' => $definition['max'] ?? null,
                'required' => $definition['required'] ?? false,
            ]),
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'default' => $this->default,
            'options' => $this->options,
            ...$this->constraints,
        ];
    }
}
