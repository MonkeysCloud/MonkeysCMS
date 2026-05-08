<?php

declare(strict_types=1);

namespace App\Cms\Admin;

/**
 * AdminMenuGroup — A labeled group of admin sidebar items.
 *
 * Groups organize the sidebar into sections: Content, Structure, System, etc.
 * Uses PHP 8.4 property hooks for computed visibility.
 */
final class AdminMenuGroup
{
    /** @var AdminMenuItem[] */
    public array $items = [];

    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly int $weight = 0,
    ) {}

    // ── PHP 8.4 Property Hooks ─────────────────────────────────────────

    /** Whether this group has any visible items. */
    public bool $hasItems {
        get => !empty($this->items);
    }

    /** Number of items in the group. */
    public int $count {
        get => count($this->items);
    }

    // ── Item Management ────────────────────────────────────────────────

    /**
     * Add an item to this group, maintaining weight order.
     */
    public function addItem(AdminMenuItem $item): static
    {
        $this->items[] = $item;
        usort($this->items, static fn(AdminMenuItem $a, AdminMenuItem $b): int => $a->weight <=> $b->weight);
        return $this;
    }

    /**
     * Remove an item by ID.
     */
    public function removeItem(string $id): static
    {
        $this->items = array_values(
            array_filter($this->items, static fn(AdminMenuItem $i): bool => $i->id !== $id),
        );
        return $this;
    }

    /**
     * Find an item by ID.
     */
    public function findItem(string $id): ?AdminMenuItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }
        return null;
    }
}
