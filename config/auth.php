<?php

declare(strict_types=1);

/**
 * Authentication Configuration
 * 
 * Configuration for the MonkeysCMS authentication system.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for JSON Web Token authentication.
    |
    */
    
    'jwt' => [
        // Secret key for signing tokens (min 32 characters)
        'secret' => getenv('JWT_SECRET') ?: '',
        
        // Access token TTL in seconds (default: 30 minutes)
        'access_ttl' => (int) (getenv('JWT_ACCESS_TTL') ?: 1800),
        
        // Refresh token TTL in seconds (default: 7 days)
        'refresh_ttl' => (int) (getenv('JWT_REFRESH_TTL') ?: 604800),
        
        // Token issuer
        'issuer' => getenv('JWT_ISSUER') ?: 'monkeyscms',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for session-based authentication.
    |
    */
    
    'session' => [
        // Session cookie name
        'name' => 'cms_session',
        
        // Session lifetime in seconds (default: 2 hours)
        'lifetime' => 7200,
        
        // Cookie path
        'path' => '/',
        
        // Cookie domain (null for current domain)
        'domain' => null,
        
        // Secure cookies (HTTPS only)
        'secure' => getenv('APP_ENV') === 'production',
        
        // HTTP only (not accessible via JavaScript)
        'httponly' => true,
        
        // SameSite policy (Strict, Lax, None)
        'samesite' => 'Lax',
    ],

    /*
    |--------------------------------------------------------------------------
    | Login Protection
    |--------------------------------------------------------------------------
    |
    | Brute force protection settings.
    |
    */
    
    'login' => [
        // Maximum login attempts before lockout
        'max_attempts' => 5,
        
        // Initial lockout duration in minutes
        'lockout_minutes' => 15,
        
        // Lockout duration multiplier for repeated lockouts
        'lockout_multiplier' => 2,
        
        // Maximum lockout duration in minutes
        'max_lockout_minutes' => 1440, // 24 hours
        
        // Track by IP address
        'track_by_ip' => true,
        
        // Track by user identifier
        'track_by_user' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration Settings
    |--------------------------------------------------------------------------
    |
    | User registration configuration.
    |
    */
    
    'registration' => [
        // Enable user registration
        'enabled' => true,
        
        // Require email verification
        'require_email_verification' => true,
        
        // Auto-login after registration
        'auto_login' => true,
        
        // Default role for new users
        'default_role' => 'authenticated',
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | Password requirements for user accounts.
    |
    */
    
    'password' => [
        // Minimum password length
        'min_length' => 8,
        
        // Require uppercase letter
        'require_uppercase' => true,
        
        // Require lowercase letter
        'require_lowercase' => false,
        
        // Require number
        'require_number' => true,
        
        // Require special character
        'require_special' => false,
        
        // Hash algorithm (PASSWORD_ARGON2ID or PASSWORD_BCRYPT)
        'algorithm' => PASSWORD_ARGON2ID,
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | 2FA configuration.
    |
    */
    
    '2fa' => [
        // Enable 2FA functionality
        'enabled' => true,
        
        // Issuer name (shown in authenticator app)
        'issuer' => getenv('APP_NAME') ?: 'MonkeysCMS',
        
        // Number of recovery codes to generate
        'recovery_codes' => 8,
        
        // Allow recovery code login
        'allow_recovery' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Providers
    |--------------------------------------------------------------------------
    |
    | Social login configuration.
    |
    */
    
    'oauth' => [
        'google' => [
            'enabled' => (bool) getenv('GOOGLE_CLIENT_ID'),
            'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
            'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
            'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: '/auth/google/callback',
        ],
        
        'github' => [
            'enabled' => (bool) getenv('GITHUB_CLIENT_ID'),
            'client_id' => getenv('GITHUB_CLIENT_ID') ?: '',
            'client_secret' => getenv('GITHUB_CLIENT_SECRET') ?: '',
            'redirect_uri' => getenv('GITHUB_REDIRECT_URI') ?: '/auth/github/callback',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    |
    | API key authentication settings.
    |
    */
    
    'api_keys' => [
        // Enable API key authentication
        'enabled' => true,
        
        // Header name for API key
        'header' => 'X-API-Key',
        
        // Prefix for generated keys
        'prefix' => 'ml_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Remember Me
    |--------------------------------------------------------------------------
    |
    | "Remember me" functionality settings.
    |
    */
    
    'remember' => [
        // Enable remember me
        'enabled' => true,
        
        // Cookie lifetime in seconds (default: 30 days)
        'lifetime' => 60 * 60 * 24 * 30,
        
        // Cookie name
        'cookie' => 'remember_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Storage
    |--------------------------------------------------------------------------
    |
    | Where to store revoked tokens.
    |
    */
    
    'token_storage' => [
        // Storage driver: 'database', 'redis', 'cache'
        'driver' => 'database',
        
        // Redis connection (if using redis driver)
        'redis' => [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => (int) (getenv('REDIS_DB') ?: 0),
        ],
    ],
];
