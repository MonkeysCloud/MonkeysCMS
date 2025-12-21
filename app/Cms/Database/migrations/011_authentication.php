<?php

declare(strict_types=1);

/**
 * Migration: Create authentication tables
 * 
 * Tables:
 * - login_attempts: Track failed login attempts
 * - login_lockouts: Store account lockouts
 * - oauth_accounts: OAuth provider accounts
 * - api_keys: API key authentication
 */
return new class {
    public function up(\PDO $db): void
    {
        // Login attempts table
        $db->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NULL,
                attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_identifier (identifier),
                INDEX idx_ip (ip_address),
                INDEX idx_attempted (attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Login lockouts table
        $db->exec("
            CREATE TABLE IF NOT EXISTS login_lockouts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NULL,
                ip_address VARCHAR(45) NULL,
                locked_until INT UNSIGNED NOT NULL,
                attempt_count INT UNSIGNED DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_identifier (identifier),
                UNIQUE KEY unique_ip (ip_address),
                INDEX idx_locked_until (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // OAuth accounts table
        $db->exec("
            CREATE TABLE IF NOT EXISTS oauth_accounts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                provider VARCHAR(50) NOT NULL,
                provider_user_id VARCHAR(255) NOT NULL,
                access_token TEXT NULL,
                refresh_token TEXT NULL,
                token_expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_provider_user (provider, provider_user_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API keys table
        $db->exec("
            CREATE TABLE IF NOT EXISTS api_keys (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                key_id VARCHAR(32) NOT NULL,
                key_hash VARCHAR(255) NOT NULL,
                scopes JSON NOT NULL,
                last_used_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                revoked_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_key_id (key_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Token blacklist (for JWT revocation)
        $db->exec("
            CREATE TABLE IF NOT EXISTS token_blacklist (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                token_id VARCHAR(64) NOT NULL,
                user_id INT UNSIGNED NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_token (token_id),
                INDEX idx_expires (expires_at),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Email verifications table
        $db->exec("
            CREATE TABLE IF NOT EXISTS email_verifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_token (token_hash),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Add 2FA columns to users table if not exists
        $db->exec("
            ALTER TABLE users
            ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(255) NULL AFTER remember_token,
            ADD COLUMN IF NOT EXISTS two_factor_recovery_codes JSON NULL AFTER two_factor_secret,
            ADD COLUMN IF NOT EXISTS token_version INT UNSIGNED DEFAULT 1 AFTER two_factor_recovery_codes
        ");

        // Update password_resets table structure
        $db->exec("
            ALTER TABLE password_resets
            ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER id,
            ADD COLUMN IF NOT EXISTS token_hash VARCHAR(255) NULL AFTER token,
            ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP NULL AFTER created_at
        ");

        // Rate limits table (for API rate limiting)
        $db->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                key_name VARCHAR(255) NOT NULL,
                timestamp INT UNSIGNED NOT NULL,
                
                INDEX idx_key (key_name),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS token_blacklist");
        $db->exec("DROP TABLE IF EXISTS api_keys");
        $db->exec("DROP TABLE IF EXISTS oauth_accounts");
        $db->exec("DROP TABLE IF EXISTS login_lockouts");
        $db->exec("DROP TABLE IF EXISTS login_attempts");

        // Note: Not removing columns from users table to preserve data
    }
};
