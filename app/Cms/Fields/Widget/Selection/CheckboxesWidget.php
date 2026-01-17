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
        return ['checkbox', 'multiselect'];
    }

    public function supportsMultiple(): bool
    {
        return true;
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/checkboxes.css');
        $this->assets->addJs('/js/fields/checkboxes.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $options = $this->getOptions($field);
        $values = is_array($value) ? $value : [$value];
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $layout = $settings->getString('layout', 'vertical');
        $showSelectAll = $settings->getBool('select_all', false);

        $wrapper = Html::div()->id($fieldId . '_wrapper');
        
        // Select All / Deselect All Controls
        if ($showSelectAll) {
            $actions = Html::div()->class('field-checkbox-actions');
            $actions->child(
                Html::button()
                    ->attr('type', 'button')
                    ->class('field-checkbox-action')
                    ->data('action', 'select-all')
                    ->text('Select All')
            );
            $actions->child(Html::span()->text('|')->attr('style', 'color: #d1d5db; font-size: 0.75rem;'));
            $actions->child(
                Html::button()
                    ->attr('type', 'button')
                    ->class('field-checkbox-action')
                    ->data('action', 'deselect-all')
                    ->text('Deselect All')
            );
            $wrapper->child($actions);
        }

        $container = Html::div()
            ->class('field-checkbox-group', "field-checkbox-group--{$layout}");

        // Columns logic replaced by Grid layout in CSS, but keeping basic support if desired
        // If layout is grid, we rely on CSS Grid. If layout is vertical/horizontal, flex works.

        $index = 0;
        foreach ($options as $optValue => $optLabel) {
            $checkboxId = $fieldId . '_' . $index;
            $checked = in_array($optValue, $values, false); // Loose comparison often better for form values

            $container->child(
                Html::element('label')
                    ->class('field-checkbox')
                    ->child(
                        Html::input('checkbox')
                            ->id($checkboxId)
                            ->name($fieldName . '[]')
                            ->value((string) $optValue)
                            ->class('field-checkbox__input')
                            ->when($checked, fn($el) => $el->attr('checked', 'checked'))
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
        
        $wrapper->child($container);

        return $wrapper;
    }
    
    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        // Initialize JS helper for the wrapper ID
        $wrapperId = $elementId . '_wrapper';
        return "CmsCheckboxes.init('{$wrapperId}');";
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
                    'vertical' => 'Vertical (Stack)',
                    'grid' => 'Grid (Auto-fill)',
                ],
                'default' => 'vertical',
            ],
            'select_all' => ['type' => 'boolean', 'label' => 'Show Select All / Deselect All', 'default' => false],
            'options' => ['type' => 'key_value', 'label' => 'Options'],
        ];
    }
}
