<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Auth\SessionManager;
use App\Modules\Core\Services\MenuService;
use MonkeysLegion\Template\MLView;
use Laminas\Diactoros\Response\HtmlResponse;
use MonkeysLegion\Router\Attributes\Middleware;
use Psr\Http\Message\ResponseInterface;
use App\Cms\Auth\Middleware\AuthenticationMiddlewareAdapter;
use App\Cms\Auth\Middleware\AdminAccessMiddleware;

/**
 * Base Admin Controller
 */
#[Middleware([AuthenticationMiddlewareAdapter::class, AdminAccessMiddleware::class])]
abstract class BaseAdminController
{
    public function __construct(
        protected readonly MLView $view,
        protected readonly MenuService $menuService,
        protected readonly ?SessionManager $session = null,
    ) {
    }

    /**
     * Render an admin view with common data (menu, user, csrf, etc)
     */
    protected function render(string $view, array $data = []): ResponseInterface
    {
        // Fetch Admin Menu
        $adminMenu = $this->menuService->getMenuByNameWithItems('admin');
        $menuTree = $adminMenu ? $adminMenu->getItemTree() : [];

        $commonData = [
            'admin_menu_tree' => $menuTree,
            'csrf_token' => $this->session?->getCsrfToken() ?? '',
        ];

        $html = $this->view->render($view, array_merge($commonData, $data));

        return new HtmlResponse($html);
    }
}
