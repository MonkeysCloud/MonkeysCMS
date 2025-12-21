<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Entities\Menu;
use App\Modules\Core\Entities\MenuItem;
use App\Modules\Core\Entities\User;
use MonkeysLegion\Database\Connection;
use MonkeysLegion\Cache\CacheManager;

/**
 * MenuService - Manages navigation menus with caching
 *
 * Uses MonkeysLegion-Cache for persistent menu caching with
 * tag-based invalidation support.
 */
final class MenuService
{
    private const CACHE_TTL = 86400; // 24 hours
    private const CACHE_TAG = 'menus';

    private array $localCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ?CacheManager $cache = null,
    ) {
    }

    /**
     * Get all menus
     *
     * @return Menu[]
     */
    public function getAllMenus(): array
    {
        $stmt = $this->connection->query(
            "SELECT * FROM menus ORDER BY name"
        );

        $menus = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $menu = new Menu();
            $menu->hydrate($row);
            $menus[] = $menu;
        }

        return $menus;
    }

    /**
     * Get menu by ID
     */
    public function getMenu(int $id): ?Menu
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM menus WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $menu = new Menu();
        $menu->hydrate($row);

        return $menu;
    }

    /**
     * Get menu by machine name
     */
    public function getMenuByName(string $machineName): ?Menu
    {
        // Check local cache first
        if (isset($this->localCache[$machineName])) {
            return $this->localCache[$machineName];
        }

        // Check persistent cache
        $cacheKey = "menu:{$machineName}";
        if ($this->cache !== null) {
            $cached = $this->cache->store()->get($cacheKey);
            if ($cached !== null) {
                $this->localCache[$machineName] = $cached;
                return $cached;
            }
        }

        $stmt = $this->connection->prepare(
            "SELECT * FROM menus WHERE machine_name = :machine_name"
        );
        $stmt->execute(['machine_name' => $machineName]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $menu = new Menu();
        $menu->hydrate($row);

        $this->localCache[$machineName] = $menu;

        // Store in persistent cache
        if ($this->cache !== null) {
            $this->cacheSet($cacheKey, $menu);
        }

        return $menu;
    }

    /**
     * Get menu with items loaded
     */
    public function getMenuWithItems(int $id, ?User $user = null): ?Menu
    {
        $menu = $this->getMenu($id);
        if (!$menu) {
            return null;
        }

        $menu->items = $this->getMenuItems($id, $user);

        return $menu;
    }

    /**
     * Get menu by name with items
     */
    public function getMenuByNameWithItems(string $machineName, ?User $user = null): ?Menu
    {
        $menu = $this->getMenuByName($machineName);
        if (!$menu) {
            return null;
        }

        $menu->items = $this->getMenuItems($menu->id, $user);

        return $menu;
    }

    /**
     * Get menu items
     *
     * @return MenuItem[]
     */
    public function getMenuItems(int $menuId, ?User $user = null, bool $publishedOnly = true): array
    {
        $sql = "SELECT * FROM menu_items WHERE menu_id = :menu_id";
        if ($publishedOnly) {
            $sql .= " AND is_published = 1";
        }
        $sql .= " ORDER BY parent_id NULLS FIRST, weight, title";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['menu_id' => $menuId]);

        $items = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $item = new MenuItem();
            $item->hydrate($row);

            // Filter by visibility
            if ($item->isVisible($user)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Get menu tree (hierarchical)
     *
     * @return MenuItem[]
     */
    public function getMenuTree(int $menuId, ?User $user = null): array
    {
        $items = $this->getMenuItems($menuId, $user);
        return $this->buildTree($items);
    }

    /**
     * Build hierarchical tree from flat list
     */
    private function buildTree(array $items): array
    {
        $tree = [];
        $itemMap = [];

        foreach ($items as $item) {
            $itemMap[$item->id] = $item;
            $item->children = [];
        }

        foreach ($items as $item) {
            if ($item->parent_id === null) {
                $tree[] = $item;
            } elseif (isset($itemMap[$item->parent_id])) {
                $itemMap[$item->parent_id]->children[] = $item;
            }
        }

        return $tree;
    }

    /**
     * Save menu
     */
    public function saveMenu(Menu $menu): Menu
    {
        $menu->prePersist();

        if ($menu->isNew()) {
            $stmt = $this->connection->prepare("
                INSERT INTO menus (name, machine_name, description, location, created_at, updated_at)
                VALUES (:name, :machine_name, :description, :location, :created_at, :updated_at)
            ");
            $stmt->execute([
                'name' => $menu->name,
                'machine_name' => $menu->machine_name,
                'description' => $menu->description,
                'location' => $menu->location,
                'created_at' => $menu->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $menu->updated_at->format('Y-m-d H:i:s'),
            ]);
            $menu->id = (int) $this->connection->lastInsertId();
        } else {
            $stmt = $this->connection->prepare("
                UPDATE menus SET
                    name = :name,
                    machine_name = :machine_name,
                    description = :description,
                    location = :location,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $menu->id,
                'name' => $menu->name,
                'machine_name' => $menu->machine_name,
                'description' => $menu->description,
                'location' => $menu->location,
                'updated_at' => $menu->updated_at->format('Y-m-d H:i:s'),
            ]);
        }

        unset($this->localCache[$menu->machine_name]);
        $this->invalidateMenuCache($menu->machine_name);

        return $menu;
    }

    /**
     * Delete menu and all its items
     */
    public function deleteMenu(Menu $menu): void
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM menu_items WHERE menu_id = :menu_id"
        );
        $stmt->execute(['menu_id' => $menu->id]);

        $stmt = $this->connection->prepare(
            "DELETE FROM menus WHERE id = :id"
        );
        $stmt->execute(['id' => $menu->id]);

        unset($this->localCache[$menu->machine_name]);
        $this->invalidateMenuCache($menu->machine_name);
    }

    /**
     * Get menu item by ID
     */
    public function getMenuItem(int $id): ?MenuItem
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM menu_items WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $item = new MenuItem();
        $item->hydrate($row);

        return $item;
    }

    /**
     * Save menu item
     */
    public function saveMenuItem(MenuItem $item): MenuItem
    {
        $item->prePersist();

        // Calculate depth
        if ($item->parent_id !== null) {
            $parent = $this->getMenuItem($item->parent_id);
            $item->depth = $parent ? $parent->depth + 1 : 0;
        } else {
            $item->depth = 0;
        }

        if ($item->isNew()) {
            $stmt = $this->connection->prepare("
                INSERT INTO menu_items (menu_id, parent_id, title, url, link_type, entity_type, entity_id, icon, css_class, target, weight, depth, expanded, is_published, attributes, visibility, created_at, updated_at)
                VALUES (:menu_id, :parent_id, :title, :url, :link_type, :entity_type, :entity_id, :icon, :css_class, :target, :weight, :depth, :expanded, :is_published, :attributes, :visibility, :created_at, :updated_at)
            ");
            $stmt->execute([
                'menu_id' => $item->menu_id,
                'parent_id' => $item->parent_id,
                'title' => $item->title,
                'url' => $item->url,
                'link_type' => $item->link_type,
                'entity_type' => $item->entity_type,
                'entity_id' => $item->entity_id,
                'icon' => $item->icon,
                'css_class' => $item->css_class,
                'target' => $item->target,
                'weight' => $item->weight,
                'depth' => $item->depth,
                'expanded' => $item->expanded ? 1 : 0,
                'is_published' => $item->is_published ? 1 : 0,
                'attributes' => json_encode($item->attributes),
                'visibility' => json_encode($item->visibility),
                'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
            ]);
            $item->id = (int) $this->connection->lastInsertId();
        } else {
            $stmt = $this->connection->prepare("
                UPDATE menu_items SET
                    parent_id = :parent_id,
                    title = :title,
                    url = :url,
                    link_type = :link_type,
                    entity_type = :entity_type,
                    entity_id = :entity_id,
                    icon = :icon,
                    css_class = :css_class,
                    target = :target,
                    weight = :weight,
                    depth = :depth,
                    expanded = :expanded,
                    is_published = :is_published,
                    attributes = :attributes,
                    visibility = :visibility,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $item->id,
                'parent_id' => $item->parent_id,
                'title' => $item->title,
                'url' => $item->url,
                'link_type' => $item->link_type,
                'entity_type' => $item->entity_type,
                'entity_id' => $item->entity_id,
                'icon' => $item->icon,
                'css_class' => $item->css_class,
                'target' => $item->target,
                'weight' => $item->weight,
                'depth' => $item->depth,
                'expanded' => $item->expanded ? 1 : 0,
                'is_published' => $item->is_published ? 1 : 0,
                'attributes' => json_encode($item->attributes),
                'visibility' => json_encode($item->visibility),
                'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
            ]);
        }

        // Clear cache
        $menu = $this->getMenu($item->menu_id);
        if ($menu) {
            unset($this->localCache[$menu->machine_name]);
            $this->invalidateMenuCache($menu->machine_name);
        }

        return $item;
    }

    /**
     * Delete menu item (and optionally children)
     */
    public function deleteMenuItem(MenuItem $item, bool $deleteChildren = false): void
    {
        if ($deleteChildren) {
            $this->deleteChildItems($item->id);
        } else {
            // Move children to parent
            $stmt = $this->connection->prepare(
                "UPDATE menu_items SET parent_id = :parent_id WHERE parent_id = :item_id"
            );
            $stmt->execute([
                'parent_id' => $item->parent_id,
                'item_id' => $item->id,
            ]);
        }

        $stmt = $this->connection->prepare(
            "DELETE FROM menu_items WHERE id = :id"
        );
        $stmt->execute(['id' => $item->id]);

        // Clear cache
        $menu = $this->getMenu($item->menu_id);
        if ($menu) {
            unset($this->localCache[$menu->machine_name]);
            $this->invalidateMenuCache($menu->machine_name);
        }
    }

    private function deleteChildItems(int $parentId): void
    {
        $stmt = $this->connection->prepare(
            "SELECT id FROM menu_items WHERE parent_id = :parent_id"
        );
        $stmt->execute(['parent_id' => $parentId]);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->deleteChildItems($row['id']);
        }

        $stmt = $this->connection->prepare(
            "DELETE FROM menu_items WHERE parent_id = :parent_id"
        );
        $stmt->execute(['parent_id' => $parentId]);
    }

    /**
     * Reorder menu items
     */
    public function reorderItems(int $menuId, array $order): void
    {
        foreach ($order as $weight => $data) {
            $itemId = is_array($data) ? $data['id'] : $data;
            $parentId = is_array($data) ? ($data['parent_id'] ?? null) : null;

            $stmt = $this->connection->prepare(
                "UPDATE menu_items SET weight = :weight, parent_id = :parent_id WHERE id = :id AND menu_id = :menu_id"
            );
            $stmt->execute([
                'weight' => $weight,
                'parent_id' => $parentId,
                'id' => $itemId,
                'menu_id' => $menuId,
            ]);
        }

        // Recalculate depths
        $this->recalculateDepths($menuId);

        // Clear cache
        $menu = $this->getMenu($menuId);
        if ($menu) {
            unset($this->localCache[$menu->machine_name]);
            $this->invalidateMenuCache($menu->machine_name);
        }
    }

    private function recalculateDepths(int $menuId): void
    {
        $items = $this->getMenuItems($menuId, null, false);
        $itemMap = [];

        foreach ($items as $item) {
            $itemMap[$item->id] = $item;
        }

        foreach ($items as $item) {
            $depth = 0;
            $current = $item;

            while ($current->parent_id !== null && isset($itemMap[$current->parent_id])) {
                $depth++;
                $current = $itemMap[$current->parent_id];
            }

            if ($item->depth !== $depth) {
                $stmt = $this->connection->prepare(
                    "UPDATE menu_items SET depth = :depth WHERE id = :id"
                );
                $stmt->execute(['depth' => $depth, 'id' => $item->id]);
            }
        }
    }

    // =========================================================================
    // Cache Helpers
    // =========================================================================

    /**
     * Set value in cache with tags
     */
    private function cacheSet(string $key, mixed $value): void
    {
        if ($this->cache === null) {
            return;
        }

        try {
            $this->cache->tags([self::CACHE_TAG])->set($key, $value, self::CACHE_TTL);
        } catch (\Exception) {
            // Fall back to regular set if tags not supported
            $this->cache->store()->set($key, $value, self::CACHE_TTL);
        }
    }

    /**
     * Invalidate menu cache
     */
    private function invalidateMenuCache(string $machineName): void
    {
        if ($this->cache === null) {
            return;
        }

        // Delete specific menu cache
        $this->cache->store()->delete("menu:{$machineName}");
        $this->cache->store()->delete("menu_tree:{$machineName}");
    }

    /**
     * Clear all menu caches
     */
    public function clearCache(): void
    {
        $this->localCache = [];

        if ($this->cache !== null) {
            try {
                $this->cache->tags([self::CACHE_TAG])->clear();
            } catch (\Exception) {
                // Tags not supported
            }
        }
    }

    /**
     * Seed default menus
     */
    public function seedDefaults(): void
    {
        $defaults = [
            ['name' => 'Main Menu', 'machine_name' => 'main', 'location' => 'header'],
            ['name' => 'Footer Menu', 'machine_name' => 'footer', 'location' => 'footer'],
        ];

        foreach ($defaults as $data) {
            if ($this->getMenuByName($data['machine_name'])) {
                continue;
            }

            $menu = new Menu();
            $menu->name = $data['name'];
            $menu->machine_name = $data['machine_name'];
            $menu->location = $data['location'];

            $this->saveMenu($menu);
        }
    }
}
