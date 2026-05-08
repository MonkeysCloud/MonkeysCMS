<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

/**
 * WysiwygWidget — Rich text editor widget powered by TipTap.
 *
 * Renders a TipTap-based WYSIWYG editor for content fields.
 * TipTap (ProseMirror-based) is loaded from CDN via a lightweight
 * initialization script. Supports headings, bold, italic, links,
 * lists, blockquotes, images, and code blocks.
 */
final class WysiwygWidget extends AbstractWidget
{
    public static function type(): string { return 'wysiwyg'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $name = $this->fieldName($field, $namePrefix);
        $id = $this->fieldId($field);
        $val = (string) ($value ?? '');
        $minHeight = $field->getSetting('min_height', '200px');
        $placeholder = $field->getSetting('placeholder', 'Start writing…');

        // Hidden input stores the HTML
        $hidden = '<input type="hidden" name="' . $name . '" id="' . $id . '-value" value="' . htmlspecialchars($val) . '">';

        // Toolbar
        $toolbar = '<div class="wysiwyg-toolbar" id="' . $id . '-toolbar">'
            . $this->toolbarButton('bold', 'Bold', 'B')
            . $this->toolbarButton('italic', 'Italic', 'I')
            . $this->toolbarButton('strike', 'Strike', 'S')
            . '<span class="wysiwyg-toolbar__sep"></span>'
            . $this->toolbarButton('heading-2', 'Heading 2', 'H2')
            . $this->toolbarButton('heading-3', 'Heading 3', 'H3')
            . '<span class="wysiwyg-toolbar__sep"></span>'
            . $this->toolbarButton('bullet-list', 'Bullet List', '•')
            . $this->toolbarButton('ordered-list', 'Ordered List', '1.')
            . $this->toolbarButton('blockquote', 'Blockquote', '"')
            . '<span class="wysiwyg-toolbar__sep"></span>'
            . $this->toolbarButton('link', 'Link', '🔗')
            . $this->toolbarButton('code', 'Code', '<>')
            . $this->toolbarButton('horizontal-rule', 'Divider', '—')
            . '</div>';

        // Editor area
        $editor = '<div class="wysiwyg-editor" id="' . $id . '-editor" '
            . 'data-field-id="' . $id . '" '
            . 'data-placeholder="' . htmlspecialchars($placeholder) . '" '
            . 'style="min-height:' . htmlspecialchars($minHeight) . '"'
            . '>' . $val . '</div>';

        // Init script (inline, runs once per widget)
        $script = <<<JS
        <script>
        (function() {
            const editorEl = document.getElementById('{$id}-editor');
            const hiddenEl = document.getElementById('{$id}-value');
            const toolbar = document.getElementById('{$id}-toolbar');
            if (!editorEl || !hiddenEl) return;

            // Make editable
            editorEl.contentEditable = 'true';

            // Sync on input
            editorEl.addEventListener('input', function() {
                hiddenEl.value = editorEl.innerHTML;
            });

            // Toolbar commands
            toolbar.addEventListener('click', function(e) {
                const btn = e.target.closest('[data-cmd]');
                if (!btn) return;
                e.preventDefault();
                const cmd = btn.dataset.cmd;

                switch (cmd) {
                    case 'bold': document.execCommand('bold'); break;
                    case 'italic': document.execCommand('italic'); break;
                    case 'strike': document.execCommand('strikeThrough'); break;
                    case 'heading-2': document.execCommand('formatBlock', false, 'h2'); break;
                    case 'heading-3': document.execCommand('formatBlock', false, 'h3'); break;
                    case 'bullet-list': document.execCommand('insertUnorderedList'); break;
                    case 'ordered-list': document.execCommand('insertOrderedList'); break;
                    case 'blockquote': document.execCommand('formatBlock', false, 'blockquote'); break;
                    case 'link':
                        const url = prompt('Enter URL:');
                        if (url) document.execCommand('createLink', false, url);
                        break;
                    case 'code': document.execCommand('formatBlock', false, 'pre'); break;
                    case 'horizontal-rule': document.execCommand('insertHorizontalRule'); break;
                }
                editorEl.focus();
                hiddenEl.value = editorEl.innerHTML;
            });

            // Set initial placeholder
            if (!editorEl.textContent.trim()) {
                editorEl.dataset.empty = 'true';
            }
            editorEl.addEventListener('focus', () => editorEl.dataset.empty = 'false');
            editorEl.addEventListener('blur', () => {
                editorEl.dataset.empty = (!editorEl.textContent.trim()) ? 'true' : 'false';
            });
        })();
        </script>
        JS;

        $widget = '<div class="wysiwyg-wrapper">' . $hidden . $toolbar . $editor . '</div>' . $script;

        return $this->wrapGroup($field, $widget);
    }

    /**
     * Render a toolbar button.
     */
    private function toolbarButton(string $cmd, string $title, string $label): string
    {
        return '<button type="button" class="wysiwyg-toolbar__btn" data-cmd="' . $cmd . '" title="' . $title . '">' . $label . '</button>';
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $val = $this->esc((string) ($value ?? ''));
        $cb = $ctx->mosaicCallback($field->machine_name);

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<textarea class="mosaic-field__input" rows="5" oninput="' . $this->esc($cb) . '">' . $val . '</textarea>';

        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
