<?php

declare(strict_types=1);

namespace App\Cms\Auth\Middleware;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\CmsApiKeyService;
use App\Cms\Auth\CmsUserProvider;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ApiAuthMiddleware - Handles API authentication via Bearer tokens or API keys
 *
 * Supports:
 * - JWT Bearer tokens: Authorization: Bearer <token>
 * - API keys: X-API-Key: <key>
 *
 * Usage:
 * ```php
 * $middleware = new ApiAuthMiddleware($auth, $userProvider, $apiKeys, [
 *     '/api/auth/login',
 *     '/api/auth/register',
 *     '/api/public/*',
 * ]);
 * ```
 */
class ApiAuthMiddleware implements MiddlewareInterface
{
    private CmsAuthService $auth;
    private CmsUserProvider $userProvider;
    private ?CmsApiKeyService $apiKeys;
    private array $publicPaths;
    private array $requiredScopes;

    public function __construct(
        CmsAuthService $auth,
        CmsUserProvider $userProvider,
        ?CmsApiKeyService $apiKeys = null,
        array $publicPaths = [],
        array $requiredScopes = []
    ) {
        $this->auth = $auth;
        $this->userProvider = $userProvider;
        $this->apiKeys = $apiKeys;
        $this->publicPaths = $publicPaths;
        $this->requiredScopes = $requiredScopes;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Check if path is public
        if ($this->isPublicPath($path)) {
            return $handler->handle($request);
        }

        // Try Bearer token first
        $bearerToken = $this->extractBearerToken($request);
        if ($bearerToken) {
            $request = $this->authenticateWithBearer($request, $bearerToken);
            if ($request->getAttribute('user')) {
                return $handler->handle($request);
            }
            return $this->unauthorized('Invalid or expired token');
        }

        // Try API key
        $apiKey = $this->extractApiKey($request);
        if ($apiKey && $this->apiKeys) {
            $request = $this->authenticateWithApiKey($request, $apiKey);
            if ($request->getAttribute('user')) {
                // Check required scopes
                $keyData = $request->getAttribute('api_key');
                foreach ($this->requiredScopes as $scope) {
                    if (!$this->apiKeys->hasScope($keyData, $scope)) {
                        return $this->forbidden("Missing required scope: {$scope}");
                    }
                }
                return $handler->handle($request);
            }
            return $this->unauthorized('Invalid API key');
        }

        return $this->unauthorized('Authentication required');
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
     * Check if path matches a pattern (supports wildcards)
     */
    private function pathMatches(string $path, string $pattern): bool
    {
        if ($path === $pattern) {
            return true;
        }

        if (str_contains($pattern, '*')) {
            $regex = str_replace(['/', '*'], ['\/', '.*'], $pattern);
            return (bool) preg_match('/^' . $regex . '$/', $path);
        }

        return false;
    }

    /**
     * Extract Bearer token from Authorization header
     */
    private function extractBearerToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract API key from header
     */
    private function extractApiKey(ServerRequestInterface $request): ?string
    {
        // Try X-API-Key header
        $apiKey = $request->getHeaderLine('X-API-Key');
        if ($apiKey) {
            return $apiKey;
        }

        // Try query parameter (not recommended but supported)
        $query = $request->getQueryParams();
        return $query['api_key'] ?? null;
    }

    /**
     * Authenticate with Bearer token
     */
    private function authenticateWithBearer(ServerRequestInterface $request, string $token): ServerRequestInterface
    {
        try {
            $jwtService = $this->auth->getJwtService();
            $payload = $jwtService->decode($token);

            // Get user
            $user = $this->userProvider->findById($payload->sub);

            if (!$user) {
                return $request;
            }

            // Check token version
            $currentVersion = $this->userProvider->getTokenVersion($user->getId());
            if (isset($payload->ver) && $payload->ver !== $currentVersion) {
                return $request;
            }

            // Check user status
            if (!$user->isActive()) {
                return $request;
            }

            // Add user to request
            return $request
                ->withAttribute('user', $user)
                ->withAttribute('auth_type', 'bearer')
                ->withAttribute('token_payload', $payload);
        } catch (\Exception $e) {
            return $request;
        }
    }

    /**
     * Authenticate with API key
     */
    private function authenticateWithApiKey(ServerRequestInterface $request, string $apiKey): ServerRequestInterface
    {
        if (!$this->apiKeys) {
            return $request;
        }

        $keyData = $this->apiKeys->validate($apiKey);

        if (!$keyData) {
            return $request;
        }

        $user = $this->apiKeys->getUser($keyData);

        if (!$user || !$user->isActive()) {
            return $request;
        }

        return $request
            ->withAttribute('user', $user)
            ->withAttribute('auth_type', 'api_key')
            ->withAttribute('api_key', $keyData);
    }

    /**
     * Return 401 Unauthorized response
     */
    private function unauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return new \Nyholm\Psr7\Response(
            401,
            [
                'Content-Type' => 'application/json',
                'WWW-Authenticate' => 'Bearer realm="api"',
            ],
            json_encode([
                'error' => true,
                'message' => $message,
            ])
        );
    }

    /**
     * Return 403 Forbidden response
     */
    private function forbidden(string $message = 'Forbidden'): ResponseInterface
    {
        return new \Nyholm\Psr7\Response(
            403,
            ['Content-Type' => 'application/json'],
            json_encode([
                'error' => true,
                'message' => $message,
            ])
        );
    }
}

/**
 * RequireScopeMiddleware - Checks for required API key scopes
 */
class RequireScopeMiddleware implements MiddlewareInterface
{
    private CmsApiKeyService $apiKeys;
    private array $scopes;

    public function __construct(CmsApiKeyService $apiKeys, array $scopes)
    {
        $this->apiKeys = $apiKeys;
        $this->scopes = $scopes;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authType = $request->getAttribute('auth_type');

        // Only check for API key auth
        if ($authType !== 'api_key') {
            return $handler->handle($request);
        }

        $keyData = $request->getAttribute('api_key');

        if (!$keyData) {
            return $this->forbidden('API key data not found');
        }

        foreach ($this->scopes as $scope) {
            if (!$this->apiKeys->hasScope($keyData, $scope)) {
                return $this->forbidden("Missing required scope: {$scope}");
            }
        }

        return $handler->handle($request);
    }

    private function forbidden(string $message): ResponseInterface
    {
        return new \Nyholm\Psr7\Response(
            403,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => true, 'message' => $message])
        );
    }
}

/**
 * RateLimitMiddleware - Rate limiting for API endpoints
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private \PDO $db;
    private int $maxRequests;
    private int $windowSeconds;
    private string $keyPrefix;

    public function __construct(
        \PDO $db,
        int $maxRequests = 60,
        int $windowSeconds = 60,
        string $keyPrefix = 'rate_limit:'
    ) {
        $this->db = $db;
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->keyPrefix = $keyPrefix;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->getRateLimitKey($request);
        $limit = $this->checkLimit($key);

        if ($limit['exceeded']) {
            return $this->rateLimited($limit['retry_after']);
        }

        $response = $handler->handle($request);

        // Add rate limit headers
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $limit['remaining'])
            ->withHeader('X-RateLimit-Reset', (string) $limit['reset']);
    }

    private function getRateLimitKey(ServerRequestInterface $request): string
    {
        // Use API key ID if available, otherwise use IP
        $keyData = $request->getAttribute('api_key');

        if ($keyData) {
            return $this->keyPrefix . 'key:' . $keyData['id'];
        }

        $user = $request->getAttribute('user');
        if ($user) {
            return $this->keyPrefix . 'user:' . $user->getId();
        }

        return $this->keyPrefix . 'ip:' . $this->getClientIp($request);
    }

    private function checkLimit(string $key): array
    {
        $now = time();
        $windowStart = $now - $this->windowSeconds;

        // Clean old entries and count current
        $stmt = $this->db->prepare("
            DELETE FROM rate_limits WHERE key_name = :key AND timestamp < :start
        ");
        $stmt->execute(['key' => $key, 'start' => $windowStart]);

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM rate_limits WHERE key_name = :key
        ");
        $stmt->execute(['key' => $key]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= $this->maxRequests) {
            // Get oldest entry to calculate retry_after
            $stmt = $this->db->prepare("
                SELECT MIN(timestamp) FROM rate_limits WHERE key_name = :key
            ");
            $stmt->execute(['key' => $key]);
            $oldest = (int) $stmt->fetchColumn();

            return [
                'exceeded' => true,
                'remaining' => 0,
                'reset' => $oldest + $this->windowSeconds,
                'retry_after' => ($oldest + $this->windowSeconds) - $now,
            ];
        }

        // Add current request
        $stmt = $this->db->prepare("
            INSERT INTO rate_limits (key_name, timestamp) VALUES (:key, :ts)
        ");
        $stmt->execute(['key' => $key, 'ts' => $now]);

        return [
            'exceeded' => false,
            'remaining' => $this->maxRequests - $count - 1,
            'reset' => $now + $this->windowSeconds,
            'retry_after' => 0,
        ];
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $headers = ['X-Forwarded-For', 'X-Real-IP'];

        foreach ($headers as $header) {
            $value = $request->getHeaderLine($header);
            if ($value) {
                $ips = explode(',', $value);
                return trim($ips[0]);
            }
        }

        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    private function rateLimited(int $retryAfter): ResponseInterface
    {
        return new \Nyholm\Psr7\Response(
            429,
            [
                'Content-Type' => 'application/json',
                'Retry-After' => (string) $retryAfter,
            ],
            json_encode([
                'error' => true,
                'message' => 'Too many requests',
                'retry_after' => $retryAfter,
            ])
        );
    }
}
