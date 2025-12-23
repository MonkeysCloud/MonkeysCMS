<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Services\MenuService;
use App\Modules\Core\Entities\Menu;
use App\Modules\Core\Entities\MenuItem;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MenuController - Admin API for menu management
 */
final class MenuController extends BaseAdminController
{
    public function __construct(
        protected readonly MenuService $menus,
        \MonkeysLegion\Template\MLView $view,
        MenuService $menuService,
    ) {
        parent::__construct($view, $menuService);
    }

    // ─────────────────────────────────────────────────────────────
    // Menu Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * List all menus
     */
    #[Route('GET', '/admin/menus')]
    public function index(): ResponseInterface
    {
        $allMenus = $this->menus->getAllMenus();

        return $this->render('admin.menus.index', [
            'menus' => $allMenus,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // HTML Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * Create Menu Form
     */
    #[Route('GET', '/admin/menus/create')]
    public function create(): ResponseInterface
    {
        return $this->render('admin.menus.form', [
            'menu' => new Menu(),
            'isNew' => true,
        ]);
    }

    /**
     * Store Menu
     */
    #[Route('POST', '/admin/menus')]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        // Fallback if parsed body is empty (depends on middleware)
        if (empty($data)) {
            $data = $_POST; 
        }

        if (empty($data['name'])) {
            // TODO: Flash error
            return $this->redirect('/admin/menus/create');
        }

        $machineName = !empty($data['machine_name']) 
            ? $data['machine_name'] 
            : strtolower(preg_replace('/[^a-z0-9]+/', '_', $data['name']));

        if ($this->menus->getMenuByName($machineName)) {
            // TODO: Flash error
            return $this->redirect('/admin/menus/create');
        }

        $menu = new Menu();
        $menu->name = $data['name'];
        $menu->machine_name = $machineName;
        $menu->description = $data['description'] ?? '';
        $menu->location = $data['location'] ?? 'custom';

        $this->menus->saveMenu($menu);

        return $this->redirect('/admin/menus');
    }

    /**
     * Edit Menu Form
     */
    #[Route('GET', '/admin/menus/{id}/edit')]
    public function edit(int $id): ResponseInterface
    {
        $menu = $this->menus->getMenuWithItems($id);

        if (!$menu) {
            return $this->redirect('/admin/menus');
        }

        return $this->render('admin.menus.form', [
            'menu' => $menu,
            'isNew' => false,
            // 'items' => $menu->getItemTree(), // Pass if we want to manage items in same form
        ]);
    }

    /**
     * Update Menu
     */
    #[Route('POST', '/admin/menus/{id}')] // Using POST for update to avoid method spoofing issues if not setup
    public function update(int $id, ServerRequestInterface $request): ResponseInterface
    {
        $menu = $this->menus->getMenu($id);
        if (!$menu) {
            return $this->redirect('/admin/menus');
        }

        $data = (array) $request->getParsedBody();
         if (empty($data)) { $data = $_POST; }

        if (!empty($data['name'])) {
            $menu->name = $data['name'];
        }
        
        if (!empty($data['machine_name'])) {
            $menu->machine_name = $data['machine_name'];
        }

        if (isset($data['description'])) {
            $menu->description = $data['description'];
        }
        
        if (isset($data['location'])) {
            $menu->location = $data['location'];
        }

        $this->menus->saveMenu($menu);

        return $this->redirect('/admin/menus');
    }

    /**
     * Delete Menu
     */
    #[Route('GET', '/admin/menus/{id}/delete')] // GET for simple link delete, usually unsfae but quick
    public function delete(int $id): ResponseInterface
    {
        $menu = $this->menus->getMenu($id);
        if ($menu) {
            $this->menus->deleteMenu($menu);
        }
        return $this->redirect('/admin/menus');
    }

    private function redirect(string $url): ResponseInterface
    {
        return new \Laminas\Diactoros\Response\RedirectResponse($url);
    }
}
