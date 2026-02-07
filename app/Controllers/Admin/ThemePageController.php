<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Themes\ThemeManager;
use App\Modules\Core\Services\MenuService;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Template\MLView;
use Psr\Http\Message\ResponseInterface;

/**
 * ThemePageController - Admin HTML pages for theme management
 */
final class ThemePageController extends BaseAdminController
{
    public function __construct(
        private readonly ThemeManager $themeManager,
        MLView $view,
        MenuService $menuService,
    ) {
        parent::__construct($view, $menuService);
    }

    /**
     * Theme management dashboard
     */
    #[Route('GET', '/admin/themes')]
    public function index(): ResponseInterface
    {
        return $this->render('admin.themes.index', [
            'title' => 'Themes',
        ]);
    }
}
