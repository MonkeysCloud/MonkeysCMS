<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * CheckboxesWidget — Renders a set of checkboxes from options.
 */
final class CheckboxesWidget extends AbstractWidget
{
    public static function type(): string { return 'checkboxes'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $options = $field->getSetting('options', []);
        $checked = is_array($value) ? $value : (is_string($value) ? json_decode($value, true) ?? [] : []);
        $name = $this->fieldName($field, $namePrefix) . '[]';

        $html = '<div class="checkboxes-group" style="display:flex;flex-direction:column;gap:.5rem">';

        foreach ($options as $optKey => $optLabel) {
            if (is_int($optKey)) {
                $optKey = $optLabel;
            }
            $isChecked = in_array($optKey, $checked, true) ? ' checked' : '';
            $cbId = $this->fieldId($field) . '-' . preg_replace('/[^a-z0-9]/', '-', strtolower((string) $optKey));

            $html .= '<label class="checkbox-item" for="' . $cbId . '" '
                . 'style="display:flex;align-items:center;gap:.5rem;cursor:pointer;color:#cbd5e1;font-size:.875rem">'
                . '<input type="checkbox" id="' . $cbId . '" name="' . $name . '" '
                . 'value="' . htmlspecialchars((string) $optKey) . '"' . $isChecked
                . ' style="width:16px;height:16px;accent-color:#818cf8;border-radius:4px">'
                . htmlspecialchars((string) $optLabel)
                . '</label>';
        }

        $html .= '</div>';

        return $this->wrapGroup($field, $html);
    }
}
