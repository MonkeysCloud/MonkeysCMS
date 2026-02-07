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
 * RadioWidget - Radio button group
 */
final class RadioWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'radio';
    }

    public function getLabel(): string
    {
        return 'Radio Buttons';
    }

    public function getCategory(): string
    {
        return 'Selection';
    }

    public function getIcon(): string
    {
        return '🔘';
    }

    public function getPriority(): int
    {
        return 90;
    }

    public function getSupportedTypes(): array
    {
        return ['radio', 'select', 'string', 'integer', 'boolean'];
    }

    public function usesLabelableInput(): bool
    {
        return false;
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $options = $this->getOptions($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $layout = $settings->getString('layout', 'vertical');

        $container = Html::div()
            ->class('field-radio-group', "field-radio-group--{$layout}");

        $index = 0;
        foreach ($options as $optValue => $optLabel) {
            $radioId = $fieldId . '_' . $index;
            $checked = $value == $optValue;

            $container->child(
                Html::element('label')
                    ->class('field-radio')
                    ->child(
                        Html::input('radio')
                            ->id($radioId)
                            ->name($fieldName)
                            ->value((string) $optValue)
                            ->when($checked, fn($el) => $el->attr('checked', true))
                            ->when($context->isDisabled(), fn($el) => $el->disabled())
                    )
                    ->child(Html::span()->class('field-radio__mark'))
                    ->child(
                        Html::span()
                            ->class('field-radio__label')
                            ->text((string) $optLabel)
                    )
            );

            $index++;
        }

        return $container;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $options = $this->getOptions($field);
        $label = $options[$value] ?? $value;

        $html = Html::span()
            ->class('field-display', 'field-display--radio')
            ->text((string) $label)
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
            'options' => ['type' => 'json', 'label' => 'Options (JSON)'],
        ];
    }
}
