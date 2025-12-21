<?php

declare(strict_types=1);

namespace App\Cms\Forms;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Widgets\FieldWidgetManager;

/**
 * FormBuilder - Builds and renders forms from field definitions
 *
 * Generates complete forms for content types, block types, and
 * other entities with dynamic field configurations.
 *
 * @example
 * ```php
 * // Build a form
 * $form = $formBuilder->create('content_edit', $fields)
 *     ->setValues($existingData)
 *     ->setAction('/admin/content/article/1')
 *     ->setMethod('PUT')
 *     ->addGroup('main', 'Main Content', ['title', 'body', 'summary'])
 *     ->addGroup('meta', 'Meta Information', ['slug', 'meta_title', 'meta_description'])
 *     ->addGroup('taxonomy', 'Categories', ['tags', 'categories'])
 *     ->build();
 *
 * // Render the form
 * echo $form->render();
 *
 * // Process submission
 * $values = $formBuilder->processSubmission($fields, $_POST);
 * $errors = $formBuilder->validate($fields, $values);
 * ```
 */
class FormBuilder
{
    private FieldWidgetManager $widgetManager;

    private string $formId = '';
    private string $action = '';
    private string $method = 'POST';
    private array $fields = [];
    private array $values = [];
    private array $errors = [];
    private array $groups = [];
    private array $attributes = [];
    private bool $hasFiles = false;
    private ?string $submitLabel = null;
    private ?string $cancelUrl = null;

    public function __construct(FieldWidgetManager $widgetManager)
    {
        $this->widgetManager = $widgetManager;
    }

    /**
     * Create a new form builder instance
     *
     * @param string $formId Unique form identifier
     * @param array<FieldDefinition> $fields Field definitions
     */
    public function create(string $formId, array $fields): self
    {
        $builder = new self($this->widgetManager);
        $builder->formId = $formId;
        $builder->fields = $fields;

        // Check for file upload fields
        foreach ($fields as $field) {
            if (in_array($field->field_type, ['image', 'file', 'gallery', 'video'])) {
                $builder->hasFiles = true;
                break;
            }
        }

        return $builder;
    }

    /**
     * Set form action URL
     */
    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * Set form method
     */
    public function setMethod(string $method): self
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * Set initial field values
     */
    public function setValues(array $values): self
    {
        $this->values = $values;
        return $this;
    }

    /**
     * Set validation errors
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * Add a form group
     */
    public function addGroup(string $id, string $label, array $fieldNames, array $options = []): self
    {
        $this->groups[$id] = [
            'label' => $label,
            'fields' => $fieldNames,
            'collapsed' => $options['collapsed'] ?? false,
            'description' => $options['description'] ?? '',
            'weight' => $options['weight'] ?? count($this->groups) * 10,
        ];
        return $this;
    }

    /**
     * Add form attribute
     */
    public function setAttribute(string $name, mixed $value): self
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    /**
     * Set submit button label
     */
    public function setSubmitLabel(string $label): self
    {
        $this->submitLabel = $label;
        return $this;
    }

    /**
     * Set cancel URL
     */
    public function setCancelUrl(string $url): self
    {
        $this->cancelUrl = $url;
        return $this;
    }

    /**
     * Build the form
     */
    public function build(): Form
    {
        return new Form(
            $this->formId,
            $this->action,
            $this->method,
            $this->fields,
            $this->values,
            $this->errors,
            $this->groups,
            $this->attributes,
            $this->hasFiles,
            $this->submitLabel,
            $this->cancelUrl,
            $this->widgetManager
        );
    }

    /**
     * Process form submission
     */
    public function processSubmission(array $fields, array $submittedData): array
    {
        return $this->widgetManager->prepareValues($fields, $submittedData);
    }

    /**
     * Validate form data
     */
    public function validate(array $fields, array $values): array
    {
        return $this->widgetManager->validateValues($fields, $values);
    }

    /**
     * Render a single field
     */
    public function renderField(FieldDefinition $field, mixed $value = null, array $context = []): string
    {
        $context['form_id'] = $context['form_id'] ?? 'form';
        $context['errors'] = $context['errors'] ?? [];

        return $this->widgetManager->renderField($field, $value, $context);
    }
}

/**
 * Form - Renderable form object
 */
class Form
{
    public function __construct(
        private readonly string $formId,
        private readonly string $action,
        private readonly string $method,
        private readonly array $fields,
        private readonly array $values,
        private readonly array $errors,
        private readonly array $groups,
        private readonly array $attributes,
        private readonly bool $hasFiles,
        private readonly ?string $submitLabel,
        private readonly ?string $cancelUrl,
        private readonly FieldWidgetManager $widgetManager,
    ) {
    }

    /**
     * Render the complete form
     */
    public function render(): string
    {
        $html = $this->renderFormOpen();
        $html .= $this->renderFields();
        $html .= $this->renderActions();
        $html .= $this->renderFormClose();
        $html .= $this->renderAssets();

        return $html;
    }

    /**
     * Render form opening tag
     */
    public function renderFormOpen(): string
    {
        $attrs = [
            'id' => $this->formId,
            'action' => $this->action,
            'method' => $this->method === 'GET' ? 'GET' : 'POST',
            'class' => 'cms-form',
        ];

        if ($this->hasFiles) {
            $attrs['enctype'] = 'multipart/form-data';
        }

        $attrs = array_merge($attrs, $this->attributes);

        $html = '<form ' . $this->buildAttributes($attrs) . '>';

        // Method spoofing for PUT/PATCH/DELETE
        if (!in_array($this->method, ['GET', 'POST'])) {
            $html .= '<input type="hidden" name="_method" value="' . $this->escape($this->method) . '">';
        }

        return $html;
    }

    /**
     * Render form closing tag
     */
    public function renderFormClose(): string
    {
        return '</form>';
    }

    /**
     * Render all fields (grouped or ungrouped)
     */
    public function renderFields(): string
    {
        if (empty($this->groups)) {
            return $this->renderUngroupedFields();
        }

        return $this->renderGroupedFields();
    }

    /**
     * Render fields without grouping
     */
    private function renderUngroupedFields(): string
    {
        $html = '<div class="cms-form__fields">';

        $context = [
            'form_id' => $this->formId,
            'errors' => $this->errors,
        ];

        foreach ($this->fields as $field) {
            $value = $this->values[$field->machine_name] ?? $field->default_value;
            $html .= $this->widgetManager->renderField($field, $value, $context);
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render fields with grouping
     */
    private function renderGroupedFields(): string
    {
        // Sort groups by weight
        $sortedGroups = $this->groups;
        uasort($sortedGroups, fn($a, $b) => $a['weight'] <=> $b['weight']);

        // Index fields by machine name
        $fieldIndex = [];
        foreach ($this->fields as $field) {
            $fieldIndex[$field->machine_name] = $field;
        }

        // Track rendered fields
        $renderedFields = [];

        $context = [
            'form_id' => $this->formId,
            'errors' => $this->errors,
        ];

        $html = '<div class="cms-form__groups">';

        foreach ($sortedGroups as $groupId => $group) {
            $collapsedClass = $group['collapsed'] ? ' cms-form__group--collapsed' : '';

            $html .= '<fieldset class="cms-form__group' . $collapsedClass . '" id="' . $this->escape($this->formId . '_group_' . $groupId) . '">';
            $html .= '<legend class="cms-form__group-legend">';
            $html .= '<button type="button" class="cms-form__group-toggle">' . $this->escape($group['label']) . '</button>';
            $html .= '</legend>';

            if ($group['description']) {
                $html .= '<div class="cms-form__group-description">' . $this->escape($group['description']) . '</div>';
            }

            $html .= '<div class="cms-form__group-content">';

            foreach ($group['fields'] as $fieldName) {
                if (isset($fieldIndex[$fieldName])) {
                    $field = $fieldIndex[$fieldName];
                    $value = $this->values[$field->machine_name] ?? $field->default_value;
                    $html .= $this->widgetManager->renderField($field, $value, $context);
                    $renderedFields[$fieldName] = true;
                }
            }

            $html .= '</div>';
            $html .= '</fieldset>';
        }

        // Render any ungrouped fields
        $ungrouped = [];
        foreach ($this->fields as $field) {
            if (!isset($renderedFields[$field->machine_name])) {
                $ungrouped[] = $field;
            }
        }

        if (!empty($ungrouped)) {
            $html .= '<fieldset class="cms-form__group">';
            $html .= '<legend class="cms-form__group-legend">Other</legend>';
            $html .= '<div class="cms-form__group-content">';

            foreach ($ungrouped as $field) {
                $value = $this->values[$field->machine_name] ?? $field->default_value;
                $html .= $this->widgetManager->renderField($field, $value, $context);
            }

            $html .= '</div>';
            $html .= '</fieldset>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render form actions (submit, cancel buttons)
     */
    public function renderActions(): string
    {
        $submitLabel = $this->submitLabel ?? 'Save';

        $html = '<div class="cms-form__actions">';
        $html .= '<button type="submit" class="cms-form__submit btn btn-primary">' . $this->escape($submitLabel) . '</button>';

        if ($this->cancelUrl) {
            $html .= '<a href="' . $this->escape($this->cancelUrl) . '" class="cms-form__cancel btn btn-secondary">Cancel</a>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render required CSS and JS assets
     */
    public function renderAssets(): string
    {
        $html = '';

        // CSS
        $cssAssets = $this->widgetManager->getCssAssets();
        foreach ($cssAssets as $css) {
            $html .= '<link rel="stylesheet" href="' . $this->escape($css) . '">';
        }

        // JS
        $jsAssets = $this->widgetManager->getJsAssets();
        foreach ($jsAssets as $js) {
            $html .= '<script src="' . $this->escape($js) . '" defer></script>';
        }

        // Init scripts
        $html .= $this->widgetManager->getInitScripts();

        return $html;
    }

    /**
     * Get form ID
     */
    public function getFormId(): string
    {
        return $this->formId;
    }

    /**
     * Check if form has errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Build HTML attributes string
     */
    private function buildAttributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value === true) {
                $parts[] = $this->escape($key);
            } elseif ($value !== false && $value !== null) {
                $parts[] = $this->escape($key) . '="' . $this->escape((string) $value) . '"';
            }
        }
        return implode(' ', $parts);
    }

    /**
     * HTML escape
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
