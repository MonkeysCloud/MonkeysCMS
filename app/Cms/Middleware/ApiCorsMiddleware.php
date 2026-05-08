<?php

declare(strict_types=1);

namespace App\Cms\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ApiCorsMiddleware — CORS headers for the public JSON:API.
 *
 * Configured via environment variables:
 *   - API_CORS_ORIGINS: comma-separated allowed origins (default: *)
 *   - API_CORS_MAX_AGE: preflight cache duration (default: 86400)
 */
final class ApiCorsMiddleware implements MiddlewareInterface
{
    private readonly string $allowedOrigins;
    private readonly int $maxAge;

    public function __construct()
    {
        $this->allowedOrigins = $_ENV['API_CORS_ORIGINS'] ?? '*';
        $this->maxAge = (int) ($_ENV['API_CORS_MAX_AGE'] ?? 86400);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Handle preflight
        if ($request->getMethod() === 'OPTIONS') {
            return $this->addCorsHeaders(
                new \MonkeysLegion\Http\Message\Response(204),
                $request,
            );
        }

        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $request);
    }

    private function addCorsHeaders(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // Determine allowed origin
        $allowOrigin = $this->allowedOrigins;
        if ($this->allowedOrigins !== '*' && $origin) {
            $allowed = array_map('trim', explode(',', $this->allowedOrigins));
            $allowOrigin = in_array($origin, $allowed, true) ? $origin : '';
        }

        if (!$allowOrigin) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Key, X-Requested-With, Accept')
            ->withHeader('Access-Control-Expose-Headers', 'X-Total-Count, X-Page-Count')
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge)
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}
