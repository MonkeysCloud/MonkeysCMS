<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Auth\SessionManager;
use App\Cms\Auth\AuthServiceProvider;
use App\Cms\Security\PermissionService;
use App\Modules\Core\Services\MenuService;
use MonkeysLegion\Template\MLView;
use Laminas\Diactoros\Response\HtmlResponse;
use MonkeysLegion\Router\Attributes\Middleware;
use Psr\Http\Message\ResponseInterface;
use App\Cms\Auth\Middleware\AuthenticationMiddlewareAdapter;
use App\Cms\Auth\Middleware\AdminAccessMiddleware;

use App\Cms\Assets\AssetManager;

/**
 * Base Admin Controller
 */
#[Middleware([AuthenticationMiddlewareAdapter::class, AdminAccessMiddleware::class])]
abstract class BaseAdminController
{
    protected ?AssetManager $assets = null;

    public function __construct(
        protected readonly MLView $view,
        protected readonly MenuService $menuService,
        protected readonly ?SessionManager $session = null,
    ) {
    }

    public function setAssetManager(AssetManager $assets): void
    {
        $this->assets = $assets;
        
        // Attach Global Admin Assets
        $this->assets->attach('monkeyscms'); // includes htmx
        $this->assets->addFile('/js/confirmation-modal.js');
        $this->assets->addFile('/js/media-edit.js');
        $this->assets->addFile('/js/media-bulk.js');
        
        // Alpine must be loaded after components (or components loaded before Alpine init)
        $this->assets->attach('alpine');
    }

    /**
     * Render an admin view with common data (menu, user, csrf, etc)
     */
    protected function render(string $view, array $data = []): ResponseInterface
    {
        // Fetch Admin Menu
        $adminMenu = $this->menuService->getMenuByNameWithItems('admin');
        $menuTree = $adminMenu ? $adminMenu->getItemTree() : [];

        // Get current authenticated user from CmsAuthService (reads from session)
        // LoginController now stores session data after successful login
        $cmsAuth = AuthServiceProvider::getCmsAuthService();
        $currentUser = $cmsAuth->user();

        $commonData = [
            'admin_menu_tree' => $menuTree,
            'csrf_token' => $this->session?->getCsrfToken() ?? '',
            'assets' => $this->assets,
            'current_user' => $currentUser,
        ];

        $html = $this->view->render($view, array_merge($commonData, $data));

        return new HtmlResponse($html);
    }
    /**
     * Helper to create a redirect response
     */
    protected function redirect(string $url): ResponseInterface
    {
        return new \Laminas\Diactoros\Response\RedirectResponse($url);
    }
}

