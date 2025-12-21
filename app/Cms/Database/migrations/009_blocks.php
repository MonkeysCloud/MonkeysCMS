<?php

declare(strict_types=1);

/**
 * Migration: Create blocks tables
 */

return new class {
    public function up(\PDO $db): void
    {
        // Block types table
        $db->exec("
            CREATE TABLE IF NOT EXISTS block_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                machine_name VARCHAR(100) NOT NULL,
                description TEXT NULL,
                category VARCHAR(100) DEFAULT 'Custom',
                template VARCHAR(255) NULL,
                icon VARCHAR(50) DEFAULT 'square',
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_machine_name (machine_name),
                INDEX idx_category (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Blocks table
        $db->exec("
            CREATE TABLE IF NOT EXISTS blocks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                block_type_id INT NOT NULL,
                
                -- Identity
                title VARCHAR(255) NOT NULL,
                machine_name VARCHAR(100) NULL,
                description TEXT NULL,
                
                -- Content
                body LONGTEXT NULL,
                settings JSON NULL,
                
                -- Placement
                region VARCHAR(100) NULL,
                theme VARCHAR(100) DEFAULT 'default',
                weight INT DEFAULT 0,
                
                -- Visibility
                status TINYINT(1) DEFAULT 1,
                visibility_pages TEXT NULL,
                visibility_pages_type ENUM('include', 'exclude') DEFAULT 'exclude',
                visibility_roles JSON NULL,
                visibility_content_types JSON NULL,
                
                -- Cache
                cache_max_age INT DEFAULT 3600,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_block_type (block_type_id),
                INDEX idx_region (region, theme),
                INDEX idx_weight (weight),
                INDEX idx_status (status),
                
                FOREIGN KEY (block_type_id) REFERENCES block_types(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Block revisions
        $db->exec("
            CREATE TABLE IF NOT EXISTS block_revisions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                block_id INT NOT NULL,
                revision_id INT NOT NULL,
                body LONGTEXT NULL,
                settings JSON NULL,
                author_id INT NULL,
                log_message TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_block (block_id),
                UNIQUE KEY unique_revision (block_id, revision_id),
                
                FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed default block types
        $db->exec("
            INSERT INTO block_types (name, machine_name, description, category, icon) VALUES
            ('Basic Block', 'basic', 'A simple HTML/text block', 'Content', 'file-text'),
            ('Menu Block', 'menu', 'Displays a navigation menu', 'Navigation', 'menu'),
            ('View Block', 'view', 'Displays content from a view', 'Content', 'list'),
            ('HTML Block', 'html', 'Raw HTML content', 'Content', 'code'),
            ('PHP Block', 'php', 'PHP code execution (admin only)', 'Development', 'terminal'),
            ('Recent Content', 'recent_content', 'Shows recent content', 'Content', 'clock'),
            ('Search', 'search', 'Search form', 'Forms', 'search'),
            ('User Login', 'user_login', 'User login form', 'User', 'user'),
            ('Breadcrumb', 'breadcrumb', 'Breadcrumb navigation', 'Navigation', 'chevrons-right'),
            ('Social Links', 'social_links', 'Social media links', 'Social', 'share-2')
        ");

        // Seed some default blocks
        $db->exec("
            INSERT INTO blocks (block_type_id, title, machine_name, region, theme, weight, settings) VALUES
            (2, 'Main Menu', 'main_menu', 'header', 'default', 0, '{\"menu\": \"main\", \"depth\": 2}'),
            (9, 'Breadcrumbs', 'breadcrumbs', 'breadcrumb', 'default', 0, '{}'),
            (2, 'Footer Menu', 'footer_menu', 'footer', 'default', 0, '{\"menu\": \"footer\", \"depth\": 1}'),
            (1, 'Powered By', 'powered_by', 'footer_bottom', 'default', 100, '{}')
        ");

        // Set content for powered by block
        $db->exec("
            UPDATE blocks SET body = '<p>Powered by <a href=\"https://monkeyscms.com\">MonkeysCMS</a></p>'
            WHERE machine_name = 'powered_by'
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS block_revisions");
        $db->exec("DROP TABLE IF EXISTS blocks");
        $db->exec("DROP TABLE IF EXISTS block_types");
    }
};
