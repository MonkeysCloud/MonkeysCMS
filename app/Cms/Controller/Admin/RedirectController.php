<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Redirect\RedirectService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * RedirectController — Admin UI for managing URL redirects.
 */
#[RoutePrefix('/admin/redirects')]
final class RedirectController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly RedirectService $redirects,
        private readonly SessionManager $session,
    ) {}

    #[Route('GET', '/', name: 'admin::redirects.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $search = trim($params['search'] ?? '');
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $items = $this->redirects->getAll($perPage, $offset, $search ?: null);
        $total = $this->redirects->count($search ?: null);
        $totalPages = (int) ceil($total / $perPage);

        return Response::html($this->renderer->render('admin::redirects.index', [
            'title'        => 'Redirects',
            'items'        => $items,
            'search'       => $search,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'total'        => $total,
            'flashSuccess' => $this->session->getFlash('redirect_success'),
            'flashError'   => $this->session->getFlash('redirect_error'),
        ]));
    }

    #[Route('POST', '/', name: 'admin::redirects.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $body = (array) $request->getParsedBody();
        $source = trim($body['source_path'] ?? '');
        $target = trim($body['target_path'] ?? '');
        $code = (int) ($body['status_code'] ?? 301);

        if ($source === '' || $target === '') {
            $this->session->flash('redirect_error', 'Source and target paths are required.');
            return Response::redirect('/admin/redirects');
        }

        if (!in_array($code, [301, 302], true)) {
            $code = 301;
        }

        try {
            $this->redirects->create($source, $target, $code);
            $this->session->flash('redirect_success', 'Redirect created successfully.');
        } catch (\Throwable $e) {
            $this->session->flash('redirect_error', 'Failed to create redirect: ' . $e->getMessage());
        }

        return Response::redirect('/admin/redirects');
    }

    #[Route('POST', '/{id:\d+}/delete', name: 'admin::redirects.delete')]
    public function delete(ServerRequestInterface $request, string $id): Response
    {
        try {
            $this->redirects->delete((int) $id);
            $this->session->flash('redirect_success', 'Redirect deleted.');
        } catch (\Throwable $e) {
            $this->session->flash('redirect_error', 'Failed to delete redirect.');
        }

        return Response::redirect('/admin/redirects');
    }
}
