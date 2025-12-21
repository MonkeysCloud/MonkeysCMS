<?php

declare(strict_types=1);

namespace App\Cms\Blocks\Types;

use App\Modules\Core\Entities\Block;

/**
 * BlockTypeInterface - Interface for code-defined block types
 * 
 * Implement this interface to create a new block type in code.
 * Block types define the structure and behavior of blocks.
 * 
 * @example
 * ```php
 * #[BlockType(
 *     id: 'gallery',
 *     label: 'Image Gallery',
 *     description: 'Display a gallery of images'
 * )]
 * class GalleryBlock implements BlockTypeInterface
 * {
 *     public static function getFields(): array
 *     {
 *         return [
 *             'images' => ['type' => 'gallery', 'label' => 'Images'],
 *             'columns' => ['type' => 'select', 'label' => 'Columns', 'options' => [2, 3, 4]],
 *         ];
 *     }
 * }
 * ```
 */
interface BlockTypeInterface
{
    /**
     * Get the unique identifier for this block type
     */
    public static function getId(): string;

    /**
     * Get the human-readable label
     */
    public static function getLabel(): string;

    /**
     * Get the description of this block type
     */
    public static function getDescription(): string;

    /**
     * Get the icon (emoji or icon class)
     */
    public static function getIcon(): string;

    /**
     * Get the category for grouping block types
     */
    public static function getCategory(): string;

    /**
     * Get field definitions for this block type
     * 
     * @return array<string, array{
     *     type: string,
     *     label: string,
     *     required?: bool,
     *     default?: mixed,
     *     description?: string,
     *     widget?: string,
     *     settings?: array
     * }>
     */
    public static function getFields(): array;

    /**
     * Get default settings for new blocks of this type
     */
    public static function getDefaultSettings(): array;

    /**
     * Render the block to HTML
     * 
     * @param Block $block The block instance
     * @param array $context Additional context variables
     * @return string The rendered HTML
     */
    public function render(Block $block, array $context = []): string;

    /**
     * Validate block data before saving
     * 
     * @param array $data The block data to validate
     * @return array<string, string> Array of field => error message
     */
    public function validate(array $data): array;

    /**
     * Process block data before saving
     * 
     * @param array $data The block data
     * @return array The processed data
     */
    public function processData(array $data): array;

    /**
     * Get cache tags for this block
     * 
     * @param Block $block The block instance
     * @return array Cache tags
     */
    public function getCacheTags(Block $block): array;

    /**
     * Get cache TTL in seconds (0 = no cache, -1 = forever)
     */
    public function getCacheTtl(): int;

    /**
     * Check if this block type can be placed in a region
     */
    public function canBePlacedInRegion(string $region): bool;

    /**
     * Get JavaScript files required by this block type
     */
    public static function getJsAssets(): array;

    /**
     * Get CSS files required by this block type
     */
    public static function getCssAssets(): array;
}
