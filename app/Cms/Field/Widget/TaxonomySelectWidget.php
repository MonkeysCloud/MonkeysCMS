<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

final class TaxonomySelectWidget extends AbstractWidget
{
    public static function type(): string { return 'taxonomy_select'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $vocabulary = $field->getSetting('vocabulary', '');
        $multiple = (bool) $field->getSetting('multiple', false);
        $name = $this->fieldName($field, $namePrefix) . ($multiple ? '[]' : '');
        $fieldId = $this->fieldId($field);

        $selected = [];
        if (is_array($value)) {
            $selected = array_map('intval', $value);
        } elseif ($value !== null && $value !== '') {
            $selected = [(int) $value];
        }

        $input = '<select name="' . $name . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-select" '
            . 'data-taxonomy-select '
            . 'data-vocabulary="' . htmlspecialchars($vocabulary) . '"'
            . ($multiple ? ' multiple' : '')
            . '>';

        if (!$field->required && !$multiple) {
            $input .= '<option value="">— Select —</option>';
        }

        foreach ($selected as $termId) {
            $input .= '<option value="' . $termId . '" selected>Term #' . $termId . '</option>';
        }

        $input .= '</select>'
            . '<p class="form-help text-xs text-muted">Vocabulary: ' . htmlspecialchars($vocabulary) . '</p>';

        return $this->wrapGroup($field, $input);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $sIdx = $ctx->sectionIdx;
        $region = $ctx->regionName;
        $bIdx = $ctx->blockIdx;
        $fn = $field->machine_name;
        $vocabulary = $field->getSetting('vocabulary', '');
        $acId = "mosaic-tax-{$sIdx}-{$region}-{$bIdx}-{$fn}";

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<div class="mosaic-autocomplete" id="' . $acId . '">';
        $html .= '<input type="text" class="mosaic-field__input mosaic-autocomplete__input" placeholder="Search terms…"'
            . ' data-autocomplete-url="/api/cms/taxonomy/search?vocabulary=' . $this->esc($vocabulary) . '&q="'
            . ' data-field="' . $fn . '"'
            . ' data-section="' . $sIdx . '" data-region="' . $region . '" data-block="' . $bIdx . '"'
            . ' data-cardinality="-1"'
            . ' oninput="MosaicEditor.entityAutocomplete(this)">';
        $html .= '<div class="mosaic-autocomplete__results" style="display:none"></div>';

        $html .= '<div class="mosaic-autocomplete__selected">';
        $ids = is_array($value) ? $value : [];
        foreach ($ids as $id) {
            $html .= '<span class="mosaic-autocomplete__chip" data-id="' . (int) $id . '">'
                . 'Term #' . (int) $id
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
