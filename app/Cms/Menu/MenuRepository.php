<?php

declare(strict_types=1);

namespace App\Cms\Menu;

use PDO;

/**
 * MenuRepository — CRUD for menus and menu items, with tree building.
 */
final class MenuRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Menu Queries ────────────────────────────────────────────────────

    public function findById(int $id): ?MenuEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM menus WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $menu = (new MenuEntity())->hydrate($row);
        $menu->items = $this->buildTree($menu->id);

        return $menu;
    }

    public function findByName(string $machineName): ?MenuEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM menus WHERE machine_name = :name AND enabled = 1');
        $stmt->execute(['name' => $machineName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $menu = (new MenuEntity())->hydrate($row);
        $menu->items = $this->buildTree($menu->id);

        return $menu;
    }

    /**
     * @return MenuEntity[]
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM menus ORDER BY label ASC');
        return array_map(
            fn(array $row) => (new MenuEntity())->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Find all menus with their item counts.
     *
     * @return array{menu: MenuEntity, item_count: int}[]
     */
    public function findAllWithItemCounts(): array
    {
        $sql = 'SELECT m.*, (SELECT COUNT(*) FROM menu_items mi WHERE mi.menu_id = m.id) AS item_count
                FROM menus m ORDER BY m.label ASC';
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $row) => [
            'menu'       => (new MenuEntity())->hydrate($row),
            'item_count' => (int) $row['item_count'],
        ], $rows);
    }

    // ── Menu Persistence ────────────────────────────────────────────────

    public function persistMenu(MenuEntity $menu): MenuEntity
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($menu->id !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE menus SET machine_name = :machine_name, label = :label,
                 description = :description, enabled = :enabled, updated_at = :updated_at WHERE id = :id'
            );
            $stmt->execute([
                'id' => $menu->id,
                'machine_name' => $menu->machine_name,
                'label' => $menu->label,
                'description' => $menu->description,
                'enabled' => (int) $menu->enabled,
                'updated_at' => $now,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO menus (machine_name, label, description, enabled, created_at, updated_at)
                 VALUES (:machine_name, :label, :description, :enabled, :created_at, :updated_at)'
            );
            $stmt->execute([
                'machine_name' => $menu->machine_name,
                'label' => $menu->label,
                'description' => $menu->description,
                'enabled' => (int) $menu->enabled,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $menu->id = (int) $this->pdo->lastInsertId();
        }

        return $menu;
    }

    public function deleteMenu(int $id): void
    {
        // Items are cascade-deleted via FK
        $this->pdo->prepare('DELETE FROM menus WHERE id = :id')->execute(['id' => $id]);
    }

    // ── Menu Item Queries ───────────────────────────────────────────────

    public function findItem(int $id): ?MenuItemEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM menu_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new MenuItemEntity())->hydrate($row) : null;
    }

    /**
     * Get flat list of all items for a menu (no tree, for admin editing).
     *
     * @return MenuItemEntity[]
     */
    public function findItemsByMenu(int $menuId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM menu_items WHERE menu_id = :menu_id ORDER BY weight ASC, title ASC'
        );
        $stmt->execute(['menu_id' => $menuId]);

        return array_map(
            fn(array $row) => (new MenuItemEntity())->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    // ── Menu Item Persistence ───────────────────────────────────────────

    public function persistItem(MenuItemEntity $item): MenuItemEntity
    {
        if ($item->id !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE menu_items SET
                    menu_id = :menu_id, parent_id = :parent_id, title = :title,
                    url = :url, route_name = :route_name, route_params = :route_params,
                    target = :target, icon = :icon, attributes = :attributes,
                    weight = :weight, enabled = :enabled
                 WHERE id = :id'
            );
            $stmt->execute([
                'id'           => $item->id,
                'menu_id'      => $item->menu_id,
                'parent_id'    => $item->parent_id,
                'title'        => $item->title,
                'url'          => $item->url,
                'route_name'   => $item->route_name,
                'route_params' => json_encode($item->route_params),
                'target'       => $item->target,
                'icon'         => $item->icon,
                'attributes'   => json_encode($item->attributes),
                'weight'       => $item->weight,
                'enabled'      => (int) $item->enabled,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO menu_items
                    (menu_id, parent_id, title, url, route_name, route_params, target, icon, attributes, weight, enabled)
                 VALUES
                    (:menu_id, :parent_id, :title, :url, :route_name, :route_params, :target, :icon, :attributes, :weight, :enabled)'
            );
            $stmt->execute([
                'menu_id'      => $item->menu_id,
                'parent_id'    => $item->parent_id,
                'title'        => $item->title,
                'url'          => $item->url,
                'route_name'   => $item->route_name,
                'route_params' => json_encode($item->route_params),
                'target'       => $item->target,
                'icon'         => $item->icon,
                'attributes'   => json_encode($item->attributes),
                'weight'       => $item->weight,
                'enabled'      => (int) $item->enabled,
            ]);
            $item->id = (int) $this->pdo->lastInsertId();
        }

        return $item;
    }

    public function deleteItem(int $id): void
    {
        // Children are set null via FK (on_delete = SET NULL)
        $this->pdo->prepare('DELETE FROM menu_items WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Reorder items from a flat array: [ {id, parent_id, weight}, ... ]
     *
     * @param array<array{id: int, parent_id: ?int, weight: int}> $order
     */
    public function reorderItems(int $menuId, array $order): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE menu_items SET parent_id = :parent_id, weight = :weight WHERE id = :id AND menu_id = :menu_id'
        );

        foreach ($order as $item) {
            $stmt->execute([
                'id'        => (int) $item['id'],
                'parent_id' => isset($item['parent_id']) ? (int) $item['parent_id'] : null,
                'weight'    => (int) $item['weight'],
                'menu_id'   => $menuId,
            ]);
        }
    }

    // ── Tree Builder ────────────────────────────────────────────────────

    /**
     * Build nested tree of menu items for a menu
     *
     * @return MenuItemEntity[]
     */
    private function buildTree(int $menuId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM menu_items WHERE menu_id = :menu_id AND enabled = 1 ORDER BY weight ASC, title ASC'
        );
        $stmt->execute(['menu_id' => $menuId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $lookup = [];

        foreach ($rows as $row) {
            $item = (new MenuItemEntity())->hydrate($row);
            $lookup[$item->id] = $item;
            $items[] = $item;
        }

        $tree = [];

        foreach ($items as $item) {
            if ($item->parent_id !== null && isset($lookup[$item->parent_id])) {
                $lookup[$item->parent_id]->children[] = $item;
            } else {
                $tree[] = $item;
            }
        }

        return $tree;
    }
}

