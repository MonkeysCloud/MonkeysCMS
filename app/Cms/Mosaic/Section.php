<?php

declare(strict_types=1);

namespace App\Cms\Mosaic;

/**
 * Section — Represents a row in the Mosaic page builder.
 *
 * A section defines a layout (e.g., "two_col", "sidebar_left") and
 * contains named regions that hold blocks.
 */
final class Section
{
    /**
     * Available section layouts and their region definitions.
     */
    public const LAYOUTS = [
        'full' => [
            'label' => 'Full Width',
            'icon' => '⬜',
            'regions' => ['main'],
        ],
        'two_col' => [
            'label' => 'Two Columns',
            'icon' => '◻◻',
            'regions' => ['first', 'second'],
        ],
        'three_col' => [
            'label' => 'Three Columns',
            'icon' => '◻◻◻',
            'regions' => ['first', 'second', 'third'],
        ],
        'four_col' => [
            'label' => 'Four Columns',
            'icon' => '◻◻◻◻',
            'regions' => ['first', 'second', 'third', 'fourth'],
        ],
        'sidebar_left' => [
            'label' => 'Sidebar Left',
            'icon' => '▮◻',
            'regions' => ['sidebar', 'main'],
        ],
        'sidebar_right' => [
            'label' => 'Sidebar Right',
            'icon' => '◻▮',
            'regions' => ['main', 'sidebar'],
        ],
    ];

    public function __construct(
        public readonly string $id,
        public string $layout = 'full',
        public array $settings = [],
        public array $regions = [],
    ) {
        // Initialize empty regions based on layout
        if (empty($this->regions)) {
            $layoutDef = self::LAYOUTS[$this->layout] ?? self::LAYOUTS['full'];
            foreach ($layoutDef['regions'] as $regionName) {
                $this->regions[$regionName] = [];
            }
        }
    }

    /**
     * Create a new section with a unique ID
     */
    public static function create(string $layout = 'full', array $settings = []): self
    {
        return new self(
            id: 'sec_' . bin2hex(random_bytes(6)),
            layout: $layout,
            settings: $settings,
        );
    }

    /**
     * Build from stored array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? 'sec_' . bin2hex(random_bytes(6)),
            layout: $data['layout'] ?? 'full',
            settings: $data['settings'] ?? [],
            regions: $data['regions'] ?? [],
        );
    }

    /**
     * Serialize to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'layout' => $this->layout,
            'settings' => $this->settings,
            'regions' => $this->regions,
        ];
    }

    /**
     * Get the region names for this section's layout
     */
    public function getRegionNames(): array
    {
        $layoutDef = self::LAYOUTS[$this->layout] ?? self::LAYOUTS['full'];
        return $layoutDef['regions'];
    }

    /**
     * Get all available layout definitions
     */
    public static function getAvailableLayouts(): array
    {
        return self::LAYOUTS;
    }
}
