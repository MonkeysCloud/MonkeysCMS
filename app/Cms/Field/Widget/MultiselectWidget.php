<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * MultiselectWidget — Multi-select dropdown using native HTML5 multiple.
 */
final class MultiselectWidget extends AbstractWidget
{
    public static function type(): string { return 'multiselect'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $options = $field->getSetting('options', []);
        $selected = is_array($value) ? $value : (is_string($value) ? json_decode($value, true) ?? [] : []);
        $name = $this->fieldName($field, $namePrefix) . '[]';

        $html = '<select name="' . $name . '" ' . $this->commonAttrs($field)
            . ' class="form-select" multiple size="' . min(count($options), 8) . '">';

        foreach ($options as $optKey => $optLabel) {
            if (is_int($optKey)) {
                $optKey = $optLabel;
            }
            $sel = in_array($optKey, $selected, true) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string) $optKey) . '"' . $sel . '>'
                . htmlspecialchars((string) $optLabel) . '</option>';
        }

        $html .= '</select>';
        $html .= '<p class="form-help" style="font-size:.75rem;color:#64748b;margin-top:.25rem">Hold Ctrl/Cmd to select multiple</p>';

        return $this->wrapGroup($field, $html);
    }
}
