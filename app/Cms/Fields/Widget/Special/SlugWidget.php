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
 * SlugWidget - URL-friendly slug generator
 */
final class SlugWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'slug';
    }

    public function getLabel(): string
    {
        return 'Slug';
    }

    public function getCategory(): string
    {
        return 'Special';
    }

    public function getIcon(): string
    {
        return '🔗';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['slug', 'string'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $sourceField = $settings->getString('source_field');

        $wrapper = Html::div()->class('field-slug');

        // Slug input
        $input = Html::input('text')
            ->attrs($this->buildCommonAttributes($field, $context))
            ->attr('pattern', '[a-z0-9]+(?:-[a-z0-9]+)*')
            ->value($value ?? '');

        if ($sourceField) {
            $input->data('source', $sourceField);
        }

        $wrapper->child($input);

        // Auto-generate button
        if ($sourceField) {
            $wrapper->child(
                Html::button()
                    ->class('field-slug__generate')
                    ->attr('type', 'button')
                    ->data('target', $fieldId)
                    ->text('Generate')
            );
        }

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        $sourceField = $settings->getString('source_field');

        if (!$sourceField) {
            return null;
        }

        $formId = explode('_', $elementId)[0];

        return <<<JS
(function() {
    var slugInput = document.getElementById('{$elementId}');
    var sourceInput = document.getElementById('{$formId}_{$sourceField}');
    var generateBtn = document.querySelector('[data-target="{$elementId}"]');
    
    function slugify(text) {
        return text.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');
    }
    
    if (generateBtn) {
        generateBtn.addEventListener('click', function() {
            if (sourceInput) {
                slugInput.value = slugify(sourceInput.value);
            }
        });
    }
    
    // Auto-generate if slug is empty
    if (sourceInput && !slugInput.value) {
        sourceInput.addEventListener('blur', function() {
            if (!slugInput.value) {
                slugInput.value = slugify(this.value);
            }
        });
    }
})();
JS;
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        // Ensure valid slug format
        return preg_replace('/[^a-z0-9-]/', '', strtolower($value));
    }

    public function getSettingsSchema(): array
    {
        return [
            'source_field' => ['type' => 'string', 'label' => 'Source Field (for auto-generation)'],
        ];
    }
}
