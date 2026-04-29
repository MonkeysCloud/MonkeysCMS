<?php

declare(strict_types=1);

namespace App\Cms\Block;

/**
 * BlockTypeRegistry — Central registry for all available block types.
 *
 * Manages both code-defined (PHP class) and database-defined block types.
 * Used by the Mosaic editor to show the block picker and render blocks.
 */
final class BlockTypeRegistry
{
    /** @var array<string, BlockTypeInterface> */
    private array $types = [];

    /**
     * Register a code-defined block type
     */
    public function register(BlockTypeInterface $type): void
    {
        $this->types[$type::getId()] = $type;
    }

    /**
     * Register multiple block types
     */
    public function registerMany(array $types): void
    {
        foreach ($types as $type) {
            $this->register($type);
        }
    }

    /**
     * Get a block type by ID
     */
    public function get(string $id): ?BlockTypeInterface
    {
        return $this->types[$id] ?? null;
    }

    /**
     * Check if a block type exists
     */
    public function has(string $id): bool
    {
        return isset($this->types[$id]);
    }

    /**
     * Get all registered block types
     *
     * @return array<string, array{id: string, label: string, description: string, icon: string, category: string, fields: array}>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->types as $id => $type) {
            $result[$id] = [
                'id' => $id,
                'label' => $type::getLabel(),
                'description' => $type::getDescription(),
                'icon' => $type::getIcon(),
                'category' => $type::getCategory(),
                'fields' => $type::getFields(),
            ];
        }

        // Sort by category then label
        uasort($result, function ($a, $b) {
            $cat = strcmp($a['category'], $b['category']);
            return $cat !== 0 ? $cat : strcmp($a['label'], $b['label']);
        });

        return $result;
    }

    /**
     * Get block types grouped by category
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->all() as $type) {
            $grouped[$type['category']][] = $type;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Render a block
     */
    public function render(string $blockType, array $data, array $settings = []): string
    {
        $type = $this->get($blockType);

        if (!$type) {
            return '<!-- Unknown block type: ' . htmlspecialchars($blockType) . ' -->';
        }

        return $type->render($data, $settings);
    }
}
