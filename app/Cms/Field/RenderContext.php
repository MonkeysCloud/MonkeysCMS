<?php

declare(strict_types=1);

namespace App\Cms\Field;

/**
 * RenderContext — Carries rendering metadata for field widgets.
 *
 * This value object is passed to widget renderers so they know
 * which rendering mode to use and have access to context-specific
 * data (Mosaic indices, enabled languages, available block types, etc.).
 *
 * Usage:
 *   $ctx = new RenderContext(
 *       mode: RenderMode::MOSAIC_INSPECTOR,
 *       sectionIdx: 0, regionName: 'main', blockIdx: 2,
 *   );
 *   $widget->renderForContext($field, $value, $ctx);
 */
final readonly class RenderContext
{
    /**
     * @param RenderMode   $mode         Rendering target
     * @param string       $fieldPrefix  Form name prefix (e.g., "fields", "settings")
     * @param int|null     $sectionIdx   Mosaic section index
     * @param string|null  $regionName   Mosaic region name
     * @param int|null     $blockIdx     Mosaic block index within region
     * @param array        $languages    Enabled LanguageEntity objects for i18n tabs
     * @param string|null  $defaultLang  Default language code
     * @param array        $blockTypes   Available block types (for slot fields)
     * @param string|null  $repeaterField  Parent repeater field name (for sub-fields)
     * @param int|null     $repeaterIndex  Parent repeater item index (for sub-fields)
     * @param array        $extra        Extensibility bag for custom module data
     */
    public function __construct(
        public RenderMode $mode = RenderMode::ADMIN_FORM,
        public string $fieldPrefix = 'fields',
        public ?int $sectionIdx = null,
        public ?string $regionName = null,
        public ?int $blockIdx = null,
        public array $languages = [],
        public ?string $defaultLang = null,
        public array $blockTypes = [],
        public ?string $repeaterField = null,
        public ?int $repeaterIndex = null,
        public array $extra = [],
    ) {}

    /**
     * Check if we're rendering for the Mosaic inspector.
     */
    public function isMosaic(): bool
    {
        return $this->mode === RenderMode::MOSAIC_INSPECTOR;
    }

    /**
     * Check if multilingual tabs should be shown.
     */
    public function isMultilingual(): bool
    {
        return count($this->languages) > 1;
    }

    /**
     * Build the JS callback string for updating a Mosaic block field.
     */
    public function mosaicCallback(string $fieldName, string $valueExpr = 'this.value'): string
    {
        if ($this->repeaterField !== null) {
            return "MosaicEditor.updateRepeaterField("
                . "{$this->sectionIdx}, '{$this->regionName}', {$this->blockIdx}, "
                . "'{$this->repeaterField}', {$this->repeaterIndex}, '{$fieldName}', {$valueExpr})";
        }

        return "MosaicEditor.updateBlockField("
            . "{$this->sectionIdx}, '{$this->regionName}', {$this->blockIdx}, "
            . "'{$fieldName}', {$valueExpr})";
    }

    /**
     * Build the JS callback for compound field updates.
     */
    public function mosaicCompoundCallback(string $fieldName, string $subKey, string $valueExpr = 'this.value'): string
    {
        return "MosaicEditor.updateBlockCompoundField("
            . "{$this->sectionIdx}, '{$this->regionName}', {$this->blockIdx}, "
            . "'{$fieldName}', '{$subKey}', {$valueExpr})";
    }

    /**
     * Create a sub-context for repeater items.
     */
    public function forRepeaterItem(string $repeaterField, int $itemIndex): self
    {
        return new self(
            mode: $this->mode,
            fieldPrefix: $this->fieldPrefix,
            sectionIdx: $this->sectionIdx,
            regionName: $this->regionName,
            blockIdx: $this->blockIdx,
            languages: $this->languages,
            defaultLang: $this->defaultLang,
            blockTypes: $this->blockTypes,
            repeaterField: $repeaterField,
            repeaterIndex: $itemIndex,
            extra: $this->extra,
        );
    }
}
