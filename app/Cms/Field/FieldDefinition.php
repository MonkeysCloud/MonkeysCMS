<?php

declare(strict_types=1);

namespace App\Cms\Field;

use MonkeysLegion\Entity\Attributes\Column;
use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Id;

/**
 * FieldDefinition — A custom field attached to a content type or block type.
 *
 * Uses the fluent builder API for ergonomic field creation in code:
 *
 *     FieldDefinition::create('Body', 'body', 'html')
 *         ->required()
 *         ->withWidget('wysiwyg')
 *         ->withWeight(0);
 */
#[Entity(table: 'field_definitions')]
class FieldDefinition
{
    #[Id]
    public ?int $id = null;

    #[Column(type: 'string', length: 100)]
    public string $name = '';

    #[Column(type: 'string', length: 100, unique: true)]
    public string $machine_name = '';

    #[Column(type: 'string', length: 50)]
    public string $field_type = 'string';

    #[Column(type: 'string', length: 500, nullable: true)]
    public ?string $description = null;

    #[Column(type: 'string', length: 500, nullable: true)]
    public ?string $help_text = null;

    #[Column(type: 'string', length: 50, nullable: true)]
    public ?string $widget = null;

    #[Column(type: 'boolean', default: false)]
    public bool $required = false;

    #[Column(type: 'boolean', default: false)]
    public bool $multiple = false;

    #[Column(type: 'integer', default: 1)]
    public int $cardinality = 1;

    #[Column(type: 'string', nullable: true)]
    public ?string $default_value = null;

    #[Column(type: 'json', default: '{}')]
    public array $settings = [];

    #[Column(type: 'json', default: '{}')]
    public array $validation = [];

    #[Column(type: 'json', default: '{}')]
    public array $widget_settings = [];

    #[Column(type: 'integer', default: 0)]
    public int $weight = 0;

    #[Column(type: 'boolean', default: false)]
    public bool $searchable = false;

    #[Column(type: 'boolean', default: false)]
    public bool $translatable = false;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    // ── Fluent Builder ──────────────────────────────────────────────────

    public static function create(string $name, string $machineName, string $fieldType): static
    {
        $instance = new static();
        $instance->name = $name;
        $instance->machine_name = $machineName;
        $instance->field_type = $fieldType;
        return $instance;
    }

    public function required(bool $required = true): static { $this->required = $required; return $this; }
    public function withHelpText(string $text): static { $this->help_text = $text; return $this; }
    public function withWidget(string $widget): static { $this->widget = $widget; return $this; }
    public function withSettings(array $s): static { $this->settings = array_merge($this->settings, $s); return $this; }
    public function withWidgetSettings(array $s): static { $this->widget_settings = array_merge($this->widget_settings, $s); return $this; }
    public function withValidation(array $v): static { $this->validation = array_merge($this->validation, $v); return $this; }
    public function withWeight(int $w): static { $this->weight = $w; return $this; }
    public function multiple(bool $m = true): static { $this->multiple = $m; return $this; }
    public function withDefault(mixed $d): static { $this->default_value = is_scalar($d) ? (string) $d : json_encode($d); return $this; }
    public function withCardinality(int $c): static { $this->cardinality = $c; return $this; }
    public function withDescription(string $d): static { $this->description = $d; return $this; }
    public function searchable(bool $s = true): static { $this->searchable = $s; return $this; }
    public function translatable(bool $t = true): static { $this->translatable = $t; return $this; }

    // ── Accessors ───────────────────────────────────────────────────────

    public function getFieldTypeEnum(): FieldType
    {
        return FieldType::from($this->field_type);
    }

    public function getWidget(): string
    {
        return $this->widget ?? $this->getFieldTypeEnum()->getDefaultWidget();
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    // ── Serialization ───────────────────────────────────────────────────

    public function hydrate(array $data): static
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : $this->id;
        $this->name = $data['name'] ?? $this->name;
        $this->machine_name = $data['machine_name'] ?? $this->machine_name;
        $this->field_type = $data['field_type'] ?? $this->field_type;
        $this->description = $data['description'] ?? $this->description;
        $this->help_text = $data['help_text'] ?? $this->help_text;
        $this->widget = $data['widget'] ?? $this->widget;
        $this->required = (bool) ($data['required'] ?? $this->required);
        $this->multiple = (bool) ($data['multiple'] ?? $this->multiple);
        $this->cardinality = (int) ($data['cardinality'] ?? $this->cardinality);
        $this->default_value = $data['default_value'] ?? $this->default_value;
        $this->weight = (int) ($data['weight'] ?? $this->weight);
        $this->searchable = (bool) ($data['searchable'] ?? $this->searchable);
        $this->translatable = (bool) ($data['translatable'] ?? $this->translatable);

        foreach (['settings', 'validation', 'widget_settings'] as $jsonField) {
            if (isset($data[$jsonField])) {
                $this->$jsonField = is_string($data[$jsonField])
                    ? (json_decode($data[$jsonField], true) ?? [])
                    : $data[$jsonField];
            }
        }

        $this->created_at = isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : $this->created_at;
        $this->updated_at = isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : $this->updated_at;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'machine_name' => $this->machine_name,
            'field_type' => $this->field_type,
            'description' => $this->description,
            'help_text' => $this->help_text,
            'widget' => $this->widget,
            'required' => $this->required,
            'multiple' => $this->multiple,
            'cardinality' => $this->cardinality,
            'default_value' => $this->default_value,
            'settings' => $this->settings,
            'validation' => $this->validation,
            'widget_settings' => $this->widget_settings,
            'weight' => $this->weight,
            'searchable' => $this->searchable,
            'translatable' => $this->translatable,
        ];
    }

    /**
     * Generate the SQL column definition for this field
     */
    public function getSqlColumnDefinition(): string
    {
        $type = $this->getFieldTypeEnum()->getSqlType();
        $nullable = $this->required ? 'NOT NULL' : 'NULL';
        $default = '';

        if ($this->default_value !== null) {
            $fieldType = $this->getFieldTypeEnum();
            $default = ' DEFAULT ' . match ($fieldType) {
                FieldType::INTEGER, FieldType::BOOLEAN => (string) (int) $this->default_value,
                FieldType::FLOAT, FieldType::DECIMAL => (string) (float) $this->default_value,
                default => "'" . addslashes($this->default_value) . "'",
            };
        } elseif (!$this->required) {
            $default = ' DEFAULT NULL';
        }

        return "{$this->machine_name} {$type} {$nullable}{$default}";
    }

    public function prePersist(): void
    {
        $now = new \DateTimeImmutable();
        if ($this->created_at === null) {
            $this->created_at = $now;
        }
        $this->updated_at = $now;

        if (empty($this->machine_name)) {
            $this->machine_name = 'field_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $this->name));
        }
    }
}
