<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

final class ToggleWidget extends AbstractWidget
{
    public static function type(): string { return 'toggle'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $checked = !empty($value) ? ' checked' : '';
        $name = $this->fieldName($field, $namePrefix);

        $input = '<input type="hidden" name="' . $name . '" value="0">'
            . '<label class="form-toggle">'
            . '<input type="checkbox" name="' . $name . '" value="1" '
            . $this->commonAttrs($field) . $checked . '>'
            . '<span class="form-toggle__slider"></span>'
            . '<span class="form-toggle__label">' . htmlspecialchars($field->description ?? $field->name) . '</span>'
            . '</label>';

        return $this->wrapGroup($field, $input);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $checked = !empty($value) ? ' checked' : '';
        $sIdx = $ctx->sectionIdx;
        $region = $ctx->regionName;
        $bIdx = $ctx->blockIdx;
        $fn = $field->machine_name;

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-toggle">';
        $html .= '<input type="checkbox" class="mosaic-toggle__input"' . $checked
            . ' onchange="MosaicEditor.updateBlockField(' . $sIdx . ', \'' . $region . '\', ' . $bIdx . ', \'' . $fn . '\', this.checked)">';
        $html .= '<span class="mosaic-toggle__slider"></span>';
        $html .= '<span class="mosaic-toggle__label">' . $this->esc($field->name) . '</span>';
        $html .= '</label>';

        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
