<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Selection;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * CheckboxesWidget - Multiple checkboxes
 */
final class CheckboxesWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'checkboxes';
    }

    public function getLabel(): string
    {
        return 'Checkboxes';
    }

    public function getCategory(): string
    {
        return 'Selection';
    }

    public function getIcon(): string
    {
        return '☑';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['checkbox', 'multiselect', 'taxonomy_reference'];
    }

    public function supportsMultiple(): bool
    {
        return true;
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $options = $this->getOptions($field);
        $values = is_array($value) ? $value : [$value];
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $layout = $settings->getString('layout', 'vertical');
        $columns = $settings->getInt('columns');
        
        $container = Html::div()
            ->class('field-checkbox-group', "field-checkbox-group--{$layout}");

        if ($columns > 0) {
            $container->attr('style', "column-count: {$columns};");
        }

        $index = 0;
        foreach ($options as $optValue => $optLabel) {
            $checkboxId = $fieldId . '_' . $index;
            $checked = in_array($optValue, $values, true);
            
            $container->child(
                Html::element('label')
                    ->class('field-checkbox')
                    ->child(
                        Html::input('checkbox')
                            ->id($checkboxId)
                            ->name($fieldName . '[]')
                            ->value((string) $optValue)
                            ->when($checked, fn($el) => $el->attr('checked', true))
                            ->when($context->isDisabled(), fn($el) => $el->disabled())
                    )
                    ->child(Html::span()->class('field-checkbox__mark'))
                    ->child(
                        Html::span()
                            ->class('field-checkbox__label')
                            ->text((string) $optLabel)
                    )
            );
            
            $index++;
        }

        return $container;
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value ? [$value] : [];
        }
        return array_values(array_filter($value));
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $options = $this->getOptions($field);
        $values = is_array($value) ? $value : [$value];
        
        $labels = [];
        foreach ($values as $v) {
            $labels[] = $options[$v] ?? $v;
        }

        $html = Html::span()
            ->class('field-display', 'field-display--checkboxes')
            ->text(implode(', ', $labels))
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'layout' => [
                'type' => 'select',
                'label' => 'Layout',
                'options' => [
                    'vertical' => 'Vertical',
                    'horizontal' => 'Horizontal',
                ],
                'default' => 'vertical',
            ],
            'columns' => ['type' => 'integer', 'label' => 'Columns', 'default' => 0],
            'options' => ['type' => 'json', 'label' => 'Options (JSON)'],
        ];
    }
}
