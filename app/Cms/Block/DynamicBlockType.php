<?php

declare(strict_types=1);

namespace App\Cms\Block;

use MonkeysLegion\Template\Renderer;

/**
 * DynamicBlockType — Wraps a database-defined block type.
 *
 * Unlike code-defined block types (PHP classes implementing BlockTypeInterface),
 * dynamic blocks are defined in the `block_types` table. Their fields are stored
 * as JSON and their rendering uses the full .ml.php template engine.
 *
 * Templates support all directives: @if, @foreach, @include, {{ }}, {!! !!}, etc.
 * Falls back to basic {{ field }} interpolation when Renderer is not available.
 *
 * PHP 8.4 property hooks for computed metadata.
 */
final class DynamicBlockType implements BlockTypeInterface
{
    private ?Renderer $renderer = null;

    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $description,
        private readonly string $icon,
        private readonly string $category,
        private readonly array  $fields,
        private readonly ?string $template = null,
    ) {}

    public static function getId(): string { return ''; } // Instance method below
    public static function getLabel(): string { return ''; }
    public static function getDescription(): string { return ''; }
    public static function getIcon(): string { return ''; }
    public static function getCategory(): string { return ''; }
    public static function getFields(): array { return []; }

    // ── Instance accessors (used by BlockTypeRegistry) ──────────────────

    /** @return string Block type identifier */
    public string $typeId {
        get => $this->id;
    }

    /** @return string Human-readable label */
    public string $typeLabel {
        get => $this->label;
    }

    /** @return string Description text */
    public string $typeDescription {
        get => $this->description;
    }

    /** @return string Lucide icon name */
    public string $typeIcon {
        get => $this->icon;
    }

    /** @return string Block category */
    public string $typeCategory {
        get => $this->category;
    }

    /** @return array Field definitions */
    public array $typeFields {
        get => $this->fields;
    }

    /**
     * Set the template engine renderer for full .ml.php support.
     */
    public function setRenderer(Renderer $renderer): void
    {
        $this->renderer = $renderer;
    }

    /**
     * Render the block to HTML.
     *
     * If a Renderer is available and a template is defined, uses the full
     * .ml.php template engine (supporting @if, @foreach, {{ }}, {!! !!}, etc.).
     * Otherwise falls back to basic interpolation or generic rendering.
     */
    public function render(array $data, array $settings = []): string
    {
        if ($this->template) {
            return $this->renderTemplate($data, $settings);
        }

        // Generic fallback: render each field's data
        return $this->renderGeneric($data);
    }

    /**
     * Create a DynamicBlockType from a database row.
     *
     * @param array{type_id: string, label: string, description: string, icon: string, category: string, fields: string, template: ?string} $row
     */
    public static function fromRow(array $row): self
    {
        $fields = is_string($row['fields'] ?? null)
            ? (json_decode($row['fields'], true) ?: [])
            : ($row['fields'] ?? []);

        // Sort fields by weight to counteract MySQL JSON column key sorting
        uasort($fields, function ($a, $b) {
            $weightA = $a['weight'] ?? 0;
            $weightB = $b['weight'] ?? 0;
            return $weightA <=> $weightB;
        });

        return new self(
            id: $row['type_id'],
            label: $row['label'] ?? $row['type_id'],
            description: $row['description'] ?? '',
            icon: $row['icon'] ?? 'puzzle',
            category: $row['category'] ?? 'Custom',
            fields: $fields,
            template: $row['template'] ?? null,
        );
    }

    // ── Private ─────────────────────────────────────────────────────────

    /**
     * Render using the stored template string.
     *
     * When a Renderer is available, uses renderString() which supports the full
     * .ml.php directive set (@if, @foreach, @include, {{ $var }}, {!! $html !!}).
     * Otherwise falls back to basic {{field}} interpolation.
     */
    private function renderTemplate(array $data, array $settings): string
    {
        // Full template engine rendering via Renderer::renderString()
        if ($this->renderer) {
            try {
                // Make all block data, settings, and metadata available as template variables
                $templateData = array_merge($data, [
                    'settings'   => $settings,
                    'blockType'  => $this->id,
                    'blockLabel' => $this->label,
                    'fields'     => $this->fields,
                ]);

                $html = $this->renderer->renderString($this->template, $templateData);

                return '<div class="block-dynamic block-' . htmlspecialchars($this->id) . '">' . $html . '</div>';
            } catch (\Throwable) {
                // Fall through to basic interpolation on error
            }
        }

        // Fallback: basic interpolation
        $html = $this->template;

        // Replace {{ $field }} and {{ field }} placeholders with escaped data values
        $html = preg_replace_callback('/\{\{\s*\$?(\w+)\s*\}\}/', function ($m) use ($data, $settings) {
            $key = $m[1];
            $value = $data[$key] ?? $settings[$key] ?? '';
            return is_string($value) ? htmlspecialchars($value) : (string) $value;
        }, $html);

        // Replace {!! $field !!} and {!! field !!} for raw HTML output
        $html = preg_replace_callback('/\{!!\s*\$?(\w+)\s*!!\}/', function ($m) use ($data, $settings) {
            return (string) ($data[$m[1]] ?? $settings[$m[1]] ?? '');
        }, $html);

        return '<div class="block-dynamic block-' . htmlspecialchars($this->id) . '">' . $html . '</div>';
    }

    /**
     * Generic rendering: outputs each non-empty field value.
     */
    private function renderGeneric(array $data): string
    {
        if (empty($data)) {
            return '<!-- empty block: ' . htmlspecialchars($this->id) . ' -->';
        }

        $html = '<div class="block-dynamic block-' . htmlspecialchars($this->id) . '">';

        foreach ($this->fields as $fieldName => $fieldDef) {
            $value = $data[$fieldName] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $type = $fieldDef['type'] ?? 'string';
            $html .= match ($type) {
                'text', 'html' => '<div class="block-dynamic__field">' . $value . '</div>',
                'media'        => '<img src="/uploads/' . (int) $value . '" alt="" loading="lazy" class="block-dynamic__img">',
                'url'          => '<a href="' . htmlspecialchars((string) $value) . '">' . htmlspecialchars((string) $value) . '</a>',
                default        => '<div class="block-dynamic__field">' . htmlspecialchars((string) $value) . '</div>',
            };
        }

        $html .= '</div>';

        return $html;
    }
}
