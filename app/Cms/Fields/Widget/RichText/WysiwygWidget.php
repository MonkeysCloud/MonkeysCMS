<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\RichText;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * WysiwygWidget - Rich text editor (TinyMCE, CKEditor, etc.)
 */
final class WysiwygWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'wysiwyg';
    }

    public function getLabel(): string
    {
        return 'Rich Text Editor';
    }

    public function getCategory(): string
    {
        return 'Rich Text';
    }

    public function getIcon(): string
    {
        return '📝';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['html', 'text', 'wysiwyg', 'richtext'];
    }

    protected function initializeAssets(): void
    {
        // Use Quill from local vendor
        $this->assets->addCss('/vendor/quill/quill.snow.css');
        $this->assets->addJs('/vendor/quill/quill.js');
        $this->assets->addJs('/js/fields/quill-init.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $input = HtmlBuilder::textarea()
            ->name($field->machine_name)
            ->id($this->getFieldId($field, $context))
            ->class('form-textarea block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm')
            ->attr('rows', '10')
            ->text($value ?? '')
            ->attr('data-quill', 'true');

        $wrapper = HtmlBuilder::div()
            ->class('field-wysiwyg')
            ->child($input);

        return $wrapper;
    }

    // getInitScript is no longer needed as quill-init.js handles it
    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return null;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        // Render HTML content as-is (trusted content)
        $html = HtmlBuilder::div()
            ->class('field-display', 'field-display--wysiwyg', 'prose')
            ->html($value)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'height' => ['type' => 'integer', 'label' => 'Editor Height (px)', 'default' => 400],
            'toolbar' => [
                'type' => 'select',
                'label' => 'Toolbar Preset',
                'options' => [
                    'minimal' => 'Minimal',
                    'simple' => 'Simple',
                    'default' => 'Default',
                    'full' => 'Full',
                ],
                'default' => 'default',
            ],
            // 'menubar' => ['type' => 'boolean', 'label' => 'Show Menu Bar', 'default' => true],
            // 'plugins' => ['type' => 'array', 'label' => 'Enabled Plugins'],
        ];
    }
}
