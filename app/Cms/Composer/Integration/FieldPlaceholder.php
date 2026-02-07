<?php

declare(strict_types=1);

namespace App\Cms\Composer\Integration;

/**
 * FieldPlaceholder - Renders content type fields within composer layouts
 * 
 * This special "block" renders a content type field at its position in the layout.
 * It allows users to control exactly where each field appears.
 */
class FieldPlaceholder
{
    /**
     * Render a field placeholder
     * 
     * @param string $fieldName The field machine name
     * @param array $fieldData The field's value/data
     * @param array $fieldConfig The field's configuration
     * @param array $options Rendering options (view_mode, hide_label)
     */
    public function render(string $fieldName, mixed $fieldData, array $fieldConfig = [], array $options = []): string
    {
        $viewMode = $options['view_mode'] ?? 'default';
        $hideLabel = $options['hide_label'] ?? false;
        $label = $fieldConfig['name'] ?? $fieldConfig['label'] ?? ucfirst(str_replace('_', ' ', $fieldName));

        $html = '<div class="mb-4">';
        
        if (!$hideLabel && !empty($label)) {
            $html .= '<div class="text-sm font-semibold text-gray-700 mb-1">' . htmlspecialchars($label) . '</div>';
        }

        $html .= '<div class="text-gray-900">';
        $html .= $this->formatFieldValue($fieldData, $fieldConfig);
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Format field value based on field type
     */
    private function formatFieldValue(mixed $value, array $config): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $type = $config['type'] ?? $config['field_type'] ?? 'text';

        return match ($type) {
            'wysiwyg', 'html', 'rich_text', 'textarea' => '<div class="prose prose-lg max-w-none">' . $value . '</div>',
            'text', 'string' => htmlspecialchars((string) $value),
            'image' => $this->formatImage($value),
            'boolean' => $value ? '<span class="text-green-600">Yes</span>' : '<span class="text-red-600">No</span>',
            'date' => $this->formatDate($value),
            'link', 'url' => $this->formatLink($value),
            default => is_array($value) ? json_encode($value) : htmlspecialchars((string) $value),
        };
    }

    private function formatImage(mixed $value): string
    {
        if (is_array($value)) {
            $src = $value['url'] ?? $value['src'] ?? '';
            $alt = $value['alt'] ?? '';
        } else {
            $src = (string) $value;
            $alt = '';
        }

        if (empty($src)) {
            return '';
        }

        return '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '" class="max-w-full h-auto rounded-lg">';
    }

    private function formatDate(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            $date = new \DateTime((string) $value);
            return $date->format('F j, Y');
        } catch (\Exception) {
            return (string) $value;
        }
    }

    private function formatLink(mixed $value): string
    {
        if (is_array($value)) {
            $url = $value['url'] ?? $value['href'] ?? '';
            $text = $value['title'] ?? $value['text'] ?? $url;
        } else {
            $url = (string) $value;
            $text = $url;
        }

        if (empty($url)) {
            return '';
        }

        return '<a href="' . htmlspecialchars($url) . '" class="text-blue-600 hover:text-blue-800 hover:underline">' . htmlspecialchars($text) . '</a>';
    }

    /**
     * Get available fields from a content type for the composer sidebar
     */
    public function getFieldsForSidebar(array $contentType): array
    {
        $fields = [];

        foreach ($contentType['fields'] ?? [] as $field) {
            $fields[] = [
                'type' => '_field_placeholder',
                'field_name' => $field['machine_name'] ?? $field['name'],
                'label' => $field['name'] ?? $field['label'] ?? 'Unknown',
                'icon' => $this->getFieldIcon($field['type'] ?? 'text'),
                'description' => $field['description'] ?? '',
            ];
        }

        return $fields;
    }

    private function getFieldIcon(string $type): string
    {
        return match ($type) {
            'text', 'string' => '📝',
            'wysiwyg', 'html', 'rich_text' => '📄',
            'image' => '🖼️',
            'file' => '📎',
            'number', 'integer', 'float' => '🔢',
            'boolean' => '✅',
            'date', 'datetime' => '📅',
            'email' => '✉️',
            'link', 'url' => '🔗',
            'select', 'radios', 'checkboxes' => '📋',
            'taxonomy' => '🏷️',
            'entity_reference' => '🔗',
            default => '📦',
        };
    }
}
