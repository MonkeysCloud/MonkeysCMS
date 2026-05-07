<?php

declare(strict_types=1);

namespace App\Cms\Middleware;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * AdminAuthMiddleware — Guards all /admin/* routes.
 *
 * Redirects unauthenticated users to /admin/login.
 * Exempts login/logout and non-admin routes.
 */
final class AdminAuthMiddleware implements MiddlewareInterface
{
    /** Routes that don't require authentication */
    private const EXEMPT = [
        '/admin/login',
        '/admin/logout',
    ];

    public function __construct(
        private readonly SessionManager $session,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Only guard /admin/* routes
        if (!str_starts_with($path, '/admin')) {
            return $handler->handle($request);
        }

        // Allow exempt routes
        foreach (self::EXEMPT as $exempt) {
            if ($path === $exempt) {
                return $handler->handle($request);
            }
        }

        // Check SessionManager for logged-in user
        if ($this->session->get('cms_user_id')) {
            return $handler->handle($request);
        }

        // Store intended URL for redirect after login
        if ($path !== '/admin') {
            $this->session->set('cms_intended_url', $path);
        }

        return Response::redirect('/admin/login');
    }
}
