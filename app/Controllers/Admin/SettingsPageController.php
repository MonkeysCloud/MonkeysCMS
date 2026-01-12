<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Services\SettingsService;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * SettingsPageController - UI for managing settings
 */
final class SettingsPageController extends BaseAdminController
{
    public function __construct(
        \MonkeysLegion\Template\MLView $view,
        \App\Modules\Core\Services\MenuService $menuService,
        private readonly SettingsService $settings
    ) {
        parent::__construct($view, $menuService);
    }

    /**
     * Settings Dashboard
     */
    #[Route('GET', '/admin/settings')]
    public function index(): ResponseInterface
    {
        return $this->redirect('/admin/settings/users');
    }

    /**
     * User Global Configuration
     */
    #[Route('GET', '/admin/settings/users')]
    public function users(): ResponseInterface
    {
        $sessionLifetime = $this->settings->get('auth.session_lifetime', 7200);
        $sessionSecure = $this->settings->get('auth.session_secure', true);

        return $this->render('admin/settings/users', [
            'title' => 'User Settings',
            'session_lifetime' => $sessionLifetime,
            'session_secure' => $sessionSecure,
        ]);
    }

    /**
     * Save User Configuration
     */
    #[Route('POST', '/admin/settings/users')]
    public function saveUsers(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        $lifetime = (int) ($data['session_lifetime'] ?? 7200);
        $secure = isset($data['session_secure']); // Checkbox

        $this->settings->set('auth.session_lifetime', $lifetime, 'int');
        $this->settings->set('auth.session_secure', $secure, 'bool');

        return $this->redirect('/admin/settings/users?success=1');
    }
}
