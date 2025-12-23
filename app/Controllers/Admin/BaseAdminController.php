<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Services\MenuService;
use MonkeysLegion\Template\MLView;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;

abstract class BaseAdminController
{
    public function __construct(
        protected readonly MLView $view,
        protected readonly MenuService $menuService,
    ) {
    }

    /**
     * Render an admin view with common data (menu, user, etc)
     */
    protected function render(string $view, array $data = []): ResponseInterface
    {
        // Fetch Admin Menu
        $adminMenu = $this->menuService->getMenuByNameWithItems('admin');
        $menuTree = $adminMenu ? $adminMenu->getItemTree() : [];

        $commonData = [
            'admin_menu_tree' => $menuTree,
        ];

        $html = $this->view->render($view, array_merge($commonData, $data));

        return new HtmlResponse($html);
    }
}
