<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

final class DatePickerWidget extends AbstractWidget
{
    public static function type(): string { return 'date_picker'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $inputType = match ($field->field_type) {
            'datetime' => 'datetime-local',
            'time'     => 'time',
            default    => 'date',
        };

        $val = '';
        if ($value !== null && $value !== '') {
            if ($value instanceof \DateTimeInterface) {
                $val = match ($inputType) {
                    'datetime-local' => $value->format('Y-m-d\TH:i'),
                    'time'           => $value->format('H:i'),
                    default          => $value->format('Y-m-d'),
                };
            } else {
                $val = htmlspecialchars((string) $value);
            }
        }

        $input = '<input type="' . $inputType . '" '
            . 'name="' . $this->fieldName($field, $namePrefix) . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-input" '
            . 'value="' . $val . '"'
            . '>';

        return $this->wrapGroup($field, $input);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $val = $this->esc((string) ($value ?? ''));
        $cb = $ctx->mosaicCallback($field->machine_name);

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<input type="date" class="mosaic-field__input" value="' . $val . '" onchange="' . $this->esc($cb) . '">';

        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
