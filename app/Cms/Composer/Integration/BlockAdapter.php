<?php

declare(strict_types=1);

namespace App\Cms\Composer\Integration;

use App\Cms\Blocks\BlockManager;
use App\Cms\Blocks\BlockRenderer;

/**
 * BlockAdapter - Adapts existing CMS BlockTypes for use in Composer
 * 
 * This allows all block types defined in /admin/structure/block-types
 * to be used within the compositor as blocks.
 */
class BlockAdapter
{
    public function __construct(
        private ?BlockManager $blockManager = null,
        private ?BlockRenderer $blockRenderer = null,
    ) {
    }

    /**
     * Get all available block types for composer
     * 
     * @return array<array{type: string, label: string, icon: string, description: string, fields: array}>
     */
    public function getBlockTypes(): array
    {
        if (!$this->blockManager) {
            return [];
        }

        $types = [];
        foreach ($this->blockManager->getTypes() as $blockType) {
            $types[] = [
                'type' => '_block_type:' . $blockType->type_id,
                'label' => $blockType->label,
                'icon' => $blockType->icon ?? '📦',
                'description' => $blockType->description ?? '',
                'fields' => $this->getBlockFields($blockType),
            ];
        }

        return $types;
    }

    /**
     * Get the field schema for a block type
     */
    public function getBlockFields(object $blockType): array
    {
        // Return the fields configured for this block type
        return $blockType->fields ?? [];
    }

    /**
     * Render a block type reference
     * 
     * @param string $blockTypeId The block type ID (without _block_type: prefix)
     * @param array $data Field values for the block
     */
    public function render(string $blockTypeId, array $data = []): string
    {
        if (!$this->blockRenderer) {
            return "<!-- Block type: {$blockTypeId} (no renderer) -->";
        }

        // Create an inline block instance with provided data
        return $this->blockRenderer->renderInline($blockTypeId, $data);
    }

    /**
     * Check if a type string is a block type reference
     */
    public static function isBlockTypeReference(string $type): bool
    {
        return str_starts_with($type, '_block_type:');
    }

    /**
     * Extract the block type ID from a reference
     */
    public static function extractBlockTypeId(string $type): string
    {
        return str_replace('_block_type:', '', $type);
    }

    public function setBlockManager(BlockManager $manager): void
    {
        $this->blockManager = $manager;
    }

    public function setBlockRenderer(BlockRenderer $renderer): void
    {
        $this->blockRenderer = $renderer;
    }
}
