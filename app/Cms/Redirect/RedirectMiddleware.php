<?php

declare(strict_types=1);

namespace App\Cms\Redirect;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use MonkeysLegion\Http\Message\Response;

/**
 * RedirectMiddleware — Intercepts requests and checks for URL redirects.
 *
 * If a redirect is found for the incoming path, returns a 301/302 response.
 * Otherwise, passes the request to the next handler. If the handler returns
 * a 404, tries redirect lookup as a fallback.
 */
final class RedirectMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RedirectService $redirects,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Skip admin and API paths
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/api')) {
            return $handler->handle($request);
        }

        // Check for redirect before handling
        $redirect = $this->redirects->findBySource($path);

        if ($redirect) {
            $this->redirects->recordHit((int) $redirect['id']);

            return new Response(
                status: (int) $redirect['status_code'],
                headers: ['Location' => $redirect['target_path']],
                body: '',
            );
        }

        // Handle normally
        $response = $handler->handle($request);

        // If 404, try redirect as fallback (for paths with/without trailing slash)
        if ($response->getStatusCode() === 404) {
            $altPath = str_ends_with($path, '/')
                ? rtrim($path, '/')
                : $path . '/';

            $redirect = $this->redirects->findBySource($altPath);
            if ($redirect) {
                $this->redirects->recordHit((int) $redirect['id']);
                return new Response(
                    status: (int) $redirect['status_code'],
                    headers: ['Location' => $redirect['target_path']],
                    body: '',
                );
            }
        }

        return $response;
    }
}
