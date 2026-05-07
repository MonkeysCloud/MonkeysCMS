<?php

declare(strict_types=1);

namespace App\Cms\Menu;

use MonkeysLegion\DI\Attributes\Singleton;

/**
 * MenuManager — Singleton service for menu management.
 *
 * Provides both DB-backed menus and a programmatic API for modules.
 * Modules can register items at boot via the fluent MenuBuilder:
 *
 *   $menuManager->menu('main')
 *       ->addItem('Home', '/', icon: 'home', weight: 0)
 *       ->addItem('Blog', '/blog', weight: 10);
 *
 *   $menuManager->create('social', 'Social Links')
 *       ->addItem('GitHub', 'https://github.com/myorg', target: '_blank');
 */
#[Singleton]
final class MenuManager
{
    /** @var array<string, MenuBuilder> Programmatic menu builders */
    private array $builders = [];

    /** @var array<string, MenuEntity> Loaded/merged menus cache */
    private array $cache = [];

    public function __construct(
        private readonly MenuRepository $repo,
    ) {}

    // ── Programmatic API ────────────────────────────────────────────────

    /**
     * Get a builder for an existing menu (by machine_name).
     * Items added here are merged with DB items at render time.
     */
    public function menu(string $machineName): MenuBuilder
    {
        if (!isset($this->builders[$machineName])) {
            $this->builders[$machineName] = new MenuBuilder($machineName);
        }
        return $this->builders[$machineName];
    }

    /**
     * Create a new menu programmatically (not persisted to DB).
     * Returns a builder for adding items.
     */
    public function create(string $machineName, string $label, string $description = ''): MenuBuilder
    {
        $builder = new MenuBuilder($machineName, $label, $description);
        $this->builders[$machineName] = $builder;
        return $builder;
    }

    // ── Query API ───────────────────────────────────────────────────────

    /**
     * Load a menu by machine_name, merging DB items with code items.
     */
    public function get(string $machineName): ?MenuEntity
    {
        if (isset($this->cache[$machineName])) {
            return $this->cache[$machineName];
        }

        // Load from DB
        $menu = $this->repo->findByName($machineName);

        // Check for code-only menu
        if (!$menu && isset($this->builders[$machineName])) {
            $builder = $this->builders[$machineName];
            $menu = new MenuEntity();
            $menu->machine_name = $machineName;
            $menu->label = $builder->label ?: ucfirst(str_replace('_', ' ', $machineName));
            $menu->description = $builder->description;
            $menu->items = $builder->buildTree();
        }

        // Merge code items into DB menu
        if ($menu && isset($this->builders[$machineName])) {
            $menu->items = $this->mergeItems($menu->items, $this->builders[$machineName]->buildTree());
        }

        if ($menu) {
            $this->cache[$machineName] = $menu;
        }

        return $menu;
    }

    /**
     * Get all available menus (DB + code-defined).
     *
     * @return array<string, MenuEntity>
     */
    public function all(): array
    {
        $menus = [];

        // DB menus
        foreach ($this->repo->findAll() as $menu) {
            $name = $menu->machine_name;
            if (isset($this->builders[$name])) {
                $menu->items = $this->mergeItems(
                    $this->repo->findByName($name)?->items ?? [],
                    $this->builders[$name]->buildTree(),
                );
            }
            $menus[$name] = $menu;
        }

        // Code-only menus (not in DB)
        foreach ($this->builders as $name => $builder) {
            if (isset($menus[$name])) {
                continue;
            }
            $menu = new MenuEntity();
            $menu->machine_name = $name;
            $menu->label = $builder->label ?: ucfirst(str_replace('_', ' ', $name));
            $menu->description = $builder->description;
            $menu->items = $builder->buildTree();
            $menus[$name] = $menu;
        }

        return $menus;
    }

    /**
     * Clear the cache (e.g., after editing menus in admin).
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    // ── Internal ────────────────────────────────────────────────────────

    /**
     * Merge DB items with code items, sorted by weight.
     *
     * @param MenuItemEntity[] $dbItems
     * @param MenuItemEntity[] $codeItems
     * @return MenuItemEntity[]
     */
    private function mergeItems(array $dbItems, array $codeItems): array
    {
        // Mark sources
        foreach ($dbItems as $item) {
            $item->attributes = array_merge($item->attributes, ['_source' => 'db']);
        }
        foreach ($codeItems as $item) {
            $item->attributes = array_merge($item->attributes, ['_source' => 'code']);
        }

        $merged = array_merge($dbItems, $codeItems);
        usort($merged, fn(MenuItemEntity $a, MenuItemEntity $b) => $a->weight <=> $b->weight);

        return $merged;
    }
}
