<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Form\FormBuilder;
use App\Cms\Form\FormRenderer;
use App\Cms\Menu\MenuEntity;
use App\Cms\Menu\MenuItemEntity;
use App\Cms\Menu\MenuManager;
use App\Cms\Menu\MenuRepository;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MenuController — Admin UI for menu management.
 *
 * Supports full CRUD for menus and their items, including
 * AJAX endpoints for drag-and-drop reordering and inline item editing.
 */
#[RoutePrefix('/admin/menus')]
final class MenuController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly MenuRepository $repo,
        private readonly MenuManager $menuManager,
        private readonly SessionManager $session,
        private readonly FormRenderer $formRenderer,
    ) {}

    // ── List ────────────────────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::menus.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $menusWithCounts = $this->repo->findAllWithItemCounts();

        return Response::html($this->renderer->render('admin::menus.index', [
            'title'          => 'Menus',
            'menusWithCounts' => $menusWithCounts,
        ]));
    }

    // ── Create ──────────────────────────────────────────────────────────

    #[Route('GET', '/create', name: 'admin::menus.create')]
    public function create(ServerRequestInterface $request): Response
    {
        $form = $this->buildMenuForm(null);

        return Response::html($this->renderer->render('admin::menus.form', [
            'title'     => 'Create Menu',
            'menu'      => null,
            'items'     => [],
            'isNew'     => true,
            'form'      => $form,
            'formHtml'  => $this->formRenderer->render($form),
            'errors'    => [],
        ]));
    }

    #[Route('POST', '/', name: 'admin::menus.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $form = $this->buildMenuForm(null);
        $validation = $form->validate($body);

        if (!$validation->isValid()) {
            return Response::html($this->renderer->render('admin::menus.form', [
                'title'    => 'Create Menu',
                'menu'     => $body,
                'items'    => [],
                'isNew'    => true,
                'form'     => $form,
                'formHtml' => $this->formRenderer->render($form),
                'errors'   => $validation->getErrors(),
            ]));
        }

        $menu = new MenuEntity();
        $menu->label = trim($body['label']);
        $menu->machine_name = $this->machineName($body['machine_name'] ?? '', $body['label']);
        $menu->description = $body['description'] ?? null;
        $menu->enabled = (bool) ($body['enabled'] ?? true);

        $menu = $this->repo->persistMenu($menu);
        $this->menuManager->clearCache();

        return Response::redirect('/admin/menus/' . $menu->id . '/edit');
    }

    // ── Edit ────────────────────────────────────────────────────────────

    #[Route('GET', '/{id:\d+}/edit', name: 'admin::menus.edit')]
    public function edit(ServerRequestInterface $request, string $id): Response
    {
        $menu = $this->repo->findById((int) $id);
        if (!$menu) {
            return Response::redirect('/admin/menus');
        }

        $form = $this->buildMenuForm($menu);
        $items = $this->repo->findItemsByMenu($menu->id);

        // Build tree for display
        $tree = $this->buildItemTree($items);

        return Response::html($this->renderer->render('admin::menus.form', [
            'title'    => 'Edit Menu: ' . $menu->label,
            'menu'     => $menu,
            'items'    => $items,
            'tree'     => $tree,
            'isNew'    => false,
            'form'     => $form,
            'formHtml' => $this->formRenderer->render($form),
            'errors'   => [],
        ]));
    }

    #[Route('POST', '/{id:\d+}', name: 'admin::menus.update')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        $menuId = (int) $id;
        $menu = $this->repo->findById($menuId);
        if (!$menu) {
            return Response::redirect('/admin/menus');
        }

        $body = $this->parseBody($request);

        $menu->label = trim($body['label']);
        $menu->machine_name = $this->machineName($body['machine_name'] ?? '', $body['label']);
        $menu->description = $body['description'] ?? null;
        $menu->enabled = (bool) ($body['enabled'] ?? false);

        $this->repo->persistMenu($menu);
        $this->menuManager->clearCache();

        return Response::redirect('/admin/menus/' . $menuId . '/edit');
    }

    // ── Delete ──────────────────────────────────────────────────────────

    #[Route('POST', '/{id:\d+}/delete', name: 'admin::menus.delete')]
    public function delete(ServerRequestInterface $request, string $id): Response
    {
        $this->repo->deleteMenu((int) $id);
        $this->menuManager->clearCache();

        return Response::redirect('/admin/menus');
    }

    // ── Item CRUD (AJAX) ────────────────────────────────────────────────

    #[Route('POST', '/{id:\d+}/items', name: 'admin::menus.items.store')]
    public function storeItem(ServerRequestInterface $request, string $id): Response
    {
        $menuId = (int) $id;
        $menu = $this->repo->findById($menuId);
        if (!$menu) {
            return Response::json(['error' => 'Menu not found'], 404);
        }

        $body = $this->parseBody($request);

        $item = new MenuItemEntity();
        $item->menu_id = $menuId;
        $item->title = trim($body['title'] ?? '');
        $item->url = trim($body['url'] ?? '') ?: null;
        $item->icon = trim($body['icon'] ?? '') ?: null;
        $item->target = $body['target'] ?? null;
        $item->parent_id = !empty($body['parent_id']) ? (int) $body['parent_id'] : null;
        $item->weight = (int) ($body['weight'] ?? 0);
        $item->enabled = (bool) ($body['enabled'] ?? true);

        if ($item->title === '') {
            return Response::json(['error' => 'Title is required'], 422);
        }

        // If editing existing
        if (!empty($body['item_id'])) {
            $item->id = (int) $body['item_id'];
        }

        $item = $this->repo->persistItem($item);
        $this->menuManager->clearCache();

        return Response::json([
            'success' => true,
            'item'    => $item->toArray(),
        ]);
    }

    #[Route('POST', '/{id:\d+}/items/{itemId:\d+}/delete', name: 'admin::menus.items.delete')]
    public function deleteItem(ServerRequestInterface $request, string $id, string $itemId): Response
    {
        $this->repo->deleteItem((int) $itemId);
        $this->menuManager->clearCache();

        return Response::json(['success' => true]);
    }

    #[Route('POST', '/{id:\d+}/reorder', name: 'admin::menus.reorder')]
    public function reorder(ServerRequestInterface $request, string $id): Response
    {
        $menuId = (int) $id;
        $body = $this->parseBody($request);
        $order = $body['order'] ?? [];

        if (!is_array($order)) {
            return Response::json(['error' => 'Invalid order data'], 422);
        }

        $this->repo->reorderItems($menuId, $order);
        $this->menuManager->clearCache();

        return Response::json(['success' => true]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function buildMenuForm(?MenuEntity $menu): \App\Cms\Form\Form
    {
        $isEdit = $menu !== null && $menu->id !== null;
        $action = $isEdit ? '/admin/menus/' . $menu->id : '/admin/menus';

        $builder = FormBuilder::create($action, 'POST')
            ->id('menu-form')
            ->group('details', 'Menu Details', 'menu')
            ->text('label', 'Menu Name')
                ->value($menu?->label ?? '')
                ->required()
                ->placeholder('e.g. Main Navigation')
                ->inGroup('details')
            ->text('machine_name', 'Machine Name')
                ->value($menu?->machine_name ?? '')
                ->placeholder('Auto-generated from name')
                ->help('Lowercase letters, numbers and underscores only.')
                ->inGroup('details')
            ->textarea('description', 'Description')
                ->value($menu?->description ?? '')
                ->placeholder('Brief description of this menu')
                ->inGroup('details')
            ->toggle('enabled', 'Enabled')
                ->value($isEdit ? (int) $menu->enabled : 1)
                ->help('Disabled menus are hidden from the frontend.')
                ->inGroup('details')
            ->submit($isEdit ? 'Save Changes' : 'Create Menu', 'save')
            ->cancel('/admin/menus');

        return $builder->build($this->session);
    }

    /**
     * Build a nested tree from flat items list.
     *
     * @param MenuItemEntity[] $items
     * @return MenuItemEntity[]
     */
    private function buildItemTree(array $items): array
    {
        $lookup = [];
        foreach ($items as $item) {
            $lookup[$item->id] = $item;
            $item->children = [];
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

    private function machineName(string $raw, string $fallback): string
    {
        $name = $raw ?: $fallback;
        $name = strtolower(trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_'));
        return $name;
    }

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody() ?? [];
        if (empty($body)) {
            $stream = $request->getBody();
            $stream->rewind();
            $raw = $stream->getContents();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
        return $body;
    }
}
