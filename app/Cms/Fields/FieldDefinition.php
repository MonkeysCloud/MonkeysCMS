<?php

declare(strict_types=1);

namespace App\Cms\Fields;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * FieldDefinition - Defines a custom field that can be attached to any entity type
 * 
 * Fields can be attached to:
 * - Block types
 * - Content types
 * - Taxonomy vocabularies
 * - User profiles
 */
#[ContentType(
    tableName: 'field_definitions',
    label: 'Field Definition',
    description: 'Custom field definitions for dynamic content types'
)]
class FieldDefinition extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(type: 'string', label: 'Field Name', required: true, length: 100)]
    public string $name = '';

    #[Field(type: 'string', label: 'Machine Name', required: true, length: 100, unique: true)]
    public string $machine_name = '';

    #[Field(type: 'string', label: 'Field Type', required: true, length: 50)]
    public string $field_type = 'string';

    #[Field(type: 'string', label: 'Description', required: false, length: 500)]
    public ?string $description = null;

    #[Field(type: 'string', label: 'Help Text', required: false, length: 500)]
    public ?string $help_text = null;

    #[Field(type: 'string', label: 'Widget', required: false, length: 50)]
    public ?string $widget = null;

    #[Field(type: 'boolean', label: 'Required', default: false)]
    public bool $required = false;

    #[Field(type: 'boolean', label: 'Multiple Values', default: false)]
    public bool $multiple = false;

    #[Field(type: 'int', label: 'Cardinality', default: 1)]
    public int $cardinality = 1; // -1 = unlimited

    #[Field(type: 'string', label: 'Default Value', required: false)]
    public ?string $default_value = null;

    #[Field(type: 'json', label: 'Settings', default: [])]
    public array $settings = [];

    #[Field(type: 'json', label: 'Validation Rules', default: [])]
    public array $validation = [];

    #[Field(type: 'json', label: 'Widget Settings', default: [])]
    public array $widget_settings = [];

    #[Field(type: 'int', label: 'Weight', default: 0)]
    public int $weight = 0;

    #[Field(type: 'boolean', label: 'Searchable', default: false)]
    public bool $searchable = false;

    #[Field(type: 'boolean', label: 'Translatable', default: false)]
    public bool $translatable = false;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    public function prePersist(): void
    {
        parent::prePersist();
        
        if (empty($this->machine_name)) {
            $this->machine_name = 'field_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $this->name));
        }
        
        // Ensure machine_name starts with field_
        if (!str_starts_with($this->machine_name, 'field_')) {
            $this->machine_name = 'field_' . $this->machine_name;
        }
    }

    /**
     * Get the FieldType enum
     */
    public function getFieldTypeEnum(): FieldType
    {
        return FieldType::from($this->field_type);
    }

    /**
     * Get the widget to use for this field
     */
    public function getWidget(): string
    {
        return $this->widget ?? $this->getFieldTypeEnum()->getDefaultWidget();
    }

    /**
     * Get a specific setting
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Get validation rules as array
     */
    public function getValidationRules(): array
    {
        $rules = $this->validation;
        
        if ($this->required) {
            $rules['required'] = true;
        }
        
        // Add type-specific validation
        $fieldType = $this->getFieldTypeEnum();
        
        if ($fieldType === FieldType::EMAIL) {
            $rules['email'] = true;
        } elseif ($fieldType === FieldType::URL) {
            $rules['url'] = true;
        } elseif ($fieldType === FieldType::INTEGER) {
            $rules['integer'] = true;
        } elseif ($fieldType === FieldType::FLOAT || $fieldType === FieldType::DECIMAL) {
            $rules['numeric'] = true;
        }
        
        return $rules;
    }

    /**
     * Cast value to appropriate PHP type
     */
    public function castValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $fieldType = $this->getFieldTypeEnum();

        return match ($fieldType) {
            FieldType::INTEGER, FieldType::ENTITY_REFERENCE,
            FieldType::TAXONOMY_REFERENCE, FieldType::USER_REFERENCE,
            FieldType::BLOCK_REFERENCE, FieldType::IMAGE,
            FieldType::FILE, FieldType::VIDEO => (int) $value,
            
            FieldType::FLOAT, FieldType::DECIMAL => (float) $value,
            
            FieldType::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            
            FieldType::DATE => $value instanceof \DateTimeInterface 
                ? $value 
                : new \DateTimeImmutable($value),
            
            FieldType::DATETIME => $value instanceof \DateTimeInterface 
                ? $value 
                : new \DateTimeImmutable($value),
            
            FieldType::CHECKBOX, FieldType::MULTISELECT, FieldType::GALLERY,
            FieldType::JSON, FieldType::LINK, FieldType::ADDRESS,
            FieldType::GEOLOCATION => is_array($value) ? $value : json_decode($value, true),
            
            default => (string) $value,
        };
    }

    /**
     * Serialize value for database storage
     */
    public function serializeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $fieldType = $this->getFieldTypeEnum();

        return match ($fieldType) {
            FieldType::BOOLEAN => $value ? '1' : '0',
            
            FieldType::DATE => $value instanceof \DateTimeInterface 
                ? $value->format('Y-m-d')
                : $value,
            
            FieldType::DATETIME => $value instanceof \DateTimeInterface 
                ? $value->format('Y-m-d H:i:s')
                : $value,
            
            FieldType::TIME => $value instanceof \DateTimeInterface 
                ? $value->format('H:i:s')
                : $value,
            
            FieldType::CHECKBOX, FieldType::MULTISELECT, FieldType::GALLERY,
            FieldType::JSON, FieldType::LINK, FieldType::ADDRESS,
            FieldType::GEOLOCATION => json_encode($value),
            
            default => (string) $value,
        };
    }

    /**
     * Get default options for select/checkbox/radio fields
     */
    public function getOptions(): array
    {
        return $this->getSetting('options', []);
    }

    /**
     * Check if field supports multiple values
     */
    public function supportsMultiple(): bool
    {
        return $this->multiple || $this->getFieldTypeEnum()->supportsMultiple();
    }

    /**
     * Generate SQL column definition
     */
    public function getSqlColumnDefinition(): string
    {
        $type = $this->getFieldTypeEnum()->getSqlType();
        $nullable = $this->required ? 'NOT NULL' : 'NULL';
        $default = '';
        
        if ($this->default_value !== null) {
            $default = " DEFAULT " . $this->getSqlDefaultValue();
        } elseif (!$this->required) {
            $default = " DEFAULT NULL";
        }
        
        return "{$this->machine_name} {$type} {$nullable}{$default}";
    }

    private function getSqlDefaultValue(): string
    {
        $fieldType = $this->getFieldTypeEnum();
        
        return match ($fieldType) {
            FieldType::INTEGER, FieldType::BOOLEAN => (string) (int) $this->default_value,
            FieldType::FLOAT, FieldType::DECIMAL => (string) (float) $this->default_value,
            default => "'" . addslashes($this->default_value) . "'",
        };
    }

    /**
     * Populate from array data (hydrate)
     */
    public function hydrate(array $data): self
    {
        $this->id = $data['id'] ?? $this->id;
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
        $this->settings = $data['settings'] ?? $this->settings;
        $this->validation = $data['validation'] ?? $this->validation;
        $this->widget_settings = $data['widget_settings'] ?? $this->widget_settings;
        $this->weight = (int) ($data['weight'] ?? $this->weight);
        $this->searchable = (bool) ($data['searchable'] ?? $this->searchable);
        $this->translatable = (bool) ($data['translatable'] ?? $this->translatable);
        
        return $this;
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->hydrate($data);
        return $instance;
    }

    /**
     * Convert to array (for API responses)
     */
    public function toArray(bool $includeNulls = false): array
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
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Validate a value against field rules
     * @return array<string> Error messages
     */
    public function validateValue(mixed $value): array
    {
        $errors = [];
        
        // Check required
        if ($this->required && $this->isEmpty($value)) {
            $errors[] = "{$this->name} is required";
            return $errors;
        }
        
        // Skip further validation if empty and not required
        if ($this->isEmpty($value)) {
            return $errors;
        }
        
        // Type-specific validation
        $fieldType = $this->getFieldTypeEnum();
        
        switch ($fieldType) {
            case FieldType::EMAIL:
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Please enter a valid email address';
                }
                break;
                
            case FieldType::URL:
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = 'Please enter a valid URL';
                }
                break;
                
            case FieldType::INTEGER:
                if (!is_numeric($value) || (int) $value != $value) {
                    $errors[] = 'Please enter a valid integer';
                }
                break;
                
            case FieldType::FLOAT:
            case FieldType::DECIMAL:
                if (!is_numeric($value)) {
                    $errors[] = 'Please enter a valid number';
                }
                break;
                
            case FieldType::DATE:
                try {
                    new \DateTimeImmutable($value);
                } catch (\Exception $e) {
                    $errors[] = 'Please enter a valid date';
                }
                break;
                
            case FieldType::DATETIME:
                try {
                    new \DateTimeImmutable($value);
                } catch (\Exception $e) {
                    $errors[] = 'Please enter a valid date and time';
                }
                break;
        }
        
        // Custom validation rules
        foreach ($this->validation as $rule => $param) {
            $ruleErrors = $this->applyValidationRule($rule, $param, $value);
            $errors = array_merge($errors, $ruleErrors);
        }
        
        // Settings-based validation
        if ($max = $this->getSetting('max_length')) {
            if (is_string($value) && strlen($value) > $max) {
                $errors[] = "Maximum length is {$max} characters";
            }
        }
        
        if ($min = $this->getSetting('min_length')) {
            if (is_string($value) && strlen($value) < $min) {
                $errors[] = "Minimum length is {$min} characters";
            }
        }
        
        if ($max = $this->getSetting('max')) {
            if (is_numeric($value) && $value > $max) {
                $errors[] = "Value must be at most {$max}";
            }
        }
        
        if ($min = $this->getSetting('min')) {
            if (is_numeric($value) && $value < $min) {
                $errors[] = "Value must be at least {$min}";
            }
        }
        
        if ($pattern = $this->getSetting('pattern')) {
            if (is_string($value) && !preg_match('/' . $pattern . '/', $value)) {
                $errors[] = 'Value does not match the required pattern';
            }
        }
        
        return $errors;
    }

    /**
     * Check if a value is considered empty
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * Apply a single validation rule
     */
    private function applyValidationRule(string $rule, mixed $param, mixed $value): array
    {
        $errors = [];
        
        switch ($rule) {
            case 'min':
                if (is_numeric($value) && $value < $param) {
                    $errors[] = "Value must be at least {$param}";
                }
                break;
                
            case 'max':
                if (is_numeric($value) && $value > $param) {
                    $errors[] = "Value must be at most {$param}";
                }
                break;
                
            case 'minLength':
                if (is_string($value) && strlen($value) < $param) {
                    $errors[] = "Minimum length is {$param} characters";
                }
                break;
                
            case 'maxLength':
                if (is_string($value) && strlen($value) > $param) {
                    $errors[] = "Maximum length is {$param} characters";
                }
                break;
                
            case 'pattern':
                if (is_string($value) && !preg_match('/' . $param . '/', $value)) {
                    $errors[] = 'Value does not match the required pattern';
                }
                break;
                
            case 'in':
                if (is_array($param) && !in_array($value, $param)) {
                    $errors[] = 'Value must be one of: ' . implode(', ', $param);
                }
                break;
                
            case 'notIn':
                if (is_array($param) && in_array($value, $param)) {
                    $errors[] = 'Value is not allowed';
                }
                break;
                
            case 'regex':
                if (is_string($value) && !preg_match($param, $value)) {
                    $errors[] = 'Value does not match the required format';
                }
                break;
        }
        
        return $errors;
    }

    /**
     * Get widget settings merged with defaults
     */
    public function getWidgetSettings(): array
    {
        return array_merge($this->settings, $this->widget_settings);
    }
}
