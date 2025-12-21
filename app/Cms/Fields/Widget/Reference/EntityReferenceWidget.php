<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Reference;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * EntityReferenceWidget - Reference to other entities
 */
final class EntityReferenceWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'entity_reference';
    }

    public function getLabel(): string
    {
        return 'Entity Reference';
    }

    public function getCategory(): string
    {
        return 'Reference';
    }

    public function getIcon(): string
    {
        return '🔗';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['entity_reference', 'reference'];
    }

    public function supportsMultiple(): bool
    {
        return true;
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/reference.css');
        $this->assets->addJs('/js/fields/entity-reference.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $entityType = $settings->getString('entity_type', 'content');
        $displayStyle = $settings->getString('display_style', 'autocomplete');
        $multiple = $field->multiple;
        $values = $this->normalizeValues($value);

        $wrapper = Html::div()
            ->class('field-entity-reference', "field-entity-reference--{$displayStyle}")
            ->data('field-id', $fieldId)
            ->data('entity-type', $entityType)
            ->data('multiple', $multiple ? 'true' : 'false');

        // Hidden input for value(s)
        $wrapper->child(
            Html::hidden($fieldName, $multiple ? json_encode($values) : ($values[0] ?? ''))
                ->id($fieldId)
                ->class('field-entity-reference__value')
        );

        if ($displayStyle === 'autocomplete') {
            $wrapper->html($this->buildAutocomplete($field, $values, $context));
        } else {
            $wrapper->html($this->buildSelect($field, $values, $context));
        }

        return $wrapper;
    }

    private function buildAutocomplete(FieldDefinition $field, array $values, RenderContext $context): string
    {
        $fieldId = $this->getFieldId($field, $context);
        $settings = $this->getSettings($field);
        $placeholder = $settings->getString('placeholder', 'Search...');

        $html = '';

        // Selected items container
        $selected = Html::div()->class('field-entity-reference__selected');

        foreach ($values as $value) {
            // In production, fetch entity label from database
            $label = $value; // Placeholder
            $selected->child($this->buildSelectedItem($value, $label));
        }

        $html .= $selected->render();

        // Search input
        $html .= Html::div()
            ->class('field-entity-reference__search')
            ->child(
                Html::input('text')
                    ->class('field-entity-reference__input')
                    ->id($fieldId . '_search')
                    ->attr('placeholder', $placeholder)
                    ->attr('autocomplete', 'off')
            )
            ->render();

        // Dropdown results
        $html .= Html::div()
            ->class('field-entity-reference__dropdown')
            ->id($fieldId . '_dropdown')
            ->render();

        return $html;
    }

    private function buildSelect(FieldDefinition $field, array $values, RenderContext $context): string
    {
        $settings = $this->getSettings($field);
        $options = $settings->getArray('options', []);
        $multiple = $field->multiple;

        $select = Html::select()
            ->attrs($this->buildCommonAttributes($field, $context));

        if ($multiple) {
            $select->attr('multiple', true);
        }

        // Empty option
        if (!$multiple) {
            $select->child(Html::option('', '- Select -'));
        }

        foreach ($options as $optValue => $optLabel) {
            $select->child(
                Html::option(
                    (string) $optValue,
                    (string) $optLabel,
                    in_array($optValue, $values)
                )
            );
        }

        return $select->render();
    }

    private function buildSelectedItem(mixed $value, string $label): HtmlBuilder
    {
        return Html::div()
            ->class('field-entity-reference__item')
            ->data('value', $value)
            ->child(Html::span()->class('field-entity-reference__item-label')->text($label))
            ->child(
                Html::button()
                    ->class('field-entity-reference__item-remove')
                    ->attr('type', 'button')
                    ->data('action', 'remove')
                    ->text('×')
            );
    }

    private function normalizeValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value));
        }

        if ($value !== null && $value !== '') {
            return [$value];
        }

        return [];
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        $entityType = $settings->getString('entity_type', 'content');
        $apiUrl = $settings->getString('api_url', '/api/entities/' . $entityType . '/search');

        return "CmsEntityReference.init('{$elementId}', '{$apiUrl}');";
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        $values = $this->normalizeValues($value);

        if (!$field->multiple) {
            return $values[0] ?? null;
        }

        return $values;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        $values = $this->normalizeValues($value);

        if (empty($values)) {
            return parent::renderDisplay($field, null, $context);
        }

        // In production, fetch entity labels from database
        $labels = $values; // Placeholder

        $html = Html::span()
            ->class('field-display', 'field-display--entity-reference')
            ->text(implode(', ', $labels))
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'default' => 'content'],
            'display_style' => [
                'type' => 'select',
                'label' => 'Display Style',
                'options' => [
                    'autocomplete' => 'Autocomplete',
                    'select' => 'Select Dropdown',
                ],
                'default' => 'autocomplete',
            ],
            'placeholder' => ['type' => 'string', 'label' => 'Placeholder', 'default' => 'Search...'],
            'api_url' => ['type' => 'string', 'label' => 'API URL'],
            'options' => ['type' => 'json', 'label' => 'Static Options (for select style)'],
        ];
    }
}
