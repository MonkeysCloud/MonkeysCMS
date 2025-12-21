<?php

declare(strict_types=1);

/**
 * Migration: Create authentication tables
 *
 * Tables:
 * - login_attempts: Track failed login attempts
 * - login_lockouts: Track account lockouts
 * - oauth_accounts: OAuth provider links
 * - token_blacklist: Revoked JWT tokens
 */

return new class {
    public function up(\PDO $db): void
    {
        // Add 2FA columns to users table
        $db->exec("
            ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(255) NULL AFTER status,
            ADD COLUMN IF NOT EXISTS two_factor_recovery_codes JSON NULL AFTER two_factor_secret,
            ADD COLUMN IF NOT EXISTS token_version INT UNSIGNED DEFAULT 1 AFTER two_factor_recovery_codes
        ");

        // Login attempts table
        $db->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
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
                user_id INT NOT NULL,
                provider VARCHAR(50) NOT NULL,
                provider_user_id VARCHAR(255) NOT NULL,
                access_token TEXT NULL,
                refresh_token TEXT NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_provider_user (provider, provider_user_id),
                INDEX idx_user (user_id),
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Token blacklist table (for JWT revocation)
        $db->exec("
            CREATE TABLE IF NOT EXISTS token_blacklist (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                token_id VARCHAR(64) NOT NULL,
                user_id INT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_token (token_id),
                INDEX idx_expires (expires_at),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // API keys table
        $db->exec("
            CREATE TABLE IF NOT EXISTS api_keys (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                key_id VARCHAR(32) NOT NULL,
                key_hash VARCHAR(255) NOT NULL,
                scopes JSON NOT NULL,
                last_used_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_key_id (key_id),
                INDEX idx_user (user_id),
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Email verification tokens
        $db->exec("
            CREATE TABLE IF NOT EXISTS email_verifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                email VARCHAR(255) NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_user (user_id),
                INDEX idx_token (token_hash),
                INDEX idx_expires (expires_at),
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS email_verifications");
        $db->exec("DROP TABLE IF EXISTS api_keys");
        $db->exec("DROP TABLE IF EXISTS token_blacklist");
        $db->exec("DROP TABLE IF EXISTS oauth_accounts");
        $db->exec("DROP TABLE IF EXISTS login_lockouts");
        $db->exec("DROP TABLE IF EXISTS login_attempts");

        // Remove 2FA columns from users
        $db->exec("
            ALTER TABLE users 
            DROP COLUMN IF EXISTS two_factor_secret,
            DROP COLUMN IF EXISTS two_factor_recovery_codes,
            DROP COLUMN IF EXISTS token_version
        ");
    }
};
