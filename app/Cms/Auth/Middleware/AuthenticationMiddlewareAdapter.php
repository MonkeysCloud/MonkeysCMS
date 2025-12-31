<?php

declare(strict_types=1);

namespace App\Cms\Auth\Middleware;

use MonkeysLegion\Auth\Middleware\AuthenticationMiddleware;
use MonkeysLegion\Router\Middleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adapter to make MonkeysLegion Auth Middleware compatible with Router MiddlewareInterface
 */
class AuthenticationMiddlewareAdapter implements MiddlewareInterface
{
    public function __construct(
        private AuthenticationMiddleware $middleware,
        private \App\Cms\Security\PermissionService $permissions
    ) {}

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Wrap callable $next into RequestHandlerInterface
        $handler = new class($next, $this->permissions) implements RequestHandlerInterface {
            private $next;
            private $permissions;

            public function __construct(callable $next, \App\Cms\Security\PermissionService $permissions)
            {
                $this->next = $next;
                $this->permissions = $permissions;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                // Capture user from request attributes set by AuthenticationMiddleware
                $user = $request->getAttribute('user');
                
                if ($user instanceof \App\Modules\Core\Entities\User) {
                    $this->permissions->setCurrentUser($user);
                }

                return ($this->next)($request);
            }
        };

        return $this->middleware->process($request, $handler);
    }
}
