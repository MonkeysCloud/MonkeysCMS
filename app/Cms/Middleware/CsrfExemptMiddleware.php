<?php

declare(strict_types=1);

namespace App\Cms\Middleware;

use MonkeysLegion\Session\Middleware\VerifyCsrfToken;
use MonkeysLegion\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Extends the framework CSRF middleware to exempt certain paths.
 *
 * Exempt paths:
 *  - /install/*  (installer API — no session yet)
 *  - /api/*      (API uses token auth, not CSRF)
 *  - /jsonapi/*  (JSON:API uses token auth)
 */
final class CsrfExemptMiddleware implements MiddlewareInterface
{
    /** @var list<string> Path prefixes exempt from CSRF verification */
    private const EXEMPT_PREFIXES = [
        '/install/',
        '/install',
        '/api/',
        '/jsonapi/',
        '/admin/login',
    ];

    private VerifyCsrfToken $csrf;

    public function __construct(SessionManager $manager)
    {
        $this->csrf = new VerifyCsrfToken($manager);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Static prefix exemptions
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix) || $path === $prefix) {
                return $handler->handle($request);
            }
        }

        // Content lock release — exempt for navigator.sendBeacon (no custom headers)
        if (preg_match('#^/admin/content/\d+/lock/release$#', $path)) {
            return $handler->handle($request);
        }

        return $this->csrf->process($request, $handler);
    }
}
