<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * AddressInputWidget — Compound fields for structured address input.
 */
final class AddressInputWidget extends AbstractWidget
{
    public static function type(): string { return 'address_input'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $data = is_array($value) ? $value : (is_string($value) ? (json_decode($value, true) ?? []) : []);
        $name = $this->fieldName($field, $namePrefix);

        $fields = [
            ['key' => 'line1', 'label' => 'Address Line 1', 'placeholder' => '123 Main St'],
            ['key' => 'line2', 'label' => 'Address Line 2', 'placeholder' => 'Apt, suite, etc.'],
            ['key' => 'city', 'label' => 'City', 'placeholder' => 'City'],
            ['key' => 'state', 'label' => 'State / Region', 'placeholder' => 'State'],
            ['key' => 'postal_code', 'label' => 'Postal Code', 'placeholder' => '12345'],
            ['key' => 'country', 'label' => 'Country', 'placeholder' => 'Country'],
        ];

        $html = '<div class="address-fields" style="display:grid;gap:.5rem">';
        foreach ($fields as $f) {
            $val = htmlspecialchars((string) ($data[$f['key']] ?? ''));
            $isSmall = in_array($f['key'], ['state', 'postal_code', 'country']);
            $html .= '<div' . ($isSmall ? '' : '') . '>'
                . '<label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.15rem">' . $f['label'] . '</label>'
                . '<input type="text" name="' . $name . '[' . $f['key'] . ']" class="form-input" '
                . 'value="' . $val . '" placeholder="' . $f['placeholder'] . '">'
                . '</div>';
        }
        $html .= '</div>';

        return $this->wrapGroup($field, $html);
    }
}
