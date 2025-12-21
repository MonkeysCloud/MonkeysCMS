<?php

declare(strict_types=1);

/**
 * Migration: Create settings table
 */
return new class {
    public function up(\PDO $db): void
    {
        // Settings table
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `group` VARCHAR(100) NOT NULL DEFAULT 'general',
                `key` VARCHAR(100) NOT NULL,
                value LONGTEXT NULL,
                type ENUM('string', 'int', 'float', 'bool', 'json', 'array') DEFAULT 'string',
                description TEXT NULL,
                is_public TINYINT(1) DEFAULT 0,
                
                UNIQUE KEY unique_group_key (`group`, `key`),
                INDEX idx_group (`group`),
                INDEX idx_public (is_public)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed default settings
        $db->exec("
            INSERT INTO settings (`group`, `key`, value, type, description, is_public) VALUES
            -- Site settings
            ('site', 'name', 'MonkeysCMS', 'string', 'Site name', 1),
            ('site', 'slogan', 'A modern PHP content management system', 'string', 'Site slogan/tagline', 1),
            ('site', 'email', 'admin@example.com', 'string', 'Site email address', 0),
            ('site', 'logo', '', 'string', 'Site logo path', 1),
            ('site', 'favicon', '', 'string', 'Favicon path', 1),
            ('site', 'timezone', 'UTC', 'string', 'Default timezone', 0),
            ('site', 'date_format', 'F j, Y', 'string', 'Default date format', 0),
            ('site', 'time_format', 'g:i a', 'string', 'Default time format', 0),
            ('site', 'frontpage', '/node/1', 'string', 'Front page path', 0),
            ('site', 'maintenance_mode', '0', 'bool', 'Maintenance mode enabled', 0),
            ('site', 'maintenance_message', 'Site is under maintenance. Please check back later.', 'string', 'Maintenance mode message', 0),
            
            -- User settings
            ('user', 'registration', '1', 'bool', 'Allow user registration', 0),
            ('user', 'email_verification', '1', 'bool', 'Require email verification', 0),
            ('user', 'admin_approval', '0', 'bool', 'Require admin approval for new users', 0),
            ('user', 'default_role', 'authenticated', 'string', 'Default role for new users', 0),
            ('user', 'password_min_length', '8', 'int', 'Minimum password length', 0),
            ('user', 'password_require_special', '0', 'bool', 'Require special characters in password', 0),
            ('user', 'login_attempts_limit', '5', 'int', 'Max failed login attempts before lockout', 0),
            ('user', 'lockout_duration', '900', 'int', 'Lockout duration in seconds', 0),
            
            -- Content settings
            ('content', 'default_status', 'draft', 'string', 'Default content status', 0),
            ('content', 'enable_revisions', '1', 'bool', 'Enable content revisions', 0),
            ('content', 'max_revisions', '10', 'int', 'Maximum revisions to keep (0 = unlimited)', 0),
            ('content', 'autosave_interval', '60', 'int', 'Autosave interval in seconds', 0),
            ('content', 'preview_mode', 'modal', 'string', 'Content preview mode (modal, new_tab, inline)', 0),
            
            -- Media settings
            ('media', 'upload_path', 'uploads', 'string', 'Upload directory path', 0),
            ('media', 'max_filesize', '10485760', 'int', 'Maximum file size in bytes (10MB)', 0),
            ('media', 'allowed_extensions', 'jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,txt', 'string', 'Allowed file extensions', 0),
            ('media', 'image_max_width', '4096', 'int', 'Maximum image width', 0),
            ('media', 'image_max_height', '4096', 'int', 'Maximum image height', 0),
            ('media', 'image_quality', '85', 'int', 'JPEG quality (1-100)', 0),
            
            -- SEO settings
            ('seo', 'site_title_separator', ' | ', 'string', 'Title separator', 0),
            ('seo', 'default_meta_description', '', 'string', 'Default meta description', 0),
            ('seo', 'robots_txt', 'User-agent: *\nAllow: /', 'string', 'robots.txt content', 0),
            ('seo', 'enable_sitemap', '1', 'bool', 'Enable XML sitemap', 0),
            ('seo', 'enable_canonical', '1', 'bool', 'Enable canonical URLs', 0),
            ('seo', 'enable_opengraph', '1', 'bool', 'Enable Open Graph meta tags', 0),
            ('seo', 'enable_twitter_cards', '1', 'bool', 'Enable Twitter Cards', 0),
            ('seo', 'google_analytics', '', 'string', 'Google Analytics ID', 0),
            
            -- Cache settings
            ('cache', 'enabled', '1', 'bool', 'Enable caching', 0),
            ('cache', 'driver', 'file', 'string', 'Cache driver (file, redis, memcached)', 0),
            ('cache', 'ttl', '3600', 'int', 'Default cache TTL in seconds', 0),
            ('cache', 'page_cache', '1', 'bool', 'Enable page caching', 0),
            ('cache', 'page_cache_ttl', '3600', 'int', 'Page cache TTL', 0),
            
            -- Mail settings
            ('mail', 'driver', 'smtp', 'string', 'Mail driver (smtp, sendmail, mail)', 0),
            ('mail', 'host', 'localhost', 'string', 'SMTP host', 0),
            ('mail', 'port', '587', 'int', 'SMTP port', 0),
            ('mail', 'username', '', 'string', 'SMTP username', 0),
            ('mail', 'password', '', 'string', 'SMTP password', 0),
            ('mail', 'encryption', 'tls', 'string', 'SMTP encryption (tls, ssl, none)', 0),
            ('mail', 'from_address', 'noreply@example.com', 'string', 'From email address', 0),
            ('mail', 'from_name', 'MonkeysCMS', 'string', 'From name', 0),
            
            -- Theme settings
            ('theme', 'default', 'default', 'string', 'Default theme', 0),
            ('theme', 'admin', 'admin', 'string', 'Admin theme', 0)
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS settings");
    }
};
