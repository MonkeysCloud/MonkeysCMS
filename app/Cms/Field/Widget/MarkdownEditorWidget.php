<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * MarkdownEditorWidget — Textarea for markdown with preview toggle.
 */
final class MarkdownEditorWidget extends AbstractWidget
{
    public static function type(): string { return 'markdown_editor'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $val = htmlspecialchars((string) ($value ?? ''));
        $id = $this->fieldId($field);
        $rows = $field->getSetting('rows', 15);

        $html = '<div class="md-editor-wrap">';

        // Toolbar
        $html .= '<div class="md-editor-toolbar" style="display:flex;gap:.25rem;padding:.35rem .5rem;'
            . 'background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.06);border-bottom:none;'
            . 'border-radius:10px 10px 0 0">';
        $buttons = [
            ['B', '**', '**', 'Bold'],
            ['I', '*', '*', 'Italic'],
            ['H2', '## ', '', 'Heading'],
            ['🔗', '[text](url)', '', 'Link'],
            ['📷', '![alt](url)', '', 'Image'],
            ['<>', '```\n', '\n```', 'Code'],
        ];
        foreach ($buttons as [$label, $before, $after, $title]) {
            $html .= '<button type="button" class="btn btn--ghost" style="padding:.2rem .5rem;font-size:.72rem;'
                . 'min-width:auto;line-height:1.4" title="' . $title . '" '
                . 'onclick="mdInsert(\'' . $id . '\',\'' . addslashes($before) . '\',\'' . addslashes($after) . '\')">'
                . $label . '</button>';
        }
        $html .= '</div>';

        // Textarea
        $html .= '<textarea name="' . $this->fieldName($field, $namePrefix) . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-input" '
            . 'rows="' . $rows . '" '
            . 'style="font-family:\'JetBrains Mono\',monospace;font-size:.8rem;line-height:1.6;'
            . 'border-radius:0 0 10px 10px;resize:vertical;border-top:none"'
            . '>' . $val . '</textarea>';

        $html .= '</div>';

        // Inline helper script
        $html .= '<script>function mdInsert(id,b,a){var t=document.getElementById(id);if(!t)return;'
            . 'var s=t.selectionStart,e=t.selectionEnd,sel=t.value.substring(s,e);'
            . 't.value=t.value.substring(0,s)+b+sel+a+t.value.substring(e);'
            . 't.focus();t.selectionStart=s+b.length;t.selectionEnd=s+b.length+sel.length;}</script>';

        return $this->wrapGroup($field, $html);
    }
}
