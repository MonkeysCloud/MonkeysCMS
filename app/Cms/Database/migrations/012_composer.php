<?php

declare(strict_types=1);

/**
 * Migration: Create Content Composer tables
 * 
 * Adds composer support to content types and creates templates table
 */

return new class {
    public function up(\PDO $db): void
    {
        // Add composer columns to content_types
        $db->exec("
            ALTER TABLE content_types 
            ADD COLUMN IF NOT EXISTS composer_enabled TINYINT(1) DEFAULT 0 AFTER has_media,
            ADD COLUMN IF NOT EXISTS composer_default TINYINT(1) DEFAULT 0 AFTER composer_enabled
        ");

        // Add composer columns to nodes
        $db->exec("
            ALTER TABLE nodes 
            ADD COLUMN IF NOT EXISTS use_composer TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS composer_data JSON NULL
        ");

        // Composer templates - saved layouts for reuse
        $db->exec("
            CREATE TABLE IF NOT EXISTS composer_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT NULL,
                category VARCHAR(100) DEFAULT 'general',
                
                -- Template data
                data JSON NOT NULL,
                
                -- Type: 'section', 'row', 'page' (full page layout)
                template_type VARCHAR(50) DEFAULT 'section',
                
                -- Preview
                preview_image VARCHAR(255) NULL,
                
                -- Status
                is_global TINYINT(1) DEFAULT 1,
                is_system TINYINT(1) DEFAULT 0,
                
                -- Timestamps
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_slug (slug),
                INDEX idx_category (category),
                INDEX idx_type (template_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Saved sections (reusable content blocks within pages)
        $db->exec("
            CREATE TABLE IF NOT EXISTS composer_saved_sections (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT NULL,
                
                -- Section data (single section JSON)
                data JSON NOT NULL,
                
                -- Categorization
                category VARCHAR(100) DEFAULT 'general',
                tags JSON NULL,
                
                -- Preview
                preview_image VARCHAR(255) NULL,
                
                -- Status
                is_global TINYINT(1) DEFAULT 1,
                created_by INT NULL,
                
                -- Timestamps
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_slug (slug),
                INDEX idx_category (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS composer_saved_sections");
        $db->exec("DROP TABLE IF EXISTS composer_templates");
        
        // Remove composer columns (be careful in production)
        $db->exec("ALTER TABLE nodes DROP COLUMN IF EXISTS composer_data");
        $db->exec("ALTER TABLE nodes DROP COLUMN IF EXISTS use_composer");
        $db->exec("ALTER TABLE content_types DROP COLUMN IF EXISTS composer_default");
        $db->exec("ALTER TABLE content_types DROP COLUMN IF EXISTS composer_enabled");
    }
};
