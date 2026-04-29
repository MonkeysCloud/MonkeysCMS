<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MediaController — Admin UI for the media library.
 */
#[RoutePrefix('/admin/media')]
final class MediaController
{
    public function __construct(
        private readonly Renderer $renderer,
    ) {}

    #[Route('GET', '/', name: 'admin.media.index')]
    public function index(ServerRequestInterface $request): Response
    {
        return Response::html($this->renderer->render('admin.media.index', [
            'title' => 'Media Library',
        ]));
    }
}
