<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * TimePickerWidget — HTML5 time input.
 */
final class TimePickerWidget extends AbstractWidget
{
    public static function type(): string { return 'time_picker'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $val = '';
        if ($value instanceof \DateTimeInterface) {
            $val = $value->format('H:i');
        } elseif (is_string($value) && $value !== '') {
            $val = htmlspecialchars($value);
        }

        $input = '<input type="time" '
            . 'name="' . $this->fieldName($field, $namePrefix) . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-input" '
            . 'value="' . $val . '" '
            . 'style="max-width:200px">';

        return $this->wrapGroup($field, $input);
    }
}
