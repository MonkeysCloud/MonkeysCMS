<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\MlcFieldAdapter;
use App\Cms\Field\RenderContext;

/**
 * RepeaterWidget — Renders a list of repeatable sub-field groups.
 *
 * Used for structured data like feature lists, FAQ items, image galleries
 * with captions, etc. Each item contains the same set of sub-fields.
 *
 * Sub-fields are defined in settings['sub_fields'] as an MLC-style array.
 */
final class RepeaterWidget extends AbstractWidget
{
    public function __construct(
        private readonly WidgetRegistry $widgetRegistry,
    ) {}

    public static function type(): string { return 'repeater'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        // Admin form rendering — basic table of sub-fields
        $items = is_array($value) ? $value : [];
        $subFields = $field->getSetting('sub_fields', []);
        $name = $this->fieldName($field, $namePrefix);

        $html = '<div class="repeater-field" data-field="' . htmlspecialchars($field->machine_name) . '">';
        $html .= '<table class="repeater-table"><tbody>';

        foreach ($items as $idx => $item) {
            $html .= '<tr class="repeater-row">';
            foreach ($subFields as $sfName => $sfDef) {
                $sfValue = $item[$sfName] ?? '';
                $html .= '<td><input type="text" name="' . $name . '[' . $idx . '][' . htmlspecialchars($sfName) . ']" '
                    . 'class="form-input" value="' . htmlspecialchars((string) $sfValue) . '"></td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<button type="button" class="btn btn-sm" data-action="add-repeater-row">+ Add item</button>';
        $html .= '</div>';

        return $this->wrapGroup($field, $html);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $items = is_array($value) ? $value : [];
        $subFieldsDef = $field->getSetting('sub_fields', []);
        $fn = $field->machine_name;
        $sIdx = $ctx->sectionIdx;
        $region = $ctx->regionName;
        $bIdx = $ctx->blockIdx;

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<div class="mosaic-repeater" data-field="' . $fn . '">';

        // Render existing items
        foreach ($items as $i => $item) {
            $html .= $this->renderMosaicItem($field, $subFieldsDef, $item, $i, $ctx);
        }

        // Add button
        $html .= '<button type="button" class="mosaic-repeater__add" '
            . 'onclick="MosaicEditor.addRepeaterItem(' . $sIdx . ', \'' . $region . '\', ' . $bIdx . ', \'' . $fn . '\')">'
            . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>'
            . ' Add item</button>';

        $html .= '</div>'; // repeater
        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>'; // field
        return $html;
    }

    private function renderMosaicItem(
        FieldDefinition $parentField,
        array $subFieldsDef,
        array $itemData,
        int $itemIdx,
        RenderContext $ctx,
    ): string {
        $fn = $parentField->machine_name;
        $sIdx = $ctx->sectionIdx;
        $region = $ctx->regionName;
        $bIdx = $ctx->blockIdx;

        $html = '<div class="mosaic-repeater__item" data-index="' . $itemIdx . '">';

        // Header
        $html .= '<div class="mosaic-repeater__item-header">';
        $html .= '<span class="mosaic-repeater__item-handle" title="Drag to reorder">⋮⋮</span>';
        $html .= '<span class="mosaic-repeater__item-label">Item #' . ($itemIdx + 1) . '</span>';
        $html .= '<div class="mosaic-repeater__item-actions">';
        $html .= '<button type="button" class="mosaic-repeater__item-btn mosaic-repeater__item-btn--danger" '
            . 'onclick="MosaicEditor.removeRepeaterItem(' . $sIdx . ', \'' . $region . '\', ' . $bIdx . ', \'' . $fn . '\', ' . $itemIdx . ')" title="Remove">'
            . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>'
            . '</button>';
        $html .= '</div></div>';

        // Sub-fields body
        $html .= '<div class="mosaic-repeater__item-body">';
        $subCtx = $ctx->forRepeaterItem($fn, $itemIdx);
        foreach ($subFieldsDef as $sfName => $sfDef) {
            $sfDefinition = MlcFieldAdapter::fromArray($sfName, $sfDef);
            $sfValue = $itemData[$sfName] ?? $sfDefinition->default_value;
            $html .= $this->widgetRegistry->renderFieldForContext($sfDefinition, $sfValue, $subCtx);
        }
        $html .= '</div></div>';

        return $html;
    }
}
