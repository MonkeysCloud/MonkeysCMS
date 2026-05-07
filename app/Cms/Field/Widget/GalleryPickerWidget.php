<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * GalleryPickerWidget — Multiple image picker using the media library.
 */
final class GalleryPickerWidget extends AbstractWidget
{
    public static function type(): string { return 'gallery_picker'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $images = is_array($value) ? $value : (is_string($value) ? (json_decode($value, true) ?? []) : []);
        $name = $this->fieldName($field, $namePrefix);
        $id = $this->fieldId($field);

        $html = '<div class="gallery-picker" id="' . $id . '-gallery">';

        // Hidden input to store JSON array of image IDs
        $html .= '<input type="hidden" name="' . $name . '" id="' . $id . '-data" '
            . 'value="' . htmlspecialchars(json_encode($images)) . '">';

        // Image grid preview
        $html .= '<div class="gallery-picker__grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));'
            . 'gap:.5rem;margin-bottom:.75rem">';
        foreach ($images as $imgId) {
            $html .= '<div class="gallery-picker__item" style="aspect-ratio:1;border-radius:8px;overflow:hidden;'
                . 'border:1px solid rgba(255,255,255,.06);position:relative">'
                . '<img src="/uploads/' . htmlspecialchars((string) $imgId) . '" '
                . 'style="width:100%;height:100%;object-fit:cover" loading="lazy">'
                . '</div>';
        }
        $html .= '</div>';

        // Add button
        $html .= '<button type="button" class="btn btn--ghost btn--sm" '
            . 'onclick="window.openMediaPicker && window.openMediaPicker(\'' . $id . '-data\', true)">'
            . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
            . '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>'
            . '<circle cx="9" cy="9" r="2"/>'
            . '<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>'
            . ' Add Images</button>';

        $html .= '</div>';

        return $this->wrapGroup($field, $html);
    }
}
