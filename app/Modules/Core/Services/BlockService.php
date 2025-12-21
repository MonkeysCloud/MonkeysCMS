<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Cms\Blocks\BlockManager;
use App\Cms\Blocks\BlockRenderer;
use App\Modules\Core\Entities\Block;
use App\Cms\Repository\CmsRepository;
use MonkeysLegion\Cache\CacheManager;

/**
 * BlockService - High-level service for managing blocks
 * 
 * Provides CRUD operations for blocks and integrates with
 * BlockManager (types) and BlockRenderer (rendering).
 */
class BlockService
{
    private const CACHE_PREFIX = 'blocks:';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly CmsRepository $repository,
        private readonly BlockManager $blockManager,
        private readonly BlockRenderer $blockRenderer,
        private readonly ?CacheManager $cache = null,
    ) {}

    // =========================================================================
    // Block CRUD
    // =========================================================================

    /**
     * Create a new block
     */
    public function create(array $data): Block
    {
        $block = new Block();
        $this->hydrateBlock($block, $data);
        $block->prePersist();

        $id = $this->repository->create(Block::class, $block->toArray());
        $block->id = $id;

        $this->invalidateCache();

        return $block;
    }

    /**
     * Update a block
     */
    public function update(int $id, array $data): ?Block
    {
        $block = $this->getById($id);
        if (!$block) {
            return null;
        }

        $this->hydrateBlock($block, $data);
        $block->updated_at = new \DateTimeImmutable();

        $this->repository->update(Block::class, $id, $block->toArray());

        $this->invalidateCache($id);
        $this->blockRenderer->clearCache($id);

        return $block;
    }

    /**
     * Delete a block
     */
    public function delete(int $id): bool
    {
        $result = $this->repository->delete(Block::class, $id);
        
        if ($result) {
            $this->invalidateCache($id);
            $this->blockRenderer->clearCache($id);
        }

        return $result;
    }

    /**
     * Get block by ID
     */
    public function getById(int $id): ?Block
    {
        $data = $this->repository->find(Block::class, $id);
        if (!$data) {
            return null;
        }

        $block = new Block();
        $block->hydrate($data);
        return $block;
    }

    /**
     * Get block by machine name
     */
    public function getByName(string $machineName): ?Block
    {
        $data = $this->repository->findOneBy(Block::class, ['machine_name' => $machineName]);
        if (!$data) {
            return null;
        }

        $block = new Block();
        $block->hydrate($data);
        return $block;
    }

    /**
     * Get all blocks
     */
    public function getAll(array $options = []): array
    {
        $page = $options['page'] ?? 1;
        $perPage = $options['per_page'] ?? 50;
        $sort = $options['sort'] ?? 'weight';
        $direction = $options['direction'] ?? 'ASC';
        $filters = $options['filters'] ?? [];

        $result = $this->repository->paginate(
            Block::class,
            $page,
            $perPage,
            $filters,
            [$sort => $direction]
        );

        $blocks = [];
        foreach ($result['items'] as $data) {
            $block = new Block();
            $block->hydrate($data);
            $blocks[] = $block;
        }

        return [
            'items' => $blocks,
            'total' => $result['total'],
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($result['total'] / $perPage),
        ];
    }

    /**
     * Get blocks by region
     */
    public function getByRegion(string $region, ?string $theme = null): array
    {
        $cacheKey = self::CACHE_PREFIX . "region:{$region}:{$theme}";
        
        if ($this->cache) {
            $cached = $this->cache->store()->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $filters = ['region' => $region, 'is_published' => true];
        if ($theme) {
            $filters['theme'] = $theme;
        }

        $data = $this->repository->findBy(
            Block::class,
            $filters,
            ['weight' => 'ASC']
        );

        $blocks = [];
        foreach ($data as $row) {
            $block = new Block();
            $block->hydrate($row);
            $blocks[] = $block;
        }

        if ($this->cache) {
            $this->cache->store()->set($cacheKey, $blocks, self::CACHE_TTL);
        }

        return $blocks;
    }

    /**
     * Get blocks by type
     */
    public function getByType(string $blockType): array
    {
        $data = $this->repository->findBy(
            Block::class,
            ['block_type' => $blockType],
            ['weight' => 'ASC']
        );

        $blocks = [];
        foreach ($data as $row) {
            $block = new Block();
            $block->hydrate($row);
            $blocks[] = $block;
        }

        return $blocks;
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Render a block
     */
    public function render(Block|int|string $block, array $context = []): string
    {
        if (is_int($block)) {
            $block = $this->getById($block);
        } elseif (is_string($block)) {
            $block = $this->getByName($block);
        }

        if (!$block) {
            return '';
        }

        return $this->blockRenderer->render($block, $context);
    }

    /**
     * Render a region
     */
    public function renderRegion(string $region, array $context = []): string
    {
        return $this->blockRenderer->renderRegion($region, $context);
    }

    /**
     * Render multiple regions
     */
    public function renderRegions(array $regions, array $context = []): array
    {
        return $this->blockRenderer->renderRegions($regions, $context);
    }

    /**
     * Get required CSS assets for rendered blocks
     */
    public function getRequiredCss(): array
    {
        return $this->blockRenderer->getRequiredCss();
    }

    /**
     * Get required JS assets for rendered blocks
     */
    public function getRequiredJs(): array
    {
        return $this->blockRenderer->getRequiredJs();
    }

    // =========================================================================
    // Block Types
    // =========================================================================

    /**
     * Get all available block types
     */
    public function getBlockTypes(): array
    {
        return $this->blockManager->getTypes();
    }

    /**
     * Get block types grouped by category
     */
    public function getBlockTypesGrouped(): array
    {
        return $this->blockManager->getTypesGrouped();
    }

    /**
     * Get a specific block type
     */
    public function getBlockType(string $typeId): ?array
    {
        return $this->blockManager->getType($typeId);
    }

    /**
     * Get fields for a block type
     */
    public function getBlockTypeFields(string $typeId): array
    {
        return $this->blockManager->getFieldsForType($typeId);
    }

    // =========================================================================
    // Placement & Ordering
    // =========================================================================

    /**
     * Move block to a region
     */
    public function moveToRegion(int $blockId, string $region, ?int $weight = null): bool
    {
        $block = $this->getById($blockId);
        if (!$block) {
            return false;
        }

        $updateData = ['region' => $region];
        if ($weight !== null) {
            $updateData['weight'] = $weight;
        }

        $this->update($blockId, $updateData);
        return true;
    }

    /**
     * Reorder blocks within a region
     */
    public function reorderBlocks(array $order): void
    {
        foreach ($order as $index => $blockId) {
            $this->repository->update(Block::class, $blockId, [
                'weight' => $index * 10,
            ]);
        }

        $this->invalidateCache();
    }

    /**
     * Get available regions for the current theme
     */
    public function getRegions(?string $theme = null): array
    {
        // This would integrate with ThemeManager to get regions
        // For now, return common defaults
        return [
            'header' => 'Header',
            'navigation' => 'Navigation',
            'sidebar_left' => 'Left Sidebar',
            'content' => 'Content',
            'sidebar_right' => 'Right Sidebar',
            'footer' => 'Footer',
        ];
    }

    // =========================================================================
    // Visibility
    // =========================================================================

    /**
     * Update block visibility settings
     */
    public function updateVisibility(int $blockId, array $visibility): bool
    {
        $updateData = [];

        if (isset($visibility['pages'])) {
            $updateData['visibility_pages'] = $visibility['pages'];
        }
        if (isset($visibility['mode'])) {
            $updateData['visibility_mode'] = $visibility['mode'];
        }
        if (isset($visibility['roles'])) {
            $updateData['visibility_roles'] = $visibility['roles'];
        }

        if (empty($updateData)) {
            return false;
        }

        return $this->update($blockId, $updateData) !== null;
    }

    /**
     * Toggle block published status
     */
    public function togglePublished(int $blockId): bool
    {
        $block = $this->getById($blockId);
        if (!$block) {
            return false;
        }

        return $this->update($blockId, ['is_published' => !$block->is_published]) !== null;
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    private function hydrateBlock(Block $block, array $data): void
    {
        if (isset($data['admin_title'])) $block->admin_title = $data['admin_title'];
        if (isset($data['machine_name'])) $block->machine_name = $data['machine_name'];
        if (isset($data['title'])) $block->title = $data['title'];
        if (isset($data['show_title'])) $block->show_title = (bool) $data['show_title'];
        if (isset($data['block_type'])) $block->block_type = $data['block_type'];
        if (isset($data['body'])) $block->body = $data['body'];
        if (isset($data['body_format'])) $block->body_format = $data['body_format'];
        if (isset($data['view_template'])) $block->view_template = $data['view_template'];
        if (isset($data['region'])) $block->region = $data['region'];
        if (isset($data['theme'])) $block->theme = $data['theme'];
        if (isset($data['weight'])) $block->weight = (int) $data['weight'];
        if (isset($data['is_published'])) $block->is_published = (bool) $data['is_published'];
        if (isset($data['visibility_pages'])) $block->visibility_pages = $data['visibility_pages'];
        if (isset($data['visibility_mode'])) $block->visibility_mode = $data['visibility_mode'];
        if (isset($data['visibility_roles'])) $block->visibility_roles = $data['visibility_roles'];
        if (isset($data['settings'])) $block->settings = $data['settings'];
        if (isset($data['css_class'])) $block->css_class = $data['css_class'];
        if (isset($data['css_id'])) $block->css_id = $data['css_id'];
        if (isset($data['author_id'])) $block->author_id = $data['author_id'];
    }

    private function invalidateCache(?int $blockId = null): void
    {
        if (!$this->cache) {
            return;
        }

        if ($blockId !== null) {
            $this->cache->store()->delete(self::CACHE_PREFIX . $blockId);
        }

        // Invalidate region caches
        try {
            $this->cache->tags(['blocks'])->clear();
        } catch (\Exception) {
            // Tags not supported, clear specific keys
        }
    }
}
