<?php

declare(strict_types=1);

namespace App\Cms\Menu;

/**
 * MenuBuilder — Fluent API for building menu items programmatically.
 *
 * Returned by MenuManager::menu() and MenuManager::create().
 *
 * Usage:
 *   $menuManager->menu('main')
 *       ->addItem('Home', '/', icon: 'home', weight: 0)
 *       ->addItem('Blog', '/blog', weight: 10)
 *       ->addItem('Latest Posts', '/blog/latest', parent: 'Blog', weight: 0)
 *       ->addItem('Archives', '/blog/archives', parent: 'Blog', weight: 1);
 */
final class MenuBuilder
{
    /** @var array<string, array{title: string, url: ?string, parent: ?string, icon: ?string, target: ?string, weight: int, enabled: bool, route_name: ?string, route_params: array, attributes: array}> */
    private array $items = [];

    /** @var list<string> Items to remove */
    private array $removals = [];

    public function __construct(
        public readonly string $machineName,
        public readonly string $label = '',
        public readonly string $description = '',
    ) {}

    // ── Fluent API ──────────────────────────────────────────────────────

    /**
     * Add a menu item.
     *
     * @param string      $title   Display text
     * @param string|null $url     Direct URL (or null if using route_name)
     * @param string|null $parent  Title of the parent item for nesting
     * @param string|null $icon    Lucide icon name
     * @param string|null $target  Link target (_blank, etc.)
     * @param int         $weight  Sort order (lower = first)
     * @param bool        $enabled Whether item is active
     * @param string|null $routeName  Named route instead of URL
     * @param array       $routeParams Route parameters
     * @param array       $attributes  Extra HTML attributes
     */
    public function addItem(
        string $title,
        ?string $url = null,
        ?string $parent = null,
        ?string $icon = null,
        ?string $target = null,
        int $weight = 0,
        bool $enabled = true,
        ?string $routeName = null,
        array $routeParams = [],
        array $attributes = [],
    ): static {
        $this->items[$title] = [
            'title'        => $title,
            'url'          => $url,
            'parent'       => $parent,
            'icon'         => $icon,
            'target'       => $target,
            'weight'       => $weight,
            'enabled'      => $enabled,
            'route_name'   => $routeName,
            'route_params' => $routeParams,
            'attributes'   => $attributes,
        ];
        return $this;
    }

    /**
     * Remove an item by title.
     */
    public function removeItem(string $title): static
    {
        $this->removals[] = $title;
        unset($this->items[$title]);
        return $this;
    }

    /**
     * Modify an existing item's properties.
     */
    public function modifyItem(
        string $title,
        ?string $url = null,
        ?string $icon = null,
        ?string $target = null,
        ?int $weight = null,
        ?bool $enabled = null,
    ): static {
        if (!isset($this->items[$title])) {
            return $this;
        }

        if ($url !== null) $this->items[$title]['url'] = $url;
        if ($icon !== null) $this->items[$title]['icon'] = $icon;
        if ($target !== null) $this->items[$title]['target'] = $target;
        if ($weight !== null) $this->items[$title]['weight'] = $weight;
        if ($enabled !== null) $this->items[$title]['enabled'] = $enabled;

        return $this;
    }

    // ── Tree Builder ────────────────────────────────────────────────────

    /**
     * Build a nested tree of MenuItemEntity objects from registered items.
     *
     * @return MenuItemEntity[]
     */
    public function buildTree(): array
    {
        $entities = [];
        $byTitle = [];

        // Create entities
        foreach ($this->items as $data) {
            if (!$data['enabled']) {
                continue;
            }

            $entity = new MenuItemEntity();
            $entity->title = $data['title'];
            $entity->url = $data['url'];
            $entity->icon = $data['icon'];
            $entity->target = $data['target'];
            $entity->weight = $data['weight'];
            $entity->enabled = $data['enabled'];
            $entity->route_name = $data['route_name'];
            $entity->route_params = $data['route_params'];
            $entity->attributes = array_merge($data['attributes'], ['_source' => 'code']);

            $byTitle[$data['title']] = $entity;
            $entities[] = $entity;
        }

        // Build parent-child relationships by title
        $tree = [];
        foreach ($entities as $entity) {
            $data = $this->items[$entity->title];
            $parentTitle = $data['parent'] ?? null;

            if ($parentTitle && isset($byTitle[$parentTitle])) {
                $byTitle[$parentTitle]->children[] = $entity;
                $entity->depth = 1;
            } else {
                $tree[] = $entity;
            }
        }

        // Sort each level by weight
        usort($tree, fn(MenuItemEntity $a, MenuItemEntity $b) => $a->weight <=> $b->weight);
        foreach ($byTitle as $entity) {
            if (!empty($entity->children)) {
                usort($entity->children, fn(MenuItemEntity $a, MenuItemEntity $b) => $a->weight <=> $b->weight);
            }
        }

        return $tree;
    }

    /**
     * Get the list of item titles marked for removal.
     *
     * @return list<string>
     */
    public function getRemovals(): array
    {
        return $this->removals;
    }
}
