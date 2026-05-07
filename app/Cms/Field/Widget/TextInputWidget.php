<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

final class TextInputWidget extends AbstractWidget
{
    public static function type(): string { return 'text_input'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $inputType = match ($field->field_type) {
            'email' => 'email',
            'url'   => 'url',
            'phone' => 'tel',
            'color' => 'color',
            default => 'text',
        };

        $maxlength = $field->getSetting('maxlength', 255);
        $placeholder = $field->getSetting('placeholder', '');
        $val = htmlspecialchars((string) ($value ?? ''));

        $input = '<input type="' . $inputType . '" '
            . 'name="' . $this->fieldName($field, $namePrefix) . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-input" '
            . 'value="' . $val . '" '
            . 'maxlength="' . $maxlength . '" '
            . ($placeholder ? 'placeholder="' . htmlspecialchars($placeholder) . '"' : '')
            . '>';

        return $this->wrapGroup($field, $input);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $val = $this->esc((string) ($value ?? $field->default_value ?? ''));
        $label = $this->esc($field->name);
        $cb = $ctx->mosaicCallback($field->machine_name);
        $placeholder = $field->getSetting('placeholder', '');

        $inputType = match ($field->field_type) {
            'email' => 'email',
            'url'   => 'url',
            'phone' => 'tel',
            default => 'text',
        };

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $label . '</label>';
        $html .= '<input type="' . $inputType . '" class="mosaic-field__input" value="' . $val . '"'
            . ($placeholder ? ' placeholder="' . $this->esc($placeholder) . '"' : '')
            . ' oninput="' . $this->esc($cb) . '">';

        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }
}
