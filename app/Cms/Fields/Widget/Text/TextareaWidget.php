<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Text;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * TextareaWidget - Multi-line text input
 */
final class TextareaWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'textarea';
    }

    public function getLabel(): string
    {
        return 'Textarea';
    }

    public function getCategory(): string
    {
        return 'Text';
    }

    public function getIcon(): string
    {
        return '📄';
    }

    public function getPriority(): int
    {
        return 90;
    }

    public function getSupportedTypes(): array
    {
        return ['text', 'textarea', 'string'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);

        $textarea = Html::textarea()
            ->attrs($this->buildCommonAttributes($field, $context))
            ->attr('rows', $settings->getInt('rows', 5))
            ->text($value ?? '');

        if ($cols = $settings->getInt('cols')) {
            $textarea->attr('cols', $cols);
        }

        if ($maxLength = $settings->getInt('max_length')) {
            $textarea->attr('maxlength', $maxLength);
        }

        if ($resize = $settings->getString('resize', 'vertical')) {
            $textarea->attr('style', "resize: {$resize};");
        }

        // Character counter
        if ($settings->getBool('show_counter') && $maxLength) {
            return Html::div()
                ->child($textarea)
                ->child(
                    Html::div()
                        ->class('field-textarea__counter')
                        ->html('<span class="field-textarea__count">' . strlen($value ?? '') . '</span> / ' . $maxLength)
                );
        }

        return $textarea;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);

        if (!$settings->getBool('show_counter') || !$settings->getInt('max_length')) {
            return null;
        }

        return <<<JS
(function() {
    var textarea = document.getElementById('{$elementId}');
    var counter = document.querySelector('#{$elementId} + .field-textarea__counter .field-textarea__count, #{$elementId}_wrapper .field-textarea__count');
    if (textarea && counter) {
        textarea.addEventListener('input', function() {
            counter.textContent = this.value.length;
        });
    }
})();
JS;
    }

    public function getSettingsSchema(): array
    {
        return [
            'rows' => ['type' => 'integer', 'label' => 'Rows', 'default' => 5],
            'cols' => ['type' => 'integer', 'label' => 'Columns'],
            'max_length' => ['type' => 'integer', 'label' => 'Max Length'],
            'resize' => [
                'type' => 'select',
                'label' => 'Resize',
                'options' => [
                    'none' => 'None',
                    'vertical' => 'Vertical',
                    'horizontal' => 'Horizontal',
                    'both' => 'Both',
                ],
                'default' => 'vertical',
            ],
            'show_counter' => ['type' => 'boolean', 'label' => 'Show Character Counter', 'default' => false],
        ];
    }
}
