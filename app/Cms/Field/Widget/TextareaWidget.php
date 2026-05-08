<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

final class TextareaWidget extends AbstractWidget
{
    public static function type(): string { return 'textarea'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $rows = $field->getSetting('rows', 5);
        $val = htmlspecialchars((string) ($value ?? ''));
        $placeholder = $field->getSetting('placeholder', '');

        $textarea = '<textarea '
            . 'name="' . $this->fieldName($field, $namePrefix) . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-textarea" '
            . 'rows="' . (int) $rows . '" '
            . ($placeholder ? 'placeholder="' . htmlspecialchars($placeholder) . '"' : '')
            . '>' . $val . '</textarea>';

        return $this->wrapGroup($field, $textarea);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $val = $this->esc((string) ($value ?? ''));
        $rows = (int) $field->getSetting('rows', 4);
        $cb = $ctx->mosaicCallback($field->machine_name);

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<textarea class="mosaic-field__input" rows="' . $rows . '" oninput="' . $this->esc($cb) . '">' . $val . '</textarea>';

        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
