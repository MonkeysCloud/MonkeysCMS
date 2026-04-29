<?php

declare(strict_types=1);

namespace App\Cms\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use MonkeysLegion\Http\Message\Response;
use PDO;

/**
 * ApiAuthMiddleware — Token-based authentication for the public JSON:API.
 *
 * Supports:
 *   - Bearer token in Authorization header
 *   - API key in X-API-Key header
 *   - Query parameter ?api_key=xxx (for legacy/webhook support)
 *
 * Read-only endpoints (GET) can optionally be public (no auth required).
 */
final class ApiAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly bool $requireAuthForReads = false,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Allow preflight CORS
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }

        // Allow public reads if configured
        if (!$this->requireAuthForReads && $request->getMethod() === 'GET') {
            return $handler->handle($request);
        }

        // Extract token
        $token = $this->extractToken($request);

        if (!$token) {
            return Response::json([
                'errors' => [[
                    'status' => '401',
                    'title' => 'Unauthorized',
                    'detail' => 'Missing API token. Provide a Bearer token, X-API-Key header, or api_key parameter.',
                ]],
                'jsonapi' => ['version' => '1.1'],
            ], 401);
        }

        // Validate token
        $user = $this->validateToken($token);

        if (!$user) {
            return Response::json([
                'errors' => [[
                    'status' => '403',
                    'title' => 'Forbidden',
                    'detail' => 'Invalid or expired API token.',
                ]],
                'jsonapi' => ['version' => '1.1'],
            ], 403);
        }

        // Attach authenticated user to request
        $request = $request
            ->withAttribute('api_user_id', (int) $user['id'])
            ->withAttribute('api_user', $user);

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        // Authorization: Bearer xxx
        $auth = $request->getHeaderLine('Authorization');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        // X-API-Key: xxx
        $apiKey = $request->getHeaderLine('X-API-Key');
        if ($apiKey) {
            return $apiKey;
        }

        // ?api_key=xxx
        return $request->getQueryParams()['api_key'] ?? null;
    }

    private function validateToken(string $token): ?array
    {
        // Check against stored API tokens
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, u.role_id
             FROM cms_users u
             INNER JOIN api_tokens t ON t.user_id = u.id
             WHERE t.token = :token
               AND t.revoked = 0
               AND (t.expires_at IS NULL OR t.expires_at > NOW())
               AND u.active = 1
             LIMIT 1'
        );
        $stmt->execute(['token' => hash('sha256', $token)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
