<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * JsonEditorWidget — Textarea for raw JSON input with validation.
 */
final class JsonEditorWidget extends AbstractWidget
{
    public static function type(): string { return 'json_editor'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $raw = match (true) {
            is_string($value) => $value,
            is_array($value)  => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            default            => '{}',
        };
        $val = htmlspecialchars($raw);
        $id = $this->fieldId($field);
        $rows = $field->getSetting('rows', 10);

        $html = '<textarea name="' . $this->fieldName($field, $namePrefix) . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-input" '
            . 'rows="' . $rows . '" '
            . 'spellcheck="false" '
            . 'style="font-family:\'JetBrains Mono\',monospace;font-size:.8rem;line-height:1.5;'
            . 'background:rgba(0,0,0,.3);border-radius:10px;resize:vertical;color:#e2e8f0" '
            . 'onblur="try{JSON.parse(this.value);this.style.borderColor=\'rgba(255,255,255,.06)\'}'
            . 'catch(e){this.style.borderColor=\'#ef4444\'}"'
            . '>' . $val . '</textarea>'
            . '<div style="display:flex;justify-content:space-between;margin-top:.25rem">'
            . '<p class="form-help" style="font-size:.72rem;color:#64748b">Must be valid JSON</p>'
            . '<button type="button" class="btn btn--ghost" style="font-size:.68rem;padding:.15rem .5rem" '
            . 'onclick="try{var t=document.getElementById(\'' . $id . '\');'
            . 't.value=JSON.stringify(JSON.parse(t.value),null,2);t.style.borderColor=\'rgba(255,255,255,.06)\'}'
            . 'catch(e){alert(\'Invalid JSON: \'+e.message)}">Format</button>'
            . '</div>';

        return $this->wrapGroup($field, $html);
    }
}
