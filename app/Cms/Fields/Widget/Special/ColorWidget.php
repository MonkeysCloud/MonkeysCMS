<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Special;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * ColorWidget - Color picker
 */
final class ColorWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'color';
    }

    public function getLabel(): string
    {
        return 'Color Picker';
    }

    public function getCategory(): string
    {
        return 'Special';
    }

    public function getIcon(): string
    {
        return '🎨';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['color', 'string'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $showPreview = $settings->getBool('show_preview', true);
        $showHex = $settings->getBool('show_hex', true);
        $currentValue = $value ?: '#000000';
        
        $wrapper = Html::div()->class('field-color');

        // Color input
        $wrapper->child(
            Html::input('color')
                ->attrs($this->buildCommonAttributes($field, $context))
                ->value($currentValue)
        );

        // Hex text input
        if ($showHex) {
            $wrapper->child(
                Html::input('text')
                    ->class('field-color__hex')
                    ->id($fieldId . '_hex')
                    ->attr('pattern', '^#[0-9A-Fa-f]{6}$')
                    ->attr('maxlength', '7')
                    ->value($currentValue)
            );
        }

        // Preview
        if ($showPreview) {
            $wrapper->child(
                Html::div()
                    ->class('field-color__preview')
                    ->id($fieldId . '_preview')
                    ->attr('style', "background-color: {$currentValue};")
            );
        }

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        
        if (!$settings->getBool('show_hex', true)) {
            return null;
        }

        return <<<JS
(function() {
    var colorInput = document.getElementById('{$elementId}');
    var hexInput = document.getElementById('{$elementId}_hex');
    var preview = document.getElementById('{$elementId}_preview');
    
    if (colorInput && hexInput) {
        colorInput.addEventListener('input', function() {
            hexInput.value = this.value;
            if (preview) preview.style.backgroundColor = this.value;
        });
        
        hexInput.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                colorInput.value = this.value;
                if (preview) preview.style.backgroundColor = this.value;
            }
        });
    }
})();
JS;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $html = Html::span()
            ->class('field-display', 'field-display--color')
            ->child(
                Html::span()
                    ->class('field-display__swatch')
                    ->attr('style', "background-color: {$value};")
            )
            ->child(
                Html::span()
                    ->class('field-display__value')
                    ->text($value)
            )
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'show_preview' => ['type' => 'boolean', 'label' => 'Show Preview', 'default' => true],
            'show_hex' => ['type' => 'boolean', 'label' => 'Show Hex Input', 'default' => true],
        ];
    }
}
