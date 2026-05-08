<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\RenderContext;
use App\Cms\Field\RenderMode;

/**
 * AbstractWidget — Base class for field widget renderers.
 *
 * Each widget knows how to render its admin form input HTML.
 * Widgets are used across the entire CMS: content forms, taxonomy forms,
 * Mosaic block inspector, and any custom module that uses fields.
 *
 * To support multiple rendering contexts, override:
 *   - render()        → Standard admin form rendering (backward compatible)
 *   - renderMosaic()  → Mosaic visual editor sidebar rendering
 */
abstract class AbstractWidget
{
    /**
     * Render the widget HTML for the admin form.
     *
     * @param FieldDefinition $field  The field definition
     * @param mixed           $value  Current field value (or null)
     * @param string          $namePrefix  Form name prefix (e.g. "fields")
     */
    abstract public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string;

    /**
     * Widget type identifier.
     */
    abstract public static function type(): string;

    // ── Multi-Context Rendering ──────────────────────────────────────

    /**
     * Render the widget for a specific context (admin form, Mosaic, API).
     *
     * Override renderMosaic() in subclasses for Mosaic-specific output.
     * Falls back to render() for unrecognized modes.
     */
    public function renderForContext(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        return match ($ctx->mode) {
            RenderMode::MOSAIC_INSPECTOR => $this->renderMosaic($field, $value, $ctx),
            default => $this->render($field, $value, $ctx->fieldPrefix),
        };
    }

    /**
     * Render the widget for the Mosaic visual editor inspector sidebar.
     *
     * Default implementation: generic text input with JS callback.
     * Override in each widget for rich, type-specific rendering.
     */
    protected function renderMosaic(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        $escapedValue = htmlspecialchars((string) ($value ?? $field->default_value ?? ''), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($field->name, ENT_QUOTES, 'UTF-8');
        $cb = $ctx->mosaicCallback($field->machine_name);

        $html = '<div class="mosaic-field">';
        $html .= '<label class="mosaic-field__label">' . $label . '</label>';
        $html .= '<input type="text" class="mosaic-field__input" value="' . $escapedValue . '" oninput="' . htmlspecialchars($cb, ENT_QUOTES) . '">';

        if ($field->description) {
            $html .= '<p class="mosaic-field__desc">' . htmlspecialchars($field->description, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    // ── Mosaic Helper ────────────────────────────────────────────────

    /**
     * Escape a string for safe HTML attribute/content output.
     */
    protected function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    // ── Shared Helpers (admin form) ──────────────────────────────────

    /**
     * Build the form field name attribute.
     */
    protected function fieldName(FieldDefinition $field, string $prefix = 'fields'): string
    {
        return $prefix . '[' . htmlspecialchars($field->machine_name) . ']';
    }

    /**
     * Build the form field ID attribute.
     */
    protected function fieldId(FieldDefinition $field): string
    {
        return 'field-' . htmlspecialchars($field->machine_name);
    }

    /**
     * Render the required asterisk indicator.
     */
    protected function requiredMark(FieldDefinition $field): string
    {
        return $field->required ? ' <span class="text-danger">*</span>' : '';
    }

    /**
     * Render common input attributes.
     */
    protected function commonAttrs(FieldDefinition $field): string
    {
        $attrs = 'id="' . $this->fieldId($field) . '"';
        if ($field->required) {
            $attrs .= ' required';
        }
        return $attrs;
    }

    /**
     * Wrap a widget in a form-group with label.
     */
    protected function wrapGroup(FieldDefinition $field, string $inputHtml): string
    {
        $html = '<div class="form-group">';
        $html .= '<label class="form-label" for="' . $this->fieldId($field) . '">';
        $html .= htmlspecialchars($field->name) . $this->requiredMark($field);
        $html .= '</label>';
        $html .= $inputHtml;

        if ($field->help_text) {
            $html .= '<p class="form-help">' . htmlspecialchars($field->help_text) . '</p>';
        }

        $html .= '</div>';
        return $html;
    }
}
