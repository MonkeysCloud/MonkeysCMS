<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

final class NumberInputWidget extends AbstractWidget
{
    public static function type(): string { return 'number_input'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $min = $field->getSetting('min');
        $max = $field->getSetting('max');
        $step = $field->getSetting('step', $field->field_type === 'integer' ? '1' : 'any');
        $val = $value !== null ? htmlspecialchars((string) $value) : '';

        $input = '<input type="number" '
            . 'name="' . $this->fieldName($field, $namePrefix) . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-input" '
            . 'value="' . $val . '" '
            . 'step="' . htmlspecialchars((string) $step) . '" '
            . ($min !== null ? 'min="' . (float) $min . '" ' : '')
            . ($max !== null ? 'max="' . (float) $max . '" ' : '')
            . '>';

        return $this->wrapGroup($field, $input);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $val = $this->esc((string) ($value ?? ''));
        $cb = $ctx->mosaicCallback($field->machine_name);
        $min = $field->getSetting('min');
        $max = $field->getSetting('max');
        $step = $field->getSetting('step', $field->field_type === 'integer' ? '1' : 'any');

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<input type="number" class="mosaic-field__input" value="' . $val . '"'
            . ' step="' . $this->esc((string) $step) . '"'
            . ($min !== null ? ' min="' . (float) $min . '"' : '')
            . ($max !== null ? ' max="' . (float) $max . '"' : '')
            . ' onchange="' . $this->esc($cb) . '">';

        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
