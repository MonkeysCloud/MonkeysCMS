<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Services\MenuService;
use App\Modules\Core\Entities\Menu;
use App\Modules\Core\Entities\MenuItem;
use MonkeysLegion\Http\Attribute\Route;
use MonkeysLegion\Http\Request;
use MonkeysLegion\Http\JsonResponse;

/**
 * MenuController - Admin API for menu management
 */
final class MenuController
{
    public function __construct(
        private readonly MenuService $menus,
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Menu Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * List all menus
     */
    #[Route('GET', '/admin/menus')]
    public function index(): JsonResponse
    {
        $menus = $this->menus->getAllMenus();

        return new JsonResponse([
            'menus' => array_map(fn(Menu $m) => $m->toArray(), $menus),
        ]);
    }

    /**
     * Get single menu with items
     */
    #[Route('GET', '/admin/menus/{id}')]
    public function show(int $id): JsonResponse
    {
        $menu = $this->menus->getMenuWithItems($id);

        if (!$menu) {
            return new JsonResponse(['error' => 'Menu not found'], 404);
        }

        return new JsonResponse(array_merge($menu->toArray(), [
            'items' => array_map(fn(MenuItem $i) => $i->toArray(), $menu->items),
            'tree' => $this->formatTree($menu->getItemTree()),
        ]));
    }

    /**
     * Get menu by machine name
     */
    #[Route('GET', '/admin/menus/by-name/{name}')]
    public function showByName(string $name): JsonResponse
    {
        $menu = $this->menus->getMenuByNameWithItems($name);

        if (!$menu) {
            return new JsonResponse(['error' => 'Menu not found'], 404);
        }

        return new JsonResponse(array_merge($menu->toArray(), [
            'items' => array_map(fn(MenuItem $i) => $i->toArray(), $menu->items),
            'tree' => $this->formatTree($menu->getItemTree()),
        ]));
    }

    /**
     * Create menu
     */
    #[Route('POST', '/admin/menus')]
    public function create(Request $request): JsonResponse
    {
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return new JsonResponse(['errors' => ['name' => 'Name is required']], 422);
        }

        $machineName = $data['machine_name'] ?? strtolower(preg_replace('/[^a-z0-9]+/', '_', $data['name']));

        if ($this->menus->getMenuByName($machineName)) {
            return new JsonResponse(['errors' => ['machine_name' => 'Machine name already exists']], 422);
        }

        $menu = new Menu();
        $menu->name = $data['name'];
        $menu->machine_name = $machineName;
        $menu->description = $data['description'] ?? '';
        $menu->location = $data['location'] ?? 'custom';

        $this->menus->saveMenu($menu);

        return new JsonResponse([
            'success' => true,
            'message' => 'Menu created',
            'menu' => $menu->toArray(),
        ], 201);
    }

    /**
     * Update menu
     */
    #[Route('PUT', '/admin/menus/{id}')]
    public function update(int $id, Request $request): JsonResponse
    {
        $menu = $this->menus->getMenu($id);

        if (!$menu) {
            return new JsonResponse(['error' => 'Menu not found'], 404);
        }

        $data = $request->getParsedBody();

        if (isset($data['name'])) {
            $menu->name = $data['name'];
        }
        if (isset($data['machine_name']) && $data['machine_name'] !== $menu->machine_name) {
            if ($this->menus->getMenuByName($data['machine_name'])) {
                return new JsonResponse(['errors' => ['machine_name' => 'Machine name already exists']], 422);
            }
            $menu->machine_name = $data['machine_name'];
        }
        if (isset($data['description'])) {
            $menu->description = $data['description'];
        }
        if (isset($data['location'])) {
            $menu->location = $data['location'];
        }

        $this->menus->saveMenu($menu);

        return new JsonResponse([
            'success' => true,
            'message' => 'Menu updated',
            'menu' => $menu->toArray(),
        ]);
    }

    /**
     * Delete menu
     */
    #[Route('DELETE', '/admin/menus/{id}')]
    public function delete(int $id): JsonResponse
    {
        $menu = $this->menus->getMenu($id);

        if (!$menu) {
            return new JsonResponse(['error' => 'Menu not found'], 404);
        }

        $this->menus->deleteMenu($menu);

        return new JsonResponse([
            'success' => true,
            'message' => 'Menu deleted',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Menu Item Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * Get menu items
     */
    #[Route('GET', '/admin/menus/{menuId}/items')]
    public function listItems(int $menuId, Request $request): JsonResponse
    {
        $menu = $this->menus->getMenu($menuId);

        if (!$menu) {
            return new JsonResponse(['error' => 'Menu not found'], 404);
        }

        $flat = filter_var($request->getQueryParams()['flat'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($flat) {
            $items = $this->menus->getMenuItems($menuId, null, false);
            return new JsonResponse([
                'menu_id' => $menuId,
                'items' => array_map(fn(MenuItem $i) => $i->toArray(), $items),
            ]);
        }

        $tree = $this->menus->getMenuTree($menuId);

        return new JsonResponse([
            'menu_id' => $menuId,
            'tree' => $this->formatTree($tree),
        ]);
    }

    /**
     * Get single menu item
     */
    #[Route('GET', '/admin/menu-items/{id}')]
    public function showItem(int $id): JsonResponse
    {
        $item = $this->menus->getMenuItem($id);

        if (!$item) {
            return new JsonResponse(['error' => 'Menu item not found'], 404);
        }

        return new JsonResponse($item->toArray());
    }

    /**
     * Create menu item
     */
    #[Route('POST', '/admin/menus/{menuId}/items')]
    public function createItem(int $menuId, Request $request): JsonResponse
    {
        $menu = $this->menus->getMenu($menuId);

        if (!$menu) {
            return new JsonResponse(['error' => 'Menu not found'], 404);
        }

        $data = $request->getParsedBody();

        if (empty($data['title'])) {
            return new JsonResponse(['errors' => ['title' => 'Title is required']], 422);
        }

        // Validate parent
        if (!empty($data['parent_id'])) {
            $parent = $this->menus->getMenuItem($data['parent_id']);
            if (!$parent || $parent->menu_id !== $menuId) {
                return new JsonResponse(['errors' => ['parent_id' => 'Invalid parent']], 422);
            }
        }

        $item = new MenuItem();
        $item->menu_id = $menuId;
        $item->parent_id = $data['parent_id'] ?? null;
        $item->title = $data['title'];
        $item->url = $data['url'] ?? null;
        $item->link_type = $data['link_type'] ?? 'custom';
        $item->entity_type = $data['entity_type'] ?? null;
        $item->entity_id = $data['entity_id'] ?? null;
        $item->icon = $data['icon'] ?? null;
        $item->css_class = $data['css_class'] ?? null;
        $item->target = $data['target'] ?? '_self';
        $item->weight = $data['weight'] ?? 0;
        $item->expanded = $data['expanded'] ?? false;
        $item->is_published = $data['is_published'] ?? true;
        $item->attributes = $data['attributes'] ?? [];
        $item->visibility = $data['visibility'] ?? [];

        $this->menus->saveMenuItem($item);

        return new JsonResponse([
            'success' => true,
            'message' => 'Menu item created',
            'item' => $item->toArray(),
        ], 201);
    }

    /**
     * Update menu item
     */
    #[Route('PUT', '/admin/menu-items/{id}')]
    public function updateItem(int $id, Request $request): JsonResponse
    {
        $item = $this->menus->getMenuItem($id);

        if (!$item) {
            return new JsonResponse(['error' => 'Menu item not found'], 404);
        }

        $data = $request->getParsedBody();

        // Validate parent change
        if (isset($data['parent_id']) && $data['parent_id'] !== $item->parent_id) {
            if ($data['parent_id'] !== null) {
                $parent = $this->menus->getMenuItem($data['parent_id']);
                if (!$parent || $parent->menu_id !== $item->menu_id) {
                    return new JsonResponse(['errors' => ['parent_id' => 'Invalid parent']], 422);
                }
                if ($parent->id === $item->id) {
                    return new JsonResponse(['errors' => ['parent_id' => 'Cannot be own parent']], 422);
                }
            }
            $item->parent_id = $data['parent_id'];
        }

        if (isset($data['title'])) $item->title = $data['title'];
        if (isset($data['url'])) $item->url = $data['url'];
        if (isset($data['link_type'])) $item->link_type = $data['link_type'];
        if (isset($data['entity_type'])) $item->entity_type = $data['entity_type'];
        if (isset($data['entity_id'])) $item->entity_id = $data['entity_id'];
        if (isset($data['icon'])) $item->icon = $data['icon'];
        if (isset($data['css_class'])) $item->css_class = $data['css_class'];
        if (isset($data['target'])) $item->target = $data['target'];
        if (isset($data['weight'])) $item->weight = $data['weight'];
        if (isset($data['expanded'])) $item->expanded = $data['expanded'];
        if (isset($data['is_published'])) $item->is_published = $data['is_published'];
        if (isset($data['attributes'])) $item->attributes = $data['attributes'];
        if (isset($data['visibility'])) $item->visibility = $data['visibility'];

        $this->menus->saveMenuItem($item);

        return new JsonResponse([
            'success' => true,
            'message' => 'Menu item updated',
            'item' => $item->toArray(),
        ]);
    }

    /**
     * Delete menu item
     */
    #[Route('DELETE', '/admin/menu-items/{id}')]
    public function deleteItem(int $id, Request $request): JsonResponse
    {
        $item = $this->menus->getMenuItem($id);

        if (!$item) {
            return new JsonResponse(['error' => 'Menu item not found'], 404);
        }

        $deleteChildren = filter_var(
            $request->getQueryParams()['delete_children'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $this->menus->deleteMenuItem($item, $deleteChildren);

        return new JsonResponse([
            'success' => true,
            'message' => 'Menu item deleted',
        ]);
    }

    /**
     * Reorder menu items
     */
    #[Route('PUT', '/admin/menus/{menuId}/items/reorder')]
    public function reorderItems(int $menuId, Request $request): JsonResponse
    {
        $menu = $this->menus->getMenu($menuId);

        if (!$menu) {
            return new JsonResponse(['error' => 'Menu not found'], 404);
        }

        $data = $request->getParsedBody();
        $order = $data['order'] ?? [];

        $this->menus->reorderItems($menuId, $order);

        return new JsonResponse([
            'success' => true,
            'message' => 'Menu items reordered',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Format tree for JSON response
     */
    private function formatTree(array $items): array
    {
        return array_map(function (MenuItem $item) {
            $data = $item->toArray();
            if (!empty($item->children)) {
                $data['children'] = $this->formatTree($item->children);
            }
            return $data;
        }, $items);
    }
}
