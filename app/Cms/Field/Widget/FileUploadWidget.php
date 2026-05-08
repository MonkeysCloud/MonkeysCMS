<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * FileUploadWidget — File upload with drag-and-drop zone.
 */
final class FileUploadWidget extends AbstractWidget
{
    public static function type(): string { return 'file_upload'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $name = $this->fieldName($field, $namePrefix);
        $id = $this->fieldId($field);
        $accept = $field->getSetting('accept', '');
        $maxSize = $field->getSetting('max_size', '10MB');
        $currentFile = htmlspecialchars((string) ($value ?? ''));

        $html = '<div class="file-upload-widget">';

        // Current file display
        if ($currentFile) {
            $html .= '<div class="file-upload-current" style="display:flex;align-items:center;gap:.5rem;'
                . 'padding:.5rem .75rem;background:rgba(255,255,255,.03);border-radius:8px;margin-bottom:.5rem">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2">'
                . '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>'
                . '<polyline points="14 2 14 8 20 8"/></svg>'
                . '<span style="color:#cbd5e1;font-size:.85rem">' . basename($currentFile) . '</span>'
                . '<input type="hidden" name="' . $name . '_current" value="' . $currentFile . '">'
                . '</div>';
        }

        // Upload input
        $html .= '<div class="file-drop-zone" style="border:2px dashed rgba(255,255,255,.08);border-radius:12px;'
            . 'padding:1.5rem;text-align:center;cursor:pointer;transition:border-color .2s" '
            . 'onclick="this.querySelector(\'input[type=file]\').click()" '
            . 'ondragover="event.preventDefault();this.style.borderColor=\'#818cf8\'" '
            . 'ondragleave="this.style.borderColor=\'rgba(255,255,255,.08)\'" '
            . 'ondrop="event.preventDefault();this.style.borderColor=\'rgba(255,255,255,.08)\';'
            . 'this.querySelector(\'input[type=file]\').files=event.dataTransfer.files">'
            . '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" '
            . 'style="margin:0 auto .5rem;display:block">'
            . '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>'
            . '<polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>'
            . '<p style="color:#94a3b8;font-size:.85rem;margin:0">Drop file here or <span style="color:#818cf8;text-decoration:underline">browse</span></p>'
            . '<p style="color:#475569;font-size:.72rem;margin:.25rem 0 0">Max size: ' . $maxSize . '</p>'
            . '<input type="file" name="' . $name . '" ' . $this->commonAttrs($field)
            . ($accept ? ' accept="' . htmlspecialchars($accept) . '"' : '')
            . ' style="display:none" onchange="let p=this.closest(\'.file-drop-zone\').querySelector(\'p\');'
            . 'p.textContent=this.files[0]?.name||\'Drop file here\'">'
            . '</div>';

        $html .= '</div>';

        return $this->wrapGroup($field, $html);
    }
}
