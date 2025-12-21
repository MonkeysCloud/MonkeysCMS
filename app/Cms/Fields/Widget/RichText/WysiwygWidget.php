<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\RichText;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
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
        $this->assets->addCss('/css/fields/wysiwyg.css');
        $this->assets->addJs('/vendor/tinymce/tinymce.min.js');
        $this->assets->addJs('/js/fields/wysiwyg.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $height = $settings->getInt('height', 400);
        $toolbar = $settings->getString('toolbar', 'default');
        $plugins = $settings->getArray('plugins', ['link', 'image', 'lists', 'table', 'code']);

        $wrapper = Html::div()
            ->class('field-wysiwyg')
            ->data('field-id', $fieldId)
            ->data('height', $height)
            ->data('toolbar', $toolbar)
            ->data('plugins', json_encode($plugins));

        $wrapper->child(
            Html::textarea()
                ->attrs($this->buildCommonAttributes($field, $context))
                ->addClass('field-wysiwyg__editor')
                ->text($value ?? '')
        );

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        $height = $settings->getInt('height', 400);
        $toolbar = $this->getToolbarConfig($settings->getString('toolbar', 'default'));
        $plugins = json_encode($settings->getArray('plugins', ['link', 'image', 'lists', 'table', 'code']));
        $menubar = $settings->getBool('menubar', true) ? 'true' : 'false';

        return <<<JS
tinymce.init({
    selector: '#{$elementId}',
    height: {$height},
    menubar: {$menubar},
    plugins: {$plugins},
    toolbar: '{$toolbar}',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 16px; }',
    setup: function(editor) {
        editor.on('change', function() {
            editor.save();
        });
    }
});
JS;
    }

    private function getToolbarConfig(string $preset): string
    {
        return match ($preset) {
            'minimal' => 'undo redo | bold italic | bullist numlist',
            'simple' => 'undo redo | formatselect | bold italic underline | bullist numlist | link image',
            'full' => 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | code removeformat',
            default => 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | code',
        };
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        // Render HTML content as-is (trusted content)
        $html = Html::div()
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
            'menubar' => ['type' => 'boolean', 'label' => 'Show Menu Bar', 'default' => true],
            'plugins' => ['type' => 'array', 'label' => 'Enabled Plugins'],
        ];
    }
}
