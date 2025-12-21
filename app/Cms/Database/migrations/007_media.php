<?php

declare(strict_types=1);

/**
 * Migration: Create media tables
 */

return new class {
    public function up(\PDO $db): void
    {
        // Media folders
        $db->exec("
            CREATE TABLE IF NOT EXISTS media_folders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                parent_id INT NULL,
                path VARCHAR(500) NULL,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_parent (parent_id),
                INDEX idx_path (path),
                
                FOREIGN KEY (parent_id) REFERENCES media_folders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Media items
        $db->exec("
            CREATE TABLE IF NOT EXISTS media (
                id INT AUTO_INCREMENT PRIMARY KEY,
                uuid VARCHAR(36) NOT NULL,
                folder_id INT NULL,
                
                -- File info
                filename VARCHAR(255) NOT NULL,
                filepath VARCHAR(500) NOT NULL,
                filesize INT NOT NULL DEFAULT 0,
                mimetype VARCHAR(100) NOT NULL,
                
                -- Metadata
                title VARCHAR(255) NULL,
                alt_text VARCHAR(255) NULL,
                description TEXT NULL,
                
                -- Image specific
                width INT NULL,
                height INT NULL,
                focal_point VARCHAR(20) NULL,
                
                -- Processing
                metadata JSON NULL,
                
                -- Ownership
                author_id INT NULL,
                
                -- Timestamps
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                
                UNIQUE KEY unique_uuid (uuid),
                INDEX idx_folder (folder_id),
                INDEX idx_mimetype (mimetype),
                INDEX idx_author (author_id),
                INDEX idx_created (created_at),
                
                FOREIGN KEY (folder_id) REFERENCES media_folders(id) ON DELETE SET NULL,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Media derivatives (image styles/sizes)
        $db->exec("
            CREATE TABLE IF NOT EXISTS media_derivatives (
                id INT AUTO_INCREMENT PRIMARY KEY,
                media_id INT NOT NULL,
                style VARCHAR(50) NOT NULL,
                filepath VARCHAR(500) NOT NULL,
                width INT NULL,
                height INT NULL,
                filesize INT NOT NULL DEFAULT 0,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_media (media_id),
                INDEX idx_style (style),
                UNIQUE KEY unique_media_style (media_id, style),
                
                FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Image styles configuration
        $db->exec("
            CREATE TABLE IF NOT EXISTS image_styles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                machine_name VARCHAR(100) NOT NULL,
                effects JSON NULL,
                
                UNIQUE KEY unique_machine_name (machine_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Media usage tracking
        $db->exec("
            CREATE TABLE IF NOT EXISTS media_usage (
                id INT AUTO_INCREMENT PRIMARY KEY,
                media_id INT NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT NOT NULL,
                field_name VARCHAR(100) NULL,
                
                INDEX idx_media (media_id),
                INDEX idx_entity (entity_type, entity_id),
                UNIQUE KEY unique_usage (media_id, entity_type, entity_id, field_name),
                
                FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed default image styles
        $db->exec("
            INSERT INTO image_styles (name, machine_name, effects) VALUES
            ('Thumbnail', 'thumbnail', '{\"resize\": {\"width\": 150, \"height\": 150, \"mode\": \"cover\"}}'),
            ('Small', 'small', '{\"resize\": {\"width\": 300, \"height\": 300, \"mode\": \"contain\"}}'),
            ('Medium', 'medium', '{\"resize\": {\"width\": 600, \"height\": 600, \"mode\": \"contain\"}}'),
            ('Large', 'large', '{\"resize\": {\"width\": 1200, \"height\": 1200, \"mode\": \"contain\"}}'),
            ('Hero', 'hero', '{\"resize\": {\"width\": 1920, \"height\": 600, \"mode\": \"cover\"}}')
        ");

        // Seed root folder
        $db->exec("
            INSERT INTO media_folders (name, path) VALUES
            ('Root', '/')
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS media_usage");
        $db->exec("DROP TABLE IF EXISTS image_styles");
        $db->exec("DROP TABLE IF EXISTS media_derivatives");
        $db->exec("DROP TABLE IF EXISTS media");
        $db->exec("DROP TABLE IF EXISTS media_folders");
    }
};
