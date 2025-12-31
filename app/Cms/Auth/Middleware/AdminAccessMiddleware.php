<?php

declare(strict_types=1);

namespace App\Cms\Auth\Middleware;

use App\Cms\Auth\CmsAuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use MonkeysLegion\Router\Middleware\MiddlewareInterface;

/**
 * AdminAccessMiddleware - Restricts access to admin area
 */
class AdminAccessMiddleware implements MiddlewareInterface
{
    // No dependencies needed if we rely on attributes populated by prior middleware
    public function __construct()
    {
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $user = $request->getAttribute('user');
        
        // Debug
        // file_put_contents('debug_trace.log', date('[Y-m-d H:i:s] ') . '[AdminAccessMiddleware] User Attribute: ' . ($user ? 'Found' : 'NONE') . "\n", FILE_APPEND);

        if (!$user) {
            return new \Nyholm\Psr7\Response(302, ['Location' => '/login']);
        }

        if (!$user->hasPermission('access_admin')) {
            return new \Nyholm\Psr7\Response(403, [], 'Access denied');
        }

        return $next($request);
    }
}
