<?php

declare(strict_types=1);

namespace App\Cms\Fields\Validation;

use App\Cms\Fields\FieldDefinition;

/**
 * FieldValidator - Validates field values using registered rules
 * 
 * Provides a pluggable validation system where rules can be
 * registered and applied to field values.
 */
final class FieldValidator
{
    /** @var array<string, ValidationRuleInterface> */
    private array $rules = [];

    public function __construct()
    {
        $this->registerDefaultRules();
    }

    /**
     * Register a validation rule
     */
    public function registerRule(ValidationRuleInterface $rule): self
    {
        $this->rules[$rule->getName()] = $rule;
        return $this;
    }

    /**
     * Register multiple rules
     * 
     * @param ValidationRuleInterface[] $rules
     */
    public function registerRules(array $rules): self
    {
        foreach ($rules as $rule) {
            $this->registerRule($rule);
        }
        return $this;
    }

    /**
     * Get a registered rule by name
     */
    public function getRule(string $name): ?ValidationRuleInterface
    {
        return $this->rules[$name] ?? null;
    }

    /**
     * Check if a rule is registered
     */
    public function hasRule(string $name): bool
    {
        return isset($this->rules[$name]);
    }

    /**
     * Validate a single field value
     * 
     * @return array<string> Error messages
     */
    public function validateField(FieldDefinition $field, mixed $value, array $allValues = []): array
    {
        $context = new ValidationContext(
            fieldName: $field->machine_name,
            fieldLabel: $field->name,
            fieldType: $field->field_type,
            allValues: $allValues,
        );

        $errors = [];

        // Required validation first
        if ($field->required) {
            $result = $this->applyRule('required', true, $value, $context);
            if (!$result->isValid()) {
                return $result->getErrors();
            }
        }

        // Skip other validation if empty and not required
        if ($this->isEmpty($value)) {
            return [];
        }

        // Type-specific validation
        $typeErrors = $this->validateType($field, $value, $context);
        $errors = array_merge($errors, $typeErrors);

        // Custom validation rules from field definition
        foreach ($field->validation as $ruleName => $parameter) {
            $result = $this->applyRule($ruleName, $parameter, $value, $context);
            $errors = array_merge($errors, $result->getErrors());
        }

        // Settings-based validation
        $settingsErrors = $this->validateSettings($field, $value, $context);
        $errors = array_merge($errors, $settingsErrors);

        return $errors;
    }

    /**
     * Validate multiple field values
     * 
     * @param FieldDefinition[] $fields
     * @param array $values Values indexed by field machine_name
     * @return array<string, array<string>> Errors indexed by field machine_name
     */
    public function validateFields(array $fields, array $values): array
    {
        $allErrors = [];

        foreach ($fields as $field) {
            $value = $values[$field->machine_name] ?? null;
            $errors = $this->validateField($field, $value, $values);
            
            if (!empty($errors)) {
                $allErrors[$field->machine_name] = $errors;
            }
        }

        return $allErrors;
    }

    /**
     * Create a validation result from field validation
     */
    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        $errors = $this->validateField($field, $value);
        
        if (empty($errors)) {
            return ValidationResult::success();
        }
        
        return ValidationResult::failure($errors);
    }

    /**
     * Apply a single validation rule
     */
    private function applyRule(string $ruleName, mixed $parameter, mixed $value, ValidationContext $context): ValidationResult
    {
        $rule = $this->getRule($ruleName);
        
        if ($rule === null) {
            // Unknown rule - skip
            return ValidationResult::success();
        }

        return $rule->validate($value, $parameter, $context);
    }

    /**
     * Validate based on field type
     */
    private function validateType(FieldDefinition $field, mixed $value, ValidationContext $context): array
    {
        $typeRules = $this->getTypeValidationRules($field->field_type);
        $errors = [];

        foreach ($typeRules as $ruleName => $parameter) {
            $result = $this->applyRule($ruleName, $parameter, $value, $context);
            $errors = array_merge($errors, $result->getErrors());
        }

        return $errors;
    }

    /**
     * Validate based on field settings
     */
    private function validateSettings(FieldDefinition $field, mixed $value, ValidationContext $context): array
    {
        $errors = [];

        // Max length
        if ($maxLength = $field->getSetting('max_length')) {
            $result = $this->applyRule('maxLength', $maxLength, $value, $context);
            $errors = array_merge($errors, $result->getErrors());
        }

        // Min length
        if ($minLength = $field->getSetting('min_length')) {
            $result = $this->applyRule('minLength', $minLength, $value, $context);
            $errors = array_merge($errors, $result->getErrors());
        }

        // Max value
        if (($max = $field->getSetting('max')) !== null) {
            $result = $this->applyRule('max', $max, $value, $context);
            $errors = array_merge($errors, $result->getErrors());
        }

        // Min value
        if (($min = $field->getSetting('min')) !== null) {
            $result = $this->applyRule('min', $min, $value, $context);
            $errors = array_merge($errors, $result->getErrors());
        }

        // Pattern
        if ($pattern = $field->getSetting('pattern')) {
            $result = $this->applyRule('pattern', $pattern, $value, $context);
            $errors = array_merge($errors, $result->getErrors());
        }

        return $errors;
    }

    /**
     * Get validation rules for a field type
     */
    private function getTypeValidationRules(string $fieldType): array
    {
        return match ($fieldType) {
            'email' => ['email' => true],
            'url' => ['url' => true],
            'integer' => ['integer' => true],
            'float', 'decimal' => ['numeric' => true],
            'date' => ['date' => true],
            'datetime' => ['date' => true],
            'json' => ['json' => true],
            'color' => ['color' => true],
            'slug' => ['slug' => true],
            default => [],
        };
    }

    /**
     * Check if a value is considered empty
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * Register default validation rules
     */
    private function registerDefaultRules(): void
    {
        $this->registerRules([
            new RequiredRule(),
            new MinLengthRule(),
            new MaxLengthRule(),
            new MinValueRule(),
            new MaxValueRule(),
            new PatternRule(),
            new EmailRule(),
            new UrlRule(),
            new IntegerRule(),
            new NumericRule(),
            new InArrayRule(),
            new DateRule(),
            new JsonRule(),
            new ColorRule(),
            new SlugRule(),
        ]);
    }
}
