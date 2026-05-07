<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

/**
 * SlotWidget — Nested component drop-zone field.
 *
 * Allows embedding other block types inside a block field,
 * supporting composition (e.g., a card with a footer slot
 * that can contain buttons, text, images).
 *
 * Settings:
 *   - allowed_types: array of block type IDs that can be placed here
 */
final class SlotWidget extends AbstractWidget
{
    public static function type(): string { return 'slot_dropzone'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        // Admin form: show info about nested content
        $blocks = is_array($value) ? $value : [];
        $count = count($blocks);

        $html = '<div class="slot-field">'
            . '<p class="form-help">' . $count . ' nested block(s). Edit in Mosaic editor.</p>'
            . '</div>';

        return $this->wrapGroup($field, $html);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $blocks = is_array($value) ? $value : [];
        $allowedTypes = $field->getSetting('allowed_types', []);
        $fn = $field->machine_name;
        $sIdx = $ctx->sectionIdx;
        $region = $ctx->regionName;
        $bIdx = $ctx->blockIdx;

        // Get available block types for the dropdown (filtered by allowed_types)
        $availableTypes = $ctx->blockTypes;
        if (!empty($allowedTypes)) {
            $availableTypes = array_filter($availableTypes, fn($bt) => in_array($bt['id'] ?? $bt, $allowedTypes, true));
        }

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<div class="mosaic-slot" data-field="' . $fn . '">';

        // Existing slot blocks
        foreach ($blocks as $i => $block) {
            $blockType = $block['type'] ?? 'unknown';
            $html .= '<div class="mosaic-slot__block" data-index="' . $i . '">';
            $html .= '<span class="mosaic-slot__block-handle">⋮⋮</span>';
            $html .= '<span class="mosaic-slot__block-type">' . $this->esc(ucfirst($blockType)) . '</span>';
            $html .= '<button type="button" class="mosaic-slot__block-edit" '
                . 'onclick="MosaicEditor.editSlotBlock(' . $sIdx . ', \'' . $region . '\', ' . $bIdx . ', \'' . $fn . '\', ' . $i . ')" title="Edit">'
                . '✎</button>';
            $html .= '<button type="button" class="mosaic-slot__block-remove" '
                . 'onclick="MosaicEditor.removeSlotBlock(' . $sIdx . ', \'' . $region . '\', ' . $bIdx . ', \'' . $fn . '\', ' . $i . ')" title="Remove">'
                . '×</button>';
            $html .= '</div>';
        }

        // Add block dropdown
        if (!empty($availableTypes)) {
            $html .= '<div class="mosaic-slot__add">';
            $html .= '<select class="mosaic-field__input mosaic-slot__type-select" id="slot-add-' . $fn . '">';
            $html .= '<option value="">Add block…</option>';
            foreach ($availableTypes as $bt) {
                $btId = is_array($bt) ? ($bt['id'] ?? '') : $bt;
                $btLabel = is_array($bt) ? ($bt['label'] ?? ucfirst($btId)) : ucfirst($bt);
                $html .= '<option value="' . $this->esc((string) $btId) . '">' . $this->esc((string) $btLabel) . '</option>';
            }
            $html .= '</select>';
            $html .= '<button type="button" class="mosaic-slot__add-btn" '
                . 'onclick="MosaicEditor.addSlotBlock(' . $sIdx . ', \'' . $region . '\', ' . $bIdx . ', \'' . $fn . '\', '
                . 'document.getElementById(\'slot-add-' . $fn . '\').value)">+ Add</button>';
            $html .= '</div>';
        }

        $html .= '</div>'; // slot
        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>'; // field
        return $html;
    }
}
