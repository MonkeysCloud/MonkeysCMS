<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * BlockSelectWidget — Dropdown to select a block instance.
 */
final class BlockSelectWidget extends AbstractWidget
{
    public static function type(): string { return 'block_select'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $val = (string) ($value ?? '');
        $name = $this->fieldName($field, $namePrefix);

        // Renders a simple select; blocks should be loaded server-side
        $html = '<select name="' . $name . '" ' . $this->commonAttrs($field) . ' class="form-select">'
            . '<option value="">— Select Block —</option>';

        // If widget_settings has block options, render them
        $options = $field->getSetting('block_options', []);
        foreach ($options as $blockId => $blockLabel) {
            $sel = (string) $blockId === $val ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string) $blockId) . '"' . $sel . '>'
                . htmlspecialchars((string) $blockLabel) . '</option>';
        }

        $html .= '</select>';

        return $this->wrapGroup($field, $html);
    }
}
