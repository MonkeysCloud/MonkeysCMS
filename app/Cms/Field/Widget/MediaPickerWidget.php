<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

final class MediaPickerWidget extends AbstractWidget
{
    public static function type(): string { return 'media_picker'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $name = $this->fieldName($field, $namePrefix);
        $fieldId = $this->fieldId($field);
        $currentId = (int) ($value ?? 0);

        $input = '<div class="media-picker" id="' . $fieldId . '-picker" data-field="' . $fieldId . '">'
            . '<input type="hidden" name="' . $name . '" id="' . $fieldId . '" value="' . ($currentId ?: '') . '">'
            . '<div class="media-picker__preview" id="' . $fieldId . '-preview"'
            . ($currentId ? '' : ' style="display:none"') . '>'
            . ($currentId ? '<img src="/api/cms/media/' . $currentId . '/thumb" alt="">' : '')
            . '<button type="button" class="media-picker__remove" data-action="remove-media" data-target="' . $fieldId . '">'
            . '<i data-lucide="x" class="w-4 h-4"></i>'
            . '</button>'
            . '</div>'
            . '<button type="button" class="media-picker__btn" data-action="open-media-browser" data-target="' . $fieldId . '">'
            . '<i data-lucide="image-plus" class="w-5 h-5"></i>'
            . '<span>Select media</span>'
            . '</button>'
            . '</div>';

        return $this->wrapGroup($field, $input);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $mediaId = (int) $value;
        $sIdx = $ctx->sectionIdx;
        $region = $ctx->regionName;
        $bIdx = $ctx->blockIdx;
        $fn = $field->machine_name;
        $pickerId = "mosaic-media-{$sIdx}-{$region}-{$bIdx}-{$fn}";

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<div class="mosaic-media-picker" id="' . $pickerId . '-picker" data-field="' . $pickerId . '">';
        $html .= '<input type="hidden" id="' . $pickerId . '" value="' . ($mediaId ?: '') . '">';

        // Thumbnail preview
        $html .= '<div class="mosaic-media-picker__preview" id="' . $pickerId . '-preview"'
            . ($mediaId ? '' : ' style="display:none"') . '>';
        if ($mediaId) {
            $html .= '<img src="/api/cms/media/' . $mediaId . '/thumb" alt="">';
        }
        $html .= '<button type="button" class="media-picker__remove" data-action="remove-media" data-target="' . $pickerId . '"'
            . ' onclick="MosaicEditor.updateBlockField(' . $sIdx . ', \'' . $region . '\', ' . $bIdx . ', \'' . $fn . '\', \'\')">'
            . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>'
            . '</button>';
        $html .= '</div>';

        // Select button
        $html .= '<button type="button" class="mosaic-media-picker__btn" data-action="open-media-browser" data-target="' . $pickerId . '"'
            . ' data-mosaic-callback="MosaicEditor.updateBlockField(' . $sIdx . ', \'' . $region . '\', ' . $bIdx . ', \'' . $fn . '\', id);">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>'
            . '<span>Select media</span>'
            . '</button>';
        $html .= '</div>';

        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
