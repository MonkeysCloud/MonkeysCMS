<?php

declare(strict_types=1);

namespace App\Cms\Form;

/**
 * FormElement — A single form element definition.
 *
 * Immutable value object using PHP 8.4 property hooks.
 * Supports all standard HTML input types plus CMS-specific widgets.
 */
final class FormElement
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @var array<string, string> */
    private array $options = [];

    /** @var array<string, mixed> */
    private array $rules = [];

    /** @var array<string, mixed> */
    private array $conditions = [];

    private ?string $wrapper = null;
    private ?string $helpText = null;
    private ?string $prefix = null;
    private ?string $suffix = null;
    private bool $required = false;
    private bool $disabled = false;
    private bool $readonly = false;
    private ?string $placeholder = null;
    private ?string $defaultValue = null;
    private ?string $group = null;

    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $label = '',
        private mixed $value = null,
    ) {}

    // ── Fluent Setters ──────────────────────────────────────────────────

    public function value(mixed $value): static
    {
        $clone = clone $this;
        $clone->value = $value;
        return $clone;
    }

    public function placeholder(string $text): static
    {
        $clone = clone $this;
        $clone->placeholder = $text;
        return $clone;
    }

    public function help(string $text): static
    {
        $clone = clone $this;
        $clone->helpText = $text;
        return $clone;
    }

    public function required(bool $flag = true): static
    {
        $clone = clone $this;
        $clone->required = $flag;
        return $clone;
    }

    public function disabled(bool $flag = true): static
    {
        $clone = clone $this;
        $clone->disabled = $flag;
        return $clone;
    }

    public function readonly(bool $flag = true): static
    {
        $clone = clone $this;
        $clone->readonly = $flag;
        return $clone;
    }

    /** @param array<string, string> $options key => label */
    public function options(array $options): static
    {
        $clone = clone $this;
        $clone->options = $options;
        return $clone;
    }

    /** @param array<string, mixed> $attrs */
    public function attrs(array $attrs): static
    {
        $clone = clone $this;
        $clone->attributes = array_merge($clone->attributes, $attrs);
        return $clone;
    }

    public function attr(string $key, mixed $val): static
    {
        $clone = clone $this;
        $clone->attributes[$key] = $val;
        return $clone;
    }

    public function default(string $value): static
    {
        $clone = clone $this;
        $clone->defaultValue = $value;
        return $clone;
    }

    public function group(string $group): static
    {
        $clone = clone $this;
        $clone->group = $group;
        return $clone;
    }

    public function prefix(string $html): static
    {
        $clone = clone $this;
        $clone->prefix = $html;
        return $clone;
    }

    public function suffix(string $html): static
    {
        $clone = clone $this;
        $clone->suffix = $html;
        return $clone;
    }

    public function wrapper(string $class): static
    {
        $clone = clone $this;
        $clone->wrapper = $class;
        return $clone;
    }

    /**
     * Conditional visibility: show only when another field has a specific value.
     *
     * @param string $field  The name of the controlling field
     * @param mixed  $value  The value that triggers display
     */
    public function showWhen(string $field, mixed $value): static
    {
        $clone = clone $this;
        $clone->conditions = ['field' => $field, 'value' => $value, 'op' => '==='];
        return $clone;
    }

    /** Validation: min length */
    public function min(int $val): static
    {
        $clone = clone $this;
        $clone->rules['min'] = $val;
        return $clone;
    }

    /** Validation: max length */
    public function max(int $val): static
    {
        $clone = clone $this;
        $clone->rules['max'] = $val;
        return $clone;
    }

    /** Validation: regex pattern */
    public function pattern(string $regex): static
    {
        $clone = clone $this;
        $clone->rules['pattern'] = $regex;
        return $clone;
    }

    // ── Read-only Accessors (PHP 8.4 property hooks) ────────────────────

    public mixed $currentValue {
        get => $this->value ?? $this->defaultValue;
    }

    public string $htmlName {
        get => htmlspecialchars($this->name);
    }

    public string $htmlId {
        get => 'form-' . str_replace(['[', ']', '.'], ['-', '', '-'], $this->name);
    }

    public bool $isRequired {
        get => $this->required;
    }

    public bool $isDisabled {
        get => $this->disabled;
    }

    public bool $isReadonly {
        get => $this->readonly;
    }

    public ?string $getPlaceholder {
        get => $this->placeholder;
    }

    public ?string $getHelp {
        get => $this->helpText;
    }

    public ?string $getPrefix {
        get => $this->prefix;
    }

    public ?string $getSuffix {
        get => $this->suffix;
    }

    public ?string $getWrapper {
        get => $this->wrapper;
    }

    public ?string $getGroup {
        get => $this->group;
    }

    /** @return array<string, string> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /** @return array<string, mixed> */
    public function getRules(): array
    {
        return $this->rules;
    }

    /** @return array<string, mixed> */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Build HTML attributes string from the attributes array.
     */
    public function buildAttrString(): string
    {
        $parts = [];
        foreach ($this->attributes as $k => $v) {
            if (is_bool($v)) {
                if ($v) {
                    $parts[] = htmlspecialchars($k);
                }
            } else {
                $parts[] = htmlspecialchars($k) . '="' . htmlspecialchars((string) $v) . '"';
            }
        }
        return $parts ? ' ' . implode(' ', $parts) : '';
    }
}
