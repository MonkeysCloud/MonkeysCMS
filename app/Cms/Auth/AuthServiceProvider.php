<?php

declare(strict_types=1);

namespace App\Cms\Auth;

use App\Cms\Auth\OAuth\CmsOAuthService;
use App\Cms\Auth\Middleware\AuthMiddleware; // Legacy?
use App\Cms\Auth\Middleware\AdminAccessMiddleware;
use App\Cms\Auth\Middleware\CsrfMiddleware;
use App\Cms\Auth\Middleware\ApiAuthMiddleware;
use App\Cms\Entity\EntityManager;
use MonkeysLegion\Auth\Service\AuthService;
use MonkeysLegion\Auth\Service\JwtService;
use MonkeysLegion\Auth\Service\PasswordHasher;
use MonkeysLegion\Auth\Middleware\AuthenticationMiddleware;

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
    private static ?AuthService $auth = null;
    private static ?JwtService $jwt = null;
    private static ?CmsUserProvider $userProvider = null;
    private static ?LoginAttempt $loginAttempt = null;
    private static ?CmsOAuthService $oauth = null;
    private static ?EmailVerification $emailVerification = null;
    private static ?CmsApiKeyService $apiKeys = null;
    private static ?CmsAuthService $cmsAuth = null;

    /**
     * Initialize the auth service provider
     */
    public static function init(\PDO $db, array $config = []): void
    {
        self::$db = $db;
        self::$config = array_merge([
            // JWT settings
            // JWT settings (use $_ENV because Dotenv doesn't use putenv by default)
            'jwt_secret' => $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: bin2hex(random_bytes(32)),
            'access_ttl' => 1800,      // 30 minutes
            'refresh_ttl' => 604800,   // 7 days
            'issuer' => 'monkeyscms',

            // Session settings (kept for flash messages)
            'session_name' => 'cms_session',
            'session_lifetime' => 7200,
            'session_secure' => true,
            'session_httponly' => true,
            'session_samesite' => 'Lax',

            // Login settings
            'max_login_attempts' => 5,
            'lockout_minutes' => 15,
            'lockout_multiplier' => 2,
            
            // App Name
            'app_name' => 'MonkeysCMS',
        ], $config);

        // Reset cached instances
        self::$session = null;
        self::$auth = null;
        self::$jwt = null;
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
     * Get JWT Service
     */
    public static function getJwtService(): JwtService
    {
        if (self::$jwt === null) {
            self::$jwt = new JwtService(
                secret: (string) self::getConfig('jwt_secret'),
                accessTtl: (int) self::getConfig('access_ttl'),
                refreshTtl: (int) self::getConfig('refresh_ttl'),
                issuer: (string) self::getConfig('issuer'),
            );
        }
        return self::$jwt;
    }

    /**
     * Get Auth Service
     */
    public static function getAuthService(): AuthService
    {
        if (self::$auth === null) {
            self::$auth = new AuthService(
                users: self::getUserProvider(),
                hasher: new PasswordHasher(),
                jwt: self::getJwtService(),
                rateLimiter: null, // Can integrate rate limiter later
                tokenStorage: null // Can integrate token storage for blacklist later
            );
        }
        return self::$auth;
    }

    /**
     * Get CMS Auth Service (wrapper with session management)
     */
    public static function getCmsAuthService(): CmsAuthService
    {
        if (self::$cmsAuth === null) {
            self::$cmsAuth = new CmsAuthService(
                self::getUserProvider(),
                self::getSessionManager(),
                self::getConfig()
            );
        }
        return self::$cmsAuth;
    }

    /**
     * Get Authentication Middleware (Standard)
     */
    public static function getAuthenticationMiddleware(): AuthenticationMiddleware
    {
        return new AuthenticationMiddleware(
            auth: self::getAuthService(),
            users: self::getUserProvider(),
            apiKeys: null, // CmsApiKeyService is not compatible yet
            publicPaths: ['/login*', '/register*', '/recovery*', '/public*', '/assets*'],
            responseFactory: function (\Throwable $e) {
                // Determine if we should return JSON (API) or Redirect (Web)
                // Since we don't have Request here, we'll default to Redirect for now as it's the main issue.
                // Ideally we'd separate API/Web middleware stacks.
                return new \Nyholm\Psr7\Response(302, ['Location' => '/login']);
            }
        );
    }

    /**
     * Get Admin Access Middleware (Custom Rights Check)
     */
    public static function getAdminAccessMiddleware(): AdminAccessMiddleware
    {
        // Now just a permissions check, doesn't need AuthService dependency if it uses Request attribute
        // But for compatibility with existing constructor if not yet refactored...
        // Wait, I planned to refactor AdminAccessMiddleware too.
        return new AdminAccessMiddleware(); 
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
            JwtService::class => fn() => self::getJwtService(),
            AuthService::class => fn() => self::getAuthService(),
            EmailVerification::class => fn() => self::getEmailVerification(),
            CmsApiKeyService::class => fn() => self::getApiKeyService(),
            AuthenticationMiddleware::class => fn() => self::getAuthenticationMiddleware(),
            AdminAccessMiddleware::class => fn() => self::getAdminAccessMiddleware(),
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
        self::$jwt = null;
        self::$userProvider = null;
        self::$loginAttempt = null;
        self::$oauth = null;
        self::$emailVerification = null;
        self::$apiKeys = null;
        self::$cmsAuth = null;
    }
    
    /**
     * Get all routes
     */
    public static function getAllRoutes(): array
    {
        // Simplified route definition, most should be attribute-based now
        return [
             ['method' => 'GET', 'path' => '/login', 'handler' => ['App\Controllers\Auth\LoginController', 'show']],
             ['method' => 'POST', 'path' => '/login', 'handler' => ['App\Controllers\Auth\LoginController', 'login']],
             ['method' => 'POST', 'path' => '/logout', 'handler' => ['App\Controllers\Auth\LogoutController', 'logout']],
             // Add more as needed or rely on controller attributes
        ];
    }
}
