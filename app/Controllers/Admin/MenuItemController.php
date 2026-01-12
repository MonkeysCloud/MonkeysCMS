<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Services\MenuService;
use App\Modules\Core\Entities\MenuItem;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MenuItemController - Admin API for menu item management
 */
final class MenuItemController extends BaseAdminController
{
    public function __construct(
        protected readonly MenuService $menus,
        \MonkeysLegion\Template\MLView $view,
        MenuService $menuService,
    ) {
        parent::__construct($view, $menuService);
    }

    /**
     * Create Menu Item Form
     */
    #[Route('GET', '/admin/menus/{menuId}/items/create')]
    public function create(int $menuId): ResponseInterface
    {
        $menu = $this->menus->getMenu($menuId);
        if (!$menu) {
            return $this->redirect('/admin/menus');
        }

        // Get flattening list for parent selection
        $allItems = $this->menus->getMenuItems($menuId, null, false);
        
        return $this->render('admin.menus.item_form', [
            'menu' => $menu,
            'item' => new MenuItem(),
            'parentOptions' => $this->buildParentOptions($allItems),
            'isNew' => true,
        ]);
    }

    /**
     * Store Menu Item
     */
    #[Route('POST', '/admin/menus/{menuId}/items')]
    public function store(int $menuId, ServerRequestInterface $request): ResponseInterface
    {
        $menu = $this->menus->getMenu($menuId);
        if (!$menu) {
            return $this->redirect('/admin/menus');
        }

        $data = (array) $request->getParsedBody();
        if (empty($data)) { $data = $_POST; }

        if (empty($data['title'])) {
             // TODO: Flash error
             return $this->redirect("/admin/menus/{$menuId}/items/create");
        }

        $item = new MenuItem();
        $item->menu_id = $menuId;
        $this->hydrateItem($item, $data);

        $this->menus->saveMenuItem($item);

        return $this->redirect("/admin/menus/{$menuId}/edit");
    }

    /**
     * Edit Menu Item Form
     */
    #[Route('GET', '/admin/menus/{menuId}/items/{id}/edit')]
    public function edit(int $menuId, int $id): ResponseInterface
    {
        $menu = $this->menus->getMenu($menuId);
        $item = $this->menus->getMenuItem($id);

        if (!$menu || !$item || $item->menu_id !== $menuId) {
            return $this->redirect('/admin/menus');
        }

        $allItems = $this->menus->getMenuItems($menuId, null, false);

        return $this->render('admin.menus.item_form', [
            'menu' => $menu,
            'item' => $item,
            'parentOptions' => $this->buildParentOptions($allItems, $item->id),
            'isNew' => false,
        ]);
    }

    /**
     * Update Menu Item
     */
    #[Route('POST', '/admin/menus/{menuId}/items/{id}')]
    public function update(int $menuId, int $id, ServerRequestInterface $request): ResponseInterface
    {
        $item = $this->menus->getMenuItem($id);
        
        if (!$item || $item->menu_id !== $menuId) {
            return $this->redirect('/admin/menus');
        }

        $data = (array) $request->getParsedBody();
        if (empty($data)) { $data = $_POST; }

        $this->hydrateItem($item, $data);
        $this->menus->saveMenuItem($item);

        return $this->redirect("/admin/menus/{$menuId}/edit");
    }

    /**
     * Delete Menu Item
     */
    #[Route('GET', '/admin/menus/{menuId}/items/{id}/delete')]
    public function delete(int $menuId, int $id): ResponseInterface
    {
        $item = $this->menus->getMenuItem($id);
        if ($item && $item->menu_id === $menuId) {
            $this->menus->deleteMenuItem($item);
        }
        return $this->redirect("/admin/menus/{$menuId}/edit");
    }

    /**
     * Helper to hydrate item from POST data
     */
    private function hydrateItem(MenuItem $item, array $data): void
    {
        $item->title = $data['title'];
        $item->url = $data['url'] ?? null;
        $item->link_type = $data['link_type'] ?? 'custom';
        $item->parent_id = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        $item->weight = (int)($data['weight'] ?? 0);
        $item->target = $data['target'] ?? '_self';
        $item->icon = $data['icon'] ?? null;
        $item->expanded = isset($data['expanded']);
        $item->is_published = isset($data['is_published']);
    }

    /**
     * Build options for parent select to avoid circular references and show hierarchy
     */
    private function buildParentOptions(array $items, ?int $excludeId = null, int $depth = 0, array &$options = []): array
    {
        // Simple flat list for now, ideally we rebuild tree to visualize depth
        // But for select box, we can just do simple loop and filter
        
        // Let's rely on string representation for hierarchy if possible, 
        // or just list them. A proper tree builder is better but let's start simple.
        
        foreach ($items as $item) {
            if ($item->id === $excludeId) {
                continue;
            }
            $prefix = str_repeat('-- ', $item->depth);
            $options[$item->id] = $prefix . $item->title;
        }
        
        return $options;
    }


}
