<?php

declare(strict_types=1);

namespace App\Cms\Auth;

use MonkeysLegion\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use MonkeysLegion\Http\Message\Response;

/**
 * TwoFactorMiddleware — Intercepts admin requests when 2FA challenge is pending.
 *
 * After a successful password login, if the user has TOTP enabled,
 * the session stores '2fa_pending'. This middleware redirects to
 * the 2FA challenge page until the code is verified.
 *
 * Exempted paths: /admin/login, /admin/2fa/*, /admin/logout
 */
final class TwoFactorMiddleware implements MiddlewareInterface
{
    private const array EXEMPT_PATHS = [
        '/admin/login',
        '/admin/logout',
        '/admin/2fa/challenge',
        '/admin/2fa/verify',
        '/admin/2fa/recovery',
    ];

    public function __construct(
        private readonly SessionManager $session,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Only apply to admin routes
        if (!str_starts_with($path, '/admin')) {
            return $handler->handle($request);
        }

        // Skip exempt paths
        foreach (self::EXEMPT_PATHS as $exempt) {
            if ($path === $exempt) {
                return $handler->handle($request);
            }
        }

        // Check if 2FA challenge is pending
        if ($this->session->get('2fa_pending')) {
            return Response::redirect('/admin/2fa/challenge');
        }

        return $handler->handle($request);
    }
}
