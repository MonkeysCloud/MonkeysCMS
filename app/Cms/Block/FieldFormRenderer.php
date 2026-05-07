<?php

declare(strict_types=1);

namespace App\Cms\Block;

use App\Cms\Field\MlcFieldAdapter;
use App\Cms\Field\RenderContext;
use App\Cms\Field\RenderMode;
use App\Cms\Field\Widget\WidgetRegistry;
use App\Cms\I18n\LanguageService;

/**
 * FieldFormRenderer — Renders Mosaic inspector form HTML for any block type.
 *
 * Uses the GLOBAL WidgetRegistry to render fields, ensuring consistent
 * field rendering across content types, taxonomy, Mosaic blocks, and
 * any custom module subsystem.
 *
 * MLC block field definitions are converted to FieldDefinition objects
 * via MlcFieldAdapter, then rendered by the WidgetRegistry's widgets
 * in MOSAIC_INSPECTOR mode.
 *
 * Also handles:
 *   - Translation language tabs for translatable fields
 *   - Block-level settings (CSS class, spacing)
 *   - Delete block button
 */
final class FieldFormRenderer
{
    public function __construct(
        private readonly WidgetRegistry $widgetRegistry,
        private readonly ?LanguageService $languageService = null,
    ) {}

    /**
     * Render the full inspector form for a block.
     *
     * @param array  $blockMeta   Block type metadata (id, label, fields, etc.)
     * @param array  $data        Current block field values
     * @param array  $settings    Current block settings
     * @param int    $sIdx        Section index in the layout
     * @param string $region      Region name within the section
     * @param int    $bIdx        Block index within the region
     * @param array  $blockTypes  Available block types (for slot fields)
     * @return string HTML
     */
    public function render(
        array $blockMeta,
        array $data,
        array $settings,
        int $sIdx,
        string $region,
        int $bIdx,
        array $blockTypes = [],
    ): string {
        $fields = $blockMeta['fields'] ?? [];
        $html = '';

        // Detect multilingual
        $languages = [];
        $defaultLang = 'en';
        if ($this->languageService?->isEnabled()) {
            $languages = $this->languageService->getEnabled();
            $defaultLang = $this->languageService->getDefaultCode();
        }

        // Build the rendering context for the global widget system
        $ctx = new RenderContext(
            mode: RenderMode::MOSAIC_INSPECTOR,
            sectionIdx: $sIdx,
            regionName: $region,
            blockIdx: $bIdx,
            languages: $languages,
            defaultLang: $defaultLang,
            blockTypes: $blockTypes,
        );

        foreach ($fields as $fieldName => $fieldDef) {
            $value = $data[$fieldName] ?? null;
            if ($value === null || $value === '') {
                $value = $fieldDef['default'] ?? '';
            }

            $isTranslatable = !empty($fieldDef['translatable']) && count($languages) > 1;

            if ($isTranslatable) {
                $html .= $this->renderTranslatableTabs($fieldName, $fieldDef, $data, $ctx);
            } else {
                // Convert MLC field def to FieldDefinition and render via global widget
                $definition = MlcFieldAdapter::fromArray($fieldName, $fieldDef);
                $html .= $this->widgetRegistry->renderFieldForContext($definition, $value, $ctx);
            }
        }

        // Block-level settings: CSS class
        $html .= '<div class="mosaic-field" style="margin-top:1rem;padding-top:.75rem;border-top:1px solid rgba(255,255,255,.06)">';
        $html .= '<label class="mosaic-field__label">CSS Class</label>';
        $cssClass = htmlspecialchars($settings['css_class'] ?? '', ENT_QUOTES, 'UTF-8');
        $html .= '<input type="text" class="mosaic-field__input" value="' . $cssClass . '" placeholder="Optional CSS class" onchange="MosaicEditor.updateBlockSetting(' . $sIdx . ', \'' . $region . '\', ' . $bIdx . ', \'css_class\', this.value)">';
        $html .= '</div>';

        // Delete button
        $html .= '<button class="me-right__delete-btn" onclick="MosaicEditor.removeBlock(' . $sIdx . ', \'' . $region . '\', ' . $bIdx . ')">Delete Block</button>';

        return $html;
    }

    /**
     * Render a translatable field with language tabs.
     */
    private function renderTranslatableTabs(
        string $fieldName,
        array $fieldDef,
        array $allData,
        RenderContext $ctx,
    ): string {
        $languages = $ctx->languages;
        $defaultLang = $ctx->defaultLang ?? 'en';
        $tabId = "tab-{$ctx->sectionIdx}-{$ctx->blockIdx}-{$fieldName}";

        $html = '<div class="mosaic-field mosaic-field--translatable">';
        $html .= '<label class="mosaic-field__label">' . htmlspecialchars($fieldDef['label'] ?? ucfirst($fieldName), ENT_QUOTES, 'UTF-8') . '</label>';

        // Language tabs
        $html .= '<div class="mosaic-lang-tabs" id="' . $tabId . '">';
        foreach ($languages as $idx => $lang) {
            $code = $lang->code;
            $active = ($idx === 0) ? ' mosaic-lang-tab--active' : '';
            $html .= '<button type="button" class="mosaic-lang-tab' . $active . '"'
                . ' data-lang="' . htmlspecialchars($code) . '"'
                . ' onclick="MosaicEditor.switchLangTab(\'' . $tabId . '\', \'' . $code . '\')">'
                . htmlspecialchars($code) . '</button>';
        }
        $html .= '</div>';

        // Tab panels (one per language)
        foreach ($languages as $idx => $lang) {
            $code = $lang->code;
            $langField = ($code === $defaultLang) ? $fieldName : "{$fieldName}__{$code}";
            $langValue = $allData[$langField] ?? ($code === $defaultLang ? ($allData[$fieldName] ?? $fieldDef['default'] ?? '') : '');
            $display = ($idx === 0) ? '' : ' style="display:none"';

            $html .= '<div class="mosaic-lang-panel" data-lang="' . htmlspecialchars($code) . '"' . $display . '>';

            // Convert and render via global widget system
            $definition = MlcFieldAdapter::fromArray($langField, $fieldDef);
            $html .= $this->widgetRegistry->renderFieldForContext($definition, $langValue, $ctx);

            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }
}
