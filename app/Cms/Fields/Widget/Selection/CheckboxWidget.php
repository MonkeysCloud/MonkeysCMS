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
 * CheckboxWidget - Single checkbox (boolean)
 */
final class CheckboxWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'checkbox';
    }

    public function getLabel(): string
    {
        return 'Checkbox';
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
        return ['boolean', 'checkbox'];
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/checkboxes.css');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $checkboxLabel = $settings->getString('checkbox_label', $field->name);

        return Html::div()
            ->class('field-checkbox')
            ->child(
                // Hidden field for unchecked state
                Html::hidden($fieldName, '0')
            )
            ->child(
                Html::element('label')
                    ->child(
                        Html::input('checkbox')
                            ->id($fieldId)
                            ->name($fieldName)
                            ->value('1')
                            ->class('field-checkbox__input')
                            ->when((bool) $value, fn($el) => $el->attr('checked', true))
                            ->when($context->isDisabled(), fn($el) => $el->disabled())
                    )
                    ->child(Html::span()->class('field-checkbox__mark'))
                    ->child(
                        Html::span()
                            ->class('field-checkbox__label')
                            ->text($checkboxLabel)
                    )
            );
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        return (bool) $value;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        $checked = (bool) $value;
        $class = $checked ? 'field-display--checked' : 'field-display--unchecked';
        $text = $checked ? '✓' : '✗';

        $html = Html::span()
            ->class('field-display', $class)
            ->text($text)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'checkbox_label' => ['type' => 'string', 'label' => 'Checkbox Label'],
        ];
    }
}
