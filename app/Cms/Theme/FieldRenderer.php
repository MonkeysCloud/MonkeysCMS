<?php

declare(strict_types=1);

namespace App\Cms\Theme;

/**
 * FieldRenderer - Global field rendering service for themes
 * 
 * This service provides consistent field rendering across all themes.
 * Theme developers can:
 * 1. Render all fields dynamically using renderAll()
 * 2. Render a single field using render()
 * 3. Check if a field exists using has()
 * 4. Get raw value using raw()
 * 
 * Usage in templates:
 *   <?= $fieldRenderer->render('field_body') ?>  - Render single field
 *   <?php foreach($fieldRenderer->all() as $field): ?>  - Loop all fields
 */
class FieldRenderer
{
    private array $item;
    private array $fields;
    private array $contentType;
    
    public function __construct(array $item, array $fields, array $contentType = [])
    {
        $this->item = $item;
        $this->fields = $fields;
        $this->contentType = $contentType;
    }
    
    /**
     * Create a FieldRenderer from content data
     */
    public static function fromContent(array $item, array $contentType): self
    {
        $fields = [];
        $fieldsConfig = $contentType['fields'] ?? [];
        
        foreach ($fieldsConfig as $fieldData) {
            $machineName = $fieldData['machine_name'] ?? '';
            if (empty($machineName)) {
                continue;
            }
            
            $value = $item[$machineName] ?? null;
            
            // Get the configured field type
            $fieldType = $fieldData['type'] ?? 'text';
            
            // Map certain field types to HTML for proper rendering
            // These field types should render HTML content without escaping
            $htmlFieldTypes = [
                'wysiwyg', 'html', 'rich_text', 'textarea_html', 
                'formatted_text', 'editor', 'ckeditor', 'quill',
                'markdown', 'body'  // markdown is converted, body is always HTML
            ];
            
            // Check if field type is HTML, OR if field name suggests HTML content
            $isHtmlField = in_array($fieldType, $htmlFieldTypes);
            $isBodyField = str_contains($machineName, 'body') || str_contains($machineName, 'content') || str_contains($machineName, 'description');
            
            if ($isHtmlField || $isBodyField) {
                $fieldType = 'html';
            }
            
            $fields[$machineName] = [
                'label' => $fieldData['name'] ?? ucfirst(str_replace('_', ' ', $machineName)),
                'value' => self::formatValue($value, $fieldType, $fieldData),
                'raw_value' => $value,
                'type' => $fieldType,
                'machine_name' => $machineName,
                'config' => $fieldData,
            ];
        }
        
        // Also include direct item fields that start with 'field_'
        foreach ($item as $key => $value) {
            if (str_starts_with($key, 'field_') && !isset($fields[$key]) && $value !== null) {
                // Body fields are always HTML type
                $autoType = 'auto';
                if (str_contains($key, 'body') || str_contains($key, 'content') || str_contains($key, 'description')) {
                    $autoType = 'html';
                }
                
                $fields[$key] = [
                    'label' => ucfirst(str_replace(['field_', '_'], ['', ' '], $key)),
                    'value' => self::formatValue($value, $autoType, []),
                    'raw_value' => $value,
                    'type' => $autoType,
                    'machine_name' => $key,
                    'config' => [],
                ];
            }
        }
        
        return new self($item, $fields, $contentType);
    }
    
    /**
     * Check if a field exists and has value
     */
    public function has(string $name): bool
    {
        return isset($this->fields[$name]) && !empty($this->fields[$name]['raw_value']);
    }
    
    /**
     * Get a single field's rendered value
     */
    public function render(string $name, string $wrapper = ''): string
    {
        if (!$this->has($name)) {
            return '';
        }
        
        $value = $this->fields[$name]['value'];
        
        if ($wrapper) {
            return "<{$wrapper}>{$value}</{$wrapper}>";
        }
        
        return $value;
    }
    
    /**
     * Get field's raw (unrendered) value
     */
    public function raw(string $name): mixed
    {
        return $this->fields[$name]['raw_value'] ?? null;
    }
    
    /**
     * Get field label
     */
    public function label(string $name): string
    {
        return $this->fields[$name]['label'] ?? '';
    }
    
    /**
     * Get field type
     */
    public function type(string $name): string
    {
        return $this->fields[$name]['type'] ?? 'text';
    }
    
    /**
     * Get all fields as array
     */
    public function all(): array
    {
        return $this->fields;
    }
    
    /**
     * Get all fields except specified ones
     */
    public function except(array $exclude): array
    {
        return array_filter($this->fields, fn($f, $k) => !in_array($k, $exclude), ARRAY_FILTER_USE_BOTH);
    }
    
    /**
     * Get only specified fields
     */
    public function only(array $include): array
    {
        return array_filter($this->fields, fn($f, $k) => in_array($k, $include), ARRAY_FILTER_USE_BOTH);
    }
    
    /**
     * Get fields with non-empty values
     */
    public function filled(): array
    {
        return array_filter($this->fields, fn($f) => !empty($f['raw_value']));
    }
    
    /**
     * Render all fields as HTML
     * 
     * @param array $options Options for rendering:
     *   - exclude: array of field names to exclude
     *   - wrapper: HTML element to wrap each field (default: 'div')
     *   - showLabels: whether to show field labels (default: true)
     *   - classes: CSS classes for the wrapper
     */
    public function renderAll(array $options = []): string
    {
        $exclude = $options['exclude'] ?? [];
        $wrapper = $options['wrapper'] ?? 'div';
        $showLabels = $options['showLabels'] ?? true;
        $classes = $options['classes'] ?? 'field-item';
        $labelClasses = $options['labelClasses'] ?? 'field-label text-sm font-medium text-gray-600 mb-1';
        $valueClasses = $options['valueClasses'] ?? 'field-value';
        
        $html = '';
        
        foreach ($this->filled() as $name => $field) {
            if (in_array($name, $exclude)) {
                continue;
            }
            
            $html .= "<{$wrapper} class=\"{$classes} field--{$name}\">";
            
            if ($showLabels) {
                $html .= "<div class=\"{$labelClasses}\">" . htmlspecialchars($field['label']) . "</div>";
            }
            
            $html .= "<div class=\"{$valueClasses}\">" . $field['value'] . "</div>";
            $html .= "</{$wrapper}>";
        }
        
        return $html;
    }
    
    /**
     * Format a field value for display based on type
     */
    private static function formatValue(mixed $value, string $type, array $config): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        
        // Decode HTML entities if they were escaped
        if (is_string($value) && (str_contains($value, '&lt;') || str_contains($value, '&gt;'))) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        // Auto-detect type for untyped fields
        if ($type === 'auto') {
            if (is_string($value) && (str_contains($value, '<p>') || str_contains($value, '<div>'))) {
                $type = 'html';
            } elseif (is_numeric($value)) {
                $type = 'number';
            } elseif (is_bool($value)) {
                $type = 'boolean';
            } else {
                $type = 'text';
            }
        }
        
        return match ($type) {
            'wysiwyg', 'html', 'rich_text', 'textarea_html' => (string) $value,
            'markdown' => self::parseMarkdown((string) $value),
            'textarea', 'text', 'string' => nl2br(htmlspecialchars((string) $value)),
            'number', 'integer', 'float' => (string) $value,
            'boolean' => $value ? '<span class="text-green-600">Yes</span>' : '<span class="text-red-600">No</span>',
            'date' => date('F j, Y', strtotime((string) $value)),
            'datetime' => date('F j, Y g:i A', strtotime((string) $value)),
            'email' => '<a href="mailto:' . htmlspecialchars((string) $value) . '" class="text-blue-600 hover:underline">' . htmlspecialchars((string) $value) . '</a>',
            'url', 'link' => '<a href="' . htmlspecialchars((string) $value) . '" target="_blank" class="text-blue-600 hover:underline">' . htmlspecialchars((string) $value) . '</a>',
            'image' => '<img src="' . htmlspecialchars((string) $value) . '" alt="" class="max-w-full h-auto rounded">',
            'select', 'radios' => htmlspecialchars((string) $value),
            'checkboxes' => is_array($value) ? implode(', ', array_map('htmlspecialchars', $value)) : htmlspecialchars((string) $value),
            default => is_array($value) ? '<pre class="text-sm">' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT)) . '</pre>' : htmlspecialchars((string) $value),
        };
    }
    
    /**
     * Basic markdown parser
     */
    private static function parseMarkdown(string $text): string
    {
        // Headers
        $text = preg_replace('/^### (.+)$/m', '<h3 class="text-lg font-semibold mt-4 mb-2">$1</h3>', $text);
        $text = preg_replace('/^## (.+)$/m', '<h2 class="text-xl font-semibold mt-6 mb-3">$1</h2>', $text);
        $text = preg_replace('/^# (.+)$/m', '<h1 class="text-2xl font-bold mt-8 mb-4">$1</h1>', $text);
        
        // Bold and italic
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        
        // Links
        $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2" class="text-blue-600 hover:underline">$1</a>', $text);
        
        // Paragraphs
        $text = '<p>' . str_replace("\n\n", '</p><p class="mb-4">', $text) . '</p>';
        $text = str_replace("\n", '<br>', $text);
        
        return $text;
    }
    
    /**
     * Get item property (title, slug, etc.)
     */
    public function get(string $property): mixed
    {
        return $this->item[$property] ?? null;
    }
    
    /**
     * Get content type info
     */
    public function getContentType(): array
    {
        return $this->contentType;
    }
}
