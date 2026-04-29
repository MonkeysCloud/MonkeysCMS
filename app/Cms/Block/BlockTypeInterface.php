<?php

declare(strict_types=1);

namespace App\Cms\Block;

/**
 * BlockTypeInterface — Contract for code-defined block types.
 *
 * Each block type that is defined in PHP (as opposed to database-defined)
 * must implement this interface.
 */
interface BlockTypeInterface
{
    /**
     * Unique block type identifier (e.g., 'text', 'image', 'html')
     */
    public static function getId(): string;

    /**
     * Human-readable label
     */
    public static function getLabel(): string;

    /**
     * Description of the block type
     */
    public static function getDescription(): string;

    /**
     * Icon (emoji or CSS class)
     */
    public static function getIcon(): string;

    /**
     * Category for grouping in the block picker
     */
    public static function getCategory(): string;

    /**
     * Field definitions for this block type
     *
     * @return array<string, array{type: string, label: string, required?: bool, default?: mixed}>
     */
    public static function getFields(): array;

    /**
     * Render the block to HTML
     */
    public function render(array $data, array $settings = []): string;
}
