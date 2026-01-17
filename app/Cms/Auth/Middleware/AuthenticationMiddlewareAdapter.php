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
        private \App\Cms\Security\PermissionService $permissions,
        private \App\Cms\Auth\CmsAuthService $authService
    ) {}

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Wrap callable $next into RequestHandlerInterface
        $handler = new class($next, $this->permissions, $this->authService) implements RequestHandlerInterface {
            private $next;
            private $permissions;
            private $authService;

            public function __construct(
                callable $next, 
                \App\Cms\Security\PermissionService $permissions,
                \App\Cms\Auth\CmsAuthService $authService
            ) {
                $this->next = $next;
                $this->permissions = $permissions;
                $this->authService = $authService;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                // Capture user from request attributes set by AuthenticationMiddleware
                $user = $request->getAttribute('user');
                
                // If no user found by standard middleware (expired JWT cookie), try session recovery
                if (!$user) {
                     // Attempt to recover user from active session/remember token
                     // This will look at PHP Session for 'user_id' even if 'auth_token' cookie is expired
                     if ($this->authService->check()) {
                         $user = $this->authService->user();
                         if ($user) {
                             $request = $request->withAttribute('user', $user);
                             // If we recovered the user, we should also ensure the cookie is refreshed for next time
                             // The check() call might have already done this via loadFromSession -> refresh()
                         }
                     }
                }
                
                if ($user instanceof \App\Modules\Core\Entities\User) {
                    $this->permissions->setCurrentUser($user);
                }

                return ($this->next)($request);
            }
        };

        return $this->middleware->process($request, $handler);
    }
}
