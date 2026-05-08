<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

final class SelectWidget extends AbstractWidget
{
    public static function type(): string { return 'select'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $options = $field->getSetting('options', []);
        $multiple = $field->multiple;
        $currentValue = (string) ($value ?? '');
        $name = $this->fieldName($field, $namePrefix) . ($multiple ? '[]' : '');

        $select = '<select name="' . $name . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-select"'
            . ($multiple ? ' multiple' : '')
            . '>';

        if (!$field->required && !$multiple) {
            $select .= '<option value="">— Select —</option>';
        }

        foreach ($options as $optValue => $optLabel) {
            if (is_int($optValue)) {
                $optValue = $optLabel;
            }
            $selected = ($currentValue === (string) $optValue) ? ' selected' : '';
            $select .= '<option value="' . htmlspecialchars((string) $optValue) . '"'
                . $selected . '>'
                . htmlspecialchars((string) $optLabel) . '</option>';
        }

        $select .= '</select>';

        return $this->wrapGroup($field, $select);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $options = $field->getSetting('options', []);
        $cb = $ctx->mosaicCallback($field->machine_name);

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<select class="mosaic-field__input" onchange="' . $this->esc($cb) . '">';

        if (!$field->required) {
            $html .= '<option value="">— Select —</option>';
        }

        foreach ($options as $k => $v) {
            if (is_int($k)) $k = $v;
            $sel = ((string) $value === (string) $k) ? ' selected' : '';
            $html .= '<option value="' . $this->esc((string) $k) . '"' . $sel . '>' . $this->esc((string) $v) . '</option>';
        }

        $html .= '</select>';
        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
