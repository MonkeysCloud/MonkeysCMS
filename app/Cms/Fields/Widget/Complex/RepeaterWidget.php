<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Complex;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * RepeaterWidget - Repeatable field groups
 */
final class RepeaterWidget extends AbstractWidget
{
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
        return 'Complex';
    }

    public function getIcon(): string
    {
        return '🔁';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['repeater', 'json'];
    }

    public function supportsMultiple(): bool
    {
        return true;
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

        $subfields = $settings->getArray('subfields', []);
        $minItems = $settings->getInt('min_items', 0);
        $maxItems = $settings->getInt('max_items', -1);
        $collapsed = $settings->getBool('collapsed', false);
        $sortable = $settings->getBool('sortable', true);
        $itemLabel = $settings->getString('item_label', 'Item');

        $items = is_array($value) ? $value : [];
        $items = array_values($items); // Ensure indexed array
        $itemCount = count($items);
        $canAdd = $maxItems < 0 || $itemCount < $maxItems;

        $wrapper = Html::div()
            ->class('field-repeater')
            ->id($fieldId . '_wrapper')
            ->data('field-id', $fieldId)
            ->data('min', $minItems)
            ->data('max', $maxItems)
            ->data('sortable', $sortable ? 'true' : 'false')
            ->data('collapsed', $collapsed ? 'true' : 'false');

        // Items container
        $itemsContainer = Html::div()
            ->class('field-repeater__items')
            ->id($fieldId . '_items');

        foreach ($items as $index => $itemData) {
            $itemsContainer->child(
                $this->buildItem($field, $fieldName, $subfields, $itemData, $index, $collapsed, $itemLabel)
            );
        }

        $wrapper->child($itemsContainer);

        // Add button
        if ($canAdd) {
            $wrapper->child(
                Html::div()
                    ->class('field-repeater__actions')
                    ->child(
                        Html::button()
                            ->class('field-repeater__add')
                            ->attr('type', 'button')
                            ->attr('onclick', "window.addRepeaterItem('{$fieldId}')")
                            ->text('+ Add ' . $itemLabel)
                    )
            );
        }

        // Template
        $wrapper->child(
            Html::element('template')
                ->id($fieldId . '_template')
                ->html(
                    $this->buildItem($field, $fieldName, $subfields, [], '__INDEX__', false, $itemLabel)->render()
                )
        );

        return $wrapper;
    }

    private function buildItem(
        FieldDefinition $field,
        string $fieldName,
        array $subfields,
        array $data,
        int|string $index,
        bool $collapsed,
        string $itemLabel
    ): HtmlBuilder {
        $collapsedClass = $collapsed ? ' field-repeater__item--collapsed' : '';
        $itemNumber = is_numeric($index) ? (int)$index + 1 : '';

        $item = Html::div()
            ->class('field-repeater__item' . $collapsedClass)
            ->data('index', $index);

        // Header
        $header = Html::div()->class('field-repeater__item-header');
        $header->child(Html::span()->class('field-repeater__item-drag')->attr('title', 'Drag to reorder')->text('⋮⋮'));
        $header->child(Html::span()->class('field-repeater__item-label')->text($itemLabel . ' ' . $itemNumber));
        $header->child(Html::button()->class('field-repeater__item-toggle')->attr('type', 'button')->attr('title', 'Toggle')->text('▼'));
        $header->child(Html::button()->class('field-repeater__item-remove')->attr('type', 'button')->attr('title', 'Remove')->attr('onclick', 'window.removeRepeaterItem(this)')->text('×'));
        $item->child($header);

        // Content
        $content = Html::div()->class('field-repeater__item-content');

        foreach ($subfields as $subfield) {
            $subfieldName = $subfield['name'] ?? $subfield['machine_name'] ?? '';
            $subfieldType = $subfield['type'] ?? 'string';
            $subfieldLabel = $subfield['label'] ?? ucfirst($subfieldName);
            $subfieldValue = $data[$subfieldName] ?? ($subfield['default'] ?? null);
            $inputName = "{$fieldName}[{$index}][{$subfieldName}]";

            $subfieldWrapper = Html::div()->class('field-repeater__subfield');
            $subfieldWrapper->child(Html::label()->text($subfieldLabel));
            $subfieldWrapper->child($this->buildSubfield($subfieldType, $inputName, $subfieldValue, $subfield));

            $content->child($subfieldWrapper);
        }

        $item->child($content);

        return $item;
    }

    private function buildSubfield(string $type, string $name, mixed $value, array $settings): HtmlBuilder
    {
        return match ($type) {
            'text', 'textarea' => Html::textarea()
                ->name($name)
                ->class('field-widget__control')
                ->attr('rows', 3)
                ->text((string) $value),
            'select' => $this->buildSubfieldSelect($name, $value, $settings['options'] ?? []),
            'checkbox', 'boolean' => Html::input('checkbox')
                ->name($name)
                ->value('1')
                ->when($value, fn($el) => $el->attr('checked', true)),
            'number', 'integer' => Html::input('number')
                ->name($name)
                ->value((string) $value)
                ->class('field-widget__control'),
            default => Html::input('text')
                ->name($name)
                ->value((string) $value)
                ->class('field-widget__control'),
        };
    }

    private function buildSubfieldSelect(string $name, mixed $value, array $options): HtmlBuilder
    {
        $select = Html::select()->name($name)->class('field-widget__control');
        foreach ($options as $optValue => $optLabel) {
            $select->child(
                Html::option((string) $optValue, (string) $optLabel, $value == $optValue)
            );
        }
        return $select;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        $options = json_encode([
            'minItems' => $settings->getInt('min_items', 0),
            'maxItems' => $settings->getInt('max_items', -1),
            'sortable' => $settings->getBool('sortable', true),
            'collapsed' => $settings->getBool('collapsed', false),
            'itemLabel' => $settings->getString('item_label', 'Item'),
        ]);

        return "window.initRepeater && window.initRepeater('{$elementId}', {$options});";
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return [];
        }

        if (!is_array($value)) {
            return [];
        }

        // Filter out empty items and re-index
        return array_values(array_filter($value, fn($item) => !empty($item)));
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value) || !is_array($value)) {
            return parent::renderDisplay($field, null, $context);
        }

        $settings = $this->getSettings($field);
        $subfields = $settings->getArray('subfields', []);

        $wrapper = Html::div()->class('field-display', 'field-display--repeater');

        foreach ($value as $index => $item) {
            $itemWrapper = Html::div()->class('field-repeater-display__item');
            $itemWrapper->child(Html::strong()->text('Item ' . ($index + 1)));

            $list = Html::ul();
            foreach ($subfields as $subfield) {
                $name = $subfield['name'] ?? $subfield['machine_name'] ?? '';
                $label = $subfield['label'] ?? ucfirst($name);
                $itemValue = $item[$name] ?? '';

                $list->child(
                    Html::li()
                        ->child(Html::element('em')->text($label . ': '))
                        ->text((string) $itemValue)
                );
            }
            $itemWrapper->child($list);
            $wrapper->child($itemWrapper);
        }

        return RenderResult::fromHtml($wrapper->render());
    }

    public function getSettingsSchema(): array
    {
        return [
            'subfields' => [
                'type' => 'json',
                'label' => 'Subfields configuration',
                'description' => 'Array of field definitions [{name, type, label, options}]',
            ],
            'min_items' => ['type' => 'integer', 'label' => 'Minimum items', 'default' => 0],
            'max_items' => ['type' => 'integer', 'label' => 'Maximum items (-1 = unlimited)', 'default' => -1],
            'collapsed' => ['type' => 'boolean', 'label' => 'Start collapsed', 'default' => false],
            'sortable' => ['type' => 'boolean', 'label' => 'Allow sorting', 'default' => true],
            'item_label' => ['type' => 'string', 'label' => 'Item label', 'default' => 'Item'],
        ];
    }
}
