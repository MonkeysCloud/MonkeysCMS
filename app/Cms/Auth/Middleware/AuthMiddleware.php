<?php

declare(strict_types=1);

namespace App\Cms\Auth\Middleware;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\SessionManager;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * AuthMiddleware - Verifies user authentication
 * 
 * Features:
 * - JWT token validation
 * - Session-based authentication
 * - Public path exclusion
 * - Guest-only routes
 */
class AuthMiddleware implements MiddlewareInterface
{
    private CmsAuthService $auth;
    private SessionManager $session;
    private array $publicPaths;
    private array $guestOnlyPaths;

    public function __construct(
        CmsAuthService $auth,
        SessionManager $session,
        array $publicPaths = [],
        array $guestOnlyPaths = []
    ) {
        $this->auth = $auth;
        $this->session = $session;
        $this->publicPaths = $publicPaths;
        $this->guestOnlyPaths = $guestOnlyPaths;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Check if path is public
        if ($this->isPublicPath($path)) {
            return $handler->handle($request);
        }

        // Check if guest-only path
        if ($this->isGuestOnlyPath($path)) {
            if ($this->auth->check()) {
                return $this->redirectToDashboard();
            }
            return $handler->handle($request);
        }

        // Validate authentication
        if (!$this->auth->check()) {
            return $this->handleUnauthenticated($request);
        }

        // Add user to request
        $request = $request->withAttribute('user', $this->auth->user());
        $request = $request->withAttribute('auth', $this->auth);

        return $handler->handle($request);
    }

    /**
     * Check if path matches public paths
     */
    private function isPublicPath(string $path): bool
    {
        foreach ($this->publicPaths as $pattern) {
            if ($this->pathMatches($path, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if path matches guest-only paths
     */
    private function isGuestOnlyPath(string $path): bool
    {
        foreach ($this->guestOnlyPaths as $pattern) {
            if ($this->pathMatches($path, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if path matches a pattern (supports wildcards)
     */
    private function pathMatches(string $path, string $pattern): bool
    {
        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Wildcard match
        if (str_contains($pattern, '*')) {
            $regex = str_replace(['/', '*'], ['\/', '.*'], $pattern);
            return (bool) preg_match('/^' . $regex . '$/', $path);
        }

        return false;
    }

    /**
     * Handle unauthenticated request
     */
    private function handleUnauthenticated(ServerRequestInterface $request): ResponseInterface
    {
        // Store intended URL
        $this->session->setIntendedUrl((string) $request->getUri());

        // Check if API request
        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return $this->jsonResponse(['error' => 'Unauthenticated'], 401);
        }

        // Redirect to login
        return $this->redirect('/login');
    }

    /**
     * Redirect to dashboard
     */
    private function redirectToDashboard(): ResponseInterface
    {
        return $this->redirect('/admin');
    }

    /**
     * Create redirect response
     */
    private function redirect(string $url, int $status = 302): ResponseInterface
    {
        return new \Nyholm\Psr7\Response($status, ['Location' => $url]);
    }

    /**
     * Create JSON response
     */
    private function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        return new \Nyholm\Psr7\Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );
    }
}

/**
 * RequirePermissionMiddleware - Checks for specific permissions
 */
class RequirePermissionMiddleware implements MiddlewareInterface
{
    private CmsAuthService $auth;
    private array $permissions;

    public function __construct(CmsAuthService $auth, array $permissions)
    {
        $this->auth = $auth;
        $this->permissions = $permissions;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->auth->user();

        if (!$user) {
            return $this->unauthorized();
        }

        foreach ($this->permissions as $permission) {
            if (!$user->hasPermission($permission)) {
                return $this->forbidden("Missing permission: {$permission}");
            }
        }

        return $handler->handle($request);
    }

    private function unauthorized(): ResponseInterface
    {
        return new \Nyholm\Psr7\Response(401, [], 'Unauthorized');
    }

    private function forbidden(string $message = 'Forbidden'): ResponseInterface
    {
        return new \Nyholm\Psr7\Response(403, [], $message);
    }
}

/**
 * RequireRoleMiddleware - Checks for specific roles
 */
class RequireRoleMiddleware implements MiddlewareInterface
{
    private CmsAuthService $auth;
    private array $roles;
    private bool $requireAll;

    public function __construct(CmsAuthService $auth, array $roles, bool $requireAll = false)
    {
        $this->auth = $auth;
        $this->roles = $roles;
        $this->requireAll = $requireAll;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->auth->user();

        if (!$user) {
            return new \Nyholm\Psr7\Response(401, [], 'Unauthorized');
        }

        if ($this->requireAll) {
            // Must have all roles
            foreach ($this->roles as $role) {
                if (!$user->hasRole($role)) {
                    return new \Nyholm\Psr7\Response(403, [], 'Forbidden');
                }
            }
        } else {
            // Must have at least one role
            $hasRole = false;
            foreach ($this->roles as $role) {
                if ($user->hasRole($role)) {
                    $hasRole = true;
                    break;
                }
            }
            if (!$hasRole) {
                return new \Nyholm\Psr7\Response(403, [], 'Forbidden');
            }
        }

        return $handler->handle($request);
    }
}

/**
 * CsrfMiddleware - Validates CSRF tokens
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private SessionManager $session;
    private array $excludePaths;

    public function __construct(SessionManager $session, array $excludePaths = [])
    {
        $this->session = $session;
        $this->excludePaths = $excludePaths;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();

        // Only check for state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $handler->handle($request);
        }

        // Check if path is excluded
        $path = $request->getUri()->getPath();
        foreach ($this->excludePaths as $pattern) {
            if (str_starts_with($path, $pattern)) {
                return $handler->handle($request);
            }
        }

        // Get token from request
        $token = $this->getTokenFromRequest($request);

        if (!$token || !$this->session->verifyCsrfToken($token)) {
            return new \Nyholm\Psr7\Response(419, [], 'CSRF token mismatch');
        }

        return $handler->handle($request);
    }

    private function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        // Check header
        $token = $request->getHeaderLine('X-CSRF-TOKEN');
        if ($token) {
            return $token;
        }

        // Check body
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['_token'])) {
            return $body['_token'];
        }

        return null;
    }
}

/**
 * AdminMiddleware - Restricts access to admin area
 */
class AdminMiddleware implements MiddlewareInterface
{
    private CmsAuthService $auth;

    public function __construct(CmsAuthService $auth)
    {
        $this->auth = $auth;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->auth->user();

        if (!$user) {
            return new \Nyholm\Psr7\Response(302, ['Location' => '/login']);
        }

        if (!$user->hasPermission('access_admin')) {
            return new \Nyholm\Psr7\Response(403, [], 'Access denied');
        }

        return $handler->handle($request);
    }
}
