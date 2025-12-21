<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\CmsUserProvider;
use App\Cms\Auth\LoginAttempt;
use App\Cms\Auth\CmsApiKeyService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * ApiAuthController - API authentication endpoints
 * 
 * Provides token-based authentication for API consumers.
 * 
 * Endpoints:
 * - POST /api/auth/login - Get access/refresh tokens
 * - POST /api/auth/refresh - Refresh access token
 * - POST /api/auth/logout - Revoke tokens
 * - GET  /api/auth/me - Get authenticated user
 * - POST /api/auth/register - Register new user
 * - POST /api/auth/2fa/verify - Verify 2FA code
 */
class ApiAuthController
{
    private CmsAuthService $auth;
    private CmsUserProvider $userProvider;
    private LoginAttempt $loginAttempt;
    private ?CmsApiKeyService $apiKeys;

    public function __construct(
        CmsAuthService $auth,
        CmsUserProvider $userProvider,
        LoginAttempt $loginAttempt,
        ?CmsApiKeyService $apiKeys = null
    ) {
        $this->auth = $auth;
        $this->userProvider = $userProvider;
        $this->loginAttempt = $loginAttempt;
        $this->apiKeys = $apiKeys;
    }

    /**
     * Login and get tokens
     * 
     * POST /api/auth/login
     * 
     * Request:
     * {
     *   "email": "user@example.com",
     *   "password": "secret"
     * }
     * 
     * Response:
     * {
     *   "access_token": "...",
     *   "refresh_token": "...",
     *   "token_type": "Bearer",
     *   "expires_in": 1800
     * }
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);
        $ip = $this->getClientIp($request);

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // Validate input
        if (empty($email) || empty($password)) {
            return $this->error('Email and password are required', 422);
        }

        // Check lockout
        $lockout = $this->loginAttempt->checkLockout($email, $ip);
        if ($lockout['locked']) {
            return $this->error('Too many login attempts', 429, [
                'retry_after' => $lockout['remaining'],
            ]);
        }

        // Attempt login
        $result = $this->auth->attempt($email, $password, false, $ip);

        if ($result->success) {
            $this->loginAttempt->recordSuccess($email, $ip);

            return $this->json([
                'access_token' => $result->tokens->accessToken,
                'refresh_token' => $result->tokens->refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 1800,
                'user' => $result->user->toApiArray(),
            ]);
        }

        // Handle 2FA requirement
        if ($result->requires2FA) {
            return $this->json([
                'requires_2fa' => true,
                'challenge_token' => $result->challengeToken,
                'message' => 'Two-factor authentication required',
            ], 200);
        }

        // Record failure
        $this->loginAttempt->recordFailure($email, $ip);
        $remaining = $this->loginAttempt->getRemainingAttempts($email, $ip);

        return $this->error('Invalid credentials', 401, [
            'attempts_remaining' => $remaining,
        ]);
    }

    /**
     * Verify 2FA and complete login
     * 
     * POST /api/auth/2fa/verify
     * 
     * Request:
     * {
     *   "challenge_token": "...",
     *   "code": "123456"
     * }
     */
    public function verify2FA(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);
        $ip = $this->getClientIp($request);

        $challengeToken = $data['challenge_token'] ?? '';
        $code = $data['code'] ?? '';

        if (empty($challengeToken) || empty($code)) {
            return $this->error('Challenge token and code are required', 422);
        }

        $result = $this->auth->verify2FA($challengeToken, $code, $ip);

        if ($result->success) {
            return $this->json([
                'access_token' => $result->tokens->accessToken,
                'refresh_token' => $result->tokens->refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 1800,
                'user' => $result->user->toApiArray(),
            ]);
        }

        return $this->error('Invalid 2FA code', 401);
    }

    /**
     * Refresh access token
     * 
     * POST /api/auth/refresh
     * 
     * Request:
     * {
     *   "refresh_token": "..."
     * }
     */
    public function refresh(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            return $this->error('Refresh token is required', 422);
        }

        $tokens = $this->auth->refresh($refreshToken);

        if (!$tokens) {
            return $this->error('Invalid or expired refresh token', 401);
        }

        return $this->json([
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 1800,
        ]);
    }

    /**
     * Logout / revoke tokens
     * 
     * POST /api/auth/logout
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);
        $allDevices = $data['all_devices'] ?? false;

        $this->auth->logout((bool) $allDevices);

        return $this->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user
     * 
     * GET /api/auth/me
     */
    public function me(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        return $this->json([
            'user' => $user->toApiArray(),
            'roles' => $user->getRoles(),
            'permissions' => $user->getPermissions(),
        ]);
    }

    /**
     * Register new user
     * 
     * POST /api/auth/register
     * 
     * Request:
     * {
     *   "email": "user@example.com",
     *   "username": "johndoe",
     *   "password": "secret123",
     *   "password_confirmation": "secret123",
     *   "display_name": "John Doe"
     * }
     */
    public function register(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);

        // Validate
        $errors = $this->validateRegistration($data);
        if (!empty($errors)) {
            return $this->error('Validation failed', 422, ['errors' => $errors]);
        }

        // Register
        $result = $this->auth->register([
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => $data['password'],
            'display_name' => $data['display_name'] ?? null,
        ], true); // Auto-login for API

        if (!$result->success) {
            return $this->error($result->error ?? 'Registration failed', 400);
        }

        $response = [
            'message' => 'Registration successful',
            'user' => $result->user->toApiArray(),
        ];

        if ($result->tokens) {
            $response['access_token'] = $result->tokens->accessToken;
            $response['refresh_token'] = $result->tokens->refreshToken;
            $response['token_type'] = 'Bearer';
            $response['expires_in'] = 1800;
        }

        return $this->json($response, 201);
    }

    /**
     * Request password reset
     * 
     * POST /api/auth/password/forgot
     */
    public function forgotPassword(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);
        $email = $data['email'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Valid email is required', 422);
        }

        // Always return success to prevent email enumeration
        $this->auth->sendPasswordReset($email);

        return $this->json([
            'message' => 'If an account exists, a reset link has been sent',
        ]);
    }

    /**
     * Reset password
     * 
     * POST /api/auth/password/reset
     */
    public function resetPassword(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);
        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';
        $confirmation = $data['password_confirmation'] ?? '';

        if (empty($token) || empty($password)) {
            return $this->error('Token and password are required', 422);
        }

        if ($password !== $confirmation) {
            return $this->error('Passwords do not match', 422);
        }

        if (strlen($password) < 8) {
            return $this->error('Password must be at least 8 characters', 422);
        }

        if ($this->auth->resetPassword($token, $password)) {
            return $this->json(['message' => 'Password reset successfully']);
        }

        return $this->error('Invalid or expired token', 400);
    }

    /**
     * Change password (authenticated)
     * 
     * PUT /api/auth/password
     */
    public function changePassword(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->auth->check()) {
            return $this->error('Unauthenticated', 401);
        }

        $data = $this->getJsonBody($request);
        $current = $data['current_password'] ?? '';
        $new = $data['new_password'] ?? '';
        $confirmation = $data['new_password_confirmation'] ?? '';

        if (empty($current) || empty($new)) {
            return $this->error('Current and new password are required', 422);
        }

        if ($new !== $confirmation) {
            return $this->error('Passwords do not match', 422);
        }

        if (strlen($new) < 8) {
            return $this->error('Password must be at least 8 characters', 422);
        }

        if ($this->auth->changePassword($current, $new)) {
            return $this->json(['message' => 'Password changed successfully']);
        }

        return $this->error('Current password is incorrect', 400);
    }

    // =========================================================================
    // API Key Endpoints
    // =========================================================================

    /**
     * List API keys
     * 
     * GET /api/auth/api-keys
     */
    public function listApiKeys(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->auth->check() || !$this->apiKeys) {
            return $this->error('Unauthenticated', 401);
        }

        $keys = $this->apiKeys->listForUser($this->auth->id());

        return $this->json(['keys' => $keys]);
    }

    /**
     * Create API key
     * 
     * POST /api/auth/api-keys
     */
    public function createApiKey(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->auth->check() || !$this->apiKeys) {
            return $this->error('Unauthenticated', 401);
        }

        $data = $this->getJsonBody($request);
        $name = $data['name'] ?? '';
        $scopes = $data['scopes'] ?? ['*'];
        $expiresAt = isset($data['expires_at']) ? new \DateTime($data['expires_at']) : null;

        if (empty($name)) {
            return $this->error('Name is required', 422);
        }

        $result = $this->apiKeys->create(
            $this->auth->id(),
            $name,
            $scopes,
            $expiresAt
        );

        return $this->json([
            'key' => $result['key'], // Only returned once!
            'id' => $result['id'],
            'name' => $result['name'],
            'scopes' => $result['scopes'],
            'message' => 'Store this key securely - it will not be shown again',
        ], 201);
    }

    /**
     * Revoke API key
     * 
     * DELETE /api/auth/api-keys/{id}
     */
    public function revokeApiKey(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->auth->check() || !$this->apiKeys) {
            return $this->error('Unauthenticated', 401);
        }

        if ($this->apiKeys->revoke($id, $this->auth->id())) {
            return $this->json(['message' => 'API key revoked']);
        }

        return $this->error('API key not found', 404);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getJsonBody(ServerRequestInterface $request): array
    {
        $body = (string) $request->getBody();
        return json_decode($body, true) ?? [];
    }

    private function getClientIp(ServerRequestInterface $request): ?string
    {
        $headers = ['X-Forwarded-For', 'X-Real-IP', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            $value = $request->getHeaderLine($header);
            if ($value) {
                // X-Forwarded-For can have multiple IPs
                $ips = explode(',', $value);
                return trim($ips[0]);
            }
        }

        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? null;
    }

    private function json(array $data, int $status = 200): ResponseInterface
    {
        return new \Nyholm\Psr7\Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($data, JSON_PRETTY_PRINT)
        );
    }

    private function error(string $message, int $status, array $extra = []): ResponseInterface
    {
        return $this->json(array_merge([
            'error' => true,
            'message' => $message,
        ], $extra), $status);
    }

    private function validateRegistration(array $data): array
    {
        $errors = [];

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($data['username'])) {
            $errors['username'] = 'Username is required';
        } elseif (strlen($data['username']) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors['username'] = 'Username can only contain letters, numbers, and underscores';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }

        return $errors;
    }
}
