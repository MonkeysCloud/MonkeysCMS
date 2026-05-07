<?php

declare(strict_types=1);

namespace App\Cms\Block;

use MonkeysLegion\Template\Renderer;

/**
 * BlockTemplateRenderer — Resolves and renders block templates from the active theme.
 *
 * Follows Atomic Design: atoms → molecules → organisms.
 * The atomic level is determined by **where the template file exists**,
 * NOT by a hardcoded mapping. This supports dynamic/custom block types.
 *
 * Template resolution order (first match wins):
 *   1. {activeTheme}::blocks.atoms.{type}
 *   2. {activeTheme}::blocks.molecules.{type}
 *   3. {activeTheme}::blocks.organisms.{type}
 *   4. {activeTheme}::blocks.{type}              (flat fallback)
 *   5. front::blocks.atoms.{type}                (core theme)
 *   6. front::blocks.molecules.{type}            (core theme)
 *   7. front::blocks.organisms.{type}            (core theme)
 *   8. front::blocks.{type}                      (core theme flat)
 *   9. null → triggers PHP render() fallback
 *
 * Block templates receive: $data, $settings, $blockType, $blockId
 */
final class BlockTemplateRenderer
{
    /** @var array<string, string|false> Cached resolved template names (false = not found) */
    private array $templateCache = [];

    /** Atomic levels to search, in priority order */
    private const LEVELS = ['atoms', 'molecules', 'organisms'];

    public function __construct(
        private readonly Renderer $renderer,
        private readonly string $activeTheme = 'front',
    ) {}

    /**
     * Render a block using theme templates.
     *
     * Returns the rendered HTML or null if no template was found
     * (caller should fall back to PHP render()).
     */
    public function render(string $blockType, array $data, array $settings = [], string $blockId = ''): ?string
    {
        $templateName = $this->resolveTemplate($blockType);

        if ($templateName === null) {
            return null;
        }

        try {
            return $this->renderer->render($templateName, [
                'data'      => $data,
                'settings'  => $settings,
                'blockType' => $blockType,
                'blockId'   => $blockId,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve the template name for a block type.
     *
     * Searches the filesystem: active theme (atoms → molecules → organisms → flat)
     * then core theme (atoms → molecules → organisms → flat).
     * The atomic level is determined dynamically by where the file exists.
     */
    private function resolveTemplate(string $blockType): ?string
    {
        if (isset($this->templateCache[$blockType])) {
            return $this->templateCache[$blockType] ?: null;
        }

        $candidates = $this->buildCandidates($blockType);

        foreach ($candidates as $candidate) {
            if ($this->templateExists($candidate)) {
                $this->templateCache[$blockType] = $candidate;
                return $candidate;
            }
        }

        // No template found
        $this->templateCache[$blockType] = false;
        return null;
    }

    /**
     * Build the ordered list of template candidates to try.
     */
    private function buildCandidates(string $blockType): array
    {
        $candidates = [];

        // Active theme (if not already core/front)
        if ($this->activeTheme !== 'front') {
            foreach (self::LEVELS as $level) {
                $candidates[] = $this->activeTheme . '::blocks.' . $level . '.' . $blockType;
            }
            $candidates[] = $this->activeTheme . '::blocks.' . $blockType;
        }

        // Core theme (no namespace, as frontend paths are added to the root loader)
        foreach (self::LEVELS as $level) {
            $candidates[] = 'blocks.' . $level . '.' . $blockType;
        }
        $candidates[] = 'blocks.' . $blockType;

        // Legacy flat path (no namespace — backward compat)
        $candidates[] = 'blocks.' . $blockType;

        return $candidates;
    }

    /**
     * Check if a template exists by attempting a render with empty data.
     */
    private function templateExists(string $templateName): bool
    {
        try {
            $this->renderer->render($templateName, [
                'data'      => [],
                'settings'  => [],
                'blockType' => '',
                'blockId'   => '',
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Invalidate the template cache (e.g., when switching themes).
     */
    public function invalidateCache(): void
    {
        $this->templateCache = [];
    }
}
