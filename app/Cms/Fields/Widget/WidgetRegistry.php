<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\AssetCollection;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\FieldValidator;
use App\Cms\Fields\Validation\ValidationResult;

/**
 * WidgetRegistry - Central registry for field widgets
 *
 * Manages widget registration, resolution, and provides
 * a unified API for field rendering and validation.
 */
final class WidgetRegistry
{
    /** @var array<string, WidgetInterface> */
    private array $widgets = [];

    /** @var array<string, string> */
    private array $typeDefaults = [];

    private AssetCollection $collectedAssets;

    public function __construct(
        private readonly FieldValidator $validator,
    ) {
        $this->collectedAssets = new AssetCollection();
    }

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * Register a widget
     */
    public function register(WidgetInterface $widget): self
    {
        $this->widgets[$widget->getId()] = $widget;
        return $this;
    }

    /**
     * Register multiple widgets
     *
     * @param WidgetInterface[] $widgets
     */
    public function registerMany(array $widgets): self
    {
        foreach ($widgets as $widget) {
            $this->register($widget);
        }
        return $this;
    }

    /**
     * Set the default widget for a field type
     */
    public function setTypeDefault(string $fieldType, string $widgetId): self
    {
        if (!isset($this->widgets[$widgetId])) {
            throw new \InvalidArgumentException("Widget '{$widgetId}' not found");
        }

        $this->typeDefaults[$fieldType] = $widgetId;
        return $this;
    }

    /**
     * Set multiple type defaults
     *
     * @param array<string, string> $defaults [fieldType => widgetId]
     */
    public function setTypeDefaults(array $defaults): self
    {
        foreach ($defaults as $fieldType => $widgetId) {
            $this->setTypeDefault($fieldType, $widgetId);
        }
        return $this;
    }

    // =========================================================================
    // Widget Resolution
    // =========================================================================

    /**
     * Get a widget by ID
     */
    public function get(string $id): ?WidgetInterface
    {
        return $this->widgets[$id] ?? null;
    }

    /**
     * Check if a widget exists
     */
    public function has(string $id): bool
    {
        return isset($this->widgets[$id]);
    }

    /**
     * Get all registered widgets
     *
     * @return array<string, WidgetInterface>
     */
    public function all(): array
    {
        return $this->widgets;
    }

    /**
     * Get widgets that support a field type
     *
     * @return array<string, WidgetInterface>
     */
    public function getForType(string $fieldType): array
    {
        $matching = [];

        foreach ($this->widgets as $id => $widget) {
            if (in_array($fieldType, $widget->getSupportedTypes(), true)) {
                $matching[$id] = $widget;
            }
        }

        // Sort by priority (highest first)
        uasort($matching, fn($a, $b) => $b->getPriority() <=> $a->getPriority());

        return $matching;
    }

    /**
     * Get the appropriate widget for a field
     *
     * Resolution order:
     * 1. Field's explicit widget setting
     * 2. Type default
     * 3. Highest priority widget for the type
     * 4. Fallback to text_input
     */
    public function resolve(FieldDefinition $field): WidgetInterface
    {
        // 1. Check field's widget setting
        if ($field->widget && isset($this->widgets[$field->widget])) {
            return $this->widgets[$field->widget];
        }

        // 2. Check type default
        if (isset($this->typeDefaults[$field->field_type])) {
            return $this->widgets[$this->typeDefaults[$field->field_type]];
        }

        // 3. Get highest priority widget for type
        $typeWidgets = $this->getForType($field->field_type);
        if (!empty($typeWidgets)) {
            return reset($typeWidgets);
        }

        // 4. Fallback
        return $this->widgets['text_input'] ?? throw new \RuntimeException('No widgets available');
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Render a single field
     */
    public function renderField(
        FieldDefinition $field,
        mixed $value,
        RenderContext $context
    ): RenderResult {
        $widget = $this->resolve($field);

        // Check for multiple values support
        if ($field->multiple && !$widget->supportsMultiple()) {
            return $this->renderRepeatedField($widget, $field, $value, $context);
        }

        $result = $widget->renderField($field, $value, $context);
        $this->collectedAssets->merge($result->getAssets());

        return $result;
    }

    /**
     * Render a field wrapped in a repeater
     */
    private function renderRepeatedField(
        WidgetInterface $widget,
        FieldDefinition $field,
        mixed $value,
        RenderContext $context
    ): RenderResult {
        // Ensure assets are loaded
        $this->collectedAssets->addJs('/js/fields/field-repeater.js');
        $this->collectedAssets->addCss('/css/fields/field-repeater.css');

        $values = is_array($value) ? $value : ($value ? [$value] : []);
        $debug = "<!-- Repeater Debug: Field={$field->machine_name}, Count=" . count($values) . ", Value=" . htmlspecialchars(json_encode($value)) . " -->";

        // Ensure at least one empty item if empty (Drupal-style behavior)
        // Show one empty field initially so user sees the form structure
        if (empty($values)) {
            $values = [null]; // One empty item by default
        }

        $fieldId = $widget->getId() . '_' . uniqid();
        $containerId = 'repeater_' . $fieldId;
        $templateId = 'template_' . $fieldId;

        // Create context with hidden labels for repeater items
        $itemContext = $context->withHideLabel(true);

        // Render Existing Items
        $itemsHtml = '';
        $index = 0;
        foreach ($values as $itemValue) {
            // Clone field and override name to be an array index
            // We can't clone FieldDefinition easily if it's readonly, but we can hack the context?
            // Actually, renderField uses getFieldName().
            // We need to modify how getFieldName() works or pass modified field.
            // Since FieldDefinition is likely shared, we shouldn't modify it.
            // But Widget::getFieldName uses $field->machine_name. Returns "machine_name".
            // We want "machine_name[0]".
            // Widgets usually do: `name($this->getFieldName(...))`

            // Problem: We can't easily force the widget to output "name[0]" without modifying the field object.
            // Solution: Create a proxy/clone of the field definition with a modified machine name?
            // Or use a regex replacement on the output HTML? Regex is brittle but efficient here.

            // Let's use Regex replacement on the NAME attribute.
            // It's safe-ish if we target `name="original_name"`

            $subResult = $widget->renderField($field, $itemValue, $itemContext);
            $this->collectedAssets->merge($subResult->getAssets());

            $html = $subResult->getHtml();
            $originalName = $field->machine_name;
            $newName = "{$originalName}[{$index}]";

            // Replace exact name match
            $html = preg_replace(
                '/name="' . preg_quote($originalName, '/') . '"/',
                'name="' . $newName . '"',
                $html
            );

            // Also need to unique-ify IDs to avoid collisions?
            // Widgets use getFieldId() -> "field_name".
            // We should append index.
            // Replace `id="field_original_name"` with `id="field_original_name_0"`
            // Note: getFieldId usually generates "field_{machine_name}".
            $originalId = 'field_' . $field->machine_name; // Default convention
            // This is a guess. AbstractWidget uses `field-{$machine_name}`? 
            // Let's check AbstractWidget.php to be sure about ID generation.
            // Assuming we replace IDs that look like the field name.

            // Better strategy: Replace commonly used field ID patterns.
            $html = $this->scopeIdsAndNames($html, $field->machine_name, (string) $index);

            $itemsHtml .= $this->wrapRepeaterItem($html);
            $index++;
        }

        // Render Template
        // We render with a placeholder index
        $templateResult = $widget->renderField($field, null, $itemContext);
        $this->collectedAssets->merge($templateResult->getAssets());
        $templateHtml = $templateResult->getHtml();
        $templateHtml = $this->scopeIdsAndNames($templateHtml, $field->machine_name, '__INDEX__');
        $templateHtml = $this->wrapRepeaterItem($templateHtml);

        // Build Repeater HTML
        $repeater = \App\Cms\Fields\Rendering\Html::div()
            ->attr('x-data', "fieldRepeater({ containerId: '{$containerId}', templateId: '{$templateId}' })")
            ->class('field-repeater');

        $repeater->child(
            \App\Cms\Fields\Rendering\Html::div()
                ->id($containerId)
                ->class('field-repeater-container')
                ->html($itemsHtml)
        );

        $repeater->child(
            \App\Cms\Fields\Rendering\Html::button()
                ->attr('type', 'button')
                ->class('field-repeater-add')
                ->attr('@click', 'add()')
                ->html('<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add Another')
        );

        $repeater->child(
            \App\Cms\Fields\Rendering\Html::element('template')
                ->id($templateId)
                ->html($templateHtml)
        );

        $script = <<<JS
<script>
    if (typeof window.fieldRepeater === 'undefined') {
        window.fieldRepeater = (config) => ({
            items: [],
            nextIndex: 0,
            init() {
                const container = document.getElementById(config.containerId);
                if (container) {
                    this.nextIndex = container.children.length;
                }
            },
            add() {
                const template = document.getElementById(config.templateId);
                const container = document.getElementById(config.containerId);
                if (!template || !container) return;

                const clone = template.content.cloneNode(true);
                const root = clone.firstElementChild;
                const uniqueId = Date.now() + "_" + this.nextIndex;

                this.replacePlaceholders(root, uniqueId);
                container.appendChild(clone);
                this.nextIndex++;

                document.dispatchEvent(new CustomEvent("cms:content-changed", { detail: { target: root } }));
            },
            remove(el) {
                el.closest(".field-repeater-item").remove();
            },
            replacePlaceholders(el, index) {
                ["id", "name", "for", "data-target", "data-field-id", "data-element-id", "aria-labelledby", "aria-describedby"].forEach((attr) => {
                    if (el.hasAttribute(attr)) {
                        el.setAttribute(attr, el.getAttribute(attr).replace(/__INDEX__/g, index));
                    }
                });
                Array.from(el.children).forEach((child) => this.replacePlaceholders(child, index));
            },
        });
    }
</script>
JS;

        // Build the outer field-widget wrapper with label (similar to AbstractWidget::buildWrapper)
        $hasError = $context->hasErrorsFor($field->machine_name);
        
        $wrapper = \App\Cms\Fields\Rendering\Html::div()
            ->class(
                'field-widget',
                'field-widget--repeater',
                'field-widget--' . $widget->getId(),
                'field-type--' . $field->field_type,
                $field->required ? 'field-widget--required' : '',
                $hasError ? 'field-widget--error' : ''
            );

        // Label (use original context, not itemContext)
        if (!$context->shouldHideLabel()) {
            $wrapper->child(
                \App\Cms\Fields\Rendering\Html::element('label')
                    ->class('field-widget__label')
                    ->text($field->name)
                    ->when($field->required, fn($el) => $el->child(
                        \App\Cms\Fields\Rendering\Html::span()->class('field-widget__required')->text(' *')
                    ))
            );
        }

        // Repeater content (input container)
        $wrapper->child(
            \App\Cms\Fields\Rendering\Html::div()
                ->class('field-widget__input')
                ->html($repeater->render())
        );

        // Help text
        if (!$context->shouldHideHelp() && $field->help_text) {
            $wrapper->child(
                \App\Cms\Fields\Rendering\Html::div()
                    ->class('field-widget__help')
                    ->text($field->help_text)
            );
        }

        // Errors
        if ($hasError) {
            $errors = $context->getErrorsFor($field->machine_name);
            $errorHtml = \App\Cms\Fields\Rendering\Html::div()
                ->class('field-widget__errors')
                ->attr('role', 'alert');
            foreach ($errors as $error) {
                $errorHtml->child(
                    \App\Cms\Fields\Rendering\Html::div()->class('field-widget__error')->text($error)
                );
            }
            $wrapper->child($errorHtml);
        }

        return RenderResult::create($debug . $script . $wrapper->render(), $this->collectedAssets);
    }

    private function wrapRepeaterItem(string $html): string
    {
        return '<div class="field-repeater-item">' .
            '<div class="field-repeater-item__content">' . $html . '</div>' .
            '<button type="button" class="field-repeater-item__remove" @click="remove($el)" title="Remove">' .
            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' .
            '</button></div>';
    }

    private function scopeIdsAndNames(string $html, string $machineName, string $suffix): string
    {
        // Replace name="machine_name" with name="machine_name[suffix]"
        $html = preg_replace(
            '/name="' . preg_quote($machineName, '/') . '"/',
            'name="' . $machineName . '[' . $suffix . ']"',
            $html
        );

        // Replace id="field-machine_name" or "field_machine_name" with ..._suffix
        // We blindly replace any ID that ENDS with the machine name to include the suffix
        // This is risky but often necessary without parsing DOM
        // Let's try to be specific to standard ID patterns if possible.
        // Or just replace `id="{any_prefix}{machine_name}"`

        $html = preg_replace_callback(
            '/id="([^"]*' . preg_quote($machineName, '/') . ')"/',
            fn($matches) => 'id="' . $matches[1] . '_' . $suffix . '"',
            $html
        );

        // Also update `for` attributes in labels
        $html = preg_replace_callback(
            '/for="([^"]*' . preg_quote($machineName, '/') . ')"/',
            fn($matches) => 'for="' . $matches[1] . '_' . $suffix . '"',
            $html
        );

        return $html;
    }

    /**
     * Render a field for display (non-editable)
     */
    public function renderFieldDisplay(
        FieldDefinition $field,
        mixed $value,
        RenderContext $context
    ): RenderResult {
        $widget = $this->resolve($field);
        return $widget->renderDisplay($field, $value, $context);
    }

    /**
     * Render multiple fields
     *
     * @param FieldDefinition[] $fields
     * @param array $values Values indexed by machine_name
     */
    public function renderFields(
        array $fields,
        array $values,
        RenderContext $context
    ): RenderResult {
        $combinedResult = RenderResult::empty();

        foreach ($fields as $field) {
            $value = $values[$field->machine_name] ?? $field->default_value;
            $result = $this->renderField($field, $value, $context);
            $combinedResult = $combinedResult->combine($result);
        }

        return $combinedResult;
    }

    // =========================================================================
    // Value Handling
    // =========================================================================

    /**
     * Prepare a single field value for storage
     */
    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        $widget = $this->resolve($field);
        return $widget->prepareValue($field, $value);
    }

    /**
     * Prepare multiple field values for storage
     *
     * @param FieldDefinition[] $fields
     * @param array $values Submitted values
     * @return array Prepared values indexed by machine_name
     */
    public function prepareValues(array $fields, array $values): array
    {
        $prepared = [];

        foreach ($fields as $field) {
            $value = $values[$field->machine_name] ?? null;
            $prepared[$field->machine_name] = $this->prepareValue($field, $value);
        }

        return $prepared;
    }

    /**
     * Format a value for form display
     */
    public function formatValue(FieldDefinition $field, mixed $value): mixed
    {
        $widget = $this->resolve($field);
        return $widget->formatValue($field, $value);
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Validate a single field value
     *
     * @return array<string> Error messages
     */
    public function validateField(FieldDefinition $field, mixed $value): array
    {
        // Field-level validation
        $fieldErrors = $this->validator->validateField($field, $value);

        // Widget-level validation
        $widget = $this->resolve($field);
        $widgetResult = $widget->validate($field, $value);

        return array_merge($fieldErrors, $widgetResult->getErrors());
    }

    /**
     * Validate multiple field values
     *
     * @param FieldDefinition[] $fields
     * @param array $values Values indexed by machine_name
     * @return array<string, array<string>> Errors indexed by machine_name
     */
    public function validateFields(array $fields, array $values): array
    {
        $allErrors = [];

        foreach ($fields as $field) {
            $value = $values[$field->machine_name] ?? null;
            $errors = $this->validateField($field, $value);

            if (!empty($errors)) {
                $allErrors[$field->machine_name] = $errors;
            }
        }

        return $allErrors;
    }

    // =========================================================================
    // Assets
    // =========================================================================

    /**
     * Get collected assets from rendered widgets
     */
    public function getCollectedAssets(): AssetCollection
    {
        return $this->collectedAssets;
    }

    /**
     * Clear collected assets
     */
    public function clearAssets(): self
    {
        $this->collectedAssets = new AssetCollection();
        return $this;
    }

    // =========================================================================
    // Widget Info
    // =========================================================================

    /**
     * Get widgets grouped by category
     *
     * @return array<string, WidgetMetadata[]>
     */
    public function getGroupedByCategory(): array
    {
        $grouped = [];

        foreach ($this->widgets as $widget) {
            $metadata = $widget->getMetadata();
            $category = $metadata->category;

            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }

            $grouped[$category][] = $metadata;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Get widget options for a field type (for select dropdowns)
     *
     * @return array<string, string> [widgetId => label]
     */
    public function getOptionsForType(string $fieldType): array
    {
        $options = [];

        foreach ($this->getForType($fieldType) as $id => $widget) {
            $options[$id] = $widget->getLabel();
        }

        return $options;
    }

    /**
     * Get all widget metadata
     *
     * @return array<string, WidgetMetadata>
     */
    public function getAllMetadata(): array
    {
        $metadata = [];

        foreach ($this->widgets as $id => $widget) {
            $metadata[$id] = $widget->getMetadata();
        }

        return $metadata;
    }
}
