<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * MapPickerWidget — Latitude/Longitude input for geolocation fields.
 */
final class MapPickerWidget extends AbstractWidget
{
    public static function type(): string { return 'map_picker'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $data = is_array($value) ? $value : (is_string($value) ? (json_decode($value, true) ?? []) : []);
        $lat = htmlspecialchars((string) ($data['lat'] ?? ''));
        $lng = htmlspecialchars((string) ($data['lng'] ?? ''));
        $name = $this->fieldName($field, $namePrefix);

        $html = '<div class="geo-input" style="display:flex;gap:.5rem;align-items:end">'
            . '<div style="flex:1">'
            . '<label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.15rem">Latitude</label>'
            . '<input type="number" step="any" name="' . $name . '[lat]" class="form-input" '
            . 'value="' . $lat . '" placeholder="40.7128" min="-90" max="90">'
            . '</div>'
            . '<div style="flex:1">'
            . '<label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.15rem">Longitude</label>'
            . '<input type="number" step="any" name="' . $name . '[lng]" class="form-input" '
            . 'value="' . $lng . '" placeholder="-74.0060" min="-180" max="180">'
            . '</div>'
            . '</div>';

        return $this->wrapGroup($field, $html);
    }
}
