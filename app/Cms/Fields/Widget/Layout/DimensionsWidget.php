<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Layout;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * DimensionsWidget - Width/Height/Depth measurements
 */
final class DimensionsWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'dimensions';
    }

    public function getLabel(): string
    {
        return 'Dimensions';
    }

    public function getCategory(): string
    {
        return 'Layout';
    }

    public function getIcon(): string
    {
        return '📐';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['dimensions', 'json', 'array'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $value = is_array($value) ? $value : [];
        $unit = $settings->getString('unit', 'cm');
        $showDepth = $settings->getBool('show_depth', true);

        $wrapper = Html::div()->class('field-dimensions');

        // Width
        $wrapper->child($this->buildDimensionField(
            $fieldId,
            $fieldName,
            'width',
            'Width',
            $value['width'] ?? '',
            $unit,
            $context
        ));

        // Height
        $wrapper->child($this->buildDimensionField(
            $fieldId,
            $fieldName,
            'height',
            'Height',
            $value['height'] ?? '',
            $unit,
            $context
        ));

        // Depth
        if ($showDepth) {
            $wrapper->child($this->buildDimensionField(
                $fieldId,
                $fieldName,
                'depth',
                'Depth',
                $value['depth'] ?? '',
                $unit,
                $context
            ));
        }

        // Unit display
        $wrapper->child(
            Html::span()
                ->class('field-dimensions__unit')
                ->text($unit)
        );

        return $wrapper;
    }

    private function buildDimensionField(
        string $fieldId,
        string $fieldName,
        string $dimension,
        string $label,
        string $value,
        string $unit,
        RenderContext $context
    ): HtmlBuilder {
        return Html::div()
            ->class('field-dimensions__field')
            ->child(
                Html::label()
                    ->attr('for', $fieldId . '_' . $dimension)
                    ->text($label)
            )
            ->child(
                Html::input('number')
                    ->id($fieldId . '_' . $dimension)
                    ->name($fieldName . '[' . $dimension . ']')
                    ->value($value)
                    ->attr('min', '0')
                    ->attr('step', '0.01')
                    ->class('field-dimensions__input')
                    ->when($context->isDisabled(), fn($el) => $el->disabled())
            );
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if (!is_array($value)) {
            return null;
        }

        $dimensions = [];

        foreach (['width', 'height', 'depth'] as $dim) {
            if (isset($value[$dim]) && $value[$dim] !== '') {
                $dimensions[$dim] = (float) $value[$dim];
            }
        }

        return empty($dimensions) ? null : $dimensions;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if (!is_array($value) || empty($value)) {
            return parent::renderDisplay($field, null, $context);
        }

        $settings = $this->getSettings($field);
        $unit = $settings->getString('unit', 'cm');

        $parts = [];
        if (isset($value['width'])) {
            $parts[] = $value['width'];
        }
        if (isset($value['height'])) {
            $parts[] = $value['height'];
        }
        if (isset($value['depth'])) {
            $parts[] = $value['depth'];
        }

        $text = implode(' × ', $parts) . ' ' . $unit;

        $html = Html::span()
            ->class('field-display', 'field-display--dimensions')
            ->text($text)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'unit' => [
                'type' => 'select',
                'label' => 'Unit',
                'options' => [
                    'cm' => 'Centimeters (cm)',
                    'mm' => 'Millimeters (mm)',
                    'm' => 'Meters (m)',
                    'in' => 'Inches (in)',
                    'ft' => 'Feet (ft)',
                ],
                'default' => 'cm',
            ],
            'show_depth' => ['type' => 'boolean', 'label' => 'Show Depth Field', 'default' => true],
        ];
    }
}
