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
 * MarkdownWidget - Markdown editor with preview
 */
final class MarkdownWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'markdown';
    }

    public function getLabel(): string
    {
        return 'Markdown Editor';
    }

    public function getCategory(): string
    {
        return 'Rich Text';
    }

    public function getIcon(): string
    {
        return '📋';
    }

    public function getPriority(): int
    {
        return 90;
    }

    public function getSupportedTypes(): array
    {
        return ['markdown', 'text', 'string'];
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/markdown.css');
        $this->assets->addJs('/vendor/marked/marked.min.js');
        $this->assets->addJs('/js/fields/markdown.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $rows = $settings->getInt('rows', 15);
        $showPreview = $settings->getBool('show_preview', true);
        $showToolbar = $settings->getBool('show_toolbar', true);

        $wrapper = Html::div()
            ->class('field-markdown')
            ->data('field-id', $fieldId);

        // Toolbar
        if ($showToolbar) {
            $wrapper->child($this->buildToolbar($fieldId));
        }

        // Editor container
        $editor = Html::div()->class('field-markdown__container');

        // Textarea
        $editor->child(
            Html::textarea()
                ->attrs($this->buildCommonAttributes($field, $context))
                ->addClass('field-markdown__input')
                ->attr('rows', $rows)
                ->text($value ?? '')
        );

        // Preview pane
        if ($showPreview) {
            $editor->child(
                Html::div()
                    ->class('field-markdown__preview', 'prose')
                    ->id($fieldId . '_preview')
            );
        }

        $wrapper->child($editor);

        // Mode toggle
        if ($showPreview) {
            $wrapper->child(
                Html::div()
                    ->class('field-markdown__modes')
                    ->child(
                        Html::button()
                            ->class('field-markdown__mode', 'active')
                            ->attr('type', 'button')
                            ->data('mode', 'edit')
                            ->text('Edit')
                    )
                    ->child(
                        Html::button()
                            ->class('field-markdown__mode')
                            ->attr('type', 'button')
                            ->data('mode', 'preview')
                            ->text('Preview')
                    )
                    ->child(
                        Html::button()
                            ->class('field-markdown__mode')
                            ->attr('type', 'button')
                            ->data('mode', 'split')
                            ->text('Split')
                    )
            );
        }

        return $wrapper;
    }

    private function buildToolbar(string $fieldId): HtmlBuilder
    {
        $buttons = [
            ['action' => 'bold', 'icon' => 'B', 'title' => 'Bold', 'wrap' => '**'],
            ['action' => 'italic', 'icon' => 'I', 'title' => 'Italic', 'wrap' => '_'],
            ['action' => 'heading', 'icon' => 'H', 'title' => 'Heading', 'prefix' => '## '],
            ['action' => 'link', 'icon' => '🔗', 'title' => 'Link'],
            ['action' => 'image', 'icon' => '🖼️', 'title' => 'Image'],
            ['action' => 'code', 'icon' => '<>', 'title' => 'Code', 'wrap' => '`'],
            ['action' => 'codeblock', 'icon' => '```', 'title' => 'Code Block'],
            ['action' => 'quote', 'icon' => '"', 'title' => 'Quote', 'prefix' => '> '],
            ['action' => 'ul', 'icon' => '•', 'title' => 'Bullet List', 'prefix' => '- '],
            ['action' => 'ol', 'icon' => '1.', 'title' => 'Numbered List', 'prefix' => '1. '],
        ];

        $toolbar = Html::div()->class('field-markdown__toolbar');

        foreach ($buttons as $btn) {
            $toolbar->child(
                Html::button()
                    ->class('field-markdown__btn')
                    ->attr('type', 'button')
                    ->data('action', $btn['action'])
                    ->data('target', $fieldId)
                    ->attr('title', $btn['title'])
                    ->text($btn['icon'])
            );
        }

        return $toolbar;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return "CmsMarkdown.init('{$elementId}');";
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        // In production, parse markdown to HTML
        // For now, wrap in pre for raw display
        $html = Html::div()
            ->class('field-display', 'field-display--markdown', 'prose')
            ->data('markdown', htmlspecialchars($value))
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'rows' => ['type' => 'integer', 'label' => 'Rows', 'default' => 15],
            'show_preview' => ['type' => 'boolean', 'label' => 'Show Preview', 'default' => true],
            'show_toolbar' => ['type' => 'boolean', 'label' => 'Show Toolbar', 'default' => true],
        ];
    }
}
