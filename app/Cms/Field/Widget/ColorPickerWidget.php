<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

/**
 * ColorPickerWidget — Native HTML5 color picker with text fallback.
 */
final class ColorPickerWidget extends AbstractWidget
{
    public static function type(): string { return 'color_picker'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $val = htmlspecialchars((string) ($value ?? '#000000'));
        $name = $this->fieldName($field, $namePrefix);
        $id = $this->fieldId($field);

        $input = '<div class="color-picker-wrap" style="display:flex;gap:.5rem;align-items:center">'
            . '<input type="color" name="' . $name . '" ' . $this->commonAttrs($field) . ' '
            . 'value="' . $val . '" style="width:48px;height:36px;padding:2px;border:1px solid rgba(255,255,255,.1);'
            . 'border-radius:8px;background:transparent;cursor:pointer">'
            . '<input type="text" value="' . $val . '" class="form-input" style="max-width:120px;font-family:monospace" '
            . 'oninput="this.previousElementSibling.value=this.value" '
            . 'onchange="this.previousElementSibling.value=this.value" '
            . 'pattern="^#[0-9a-fA-F]{6}$" placeholder="#000000">'
            . '<script>document.getElementById("' . $id . '").addEventListener("input",function(e){'
            . 'e.target.nextElementSibling.value=e.target.value});</script>'
            . '</div>';

        return $this->wrapGroup($field, $input);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $val = (string) ($value ?: '#ffffff');
        $cb = $ctx->mosaicCallback($field->machine_name);

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<input type="color" class="mosaic-field__input" style="height:36px;padding:2px" value="' . $this->esc($val) . '" onchange="' . $this->esc($cb) . '">';

        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
