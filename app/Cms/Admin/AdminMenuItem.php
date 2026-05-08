<?php

declare(strict_types=1);

namespace App\Cms\Admin;

/**
 * AdminMenuItem — A single admin sidebar menu item.
 *
 * Uses PHP 8.4 property hooks for computed properties.
 * Supports nested children (expandable sub-menus) and
 * permission-based visibility filtering.
 */
final class AdminMenuItem
{
    /** @var AdminMenuItem[] */
    public array $children = [];

    /** @var array<string, string> Extra HTML attributes */
    public array $attributes = [];

    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $url,
        public readonly string $group,
        public readonly ?string $icon = null,
        public readonly ?string $permission = null,
        public readonly int $weight = 0,
        public readonly ?string $badge = null,
        public readonly ?string $badgeVariant = null,
        public readonly ?string $target = null,
        public readonly bool $enabled = true,
    ) {}

    // ── PHP 8.4 Property Hooks ─────────────────────────────────────────

    /** Whether this item has children (expandable in sidebar). */
    public bool $isExpandable {
        get => !empty($this->children);
    }

    /** CSS class for active state detection. */
    public string $activePattern {
        get => rtrim($this->url, '/');
    }

    // ── Fluent Child Management ────────────────────────────────────────

    /**
     * Add a child sub-item.
     */
    public function addChild(AdminMenuItem $child): static
    {
        $this->children[] = $child;
        usort($this->children, static fn(self $a, self $b): int => $a->weight <=> $b->weight);
        return $this;
    }

    /**
     * Remove a child by ID.
     */
    public function removeChild(string $id): static
    {
        $this->children = array_values(
            array_filter($this->children, static fn(self $c): bool => $c->id !== $id),
        );
        return $this;
    }

    /**
     * Find a child by ID (recursive).
     */
    public function findChild(string $id): ?self
    {
        foreach ($this->children as $child) {
            if ($child->id === $id) {
                return $child;
            }
            $found = $child->findChild($id);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    /**
     * Whether this item or any child is active for the given path.
     */
    public function isActive(string $currentPath): bool
    {
        $path = rtrim($currentPath, '/');
        $pattern = $this->activePattern;

        if ($path === $pattern || str_starts_with($path, $pattern . '/')) {
            return true;
        }

        foreach ($this->children as $child) {
            if ($child->isActive($currentPath)) {
                return true;
            }
        }

        return false;
    }
}
