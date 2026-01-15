<?php

declare(strict_types=1);

/**
 * Migration: Create content types tables
 * 
 * Schema aligned with ContentTypeManager and ContentTypeEntity
 */

return new class {
    public function up(\PDO $db): void
    {
        // Content types table - aligned with ContentTypeManager
        $db->exec("
            CREATE TABLE IF NOT EXISTS content_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type_id VARCHAR(100) NOT NULL,
                label VARCHAR(255) NOT NULL,
                label_plural VARCHAR(255) NOT NULL,
                description TEXT NULL,
                icon VARCHAR(50) DEFAULT '📄',
                
                -- System flags
                is_system TINYINT(1) DEFAULT 0,
                enabled TINYINT(1) DEFAULT 1,
                
                -- Features
                publishable TINYINT(1) DEFAULT 1,
                revisionable TINYINT(1) DEFAULT 0,
                translatable TINYINT(1) DEFAULT 0,
                has_author TINYINT(1) DEFAULT 1,
                has_taxonomy TINYINT(1) DEFAULT 1,
                has_media TINYINT(1) DEFAULT 1,
                
                -- Field config
                title_field VARCHAR(50) DEFAULT 'title',
                slug_field VARCHAR(50) DEFAULT 'slug',
                url_pattern VARCHAR(255) NULL,
                
                -- JSON settings
                default_values JSON NULL,
                settings JSON NULL,
                allowed_vocabularies JSON NULL,
                
                -- Ordering
                weight INT DEFAULT 0,
                
                -- Timestamps
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_type_id (type_id),
                INDEX idx_weight (weight),
                INDEX idx_enabled (enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Content type fields table - aligned with FieldDefinition
        $db->exec("
            CREATE TABLE IF NOT EXISTS content_type_fields (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content_type_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                machine_name VARCHAR(100) NOT NULL,
                field_type VARCHAR(50) NOT NULL,
                description TEXT NULL,
                help_text TEXT NULL,
                widget VARCHAR(50) NULL,
                
                -- Field settings
                required TINYINT(1) DEFAULT 0,
                multiple TINYINT(1) DEFAULT 0,
                cardinality INT DEFAULT 1,
                default_value TEXT NULL,
                
                -- JSON config
                settings JSON NULL,
                validation JSON NULL,
                widget_settings JSON NULL,
                
                -- Display
                weight INT DEFAULT 0,
                searchable TINYINT(1) DEFAULT 0,
                translatable TINYINT(1) DEFAULT 0,
                
                -- Timestamps
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                FOREIGN KEY (content_type_id) REFERENCES content_types(id) ON DELETE CASCADE,
                UNIQUE KEY unique_field_per_type (content_type_id, machine_name),
                INDEX idx_content_type (content_type_id),
                INDEX idx_weight (weight)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS content_type_fields");
        $db->exec("DROP TABLE IF EXISTS content_types");
    }
};
