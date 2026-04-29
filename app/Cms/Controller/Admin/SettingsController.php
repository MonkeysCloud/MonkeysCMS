<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * SettingsController — Admin UI for CMS settings.
 */
#[RoutePrefix('/admin/settings')]
final class SettingsController
{
    public function __construct(
        private readonly Renderer $renderer,
    ) {}

    #[Route('GET', '/', name: 'admin.settings.index')]
    public function index(ServerRequestInterface $request): Response
    {
        return Response::html($this->renderer->render('admin.settings.index', [
            'title' => 'Settings',
        ]));
    }
}
