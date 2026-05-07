<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * CodeEditorWidget — Textarea with monospace font for code/HTML editing.
 */
final class CodeEditorWidget extends AbstractWidget
{
    public static function type(): string { return 'code_editor'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $val = htmlspecialchars((string) ($value ?? ''));
        $language = $field->getSetting('language', 'html');
        $rows = $field->getSetting('rows', 12);

        $html = '<textarea name="' . $this->fieldName($field, $namePrefix) . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-input code-editor" '
            . 'rows="' . $rows . '" '
            . 'spellcheck="false" '
            . 'style="font-family:\'JetBrains Mono\',\'Fira Code\',\'Cascadia Code\',monospace;'
            . 'font-size:.8rem;line-height:1.6;tab-size:2;background:rgba(0,0,0,.3);'
            . 'border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:.75rem 1rem;'
            . 'color:#e2e8f0;resize:vertical" '
            . 'data-language="' . htmlspecialchars($language) . '"'
            . '>' . $val . '</textarea>'
            . '<p class="form-help" style="font-size:.72rem;color:#64748b;margin-top:.25rem">'
            . 'Language: ' . htmlspecialchars($language) . '</p>';

        return $this->wrapGroup($field, $html);
    }
}
