<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Composite;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\AbstractWidget;
use App\Cms\Fields\Widget\WidgetRegistry;

/**
 * RepeaterWidget - Repeatable field groups
 *
 * Allows creating multiple instances of a set of sub-fields.
 * Supports drag-drop reordering, min/max items, and collapsible items.
 */
final class RepeaterWidget extends AbstractWidget
{
    private ?WidgetRegistry $widgetRegistry = null;

    public function getId(): string
    {
        return 'repeater';
    }

    public function getLabel(): string
    {
        return 'Repeater';
    }

    public function getCategory(): string
    {
        return 'Composite';
    }

    public function getIcon(): string
    {
        return '🔄';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['repeater', 'array'];
    }

    public function supportsMultiple(): bool
    {
        return true;
    }

    public function usesLabelableInput(): bool
    {
        return false;
    }

    /**
     * Inject the widget registry for rendering sub-fields
     */
    public function setWidgetRegistry(WidgetRegistry $registry): self
    {
        $this->widgetRegistry = $registry;
        return $this;
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/repeater.css');
        $this->assets->addJs('/js/fields/repeater.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $subFields = $this->getSubFields($field);
        $items = is_array($value) ? $value : [];

        $minItems = $settings->getInt('min_items', 0);
        $maxItems = $settings->getInt('max_items', 0);
        $collapsible = $settings->getBool('collapsible', true);
        $confirmDelete = $settings->getBool('confirm_delete', true);
        $itemLabel = $settings->getString('item_label', 'Item');
        $addLabel = $settings->getString('add_label', '+ Add ' . $itemLabel);

        $wrapper = Html::div()
            ->class('field-repeater')
            ->data('field-id', $fieldId)
            ->data('min-items', $minItems)
            ->data('max-items', $maxItems)
            ->data('collapsible', $collapsible ? 'true' : 'false')
            ->data('confirm-delete', $confirmDelete ? 'true' : 'false');

        // Items container
        $itemsContainer = Html::div()->class('field-repeater__items');

        foreach ($items as $index => $itemValue) {
            $itemsContainer->html(
                $this->buildRepeaterItem($field, $subFields, $itemValue, $index, $context, $itemLabel)
            );
        }

        $wrapper->child($itemsContainer);

        // Add button
        $addButton = Html::button()
            ->class('field-repeater__add')
            ->attr('type', 'button')
            ->data('action', 'add')
            ->text($addLabel);

        if ($maxItems > 0 && count($items) >= $maxItems) {
            $addButton->attr('disabled', true);
        }

        $wrapper->child($addButton);

        // Template for new items (hidden)
        $wrapper->child(
            Html::element('template')
                ->class('field-repeater__template')
                ->html($this->buildRepeaterItem($field, $subFields, [], '__INDEX__', $context, $itemLabel))
        );

        return $wrapper;
    }

    private function buildRepeaterItem(
        FieldDefinition $field,
        array $subFields,
        array $values,
        int|string $index,
        RenderContext $context,
        string $itemLabel
    ): string {
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $settings = $this->getSettings($field);
        $collapsible = $settings->getBool('collapsible', true);

        $item = Html::div()
            ->class('field-repeater__item')
            ->data('index', $index);

        // Item header
        $header = Html::div()->class('field-repeater__item-header');

        // Drag handle
        $header->child(
            Html::span()
                ->class('field-repeater__drag-handle')
                ->text('⋮⋮')
        );

        // Item title
        $titleValue = $this->getItemTitle($values, $subFields);
        $header->child(
            Html::span()
                ->class('field-repeater__item-title')
                ->text($titleValue ?: $itemLabel . ' ' . ($index === '__INDEX__' ? '' : $index + 1))
        );

        // Item number
        $header->child(
            Html::span()
                ->class('field-repeater__item-number')
                ->text('#' . ($index === '__INDEX__' ? '' : $index + 1))
        );

        // Collapse toggle
        if ($collapsible) {
            $header->child(
                Html::button()
                    ->class('field-repeater__toggle')
                    ->attr('type', 'button')
                    ->data('action', 'toggle')
                    ->text('▼')
            );
        }

        // Remove button
        $header->child(
            Html::button()
                ->class('field-repeater__remove')
                ->attr('type', 'button')
                ->data('action', 'remove')
                ->text('×')
        );

        $item->child($header);

        // Item content (sub-fields)
        $content = Html::div()->class('field-repeater__item-content');

        foreach ($subFields as $subField) {
            $subFieldValue = $values[$subField->machine_name] ?? $subField->default_value;
            $subFieldHtml = $this->renderSubField($subField, $subFieldValue, $fieldName, $index, $context);
            $content->html($subFieldHtml);
        }

        $item->child($content);

        return $item->render();
    }

    private function renderSubField(
        FieldDefinition $subField,
        mixed $value,
        string $parentName,
        int|string $index,
        RenderContext $parentContext
    ): string {
        // Create context for sub-field
        $subContext = $parentContext
            ->withNamePrefix($parentName . '[' . $index . ']')
            ->withIndex(is_int($index) ? $index : 0);

        // If we have a widget registry, use it
        if ($this->widgetRegistry) {
            $result = $this->widgetRegistry->renderField($subField, $value, $subContext);
            return $result->getHtml();
        }

        // Fallback: simple text input
        $name = $parentName . '[' . $index . '][' . $subField->machine_name . ']';
        $id = str_replace(['[', ']'], ['_', ''], $name);

        return Html::div()
            ->class('field-widget')
            ->child(
                Html::label()
                    ->attr('for', $id)
                    ->text($subField->name)
            )
            ->child(
                Html::input('text')
                    ->id($id)
                    ->name($name)
                    ->value($value ?? '')
            )
            ->render();
    }

    private function getSubFields(FieldDefinition $field): array
    {
        $settings = $this->getSettings($field);
        $subFieldsConfig = $settings->getArray('sub_fields', []);

        $subFields = [];
        foreach ($subFieldsConfig as $config) {
            $subField = new FieldDefinition();
            $subField->hydrate($config);
            $subFields[] = $subField;
        }

        return $subFields;
    }

    private function getItemTitle(array $values, array $subFields): string
    {
        // Use first text field as title
        foreach ($subFields as $subField) {
            if (in_array($subField->field_type, ['string', 'text']) && !empty($values[$subField->machine_name])) {
                $title = $values[$subField->machine_name];
                return strlen($title) > 40 ? substr($title, 0, 40) . '...' : $title;
            }
        }

        return '';
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return "CmsRepeater.init('{$elementId}');";
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if (!is_array($value)) {
            return [];
        }

        // Re-index array and filter empty items
        $prepared = [];
        $subFields = $this->getSubFields($field);

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $preparedItem = [];
            foreach ($subFields as $subField) {
                $subValue = $item[$subField->machine_name] ?? null;

                if ($this->widgetRegistry) {
                    $preparedItem[$subField->machine_name] = $this->widgetRegistry->prepareValue($subField, $subValue);
                } else {
                    $preparedItem[$subField->machine_name] = $subValue;
                }
            }

            // Only add non-empty items
            if (!empty(array_filter($preparedItem, fn($v) => $v !== null && $v !== ''))) {
                $prepared[] = $preparedItem;
            }
        }

        return $prepared;
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        if (!is_array($value)) {
            $value = [];
        }

        $settings = $this->getSettings($field);
        $minItems = $settings->getInt('min_items', 0);
        $maxItems = $settings->getInt('max_items', 0);
        $errors = [];

        // Check min/max items
        $count = count($value);

        if ($minItems > 0 && $count < $minItems) {
            $errors[] = "At least {$minItems} items are required";
        }

        if ($maxItems > 0 && $count > $maxItems) {
            $errors[] = "No more than {$maxItems} items are allowed";
        }

        // Validate each item's sub-fields
        if ($this->widgetRegistry) {
            $subFields = $this->getSubFields($field);

            foreach ($value as $index => $itemValues) {
                if (!is_array($itemValues)) {
                    continue;
                }

                $itemErrors = $this->widgetRegistry->validateFields($subFields, $itemValues);

                foreach ($itemErrors as $subFieldName => $subErrors) {
                    foreach ($subErrors as $error) {
                        $errors[] = "Item " . ($index + 1) . " - {$error}";
                    }
                }
            }
        }

        return empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        $items = is_array($value) ? $value : [];

        if (empty($items)) {
            return parent::renderDisplay($field, null, $context);
        }

        $settings = $this->getSettings($field);
        $subFields = $this->getSubFields($field);
        $itemLabel = $settings->getString('item_label', 'Item');

        $list = Html::element('ul')->class('field-display', 'field-display--repeater');

        foreach ($items as $index => $itemValues) {
            $listItem = Html::element('li');

            // Item header
            $title = $this->getItemTitle($itemValues, $subFields) ?: $itemLabel . ' ' . ($index + 1);
            $listItem->child(
                Html::element('strong')->text($title)
            );

            // Item fields
            $fieldList = Html::element('ul');

            foreach ($subFields as $subField) {
                $subValue = $itemValues[$subField->machine_name] ?? null;

                if ($subValue !== null && $subValue !== '') {
                    $fieldList->child(
                        Html::element('li')
                            ->text($subField->name . ': ' . (is_array($subValue) ? json_encode($subValue) : $subValue))
                    );
                }
            }

            $listItem->child($fieldList);
            $list->child($listItem);
        }

        return RenderResult::fromHtml($list->render());
    }

    public function getSettingsSchema(): array
    {
        return [
            'sub_fields' => [
                'type' => 'json',
                'label' => 'Sub-fields Configuration',
                'default' => [],
            ],
            'min_items' => ['type' => 'integer', 'label' => 'Minimum Items', 'default' => 0],
            'max_items' => ['type' => 'integer', 'label' => 'Maximum Items (0 = unlimited)', 'default' => 0],
            'collapsible' => ['type' => 'boolean', 'label' => 'Collapsible Items', 'default' => true],
            'confirm_delete' => ['type' => 'boolean', 'label' => 'Confirm Before Delete', 'default' => true],
            'item_label' => ['type' => 'string', 'label' => 'Item Label', 'default' => 'Item'],
            'add_label' => ['type' => 'string', 'label' => 'Add Button Label'],
            'layout' => [
                'type' => 'select',
                'label' => 'Layout',
                'options' => [
                    'vertical' => 'Vertical',
                    'horizontal' => 'Horizontal',
                    'table' => 'Table',
                ],
                'default' => 'vertical',
            ],
        ];
    }
}
