<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;

/**
 * LinkInputWidget — Compound input for URL + link text + target.
 */
final class LinkInputWidget extends AbstractWidget
{
    public static function type(): string { return 'link_input'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $data = is_array($value) ? $value : (is_string($value) ? (json_decode($value, true) ?? ['url' => $value, 'text' => '']) : ['url' => '', 'text' => '']);
        $url = htmlspecialchars($data['url'] ?? '');
        $text = htmlspecialchars($data['text'] ?? '');
        $target = htmlspecialchars($data['target'] ?? '_self');
        $name = $this->fieldName($field, $namePrefix);

        $html = '<div class="link-input-group" style="display:flex;flex-direction:column;gap:.5rem">'
            . '<input type="url" name="' . $name . '[url]" class="form-input" '
            . 'value="' . $url . '" placeholder="https://example.com" ' . $this->commonAttrs($field) . '>'
            . '<input type="text" name="' . $name . '[text]" class="form-input" '
            . 'value="' . $text . '" placeholder="Link text (optional)">'
            . '<select name="' . $name . '[target]" class="form-select" style="max-width:200px">'
            . '<option value="_self"' . ($target === '_self' ? ' selected' : '') . '>Same window</option>'
            . '<option value="_blank"' . ($target === '_blank' ? ' selected' : '') . '>New window</option>'
            . '</select>'
            . '</div>';

        return $this->wrapGroup($field, $html);
    }

    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $data = is_array($value) ? $value : [];
        $url = $this->esc($data['url'] ?? '');
        $label = $this->esc($data['label'] ?? $data['text'] ?? '');
        $target = $data['target'] ?? '_self';
        $fn = $field->machine_name;

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $this->esc($field->name) . '</label>';
        $html .= '<div class="mosaic-compound-field">';

        // URL
        $html .= '<input type="url" class="mosaic-field__input" value="' . $url . '" placeholder="https://..."'
            . ' oninput="' . $this->esc($ctx->mosaicCompoundCallback($fn, 'url')) . '">';

        // Label
        $html .= '<input type="text" class="mosaic-field__input" value="' . $label . '" placeholder="Link text"'
            . ' oninput="' . $this->esc($ctx->mosaicCompoundCallback($fn, 'label')) . '">';

        // Target
        $selSelf = $target === '_self' ? ' selected' : '';
        $selBlank = $target === '_blank' ? ' selected' : '';
        $html .= '<select class="mosaic-field__input" onchange="' . $this->esc($ctx->mosaicCompoundCallback($fn, 'target')) . '">';
        $html .= '<option value="_self"' . $selSelf . '>Same window</option>';
        $html .= '<option value="_blank"' . $selBlank . '>New window</option>';
        $html .= '</select>';

        $html .= '</div>';
        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . $this->esc($field->description) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
