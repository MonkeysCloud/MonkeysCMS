<?php

declare(strict_types=1);

namespace App\Cms\Blocks;

use App\Cms\Blocks\Types\BlockTypeInterface;
use App\Modules\Core\Entities\Block;
use App\Modules\Core\Entities\User;
use MonkeysLegion\Database\Connection;
use MonkeysLegion\Cache\CacheManager;
use MonkeysLegion\Template\Renderer;

/**
 * BlockRenderer - Renders blocks to HTML
 * 
 * Handles:
 * - Block visibility checks (path, user/role)
 * - Caching of rendered output
 * - Asset collection (CSS/JS)
 * - Region-based rendering
 * 
 * @example
 * ```php
 * // Render a single block
 * $html = $renderer->render($block, ['current_path' => '/about']);
 * 
 * // Render all blocks in a region
 * $html = $renderer->renderRegion('sidebar', ['user' => $currentUser]);
 * 
 * // Get required assets
 * $css = $renderer->getRequiredCss();
 * $js = $renderer->getRequiredJs();
 * ```
 */
final class BlockRenderer
{
    private const CACHE_PREFIX = 'block:rendered:';
    private const CACHE_TTL = 3600;

    /** @var array<string> Collected CSS assets */
    private array $cssAssets = [];

    /** @var array<string> Collected JS assets */
    private array $jsAssets = [];

    /** @var array<int, string> Rendered block cache (in-memory) */
    private array $rendered = [];

    public function __construct(
        private readonly BlockManager $blockManager,
        private readonly Connection $connection,
        private readonly ?CacheManager $cache = null,
        private readonly ?Renderer $templateRenderer = null,
    ) {}

    /**
     * Render a single block
     */
    public function render(Block $block, array $context = []): string
    {
        // Check visibility
        if (!$this->isVisible($block, $context)) {
            return '';
        }

        // Check in-memory cache
        $cacheKey = $this->getCacheKey($block, $context);
        if (isset($this->rendered[$block->id])) {
            return $this->rendered[$block->id];
        }

        // Check persistent cache
        if ($this->cache) {
            $cached = $this->cache->store()->get($cacheKey);
            if ($cached !== null) {
                $this->rendered[$block->id] = $cached;
                return $cached;
            }
        }

        // Render the block
        $html = $this->doRender($block, $context);

        // Wrap with container
        $html = $this->wrapBlock($block, $html);

        // Cache the result
        $this->rendered[$block->id] = $html;
        if ($this->cache) {
            $ttl = $this->getCacheTtl($block);
            if ($ttl !== 0) {
                $this->cache->store()->set($cacheKey, $html, $ttl);
            }
        }

        return $html;
    }

    /**
     * Render all blocks in a region
     */
    public function renderRegion(string $region, array $context = []): string
    {
        $blocks = $this->getBlocksForRegion($region, $context['theme'] ?? null);
        
        if (empty($blocks)) {
            return '';
        }

        $html = '';
        foreach ($blocks as $block) {
            $html .= $this->render($block, $context);
        }

        if (!$html) {
            return '';
        }

        return sprintf(
            '<div class="region region--%s" data-region="%s">%s</div>',
            htmlspecialchars($region),
            htmlspecialchars($region),
            $html
        );
    }

    /**
     * Render multiple regions
     */
    public function renderRegions(array $regions, array $context = []): array
    {
        $output = [];
        foreach ($regions as $region) {
            $output[$region] = $this->renderRegion($region, $context);
        }
        return $output;
    }

    /**
     * Render a block by ID
     */
    public function renderById(int $blockId, array $context = []): string
    {
        $block = $this->getBlock($blockId);
        if (!$block) {
            return '';
        }
        return $this->render($block, $context);
    }

    /**
     * Render a block by machine name
     */
    public function renderByName(string $machineName, array $context = []): string
    {
        $block = $this->getBlockByName($machineName);
        if (!$block) {
            return '';
        }
        return $this->render($block, $context);
    }

    /**
     * Get all collected CSS assets
     */
    public function getRequiredCss(): array
    {
        return array_unique($this->cssAssets);
    }

    /**
     * Get all collected JS assets
     */
    public function getRequiredJs(): array
    {
        return array_unique($this->jsAssets);
    }

    /**
     * Clear rendered cache
     */
    public function clearCache(?int $blockId = null): void
    {
        if ($blockId !== null) {
            unset($this->rendered[$blockId]);
            if ($this->cache) {
                // Clear all cache variants for this block
                $this->cache->store()->delete(self::CACHE_PREFIX . $blockId);
            }
        } else {
            $this->rendered = [];
            if ($this->cache) {
                try {
                    $this->cache->tags(['blocks'])->clear();
                } catch (\Exception) {
                    // Tags not supported
                }
            }
        }
    }

    /**
     * Perform the actual rendering
     */
    private function doRender(Block $block, array $context): string
    {
        $typeId = $block->block_type;

        // Collect assets for this block type
        $this->collectAssets($typeId);

        // Try to render via BlockManager (handles both code and database types)
        $html = $this->blockManager->renderBlock($block, $context);

        // If no content from block type, try view template
        if (empty($html) && $block->view_template && $this->templateRenderer) {
            try {
                $html = $this->templateRenderer->render($block->view_template, [
                    'block' => $block,
                    'context' => $context,
                ]);
            } catch (\Exception $e) {
                $html = '<!-- Block template error: ' . htmlspecialchars($e->getMessage()) . ' -->';
            }
        }

        // Fallback to body content
        if (empty($html)) {
            $html = $block->getRenderedBody();
        }

        return $html;
    }

    /**
     * Wrap block with container markup
     */
    private function wrapBlock(Block $block, string $content): string
    {
        if (empty($content)) {
            return '';
        }

        $classes = ['block', 'block--' . $block->block_type];
        if ($block->css_class) {
            $classes[] = $block->css_class;
        }

        $id = $block->css_id ?: 'block-' . $block->id;
        $title = '';

        if ($block->show_title && $block->title) {
            $title = sprintf(
                '<h2 class="block__title">%s</h2>',
                htmlspecialchars($block->title)
            );
        }

        return sprintf(
            '<div id="%s" class="%s" data-block-id="%d" data-block-type="%s">%s<div class="block__content">%s</div></div>',
            htmlspecialchars($id),
            htmlspecialchars(implode(' ', $classes)),
            $block->id,
            htmlspecialchars($block->block_type),
            $title,
            $content
        );
    }

    /**
     * Check if block is visible
     */
    private function isVisible(Block $block, array $context): bool
    {
        // Check published status
        if (!$block->is_published) {
            return false;
        }

        // Check path visibility
        $currentPath = $context['current_path'] ?? '/';
        if (!$block->isVisibleOnPath($currentPath)) {
            return false;
        }

        // Check user/role visibility
        $user = $context['user'] ?? null;
        if (!$block->isVisibleForUser($user)) {
            return false;
        }

        return true;
    }

    /**
     * Collect CSS/JS assets for a block type
     */
    private function collectAssets(string $typeId): void
    {
        $type = $this->blockManager->getType($typeId);
        if (!$type) {
            return;
        }

        // Code-defined types
        if ($type['source'] === 'code' && isset($type['instance'])) {
            $instance = $type['instance'];
            $this->cssAssets = array_merge($this->cssAssets, $instance::getCssAssets());
            $this->jsAssets = array_merge($this->jsAssets, $instance::getJsAssets());
        }

        // Database-defined types
        if ($type['source'] === 'database' && isset($type['entity'])) {
            $entity = $type['entity'];
            $this->cssAssets = array_merge($this->cssAssets, $entity->css_assets ?? []);
            $this->jsAssets = array_merge($this->jsAssets, $entity->js_assets ?? []);
        }
    }

    /**
     * Get blocks for a specific region
     */
    private function getBlocksForRegion(string $region, ?string $theme = null): array
    {
        $sql = "SELECT * FROM blocks WHERE region = :region AND is_published = 1";
        $params = ['region' => $region];

        if ($theme) {
            $sql .= " AND (theme = :theme OR theme IS NULL)";
            $params['theme'] = $theme;
        }

        $sql .= " ORDER BY weight ASC, id ASC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        $blocks = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $block = new Block();
            $block->hydrate($row);
            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Get a block by ID
     */
    private function getBlock(int $id): ?Block
    {
        $stmt = $this->connection->prepare("SELECT * FROM blocks WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $block = new Block();
        $block->hydrate($row);
        return $block;
    }

    /**
     * Get a block by machine name
     */
    private function getBlockByName(string $machineName): ?Block
    {
        $stmt = $this->connection->prepare("SELECT * FROM blocks WHERE machine_name = :name");
        $stmt->execute(['name' => $machineName]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $block = new Block();
        $block->hydrate($row);
        return $block;
    }

    /**
     * Generate cache key for a block
     */
    private function getCacheKey(Block $block, array $context): string
    {
        $parts = [
            self::CACHE_PREFIX . $block->id,
            $context['current_path'] ?? 'global',
            $context['user']?->id ?? 'anon',
            $context['language'] ?? 'en',
        ];
        return implode(':', $parts);
    }

    /**
     * Get cache TTL for a block
     */
    private function getCacheTtl(Block $block): int
    {
        // Get from block type if available
        $type = $this->blockManager->getType($block->block_type);
        if ($type) {
            if ($type['source'] === 'code' && isset($type['instance'])) {
                return $type['instance']->getCacheTtl();
            }
            if ($type['source'] === 'database' && isset($type['entity'])) {
                return $type['entity']->cache_ttl;
            }
        }

        // Default TTL from block settings
        return $block->getSetting('cache_ttl', self::CACHE_TTL);
    }

    /**
     * Preview a block (without caching)
     */
    public function preview(Block $block, array $context = []): string
    {
        return $this->wrapBlock($block, $this->doRender($block, $context));
    }

    /**
     * Render a block type preview (empty block of that type)
     */
    public function previewType(string $typeId): string
    {
        $type = $this->blockManager->getType($typeId);
        if (!$type) {
            return '<div class="block-preview block-preview--unknown">Unknown block type</div>';
        }

        $html = '<div class="block-preview block-preview--' . htmlspecialchars($typeId) . '">';
        $html .= '<div class="block-preview__header">';
        $html .= '<span class="block-preview__icon">' . ($type['icon'] ?? '🧱') . '</span>';
        $html .= '<span class="block-preview__label">' . htmlspecialchars($type['label']) . '</span>';
        $html .= '</div>';
        
        if (!empty($type['description'])) {
            $html .= '<div class="block-preview__description">' . htmlspecialchars($type['description']) . '</div>';
        }

        if (!empty($type['fields'])) {
            $html .= '<div class="block-preview__fields">';
            $html .= '<strong>Fields:</strong> ';
            $fieldNames = array_map(fn($f) => $f['label'] ?? $f, $type['fields']);
            $html .= implode(', ', array_slice($fieldNames, 0, 5));
            if (count($fieldNames) > 5) {
                $html .= ' (+' . (count($fieldNames) - 5) . ' more)';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
