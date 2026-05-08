<?php

declare(strict_types=1);

namespace App\Cms\Block\Types;

use App\Cms\Block\BlockTypeInterface;

/**
 * FieldBlock — Renders a content type field value inside the Mosaic layout.
 *
 * This special block type bridges EAV fields with the visual builder.
 * Authors drag a "Field" block into a region, pick a field machine name,
 * and the front-end renderer resolves the actual stored value at render time.
 *
 * The editor shows a dropdown of available fields for the current content type.
 */
final class FieldBlock implements BlockTypeInterface
{
    public static function getId(): string { return 'field'; }
    public static function getLabel(): string { return 'Content Field'; }
    public static function getDescription(): string { return 'Display a content type field (title, body, or custom EAV field)'; }
    public static function getIcon(): string { return 'database'; }
    public static function getCategory(): string { return 'Content'; }

    public static function getFields(): array
    {
        return [
            'field_name' => [
                'type'     => 'string',
                'label'    => 'Field',
                'required' => true,
                'default'  => 'title',
                'readonly' => true,
            ],
            'display_label' => [
                'type'    => 'select',
                'label'   => 'Show Label',
                'default' => 'false',
                'options' => [
                    'false' => 'No',
                    'true'  => 'Yes',
                ],
                'description' => 'Show the field name as a label above the value.',
            ],
            'wrapper_tag' => [
                'type'    => 'select',
                'label'   => 'Wrapper Element',
                'default' => 'div',
                'options' => [
                    'div'  => 'div — Block container',
                    'p'    => 'p — Paragraph',
                    'span' => 'span — Inline',
                    'h1'   => 'h1 — Heading 1',
                    'h2'   => 'h2 — Heading 2',
                    'h3'   => 'h3 — Heading 3',
                    'none' => 'None — Raw output',
                ],
                'description' => 'HTML element wrapping the field value.',
            ],
        ];
    }

    /**
     * Render a placeholder — the actual rendering is done by MosaicRenderer
     * which resolves the field value from the node context.
     */
    public function render(array $data, array $settings = []): string
    {
        $fieldName = $data['field_name'] ?? 'title';
        $value = $data['_resolved_value'] ?? null;
        $tag = $data['wrapper_tag'] ?? 'div';
        $showLabel = ($data['display_label'] ?? 'false') === 'true';
        $label = $data['_field_label'] ?? ucfirst(str_replace('_', ' ', $fieldName));

        if ($value === null) {
            return '<!-- field: ' . htmlspecialchars($fieldName) . ' (not resolved) -->';
        }

        $html = '';

        if ($showLabel) {
            $html .= '<div class="block-field__label">' . htmlspecialchars($label) . '</div>';
        }

        if ($tag === 'none') {
            $html .= $value;
        } else {
            $html .= '<' . $tag . ' class="block-field__value">' . $value . '</' . $tag . '>';
        }

        return '<div class="block-field block-field--' . htmlspecialchars($fieldName) . '">' . $html . '</div>';
    }
}
