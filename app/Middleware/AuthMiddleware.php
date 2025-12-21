<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Cms\Security\PermissionService;
use App\Modules\Core\Entities\User;
use App\Cms\Repository\CmsRepository;
use MonkeysLegion\Http\Request;
use MonkeysLegion\Http\Response;
use MonkeysLegion\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * AuthMiddleware - Handles authentication and sets up permission context
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly CmsRepository $repository,
        private readonly string $jwtSecret,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->authenticateRequest($request);

        if ($user !== null) {
            // Set user context in permission service
            $this->permissions->setCurrentUser($user);
            
            // Load user's roles and permissions
            $this->permissions->loadUserRoles($user);
            
            // Add user to request attributes
            $request = $request->withAttribute('user', $user);
            $request = $request->withAttribute('user_id', $user->id);
        }

        return $handler->handle($request);
    }

    /**
     * Authenticate request and return user if valid
     */
    private function authenticateRequest(ServerRequestInterface $request): ?User
    {
        // Try Bearer token first
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            return $this->validateJwtToken($token);
        }

        // Try session
        $sessionId = $this->getSessionId($request);
        if ($sessionId !== null) {
            return $this->getUserFromSession($sessionId);
        }

        // Try API key
        $apiKey = $request->getHeaderLine('X-API-Key');
        if (!empty($apiKey)) {
            return $this->validateApiKey($apiKey);
        }

        return null;
    }

    /**
     * Validate JWT token and return user
     */
    private function validateJwtToken(string $token): ?User
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            [$header, $payload, $signature] = $parts;

            // Verify signature
            $expectedSignature = $this->base64UrlEncode(
                hash_hmac('sha256', "{$header}.{$payload}", $this->jwtSecret, true)
            );

            if (!hash_equals($expectedSignature, $signature)) {
                return null;
            }

            // Decode payload
            $payloadData = json_decode($this->base64UrlDecode($payload), true);
            if (!$payloadData) {
                return null;
            }

            // Check expiration
            if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
                return null;
            }

            // Get user
            $userId = $payloadData['sub'] ?? $payloadData['user_id'] ?? null;
            if ($userId === null) {
                return null;
            }

            $user = $this->repository->find(User::class, (int) $userId);
            
            if ($user && $user->isActive()) {
                return $user;
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get session ID from cookies
     */
    private function getSessionId(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();
        return $cookies['monkeyscms_session'] ?? null;
    }

    /**
     * Get user from session
     */
    private function getUserFromSession(string $sessionId): ?User
    {
        // This would integrate with your session storage
        // For now, return null (implement based on your session driver)
        return null;
    }

    /**
     * Validate API key
     */
    private function validateApiKey(string $apiKey): ?User
    {
        // This would look up the API key in your database
        // For now, return null (implement based on your API key storage)
        return null;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
