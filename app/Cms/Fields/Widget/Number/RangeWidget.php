<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Number;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * RangeWidget - Slider input
 */
final class RangeWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'range';
    }

    public function getLabel(): string
    {
        return 'Range Slider';
    }

    public function getCategory(): string
    {
        return 'Number';
    }

    public function getIcon(): string
    {
        return '↔️';
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function getSupportedTypes(): array
    {
        return ['integer', 'float', 'decimal'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $min = $settings->get('min', 0);
        $max = $settings->get('max', 100);
        $step = $settings->get('step', 1);
        $showValue = $settings->getBool('show_value', true);
        $currentValue = $value ?? $min;
        
        $wrapper = Html::div()->class('field-range');

        $wrapper->child(
            Html::input('range')
                ->attrs($this->buildCommonAttributes($field, $context))
                ->attr('min', $min)
                ->attr('max', $max)
                ->attr('step', $step)
                ->value($currentValue)
        );

        if ($showValue) {
            $wrapper->child(
                Html::element('output')
                    ->class('field-range__value')
                    ->id($fieldId . '_output')
                    ->text((string) $currentValue)
            );
        }

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        
        if (!$settings->getBool('show_value', true)) {
            return null;
        }

        return <<<JS
(function() {
    var input = document.getElementById('{$elementId}');
    var output = document.getElementById('{$elementId}_output');
    if (input && output) {
        input.addEventListener('input', function() {
            output.textContent = this.value;
        });
    }
})();
JS;
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field->field_type) {
            'integer' => (int) $value,
            'float', 'decimal' => (float) $value,
            default => $value,
        };
    }

    public function getSettingsSchema(): array
    {
        return [
            'min' => ['type' => 'number', 'label' => 'Minimum', 'default' => 0],
            'max' => ['type' => 'number', 'label' => 'Maximum', 'default' => 100],
            'step' => ['type' => 'number', 'label' => 'Step', 'default' => 1],
            'show_value' => ['type' => 'boolean', 'label' => 'Show Value', 'default' => true],
        ];
    }
}
