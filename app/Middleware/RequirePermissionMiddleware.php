<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Cms\Security\PermissionService;
use MonkeysLegion\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * RequirePermissionMiddleware - Checks for specific permission before allowing access
 * 
 * Usage in routes:
 *   #[Route('GET', '/admin/users', middleware: ['permission:view_users'])]
 */
final class RequirePermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly string $requiredPermission,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check if user is authenticated
        $user = $this->permissions->getCurrentUser();
        
        if ($user === null) {
            return new JsonResponse([
                'error' => 'Authentication required',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // Check permission
        if (!$this->permissions->can($this->requiredPermission)) {
            return new JsonResponse([
                'error' => 'Access denied',
                'code' => 'FORBIDDEN',
                'required_permission' => $this->requiredPermission,
            ], 403);
        }

        return $handler->handle($request);
    }

    /**
     * Factory method to create middleware with specific permission
     */
    public static function requiring(string $permission, PermissionService $permissions): self
    {
        return new self($permissions, $permission);
    }
}
