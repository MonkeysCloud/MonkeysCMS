<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Special;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * JsonWidget - JSON editor
 */
final class JsonWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'json';
    }

    public function getLabel(): string
    {
        return 'JSON Editor';
    }

    public function getCategory(): string
    {
        return 'Special';
    }

    public function getIcon(): string
    {
        return '{ }';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['json', 'array', 'object'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $rows = $settings->getInt('rows', 10);

        // Convert array/object to JSON string
        $jsonValue = is_array($value) || is_object($value)
            ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $value;

        $wrapper = Html::div()->class('field-json');

        // Textarea for JSON
        $wrapper->child(
            Html::textarea()
                ->attrs($this->buildCommonAttributes($field, $context))
                ->addClass('field-json__editor')
                ->attr('rows', $rows)
                ->attr('spellcheck', 'false')
                ->text($jsonValue ?? '')
        );

        // Validation status
        $wrapper->child(
            Html::div()
                ->class('field-json__status')
                ->id($fieldId . '_status')
                ->text('Valid JSON')
        );

        // Format button
        $wrapper->child(
            Html::button()
                ->class('field-json__format')
                ->attr('type', 'button')
                ->data('target', $fieldId)
                ->text('Format')
        );

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return <<<JAVASCRIPT
(function() {
    var textarea = document.getElementById('{$elementId}');
    var status = document.getElementById('{$elementId}_status');
    var formatBtn = document.querySelector('[data-target="{$elementId}"]');
    
    function validateJson() {
        try {
            if (textarea.value.trim()) {
                JSON.parse(textarea.value);
            }
            status.textContent = 'Valid JSON';
            status.className = 'field-json__status field-json__status--valid';
            return true;
        } catch (e) {
            status.textContent = 'Invalid: ' + e.message;
            status.className = 'field-json__status field-json__status--error';
            return false;
        }
    }
    
    textarea.addEventListener('input', validateJson);
    
    if (formatBtn) {
        formatBtn.addEventListener('click', function() {
            try {
                var obj = JSON.parse(textarea.value);
                textarea.value = JSON.stringify(obj, null, 2);
                validateJson();
            } catch (e) {
                // Can't format invalid JSON
            }
        });
    }
    
    validateJson();
})();
JAVASCRIPT;
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return $value;
    }

    public function formatValue(FieldDefinition $field, mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        return $value;
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        if ($this->isEmpty($value)) {
            return ValidationResult::success();
        }

        if (is_string($value)) {
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ValidationResult::failure('Invalid JSON: ' . json_last_error_msg());
            }
        }

        return ValidationResult::success();
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $json = is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT);

        $html = Html::element('pre')
            ->class('field-display', 'field-display--json')
            ->child(
                Html::element('code')
                    ->text($json)
            )
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'rows' => ['type' => 'integer', 'label' => 'Rows', 'default' => 10],
        ];
    }
}
