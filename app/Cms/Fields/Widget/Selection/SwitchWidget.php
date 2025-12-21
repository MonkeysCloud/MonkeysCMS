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
 * SwitchWidget - Toggle switch (boolean)
 */
final class SwitchWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'switch';
    }

    public function getLabel(): string
    {
        return 'Toggle Switch';
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
        return 110;
    }

    public function getSupportedTypes(): array
    {
        return ['boolean'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $onLabel = $settings->getString('on_label', 'On');
        $offLabel = $settings->getString('off_label', 'Off');

        return Html::element('label')
            ->class('field-switch')
            ->child(Html::hidden($fieldName, '0'))
            ->child(
                Html::input('checkbox')
                    ->id($fieldId)
                    ->name($fieldName)
                    ->value('1')
                    ->when((bool) $value, fn($el) => $el->attr('checked', true))
                    ->when($context->isDisabled(), fn($el) => $el->disabled())
            )
            ->child(
                Html::span()
                    ->class('field-switch__track')
                    ->child(Html::span()->class('field-switch__thumb'))
            )
            ->child(
                Html::span()
                    ->class('field-switch__labels')
                    ->child(Html::span()->class('field-switch__on')->text($onLabel))
                    ->child(Html::span()->class('field-switch__off')->text($offLabel))
            );
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        return (bool) $value;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        $settings = $this->getSettings($field);
        $on = (bool) $value;
        $class = $on ? 'field-display--on' : 'field-display--off';
        $text = $on
            ? $settings->getString('on_label', 'On')
            : $settings->getString('off_label', 'Off');

        $html = Html::span()
            ->class('field-display', 'field-display--switch', $class)
            ->text($text)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'on_label' => ['type' => 'string', 'label' => 'On Label', 'default' => 'On'],
            'off_label' => ['type' => 'string', 'label' => 'Off Label', 'default' => 'Off'],
        ];
    }
}
