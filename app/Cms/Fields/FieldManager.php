<?php

declare(strict_types=1);

namespace App\Cms\Fields;

use App\Cms\Fields\Form\FormBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Storage\FieldValueStorage;
use App\Cms\Fields\Storage\FieldValueStorageInterface;
use App\Cms\Fields\Validation\FieldValidator;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\WidgetFactory;
use App\Cms\Fields\Widget\WidgetInterface;
use App\Cms\Fields\Widget\WidgetRegistry;

/**
 * FieldManager - Main orchestration class for the field system
 *
 * This class provides a unified API for working with fields, including:
 * - Field definition management
 * - Value storage and retrieval
 * - Rendering (editable forms and display)
 * - Validation
 * - Widget management
 *
 * Usage:
 * ```php
 * $manager = FieldManager::create($pdo);
 *
 * // Define a field
 * $field = $manager->defineField('title', 'string')
 *     ->required()
 *     ->withWidget('text_input')
 *     ->save();
 *
 * // Store a value
 * $manager->setValue($field, 'node', 1, 'Hello World');
 *
 * // Render form
 * echo $manager->renderForm($field, $value);
 *
 * // Validate
 * $result = $manager->validate($field, $value);
 * ```
 */
final class FieldManager
{
    private ?FieldRepositoryInterface $repository;
    private WidgetRegistry $widgets;
    private FieldValidator $validator;
    private FormBuilder $formBuilder;
    private ?FieldValueStorageInterface $storage;

    public function __construct(
        ?FieldRepositoryInterface $repository = null,
        ?WidgetRegistry $widgets = null,
        ?FieldValidator $validator = null,
        ?FieldValueStorageInterface $storage = null
    ) {
        $this->repository = $repository;
        $this->validator = $validator ?? new FieldValidator();
        $this->widgets = $widgets ?? WidgetFactory::create($this->validator);
        $this->formBuilder = new FormBuilder($this->widgets);
        $this->storage = $storage;
    }

    /**
     * Create a manager with database connections
     */
    public static function create(\PDO $pdo): self
    {
        $validator = new FieldValidator();
        $widgets = WidgetFactory::create($validator);
        $repository = new FieldRepository($pdo);
        $storage = new FieldValueStorage($pdo);

        return new self($repository, $widgets, $validator, $storage);
    }

    /**
     * Create a manager for testing (in-memory)
     */
    public static function createForTesting(): self
    {
        $validator = new FieldValidator();
        $widgets = WidgetFactory::create($validator);
        $repository = new InMemoryFieldRepository();

        return new self($repository, $widgets, $validator, null);
    }

    // =========================================================================
    // Field Definition
    // =========================================================================

    /**
     * Start defining a new field
     */
    public function defineField(string $machineName, string $type): FieldDefinitionBuilder
    {
        return new FieldDefinitionBuilder($this, $machineName, $type);
    }

    /**
     * Get a field by ID
     */
    public function getField(int $id): ?FieldDefinition
    {
        return $this->repository?->find($id);
    }

    /**
     * Get a field by machine name
     */
    public function getFieldByName(string $machineName): ?FieldDefinition
    {
        return $this->repository?->findByMachineName($machineName);
    }

    /**
     * Get all fields
     *
     * @return FieldDefinition[]
     */
    public function getAllFields(): array
    {
        return $this->repository?->findAll() ?? [];
    }

    /**
     * Get fields for an entity type
     *
     * @return FieldDefinition[]
     */
    public function getFieldsForEntity(string $entityType, ?int $bundleId = null): array
    {
        return $this->repository?->findByEntityType($entityType, $bundleId) ?? [];
    }

    /**
     * Save a field definition
     */
    public function saveField(FieldDefinition $field): FieldDefinition
    {
        $this->repository?->save($field);
        return $field;
    }

    /**
     * Delete a field definition
     */
    public function deleteField(int $id): void
    {
        $field = $this->getField($id);
        if ($field && $this->repository) {
            $this->repository->delete($field);
        }
    }

    // =========================================================================
    // Value Storage
    // =========================================================================

    /**
     * Get field value for an entity
     */
    public function getValue(FieldDefinition|int $field, string $entityType, int $entityId, string $langcode = 'en'): mixed
    {
        $fieldId = $field instanceof FieldDefinition ? $field->getId() : $field;
        return $this->storage?->getValue($fieldId, $entityType, $entityId, $langcode);
    }

    /**
     * Get all field values for an entity
     *
     * @return array<string, mixed>
     */
    public function getEntityValues(string $entityType, int $entityId, string $langcode = 'en'): array
    {
        return $this->storage?->getEntityValues($entityType, $entityId, $langcode) ?? [];
    }

    /**
     * Set field value for an entity
     */
    public function setValue(FieldDefinition|int $field, string $entityType, int $entityId, mixed $value, string $langcode = 'en'): void
    {
        $fieldId = $field instanceof FieldDefinition ? $field->getId() : $field;
        $this->storage?->setValue($fieldId, $entityType, $entityId, $value, $langcode);
    }

    /**
     * Set multiple field values for an entity
     *
     * @param array<int, mixed> $values Field ID => value
     */
    public function setValues(string $entityType, int $entityId, array $values, string $langcode = 'en'): void
    {
        $this->storage?->setValues($entityType, $entityId, $values, $langcode);
    }

    /**
     * Delete field value
     */
    public function deleteValue(FieldDefinition|int $field, string $entityType, int $entityId, string $langcode = 'en'): void
    {
        $fieldId = $field instanceof FieldDefinition ? $field->getId() : $field;
        $this->storage?->deleteValue($fieldId, $entityType, $entityId, $langcode);
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Render a single field for editing
     */
    public function renderField(FieldDefinition $field, mixed $value = null, ?RenderContext $context = null): RenderResult
    {
        $widget = $this->getWidgetForField($field);
        $context = $context ?? RenderContext::create();

        return $widget->render($field, $value, $context);
    }

    /**
     * Render a single field for display (non-editable)
     */
    public function renderFieldDisplay(FieldDefinition $field, mixed $value, ?RenderContext $context = null): RenderResult
    {
        $widget = $this->getWidgetForField($field);
        $context = $context ?? RenderContext::create();

        return $widget->renderDisplay($field, $value, $context);
    }

    /**
     * Render multiple fields as a form
     *
     * @param FieldDefinition[] $fields
     * @param array<string, mixed> $values
     * @param array<string, array<string>> $errors
     */
    public function renderForm(array $fields, array $values = [], array $errors = [], array $options = []): string
    {
        return $this->formBuilder->build($fields, $values, $errors, $options);
    }

    /**
     * Render fields for an entity
     */
    public function renderEntityForm(string $entityType, int $entityId, ?int $bundleId = null, array $options = []): string
    {
        $fields = $this->getFieldsForEntity($entityType, $bundleId);
        $values = $this->getEntityValues($entityType, $entityId);

        return $this->renderForm($fields, $values, [], $options);
    }

    /**
     * Render fields for entity display
     */
    public function renderEntityDisplay(string $entityType, int $entityId, ?int $bundleId = null): string
    {
        $fields = $this->getFieldsForEntity($entityType, $bundleId);
        $values = $this->getEntityValues($entityType, $entityId);
        $context = RenderContext::create();

        $html = '<div class="field-display-container">';

        foreach ($fields as $field) {
            $machineName = $field->getMachineName();
            $value = $values[$machineName] ?? null;

            $result = $this->renderFieldDisplay($field, $value, $context);
            $html .= $result->getHtml();
        }

        $html .= '</div>';

        return $html;
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Validate a single field value
     */
    public function validateField(FieldDefinition $field, mixed $value): ValidationResult
    {
        return $this->validator->validate($field, $value);
    }

    /**
     * Validate multiple field values
     *
     * @param FieldDefinition[] $fields
     * @param array<string, mixed> $values
     * @return array<string, ValidationResult>
     */
    public function validateFields(array $fields, array $values): array
    {
        return $this->validator->validateMultiple($fields, $values);
    }

    /**
     * Validate values for an entity
     *
     * @return array<string, ValidationResult>
     */
    public function validateEntityValues(string $entityType, array $values, ?int $bundleId = null): array
    {
        $fields = $this->getFieldsForEntity($entityType, $bundleId);
        return $this->validateFields($fields, $values);
    }

    /**
     * Check if all field values are valid
     *
     * @param FieldDefinition[] $fields
     * @param array<string, mixed> $values
     */
    public function isValid(array $fields, array $values): bool
    {
        $results = $this->validateFields($fields, $values);

        foreach ($results as $result) {
            if (!$result->isValid()) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // Widget Management
    // =========================================================================

    /**
     * Get the widget registry
     */
    public function getWidgetRegistry(): WidgetRegistry
    {
        return $this->widgets;
    }

    /**
     * Get widget for a field
     */
    public function getWidgetForField(FieldDefinition $field): WidgetInterface
    {
        $widgetId = $field->getWidget();

        if ($widgetId && $this->widgets->has($widgetId)) {
            return $this->widgets->get($widgetId);
        }

        return $this->widgets->getForType($field->getType());
    }

    /**
     * Get available widgets for a field type
     *
     * @return WidgetInterface[]
     */
    public function getWidgetsForType(string $type): array
    {
        return $this->widgets->getForFieldType($type);
    }

    /**
     * Register a custom widget
     */
    public function registerWidget(WidgetInterface $widget): void
    {
        $this->widgets->register($widget);
    }

    // =========================================================================
    // Value Processing
    // =========================================================================

    /**
     * Prepare a value for storage (from form submission)
     */
    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        $widget = $this->getWidgetForField($field);
        return $widget->prepareValue($field, $value);
    }

    /**
     * Format a value for display
     */
    public function formatValue(FieldDefinition $field, mixed $value): mixed
    {
        $widget = $this->getWidgetForField($field);
        return $widget->formatValue($field, $value);
    }

    // =========================================================================
    // Assets
    // =========================================================================

    /**
     * Get all CSS assets needed for current fields
     *
     * @param FieldDefinition[] $fields
     * @return string[]
     */
    public function getCssAssets(array $fields): array
    {
        $assets = [];

        foreach ($fields as $field) {
            $widget = $this->getWidgetForField($field);
            $widgetAssets = $widget->getAssets();
            $assets = array_merge($assets, $widgetAssets->getCss());
        }

        return array_unique($assets);
    }

    /**
     * Get all JS assets needed for current fields
     *
     * @param FieldDefinition[] $fields
     * @return string[]
     */
    public function getJsAssets(array $fields): array
    {
        $assets = [];

        foreach ($fields as $field) {
            $widget = $this->getWidgetForField($field);
            $widgetAssets = $widget->getAssets();
            $assets = array_merge($assets, $widgetAssets->getJs());
        }

        return array_unique($assets);
    }

    /**
     * Render asset tags (CSS and JS)
     *
     * @param FieldDefinition[] $fields
     */
    public function renderAssetTags(array $fields): string
    {
        $css = $this->getCssAssets($fields);
        $js = $this->getJsAssets($fields);

        $html = '';

        foreach ($css as $url) {
            $html .= '<link rel="stylesheet" href="' . htmlspecialchars($url) . '">' . "\n";
        }

        foreach ($js as $url) {
            $html .= '<script src="' . htmlspecialchars($url) . '"></script>' . "\n";
        }

        return $html;
    }
}

/**
 * FieldDefinitionBuilder - Fluent builder for field definitions
 */
final class FieldDefinitionBuilder
{
    private FieldManager $manager;
    private array $data;

    public function __construct(FieldManager $manager, string $machineName, string $type)
    {
        $this->manager = $manager;
        $this->data = [
            'machine_name' => 'field_' . ltrim($machineName, 'field_'),
            'field_type' => $type,
            'name' => ucwords(str_replace('_', ' ', $machineName)),
        ];
    }

    public function name(string $name): self
    {
        $this->data['name'] = $name;
        return $this;
    }

    public function description(string $description): self
    {
        $this->data['description'] = $description;
        return $this;
    }

    public function helpText(string $help): self
    {
        $this->data['help_text'] = $help;
        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->data['required'] = $required;
        return $this;
    }

    public function multiple(bool $multiple = true, int $cardinality = -1): self
    {
        $this->data['multiple'] = $multiple;
        $this->data['cardinality'] = $cardinality;
        return $this;
    }

    public function withWidget(string $widget): self
    {
        $this->data['widget'] = $widget;
        return $this;
    }

    public function widgetSettings(array $settings): self
    {
        $this->data['widget_settings'] = $settings;
        return $this;
    }

    public function settings(array $settings): self
    {
        $this->data['settings'] = $settings;
        return $this;
    }

    public function withDefault(mixed $value): self
    {
        $this->data['default_value'] = $value;
        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->data['searchable'] = $searchable;
        return $this;
    }

    public function translatable(bool $translatable = true): self
    {
        $this->data['translatable'] = $translatable;
        return $this;
    }

    public function validation(array $rules): self
    {
        $this->data['validation'] = $rules;
        return $this;
    }

    public function weight(int $weight): self
    {
        $this->data['weight'] = $weight;
        return $this;
    }

    /**
     * Build the field definition without saving
     */
    public function build(): FieldDefinition
    {
        return new FieldDefinition($this->data);
    }

    /**
     * Build and save the field definition
     */
    public function save(): FieldDefinition
    {
        $field = $this->build();
        return $this->manager->saveField($field);
    }
}
