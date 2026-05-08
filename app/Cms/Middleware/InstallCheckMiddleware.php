<?php

declare(strict_types=1);

namespace App\Cms\Middleware;

use MonkeysLegion\Http\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Redirects all non-install requests to /install if CMS is not installed.
 *
 * Also marks install API routes as CSRF-exempt by injecting a request
 * attribute that downstream middleware can check.
 */
final class InstallCheckMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Always allow static assets through
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|map)$/i', $path)) {
            return $handler->handle($request);
        }

        // Check if installed
        $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : '';

        if ($basePath && !file_exists($basePath . '/storage/.installed')) {
            // Allow installer routes through
            if (str_starts_with($path, '/install')) {
                // Mark install API requests as CSRF-exempt
                $request = $request->withAttribute('csrf_exempt', true);
                return $handler->handle($request);
            }

            // Everything else → redirect to installer
            return Response::redirect('/install');
        }

        return $handler->handle($request);
    }
}
