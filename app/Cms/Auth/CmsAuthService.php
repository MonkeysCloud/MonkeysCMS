<?php

declare(strict_types=1);

namespace App\Cms\Auth;

use App\Cms\User\User;
use App\Cms\User\UserManager;
use MonkeysLegion\Auth\Service\AuthService;
use MonkeysLegion\Auth\Service\JwtService;
use MonkeysLegion\Auth\Service\PasswordHasher;
use MonkeysLegion\Auth\Service\TwoFactorService;
use MonkeysLegion\Auth\TwoFactor\TotpProvider;
use MonkeysLegion\Auth\Exception\InvalidCredentialsException;
use MonkeysLegion\Auth\Exception\AccountLockedException;
use MonkeysLegion\Auth\Exception\TwoFactorRequiredException;
use MonkeysLegion\Auth\RateLimit\RateLimiterInterface;
use MonkeysLegion\Auth\Token\TokenStorageInterface;

/**
 * CmsAuthService - Authentication service for MonkeysCMS
 * 
 * Wraps MonkeysLegion-Auth with CMS-specific functionality:
 * - Integration with CMS User entity
 * - Session management
 * - Remember me functionality
 * - Audit logging
 * 
 * Usage:
 * ```php
 * $auth = new CmsAuthService($config);
 * 
 * // Login
 * $result = $auth->attempt($email, $password);
 * if ($result->success) {
 *     // Store tokens in session/cookie
 * }
 * 
 * // Check authentication
 * if ($auth->check()) {
 *     $user = $auth->user();
 * }
 * 
 * // Logout
 * $auth->logout();
 * ```
 */
class CmsAuthService
{
    private AuthService $authService;
    private JwtService $jwtService;
    private PasswordHasher $hasher;
    private ?TwoFactorService $twoFactor = null;
    private CmsUserProvider $userProvider;
    private SessionManager $session;
    private ?TokenStorageInterface $tokenStorage;
    private ?RateLimiterInterface $rateLimiter;

    private ?User $currentUser = null;
    private ?string $accessToken = null;

    public function __construct(
        CmsUserProvider $userProvider,
        SessionManager $session,
        array $config = [],
        ?TokenStorageInterface $tokenStorage = null,
        ?RateLimiterInterface $rateLimiter = null
    ) {
        $this->userProvider = $userProvider;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->rateLimiter = $rateLimiter;

        // Initialize password hasher
        $this->hasher = new PasswordHasher();

        // Initialize JWT service
        $this->jwtService = new JwtService(
            secret: $config['jwt_secret'] ?? $this->generateSecret(),
            accessTtl: $config['access_ttl'] ?? 1800,      // 30 minutes
            refreshTtl: $config['refresh_ttl'] ?? 604800,   // 7 days
            issuer: $config['issuer'] ?? 'monkeyscms',
        );

        // Initialize auth service
        $this->authService = new AuthService(
            users: $userProvider,
            hasher: $this->hasher,
            jwt: $this->jwtService,
            tokenStorage: $tokenStorage,
            rateLimiter: $rateLimiter,
        );

        // Initialize 2FA if enabled
        if ($config['enable_2fa'] ?? true) {
            $totp = new TotpProvider();
            $this->twoFactor = new TwoFactorService(
                $totp,
                issuer: $config['app_name'] ?? 'MonkeysCMS'
            );
        }
    }

    // =========================================================================
    // Authentication
    // =========================================================================

    /**
     * Attempt to authenticate a user
     * 
     * @return AuthResult
     * @throws InvalidCredentialsException
     * @throws AccountLockedException
     */
    public function attempt(string $email, string $password, bool $remember = false, ?string $ip = null): AuthResult
    {
        try {
            $result = $this->authService->login($email, $password, $ip);

            // Check if 2FA is required
            if ($result->requires2FA) {
                return new AuthResult(
                    success: false,
                    requires2FA: true,
                    challengeToken: $result->challengeToken,
                );
            }

            // Get user
            $user = $this->userProvider->findByEmail($email);
            
            // Record login
            $user->recordLogin($ip);
            $this->userProvider->save($user);

            // Store in session
            $this->storeAuthentication($user, $result->tokens, $remember);

            return new AuthResult(
                success: true,
                user: $user,
                tokens: $result->tokens,
            );

        } catch (InvalidCredentialsException $e) {
            return new AuthResult(
                success: false,
                error: 'Invalid email or password',
            );
        } catch (AccountLockedException $e) {
            return new AuthResult(
                success: false,
                error: 'Account is locked',
                lockedUntil: $e->getLockedUntil(),
            );
        }
    }

    /**
     * Verify 2FA code
     */
    public function verify2FA(string $challengeToken, string $code, ?string $ip = null): AuthResult
    {
        try {
            $result = $this->authService->verify2FA($challengeToken, $code);

            // Get user from token
            $payload = $this->jwtService->decode($result->tokens->accessToken);
            $user = $this->userProvider->findById($payload->sub);

            // Record login
            $user->recordLogin($ip);
            $this->userProvider->save($user);

            // Store in session
            $this->storeAuthentication($user, $result->tokens, false);

            return new AuthResult(
                success: true,
                user: $user,
                tokens: $result->tokens,
            );

        } catch (\Exception $e) {
            return new AuthResult(
                success: false,
                error: 'Invalid 2FA code',
            );
        }
    }

    /**
     * Logout the current user
     */
    public function logout(bool $allDevices = false): void
    {
        if ($this->accessToken) {
            $this->authService->logout($this->accessToken, $allDevices);
        }

        $this->currentUser = null;
        $this->accessToken = null;
        $this->session->destroy();
    }

    /**
     * Refresh access token
     */
    public function refresh(string $refreshToken): ?TokenPair
    {
        try {
            $tokens = $this->authService->refresh($refreshToken);
            
            // Update session
            $this->session->set('access_token', $tokens->accessToken);
            $this->session->set('refresh_token', $tokens->refreshToken);
            $this->session->set('token_expires', $tokens->accessExpiresAt);

            return new TokenPair(
                accessToken: $tokens->accessToken,
                refreshToken: $tokens->refreshToken,
                accessExpiresAt: $tokens->accessExpiresAt,
            );

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if user is authenticated
     */
    public function check(): bool
    {
        if ($this->currentUser !== null) {
            return true;
        }

        return $this->loadFromSession();
    }

    /**
     * Get the current authenticated user
     */
    public function user(): ?User
    {
        if ($this->currentUser === null) {
            $this->loadFromSession();
        }

        return $this->currentUser;
    }

    /**
     * Get user ID if authenticated
     */
    public function id(): ?int
    {
        return $this->user()?->getId();
    }

    /**
     * Check if user is a guest (not authenticated)
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * Register a new user
     * 
     * @param array{email: string, username: string, password: string, display_name?: string} $data
     */
    public function register(array $data, bool $autoLogin = true): AuthResult
    {
        // Validate email uniqueness
        if ($this->userProvider->findByEmail($data['email'])) {
            return new AuthResult(
                success: false,
                error: 'Email already exists',
            );
        }

        // Validate username uniqueness
        if ($this->userProvider->findByUsername($data['username'])) {
            return new AuthResult(
                success: false,
                error: 'Username already exists',
            );
        }

        // Create user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setUsername($data['username']);
        $user->setPassword($data['password']);
        $user->setDisplayName($data['display_name'] ?? null);
        $user->setStatus('pending');

        $this->userProvider->save($user);

        // Assign default role
        $this->userProvider->assignRole($user, 'authenticated');

        // Auto login if requested
        if ($autoLogin) {
            $tokens = $this->authService->issueTokenPair($user);
            $this->storeAuthentication($user, $tokens, false);

            return new AuthResult(
                success: true,
                user: $user,
                tokens: $tokens,
            );
        }

        return new AuthResult(
            success: true,
            user: $user,
        );
    }

    // =========================================================================
    // Password Management
    // =========================================================================

    /**
     * Send password reset email
     */
    public function sendPasswordReset(string $email): bool
    {
        $user = $this->userProvider->findByEmail($email);
        
        if (!$user) {
            // Don't reveal if email exists
            return true;
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = new \DateTimeImmutable('+1 hour');

        // Store reset token
        $this->userProvider->storePasswordResetToken($user->getId(), $hash, $expires);

        // TODO: Send email with reset link
        // EmailService::send($email, 'password-reset', ['token' => $token]);

        return true;
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $hash = hash('sha256', $token);
        $userId = $this->userProvider->findUserByResetToken($hash);

        if (!$userId) {
            return false;
        }

        $user = $this->userProvider->findById($userId);
        if (!$user) {
            return false;
        }

        $user->setPassword($newPassword);
        $user->clearRememberToken();
        $this->userProvider->save($user);

        // Delete reset token
        $this->userProvider->deletePasswordResetToken($hash);

        // Invalidate all existing tokens
        $this->userProvider->incrementTokenVersion($user->getId());

        return true;
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(string $currentPassword, string $newPassword): bool
    {
        $user = $this->user();
        
        if (!$user || !$user->verifyPassword($currentPassword)) {
            return false;
        }

        $user->setPassword($newPassword);
        $user->clearRememberToken();
        $this->userProvider->save($user);

        // Invalidate other sessions
        $this->userProvider->incrementTokenVersion($user->getId());

        return true;
    }

    // =========================================================================
    // Two-Factor Authentication
    // =========================================================================

    /**
     * Generate 2FA setup data
     */
    public function generate2FASetup(): ?array
    {
        $user = $this->user();
        
        if (!$user || !$this->twoFactor) {
            return null;
        }

        return $this->twoFactor->generateSetup($user->getEmail());
    }

    /**
     * Enable 2FA for current user
     */
    public function enable2FA(string $secret, string $code): bool
    {
        $user = $this->user();
        
        if (!$user || !$this->twoFactor) {
            return false;
        }

        try {
            $this->twoFactor->enable($secret, $code, $user->getId());
            
            // Store secret on user
            $this->userProvider->store2FASecret($user->getId(), $secret);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Disable 2FA for current user
     */
    public function disable2FA(string $password): bool
    {
        $user = $this->user();
        
        if (!$user || !$user->verifyPassword($password)) {
            return false;
        }

        $this->userProvider->store2FASecret($user->getId(), null);

        return true;
    }

    /**
     * Verify 2FA code (for settings/sensitive actions)
     */
    public function verify2FACode(string $code): bool
    {
        $user = $this->user();
        
        if (!$user || !$this->twoFactor) {
            return false;
        }

        $secret = $this->userProvider->get2FASecret($user->getId());
        
        if (!$secret) {
            return false;
        }

        return $this->twoFactor->verify($secret, $code);
    }

    // =========================================================================
    // Authorization Helpers
    // =========================================================================

    /**
     * Check if user has permission
     */
    public function can(string $permission): bool
    {
        $user = $this->user();
        return $user?->hasPermission($permission) ?? false;
    }

    /**
     * Check if user has role
     */
    public function hasRole(string $role): bool
    {
        $user = $this->user();
        return $user?->hasRole($role) ?? false;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    // =========================================================================
    // Session Management
    // =========================================================================

    /**
     * Store authentication in session
     */
    private function storeAuthentication(User $user, object $tokens, bool $remember): void
    {
        $this->currentUser = $user;
        $this->accessToken = $tokens->accessToken;

        $this->session->set('user_id', $user->getId());
        $this->session->set('access_token', $tokens->accessToken);
        $this->session->set('refresh_token', $tokens->refreshToken);
        $this->session->set('token_expires', $tokens->accessExpiresAt);

        if ($remember) {
            $rememberToken = $user->generateRememberToken();
            $this->userProvider->save($user);
            $this->session->setCookie('remember_token', $rememberToken, 60 * 60 * 24 * 30);
        }
    }

    /**
     * Load authentication from session
     */
    private function loadFromSession(): bool
    {
        $userId = $this->session->get('user_id');
        $accessToken = $this->session->get('access_token');
        $tokenExpires = $this->session->get('token_expires');

        if (!$userId || !$accessToken) {
            // Try remember token
            return $this->loadFromRememberToken();
        }

        // Check if token expired
        if ($tokenExpires && $tokenExpires < time()) {
            // Try to refresh
            $refreshToken = $this->session->get('refresh_token');
            if ($refreshToken && $this->refresh($refreshToken)) {
                $accessToken = $this->session->get('access_token');
            } else {
                return $this->loadFromRememberToken();
            }
        }

        // Validate token
        try {
            $payload = $this->jwtService->decode($accessToken);
            $user = $this->userProvider->findById($payload->sub);
            
            if ($user) {
                $this->currentUser = $user;
                $this->accessToken = $accessToken;
                return true;
            }
        } catch (\Exception $e) {
            // Token invalid
        }

        return $this->loadFromRememberToken();
    }

    /**
     * Load authentication from remember token
     */
    private function loadFromRememberToken(): bool
    {
        $rememberToken = $this->session->getCookie('remember_token');
        
        if (!$rememberToken) {
            return false;
        }

        $user = $this->userProvider->findByRememberToken($rememberToken);
        
        if (!$user || !$user->isActive()) {
            return false;
        }

        // Issue new tokens
        $tokens = $this->authService->issueTokenPair($user);
        $this->storeAuthentication($user, $tokens, true);

        return true;
    }

    /**
     * Generate a random JWT secret
     */
    private function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function getAuthService(): AuthService
    {
        return $this->authService;
    }

    public function getJwtService(): JwtService
    {
        return $this->jwtService;
    }

    public function getTwoFactorService(): ?TwoFactorService
    {
        return $this->twoFactor;
    }

    public function getSession(): SessionManager
    {
        return $this->session;
    }
}

/**
 * AuthResult - Result of an authentication attempt
 */
class AuthResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?User $user = null,
        public readonly ?object $tokens = null,
        public readonly bool $requires2FA = false,
        public readonly ?string $challengeToken = null,
        public readonly ?string $error = null,
        public readonly ?int $lockedUntil = null,
    ) {}

    public function failed(): bool
    {
        return !$this->success;
    }
}

/**
 * TokenPair - Access and refresh tokens
 */
class TokenPair
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $accessExpiresAt,
    ) {}
}
