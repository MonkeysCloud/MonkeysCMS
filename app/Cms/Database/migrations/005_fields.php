<?php

declare(strict_types=1);

/**
 * Migration: Create field definitions and values tables
 */

return new class {
    public function up(\PDO $db): void
    {
        // Field definitions table
        $db->exec("
            CREATE TABLE IF NOT EXISTS field_definitions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                machine_name VARCHAR(100) NOT NULL,
                field_type VARCHAR(50) NOT NULL,
                description TEXT NULL,
                help_text TEXT NULL,
                
                -- Widget settings
                widget VARCHAR(50) NULL,
                widget_settings JSON NULL,
                
                -- Validation
                required TINYINT(1) DEFAULT 0,
                multiple TINYINT(1) DEFAULT 0,
                cardinality INT DEFAULT 1,
                default_value TEXT NULL,
                settings JSON NULL,
                validation JSON NULL,
                
                -- Display
                weight INT DEFAULT 0,
                
                -- Flags
                searchable TINYINT(1) DEFAULT 0,
                translatable TINYINT(1) DEFAULT 0,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_machine_name (machine_name),
                INDEX idx_field_type (field_type),
                INDEX idx_weight (weight)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Field attachments - links fields to entity types
        $db->exec("
            CREATE TABLE IF NOT EXISTS field_attachments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                field_id INT NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                bundle_id INT NULL,
                weight INT DEFAULT 0,
                settings JSON NULL,
                display_settings JSON NULL,
                
                INDEX idx_field (field_id),
                INDEX idx_entity_bundle (entity_type, bundle_id),
                
                FOREIGN KEY (field_id) REFERENCES field_definitions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Field values table - EAV pattern
        $db->exec("
            CREATE TABLE IF NOT EXISTS field_values (
                id INT AUTO_INCREMENT PRIMARY KEY,
                field_id INT NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT NOT NULL,
                bundle_id INT NULL,
                langcode VARCHAR(10) DEFAULT 'en',
                delta INT DEFAULT 0,
                
                -- Value columns for different data types
                value_string VARCHAR(255) NULL,
                value_text LONGTEXT NULL,
                value_int INT NULL,
                value_decimal DECIMAL(20, 6) NULL,
                value_boolean TINYINT(1) NULL,
                value_date DATE NULL,
                value_datetime DATETIME NULL,
                value_json JSON NULL,
                value_blob LONGBLOB NULL,
                
                -- Indexes
                INDEX idx_field_entity (field_id, entity_type, entity_id),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_string (value_string),
                INDEX idx_int (value_int),
                INDEX idx_date (value_date),
                FULLTEXT idx_text (value_text),
                
                -- Unique constraint
                UNIQUE KEY unique_field_value (field_id, entity_type, entity_id, langcode, delta),
                
                FOREIGN KEY (field_id) REFERENCES field_definitions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Field revisions table
        $db->exec("
            CREATE TABLE IF NOT EXISTS field_revisions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                field_value_id INT NULL,
                field_id INT NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT NOT NULL,
                revision_id INT NOT NULL,
                langcode VARCHAR(10) DEFAULT 'en',
                delta INT DEFAULT 0,
                
                -- Value columns
                value_string VARCHAR(255) NULL,
                value_text LONGTEXT NULL,
                value_int INT NULL,
                value_decimal DECIMAL(20, 6) NULL,
                value_boolean TINYINT(1) NULL,
                value_date DATE NULL,
                value_datetime DATETIME NULL,
                value_json JSON NULL,
                
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_field_entity_revision (field_id, entity_type, entity_id, revision_id),
                
                FOREIGN KEY (field_id) REFERENCES field_definitions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed default fields
        $db->exec("
            INSERT INTO field_definitions (name, machine_name, field_type, description, widget, required, searchable) VALUES
            ('Body', 'field_body', 'text', 'The main content body', 'wysiwyg', 0, 1),
            ('Summary', 'field_summary', 'text', 'A brief summary', 'textarea', 0, 1),
            ('Image', 'field_image', 'image', 'Featured image', 'image', 0, 0),
            ('Tags', 'field_tags', 'taxonomy_reference', 'Content tags', 'taxonomy', 0, 0),
            ('Category', 'field_category', 'taxonomy_reference', 'Content category', 'select', 0, 0)
        ");

        // Attach fields to article content type
        $db->exec("
            INSERT INTO field_attachments (field_id, entity_type, bundle_id, weight) VALUES
            (1, 'node', 1, 0),
            (2, 'node', 1, 1),
            (3, 'node', 1, 2),
            (4, 'node', 1, 3),
            (5, 'node', 1, 4)
        ");

        // Attach body field to page content type
        $db->exec("
            INSERT INTO field_attachments (field_id, entity_type, bundle_id, weight) VALUES
            (1, 'node', 2, 0),
            (3, 'node', 2, 1)
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS field_revisions");
        $db->exec("DROP TABLE IF EXISTS field_values");
        $db->exec("DROP TABLE IF EXISTS field_attachments");
        $db->exec("DROP TABLE IF EXISTS field_definitions");
    }
};
