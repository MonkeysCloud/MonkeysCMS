<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

/**
 * DatetimePickerWidget — HTML5 datetime-local input.
 */
final class DatetimePickerWidget extends AbstractWidget
{
    public static function type(): string { return 'datetime_picker'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $val = '';
        if ($value instanceof \DateTimeInterface) {
            $val = $value->format('Y-m-d\TH:i');
        } elseif (is_string($value) && $value !== '') {
            try {
                $val = (new \DateTimeImmutable($value))->format('Y-m-d\TH:i');
            } catch (\Throwable) {
                $val = htmlspecialchars($value);
            }
        }

        $input = '<input type="datetime-local" '
            . 'name="' . $this->fieldName($field, $namePrefix) . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-input" '
            . 'value="' . $val . '">';

        return $this->wrapGroup($field, $input);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $val = $this->esc((string) ($value ?? ''));
        $cb = $ctx->mosaicCallback($field->machine_name);

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<input type="datetime-local" class="mosaic-field__input" value="' . $val . '" onchange="' . $this->esc($cb) . '">';
        $html .= '</div>';
        return $html;
    }
}
