<?php

declare(strict_types=1);

/**
 * Migration: Create content types table
 */
return new class {
    public function up(\PDO $db): void
    {
        // Content types table
        $db->exec("
            CREATE TABLE IF NOT EXISTS content_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                machine_name VARCHAR(100) NOT NULL,
                description TEXT NULL,
                
                -- Display settings
                title_label VARCHAR(100) DEFAULT 'Title',
                show_title TINYINT(1) DEFAULT 1,
                show_author TINYINT(1) DEFAULT 1,
                show_date TINYINT(1) DEFAULT 1,
                
                -- Publishing options
                default_status ENUM('draft', 'published') DEFAULT 'draft',
                enable_revisions TINYINT(1) DEFAULT 1,
                enable_comments TINYINT(1) DEFAULT 0,
                
                -- URL pattern
                url_pattern VARCHAR(255) DEFAULT '/[type]/[slug]',
                
                -- Workflow
                workflow_id INT NULL,
                
                -- Metadata
                icon VARCHAR(50) DEFAULT 'file-text',
                is_system TINYINT(1) DEFAULT 0,
                weight INT DEFAULT 0,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_machine_name (machine_name),
                INDEX idx_weight (weight)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Content type field assignments
        $db->exec("
            CREATE TABLE IF NOT EXISTS content_type_fields (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content_type_id INT NOT NULL,
                field_id INT NOT NULL,
                weight INT DEFAULT 0,
                region VARCHAR(50) DEFAULT 'main',
                settings JSON NULL,
                display_settings JSON NULL,
                
                FOREIGN KEY (content_type_id) REFERENCES content_types(id) ON DELETE CASCADE,
                INDEX idx_content_type (content_type_id),
                INDEX idx_weight (weight)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed default content types
        $db->exec("
            INSERT INTO content_types (name, machine_name, description, icon, is_system) VALUES
            ('Article', 'article', 'Use articles for time-sensitive content like news, press releases or blog posts.', 'newspaper', 0),
            ('Page', 'page', 'Use basic pages for static content, such as an About page.', 'file-text', 0)
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS content_type_fields");
        $db->exec("DROP TABLE IF EXISTS content_types");
    }
};
