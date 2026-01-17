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

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/textarea.css');
        $this->assets->addJs('/js/fields/textarea.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        
        // Wrapper
        $wrapper = Html::div()
            ->class('field-textarea')
            ->data('field-id', $fieldId);

        $textarea = Html::textarea()
            ->id($fieldId)
            ->name($this->getFieldName($field, $context))
            ->class('field-textarea__input block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 disabled:bg-gray-50 disabled:text-gray-500 transition-colors duration-200')
            ->text($value ?? '')
            ->attr('rows', $settings->getInt('rows', 5));

        if ($cols = $settings->getInt('cols')) {
            $textarea->attr('cols', $cols);
        }

        if ($maxLength = $settings->getInt('max_length')) {
            $textarea->attr('maxlength', $maxLength);
        }
        
        // Manual resize style if not using auto-resize
        if ($resize = $settings->getString('resize', 'vertical')) {
            // Auto resize usually implies overflow hidden and handle height manually
            if ($resize === 'auto') {
                $textarea->attr('style', 'resize: none; overflow-y: hidden;');
            } else {
                $textarea->attr('style', "resize: {$resize};");
            }
        }

        $wrapper->child($textarea);

        // Counter placeholder (will be filled by JS if needed, or PHP fallback)
        if ($settings->getBool('show_counter') && $maxLength) {
             $wrapper->child(
                Html::div()
                    ->class('field-textarea__counter')
                    ->text(strlen($value ?? '') . ' / ' . $maxLength)
            );
        }

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        
        $options = [
            'autoResize' => $settings->getString('resize') === 'auto',
            'showCounter' => $settings->getBool('show_counter', false),
            'maxLength' => $settings->getInt('max_length'),
        ];
        
        // Only output script if we have dynamic features
        if (!$options['autoResize'] && !$options['showCounter']) {
            return null;
        }

        $optionsJson = json_encode($options);
        return "CmsTextarea.init('{$elementId}', {$optionsJson});";
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
                    'auto' => 'Auto Resize (Dynamic Height)',
                ],
                'default' => 'vertical',
            ],
            'show_counter' => ['type' => 'boolean', 'label' => 'Show Character Counter', 'default' => false],
        ];
    }
}
