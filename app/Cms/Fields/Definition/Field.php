<?php

declare(strict_types=1);

namespace App\Cms\Fields\Definition;

use App\Cms\Fields\Settings\FieldSettings;
use App\Cms\Fields\Value\FieldValue;

/**
 * Field - Domain entity representing a field definition
 *
 * Encapsulates field configuration with proper behavior and validation.
 * This is an aggregate root for field-related operations.
 */
final class Field
{
    private FieldSettings $settings;
    private FieldSettings $widgetSettings;
    private ValidationRules $validationRules;

    private function __construct(
        private readonly FieldIdentifier $identifier,
        private readonly FieldType $type,
        private FieldLabel $label,
        private ?string $description,
        private ?string $helpText,
        private ?string $widgetId,
        private bool $required,
        private bool $multiple,
        private int $cardinality,
        private ?FieldValue $defaultValue,
        array $settings,
        array $validation,
        array $widgetSettings,
        private int $weight,
        private bool $searchable,
        private bool $translatable,
    ) {
        $this->settings = FieldSettings::fromArray($settings);
        $this->widgetSettings = FieldSettings::fromArray($widgetSettings);
        $this->validationRules = ValidationRules::fromArray($validation);
    }

    // =========================================================================
    // Factory Methods
    // =========================================================================

    public static function create(
        string $name,
        string $machineName,
        string $fieldType,
    ): self {
        return new self(
            identifier: FieldIdentifier::from($machineName),
            type: FieldType::from($fieldType),
            label: FieldLabel::from($name),
            description: null,
            helpText: null,
            widgetId: null,
            required: false,
            multiple: false,
            cardinality: 1,
            defaultValue: null,
            settings: [],
            validation: [],
            widgetSettings: [],
            weight: 0,
            searchable: false,
            translatable: false,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            identifier: FieldIdentifier::from($data['machine_name'] ?? ''),
            type: FieldType::from($data['field_type'] ?? 'string'),
            label: FieldLabel::from($data['name'] ?? ''),
            description: $data['description'] ?? null,
            helpText: $data['help_text'] ?? null,
            widgetId: $data['widget'] ?? null,
            required: (bool) ($data['required'] ?? false),
            multiple: (bool) ($data['multiple'] ?? false),
            cardinality: (int) ($data['cardinality'] ?? 1),
            defaultValue: isset($data['default_value'])
                ? FieldValue::of($data['default_value'], $data['field_type'] ?? 'string')
                : null,
            settings: $data['settings'] ?? [],
            validation: $data['validation'] ?? [],
            widgetSettings: $data['widget_settings'] ?? [],
            weight: (int) ($data['weight'] ?? 0),
            searchable: (bool) ($data['searchable'] ?? false),
            translatable: (bool) ($data['translatable'] ?? false),
        );
    }

    // =========================================================================
    // Identity
    // =========================================================================

    public function getIdentifier(): FieldIdentifier
    {
        return $this->identifier;
    }

    public function getMachineName(): string
    {
        return $this->identifier->toString();
    }

    public function getType(): FieldType
    {
        return $this->type;
    }

    public function getTypeName(): string
    {
        return $this->type->getName();
    }

    // =========================================================================
    // Labels & Text
    // =========================================================================

    public function getLabel(): FieldLabel
    {
        return $this->label;
    }

    public function getName(): string
    {
        return $this->label->toString();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    // =========================================================================
    // Widget
    // =========================================================================

    public function getWidgetId(): ?string
    {
        return $this->widgetId;
    }

    public function hasExplicitWidget(): bool
    {
        return $this->widgetId !== null;
    }

    public function getWidgetSettings(): FieldSettings
    {
        return $this->widgetSettings;
    }

    // =========================================================================
    // Configuration
    // =========================================================================

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function getCardinality(): int
    {
        return $this->cardinality;
    }

    public function hasUnlimitedCardinality(): bool
    {
        return $this->cardinality === -1;
    }

    public function getDefaultValue(): ?FieldValue
    {
        return $this->defaultValue;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isTranslatable(): bool
    {
        return $this->translatable;
    }

    // =========================================================================
    // Settings
    // =========================================================================

    public function getSettings(): FieldSettings
    {
        return $this->settings;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings->get($key, $default);
    }

    public function getValidationRules(): ValidationRules
    {
        return $this->validationRules;
    }

    // =========================================================================
    // Mutations (return new instance)
    // =========================================================================

    public function withLabel(string $name): self
    {
        $clone = clone $this;
        $clone->label = FieldLabel::from($name);
        return $clone;
    }

    public function withDescription(?string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;
        return $clone;
    }

    public function withHelpText(?string $helpText): self
    {
        $clone = clone $this;
        $clone->helpText = $helpText;
        return $clone;
    }

    public function withWidget(string $widgetId): self
    {
        $clone = clone $this;
        $clone->widgetId = $widgetId;
        return $clone;
    }

    public function withRequired(bool $required): self
    {
        $clone = clone $this;
        $clone->required = $required;
        return $clone;
    }

    public function withMultiple(bool $multiple): self
    {
        $clone = clone $this;
        $clone->multiple = $multiple;
        return $clone;
    }

    public function withSettings(array $settings): self
    {
        $clone = clone $this;
        $clone->settings = FieldSettings::fromArray($settings);
        return $clone;
    }

    public function withValidation(array $rules): self
    {
        $clone = clone $this;
        $clone->validationRules = ValidationRules::fromArray($rules);
        return $clone;
    }

    // =========================================================================
    // Serialization
    // =========================================================================

    public function toArray(): array
    {
        return [
            'machine_name' => $this->identifier->toString(),
            'name' => $this->label->toString(),
            'field_type' => $this->type->getName(),
            'description' => $this->description,
            'help_text' => $this->helpText,
            'widget' => $this->widgetId,
            'required' => $this->required,
            'multiple' => $this->multiple,
            'cardinality' => $this->cardinality,
            'default_value' => $this->defaultValue?->get(),
            'settings' => $this->settings->all(),
            'validation' => $this->validationRules->toArray(),
            'widget_settings' => $this->widgetSettings->all(),
            'weight' => $this->weight,
            'searchable' => $this->searchable,
            'translatable' => $this->translatable,
        ];
    }
}

/**
 * FieldIdentifier - Value object for field machine name
 */
final class FieldIdentifier
{
    private function __construct(
        private readonly string $value,
    ) {
    }

    public static function from(string $value): self
    {
        $normalized = self::normalize($value);
        self::validate($normalized);
        return new self($normalized);
    }

    public static function generate(string $label): self
    {
        $machineName = 'field_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
        return new self($machineName);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));

        if (!str_starts_with($value, 'field_')) {
            $value = 'field_' . $value;
        }

        return $value;
    }

    private static function validate(string $value): void
    {
        if (!preg_match('/^field_[a-z][a-z0-9_]*$/', $value)) {
            throw new \InvalidArgumentException(
                "Invalid field identifier: '{$value}'. Must start with 'field_' followed by lowercase alphanumeric characters."
            );
        }
    }
}

/**
 * FieldLabel - Value object for field label
 */
final class FieldLabel
{
    private function __construct(
        private readonly string $value,
    ) {
    }

    public static function from(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Field label cannot be empty');
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

/**
 * FieldType - Value object for field type
 */
final class FieldType
{
    private const VALID_TYPES = [
        'string', 'text', 'textarea', 'html', 'markdown', 'code',
        'integer', 'float', 'decimal',
        'boolean', 'select', 'radio', 'checkbox', 'multiselect',
        'date', 'datetime', 'time',
        'email', 'url', 'phone', 'color', 'slug',
        'json', 'link', 'address', 'geolocation',
        'image', 'file', 'gallery', 'video',
        'entity_reference', 'taxonomy_reference', 'user_reference', 'block_reference',
    ];

    private function __construct(
        private readonly string $name,
    ) {
    }

    public static function from(string $name): self
    {
        $name = strtolower(trim($name));

        if (!in_array($name, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid field type: '{$name}'");
        }

        return new self($name);
    }

    public static function string(): self
    {
        return new self('string');
    }

    public static function text(): self
    {
        return new self('text');
    }

    public static function integer(): self
    {
        return new self('integer');
    }

    public static function boolean(): self
    {
        return new self('boolean');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name;
    }

    public function isText(): bool
    {
        return in_array($this->name, ['string', 'text', 'textarea', 'html', 'markdown', 'code']);
    }

    public function isNumeric(): bool
    {
        return in_array($this->name, ['integer', 'float', 'decimal']);
    }

    public function isSelection(): bool
    {
        return in_array($this->name, ['boolean', 'select', 'radio', 'checkbox', 'multiselect']);
    }

    public function isDate(): bool
    {
        return in_array($this->name, ['date', 'datetime', 'time']);
    }

    public function isMedia(): bool
    {
        return in_array($this->name, ['image', 'file', 'gallery', 'video']);
    }

    public function isReference(): bool
    {
        return in_array($this->name, ['entity_reference', 'taxonomy_reference', 'user_reference', 'block_reference']);
    }
}

/**
 * ValidationRules - Value object containing validation configuration
 */
final class ValidationRules
{
    private function __construct(
        private readonly array $rules,
    ) {
    }

    public static function fromArray(array $rules): self
    {
        return new self($rules);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function has(string $rule): bool
    {
        return isset($this->rules[$rule]);
    }

    public function get(string $rule): mixed
    {
        return $this->rules[$rule] ?? null;
    }

    public function with(string $rule, mixed $value): self
    {
        $rules = $this->rules;
        $rules[$rule] = $value;
        return new self($rules);
    }

    public function without(string $rule): self
    {
        $rules = $this->rules;
        unset($rules[$rule]);
        return new self($rules);
    }

    public function toArray(): array
    {
        return $this->rules;
    }

    public function isEmpty(): bool
    {
        return empty($this->rules);
    }
}
