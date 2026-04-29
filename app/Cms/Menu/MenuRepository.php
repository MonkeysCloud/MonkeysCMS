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
