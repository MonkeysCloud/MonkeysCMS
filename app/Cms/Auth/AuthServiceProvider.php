<?php

declare(strict_types=1);

namespace App\Cms\Auth;

use App\Cms\Auth\OAuth\CmsOAuthService;
use App\Cms\Auth\Middleware\AuthMiddleware;
use App\Cms\Auth\Middleware\AdminMiddleware;
use App\Cms\Auth\Middleware\CsrfMiddleware;
use App\Cms\Auth\Middleware\ApiAuthMiddleware;
use App\Cms\Entity\EntityManager;

/**
 * AuthServiceProvider - Dependency injection for authentication
 *
 * Provides factory methods for authentication services.
 *
 * Usage:
 * ```php
 * // Initialize
 * AuthServiceProvider::init($db, $config);
 *
 * // Get services
 * $auth = AuthServiceProvider::getAuthService();
 * $session = AuthServiceProvider::getSessionManager();
 * ```
 */
class AuthServiceProvider
{
    private static ?\PDO $db = null;
    private static ?array $config = null;
    private static ?SessionManager $session = null;
    private static ?CmsAuthService $auth = null;
    private static ?CmsUserProvider $userProvider = null;
    private static ?LoginAttempt $loginAttempt = null;
    private static ?CmsOAuthService $oauth = null;
    private static ?EmailVerification $emailVerification = null;
    private static ?CmsApiKeyService $apiKeys = null;

    /**
     * Initialize the auth service provider
     */
    public static function init(\PDO $db, array $config = []): void
    {
        self::$db = $db;
        self::$config = array_merge([
            // JWT settings
            'jwt_secret' => getenv('JWT_SECRET') ?: bin2hex(random_bytes(32)),
            'access_ttl' => 1800,      // 30 minutes
            'refresh_ttl' => 604800,   // 7 days
            'issuer' => 'monkeyscms',

            // Session settings
            'session_name' => 'cms_session',
            'session_lifetime' => 7200,
            'session_secure' => true,
            'session_httponly' => true,
            'session_samesite' => 'Lax',

            // Login settings
            'max_login_attempts' => 5,
            'lockout_minutes' => 15,
            'lockout_multiplier' => 2,

            // Registration settings
            'require_email_verification' => true,
            'auto_login_after_register' => true,
            'password_min_length' => 8,

            // 2FA settings
            'enable_2fa' => true,
            'app_name' => 'MonkeysCMS',

            // OAuth settings
            'oauth' => [
                'google' => [
                    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
                    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
                    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: '',
                ],
                'github' => [
                    'client_id' => getenv('GITHUB_CLIENT_ID') ?: '',
                    'client_secret' => getenv('GITHUB_CLIENT_SECRET') ?: '',
                    'redirect_uri' => getenv('GITHUB_REDIRECT_URI') ?: '',
                ],
            ],
        ], $config);

        // Reset cached instances
        self::$session = null;
        self::$auth = null;
        self::$userProvider = null;
        self::$loginAttempt = null;
        self::$oauth = null;
        self::$emailVerification = null;
        self::$apiKeys = null;
    }

    /**
     * Get database connection
     */
    public static function getConnection(): \PDO
    {
        if (self::$db === null) {
            throw new \RuntimeException("AuthServiceProvider not initialized. Call init() first.");
        }
        return self::$db;
    }

    /**
     * Get configuration
     */
    public static function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::$config ?? [];
        }
        return self::$config[$key] ?? $default;
    }

    /**
     * Get session manager
     */
    public static function getSessionManager(): SessionManager
    {
        if (self::$session === null) {
            self::$session = new SessionManager([
                'name' => self::getConfig('session_name'),
                'lifetime' => self::getConfig('session_lifetime'),
                'secure' => self::getConfig('session_secure'),
                'httponly' => self::getConfig('session_httponly'),
                'samesite' => self::getConfig('session_samesite'),
            ]);
        }
        return self::$session;
    }

    /**
     * Get user provider
     */
    public static function getUserProvider(): CmsUserProvider
    {
        if (self::$userProvider === null) {
            $em = new EntityManager(self::getConnection());
            self::$userProvider = new CmsUserProvider($em);
        }
        return self::$userProvider;
    }

    /**
     * Get login attempt tracker
     */
    public static function getLoginAttempt(): LoginAttempt
    {
        if (self::$loginAttempt === null) {
            self::$loginAttempt = new LoginAttempt(self::getConnection(), [
                'max_attempts' => self::getConfig('max_login_attempts'),
                'lockout_minutes' => self::getConfig('lockout_minutes'),
                'lockout_multiplier' => self::getConfig('lockout_multiplier'),
            ]);
        }
        return self::$loginAttempt;
    }

    /**
     * Get auth service
     */
    public static function getAuthService(): CmsAuthService
    {
        if (self::$auth === null) {
            self::$auth = new CmsAuthService(
                self::getUserProvider(),
                self::getSessionManager(),
                [
                    'jwt_secret' => self::getConfig('jwt_secret'),
                    'access_ttl' => self::getConfig('access_ttl'),
                    'refresh_ttl' => self::getConfig('refresh_ttl'),
                    'issuer' => self::getConfig('issuer'),
                    'enable_2fa' => self::getConfig('enable_2fa'),
                    'app_name' => self::getConfig('app_name'),
                ]
            );
        }
        return self::$auth;
    }

    /**
     * Get OAuth service
     */
    public static function getOAuthService(): CmsOAuthService
    {
        if (self::$oauth === null) {
            self::$oauth = new CmsOAuthService(
                self::getUserProvider(),
                self::getAuthService(),
                self::getSessionManager(),
                self::getConnection(),
                self::getConfig('oauth')
            );
        }
        return self::$oauth;
    }

    /**
     * Get auth middleware
     */
    public static function getAuthMiddleware(array $publicPaths = [], array $guestOnlyPaths = []): AuthMiddleware
    {
        return new AuthMiddleware(
            self::getAuthService(),
            self::getSessionManager(),
            $publicPaths,
            $guestOnlyPaths
        );
    }

    /**
     * Get admin middleware
     */
    public static function getAdminMiddleware(): AdminMiddleware
    {
        return new AdminMiddleware(self::getAuthService());
    }

    /**
     * Get CSRF middleware
     */
    public static function getCsrfMiddleware(array $excludePaths = []): CsrfMiddleware
    {
        return new CsrfMiddleware(self::getSessionManager(), $excludePaths);
    }

    /**
     * Get container definitions for DI containers
     *
     * @return array<string, callable>
     */
    public static function getDefinitions(): array
    {
        return [
            SessionManager::class => fn() => self::getSessionManager(),
            CmsUserProvider::class => fn() => self::getUserProvider(),
            LoginAttempt::class => fn() => self::getLoginAttempt(),
            CmsAuthService::class => fn() => self::getAuthService(),
            CmsOAuthService::class => fn() => self::getOAuthService(),
            EmailVerification::class => fn() => self::getEmailVerification(),
            CmsApiKeyService::class => fn() => self::getApiKeyService(),
            AuthMiddleware::class => fn() => self::getAuthMiddleware(),
            AdminMiddleware::class => fn() => self::getAdminMiddleware(),
            CsrfMiddleware::class => fn() => self::getCsrfMiddleware(),
        ];
    }

    /**
     * Reset all cached instances
     */
    public static function reset(): void
    {
        self::$db = null;
        self::$config = null;
        self::$session = null;
        self::$auth = null;
        self::$userProvider = null;
        self::$loginAttempt = null;
        self::$oauth = null;
        self::$emailVerification = null;
        self::$apiKeys = null;
    }

    /**
     * Get email verification service
     */
    public static function getEmailVerification(): EmailVerification
    {
        if (self::$emailVerification === null) {
            self::$emailVerification = new EmailVerification(
                self::getConnection(),
                self::getUserProvider(),
                3600 // 1 hour token expiry
            );
        }
        return self::$emailVerification;
    }

    /**
     * Get API key service
     */
    public static function getApiKeyService(): CmsApiKeyService
    {
        if (self::$apiKeys === null) {
            self::$apiKeys = new CmsApiKeyService(
                self::getConnection(),
                self::getUserProvider()
            );
        }
        return self::$apiKeys;
    }

    /**
     * Create authentication routes
     *
     * @return array<array{method: string, path: string, handler: array}>
     */
    public static function getRoutes(): array
    {
        return [
            // Login
            ['method' => 'GET', 'path' => '/login', 'handler' => ['App\Controllers\Auth\LoginController', 'show']],
            ['method' => 'POST', 'path' => '/login', 'handler' => ['App\Controllers\Auth\LoginController', 'login']],
            ['method' => 'GET', 'path' => '/login/2fa', 'handler' => ['App\Controllers\Auth\LoginController', 'show2FA']],
            ['method' => 'POST', 'path' => '/login/2fa', 'handler' => ['App\Controllers\Auth\LoginController', 'verify2FA']],
            ['method' => 'GET', 'path' => '/login/2fa/cancel', 'handler' => ['App\Controllers\Auth\LoginController', 'cancel2FA']],

            // Logout
            ['method' => 'POST', 'path' => '/logout', 'handler' => ['App\Controllers\Auth\LogoutController', 'logout']],
            ['method' => 'POST', 'path' => '/logout/all', 'handler' => ['App\Controllers\Auth\LogoutController', 'logoutAll']],

            // Register
            ['method' => 'GET', 'path' => '/register', 'handler' => ['App\Controllers\Auth\RegisterController', 'show']],
            ['method' => 'POST', 'path' => '/register', 'handler' => ['App\Controllers\Auth\RegisterController', 'register']],

            // Password Reset
            ['method' => 'GET', 'path' => '/password/forgot', 'handler' => ['App\Controllers\Auth\PasswordResetController', 'showForgot']],
            ['method' => 'POST', 'path' => '/password/forgot', 'handler' => ['App\Controllers\Auth\PasswordResetController', 'sendReset']],
            ['method' => 'GET', 'path' => '/password/reset/{token}', 'handler' => ['App\Controllers\Auth\PasswordResetController', 'showReset']],
            ['method' => 'POST', 'path' => '/password/reset', 'handler' => ['App\Controllers\Auth\PasswordResetController', 'reset']],

            // Profile
            ['method' => 'GET', 'path' => '/profile', 'handler' => ['App\Controllers\Auth\ProfileController', 'show']],
            ['method' => 'GET', 'path' => '/profile/edit', 'handler' => ['App\Controllers\Auth\ProfileController', 'edit']],
            ['method' => 'PUT', 'path' => '/profile', 'handler' => ['App\Controllers\Auth\ProfileController', 'update']],
            ['method' => 'GET', 'path' => '/profile/password', 'handler' => ['App\Controllers\Auth\ProfileController', 'showPasswordForm']],
            ['method' => 'PUT', 'path' => '/profile/password', 'handler' => ['App\Controllers\Auth\ProfileController', 'updatePassword']],
            ['method' => 'GET', 'path' => '/profile/sessions', 'handler' => ['App\Controllers\Auth\ProfileController', 'showSessions']],
            ['method' => 'DELETE', 'path' => '/profile/sessions/{id}', 'handler' => ['App\Controllers\Auth\ProfileController', 'revokeSession']],

            // 2FA Settings
            ['method' => 'GET', 'path' => '/settings/2fa', 'handler' => ['App\Controllers\Auth\TwoFactorController', 'show']],
            ['method' => 'POST', 'path' => '/settings/2fa/setup', 'handler' => ['App\Controllers\Auth\TwoFactorController', 'setup']],
            ['method' => 'POST', 'path' => '/settings/2fa/enable', 'handler' => ['App\Controllers\Auth\TwoFactorController', 'enable']],
            ['method' => 'POST', 'path' => '/settings/2fa/disable', 'handler' => ['App\Controllers\Auth\TwoFactorController', 'disable']],
        ];
    }

    /**
     * Get API authentication routes
     *
     * @return array<array{method: string, path: string, handler: array}>
     */
    public static function getApiRoutes(): array
    {
        return [
            // Token Authentication
            ['method' => 'POST', 'path' => '/api/auth/login', 'handler' => ['App\Controllers\Api\ApiAuthController', 'login']],
            ['method' => 'POST', 'path' => '/api/auth/2fa/verify', 'handler' => ['App\Controllers\Api\ApiAuthController', 'verify2FA']],
            ['method' => 'POST', 'path' => '/api/auth/refresh', 'handler' => ['App\Controllers\Api\ApiAuthController', 'refresh']],
            ['method' => 'POST', 'path' => '/api/auth/logout', 'handler' => ['App\Controllers\Api\ApiAuthController', 'logout']],
            ['method' => 'GET', 'path' => '/api/auth/me', 'handler' => ['App\Controllers\Api\ApiAuthController', 'me']],

            // Registration
            ['method' => 'POST', 'path' => '/api/auth/register', 'handler' => ['App\Controllers\Api\ApiAuthController', 'register']],

            // Password
            ['method' => 'POST', 'path' => '/api/auth/password/forgot', 'handler' => ['App\Controllers\Api\ApiAuthController', 'forgotPassword']],
            ['method' => 'POST', 'path' => '/api/auth/password/reset', 'handler' => ['App\Controllers\Api\ApiAuthController', 'resetPassword']],
            ['method' => 'PUT', 'path' => '/api/auth/password', 'handler' => ['App\Controllers\Api\ApiAuthController', 'changePassword']],

            // API Keys
            ['method' => 'GET', 'path' => '/api/auth/api-keys', 'handler' => ['App\Controllers\Api\ApiAuthController', 'listApiKeys']],
            ['method' => 'POST', 'path' => '/api/auth/api-keys', 'handler' => ['App\Controllers\Api\ApiAuthController', 'createApiKey']],
            ['method' => 'DELETE', 'path' => '/api/auth/api-keys/{id}', 'handler' => ['App\Controllers\Api\ApiAuthController', 'revokeApiKey']],
        ];
    }

    /**
     * Get all routes (web + API)
     *
     * @return array<array{method: string, path: string, handler: array}>
     */
    public static function getAllRoutes(): array
    {
        return array_merge(self::getRoutes(), self::getApiRoutes());
    }

    /**
     * Get API middleware stack
     */
    public static function getApiMiddleware(array $publicPaths = []): ApiAuthMiddleware
    {
        return new ApiAuthMiddleware(
            self::getAuthService(),
            self::getUserProvider(),
            self::getApiKeyService(),
            $publicPaths
        );
    }
}
