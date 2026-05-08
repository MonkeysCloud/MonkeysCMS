<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

/**
 * EntityAutocompleteWidget — Autocomplete input for content references.
 */
final class EntityAutocompleteWidget extends AbstractWidget
{
    public static function type(): string { return 'entity_autocomplete'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $val = htmlspecialchars((string) ($value ?? ''));
        $id = $this->fieldId($field);
        $targetType = $field->getSetting('target_type', '');

        $html = '<div class="entity-autocomplete" style="position:relative">'
            . '<input type="hidden" name="' . $this->fieldName($field, $namePrefix) . '" id="' . $id . '-value" value="' . $val . '">'
            . '<input type="text" id="' . $id . '" class="form-input" '
            . 'placeholder="Search content..." '
            . 'data-autocomplete-url="/admin/api/content/search" '
            . 'data-target-type="' . htmlspecialchars($targetType) . '" '
            . 'data-value-field="' . $id . '-value" '
            . 'autocomplete="off">'
            . '</div>';

        return $this->wrapGroup($field, $html);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $sIdx = $ctx->sectionIdx;
        $region = $ctx->regionName;
        $bIdx = $ctx->blockIdx;
        $fn = $field->machine_name;
        $entityType = $field->getSetting('entity_type', $field->getSetting('target_type', ''));
        $acId = "mosaic-entity-{$sIdx}-{$region}-{$bIdx}-{$fn}";

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<div class="mosaic-autocomplete" id="' . $acId . '">';
        $html .= '<input type="text" class="mosaic-field__input mosaic-autocomplete__input" placeholder="Search content…"'
            . ' data-autocomplete-url="/api/cms/content/search?type=' . $this->esc($entityType) . '&q="'
            . ' data-field="' . $fn . '"'
            . ' data-section="' . $sIdx . '" data-region="' . $region . '" data-block="' . $bIdx . '"'
            . ' data-cardinality="' . $field->cardinality . '"'
            . ' oninput="MosaicEditor.entityAutocomplete(this)">';
        $html .= '<div class="mosaic-autocomplete__results" style="display:none"></div>';

        // Selected entities
        $html .= '<div class="mosaic-autocomplete__selected">';
        $ids = is_array($value) ? $value : ($value ? [$value] : []);
        foreach ($ids as $id) {
            $html .= '<span class="mosaic-autocomplete__chip" data-id="' . (int) $id . '">'
                . 'Content #' . (int) $id
                . '<button type="button" class="mosaic-autocomplete__chip-remove"'
                . ' onclick="MosaicEditor.removeEntityRef(' . $sIdx . ', \'' . $region . '\', ' . $bIdx . ', \'' . $fn . '\', ' . (int) $id . ')">&times;</button>'
                . '</span>';
        }
        $html .= '</div></div>';

        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
