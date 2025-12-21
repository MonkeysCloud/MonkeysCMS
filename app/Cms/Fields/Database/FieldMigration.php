<?php

declare(strict_types=1);

namespace App\Cms\Fields\Database;

/**
 * FieldMigration - Database schema for the field widget system
 *
 * Creates tables for:
 * - field_definitions: Stores field configurations
 * - field_attachments: Links fields to entity types
 * - field_values: Stores field values (EAV pattern)
 */
final class FieldMigration
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Run all migrations
     */
    public function up(): void
    {
        $this->createFieldDefinitionsTable();
        $this->createFieldAttachmentsTable();
        $this->createFieldValuesTable();
        $this->createFieldRevisionsTable();
        $this->createIndexes();
    }

    /**
     * Rollback all migrations
     */
    public function down(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS field_revisions');
        $this->db->exec('DROP TABLE IF EXISTS field_values');
        $this->db->exec('DROP TABLE IF EXISTS field_attachments');
        $this->db->exec('DROP TABLE IF EXISTS field_definitions');
    }

    /**
     * Create field_definitions table
     */
    private function createFieldDefinitionsTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS field_definitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    machine_name VARCHAR(100) NOT NULL UNIQUE,
    field_type VARCHAR(50) NOT NULL,
    description VARCHAR(500) NULL,
    help_text VARCHAR(500) NULL,
    widget VARCHAR(50) NULL,
    widget_settings JSON NULL,
    required TINYINT(1) NOT NULL DEFAULT 0,
    multiple TINYINT(1) NOT NULL DEFAULT 0,
    cardinality INT NOT NULL DEFAULT 1,
    default_value TEXT NULL,
    settings JSON NULL,
    validation JSON NULL,
    weight INT NOT NULL DEFAULT 0,
    searchable TINYINT(1) NOT NULL DEFAULT 0,
    translatable TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_field_type (field_type),
    INDEX idx_machine_name (machine_name),
    INDEX idx_weight (weight)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $this->db->exec($sql);
    }

    /**
     * Create field_attachments table
     *
     * Links fields to entity types (content types, block types, etc.)
     */
    private function createFieldAttachmentsTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS field_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    field_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    bundle_id INT UNSIGNED NULL,
    weight INT NOT NULL DEFAULT 0,
    settings JSON NULL,
    display_settings JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_field_entity (field_id, entity_type, bundle_id),
    INDEX idx_entity_type (entity_type),
    INDEX idx_bundle_id (bundle_id),
    INDEX idx_weight (weight),
    
    CONSTRAINT fk_attachment_field 
        FOREIGN KEY (field_id) REFERENCES field_definitions(id) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $this->db->exec($sql);
    }

    /**
     * Create field_values table (EAV pattern)
     *
     * Stores field values for all entities
     */
    private function createFieldValuesTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS field_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    field_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    bundle_id INT UNSIGNED NULL,
    langcode VARCHAR(12) NOT NULL DEFAULT 'en',
    delta INT UNSIGNED NOT NULL DEFAULT 0,
    
    -- Value columns for different types
    value_string VARCHAR(255) NULL,
    value_text LONGTEXT NULL,
    value_int BIGINT NULL,
    value_decimal DECIMAL(20,6) NULL,
    value_boolean TINYINT(1) NULL,
    value_date DATE NULL,
    value_datetime DATETIME NULL,
    value_json JSON NULL,
    value_blob LONGBLOB NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_field_value (field_id, entity_type, entity_id, langcode, delta),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_field_entity (field_id, entity_type),
    INDEX idx_bundle (bundle_id),
    INDEX idx_langcode (langcode),
    INDEX idx_value_string (value_string),
    INDEX idx_value_int (value_int),
    INDEX idx_value_date (value_date),
    
    CONSTRAINT fk_value_field 
        FOREIGN KEY (field_id) REFERENCES field_definitions(id) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $this->db->exec($sql);
    }

    /**
     * Create field_revisions table
     *
     * Stores historical versions of field values
     */
    private function createFieldRevisionsTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS field_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    field_value_id BIGINT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    revision_id INT UNSIGNED NOT NULL,
    langcode VARCHAR(12) NOT NULL DEFAULT 'en',
    delta INT UNSIGNED NOT NULL DEFAULT 0,
    
    -- Value columns (same as field_values)
    value_string VARCHAR(255) NULL,
    value_text LONGTEXT NULL,
    value_int BIGINT NULL,
    value_decimal DECIMAL(20,6) NULL,
    value_boolean TINYINT(1) NULL,
    value_date DATE NULL,
    value_datetime DATETIME NULL,
    value_json JSON NULL,
    value_blob LONGBLOB NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    
    INDEX idx_field_value (field_value_id),
    INDEX idx_entity_revision (entity_type, entity_id, revision_id),
    INDEX idx_field_revision (field_id, revision_id),
    
    CONSTRAINT fk_revision_field 
        FOREIGN KEY (field_id) REFERENCES field_definitions(id) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $this->db->exec($sql);
    }

    /**
     * Create additional indexes for performance
     */
    private function createIndexes(): void
    {
        // Full-text index for searchable text fields
        try {
            $this->db->exec(
                "ALTER TABLE field_values ADD FULLTEXT INDEX ft_value_text (value_text)"
            );
        } catch (\PDOException $e) {
            // Index might already exist
        }
    }

    /**
     * Seed default field types
     */
    public function seed(): void
    {
        $fields = [
            [
                'name' => 'Title',
                'machine_name' => 'field_title',
                'field_type' => 'string',
                'required' => true,
                'searchable' => true,
                'settings' => json_encode(['max_length' => 255]),
            ],
            [
                'name' => 'Body',
                'machine_name' => 'field_body',
                'field_type' => 'text',
                'widget' => 'wysiwyg',
                'searchable' => true,
            ],
            [
                'name' => 'Summary',
                'machine_name' => 'field_summary',
                'field_type' => 'text',
                'widget' => 'textarea',
                'settings' => json_encode(['max_length' => 500]),
            ],
            [
                'name' => 'Featured Image',
                'machine_name' => 'field_image',
                'field_type' => 'image',
            ],
            [
                'name' => 'Tags',
                'machine_name' => 'field_tags',
                'field_type' => 'taxonomy_reference',
                'multiple' => true,
                'widget_settings' => json_encode([
                    'vocabulary' => 'tags',
                    'display_style' => 'tags',
                    'allow_new' => true,
                ]),
            ],
            [
                'name' => 'Category',
                'machine_name' => 'field_category',
                'field_type' => 'taxonomy_reference',
                'widget_settings' => json_encode([
                    'vocabulary' => 'categories',
                    'display_style' => 'select',
                ]),
            ],
            [
                'name' => 'Published',
                'machine_name' => 'field_published',
                'field_type' => 'boolean',
                'widget' => 'switch',
                'default_value' => '0',
            ],
            [
                'name' => 'Publish Date',
                'machine_name' => 'field_publish_date',
                'field_type' => 'datetime',
            ],
            [
                'name' => 'Author',
                'machine_name' => 'field_author',
                'field_type' => 'user_reference',
            ],
            [
                'name' => 'URL Slug',
                'machine_name' => 'field_slug',
                'field_type' => 'slug',
                'required' => true,
                'widget_settings' => json_encode(['source_field' => 'field_title']),
            ],
        ];

        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO field_definitions 
            (name, machine_name, field_type, widget, required, multiple, searchable, default_value, settings, widget_settings) 
            VALUES 
            (:name, :machine_name, :field_type, :widget, :required, :multiple, :searchable, :default_value, :settings, :widget_settings)"
        );

        foreach ($fields as $field) {
            $stmt->execute([
                'name' => $field['name'],
                'machine_name' => $field['machine_name'],
                'field_type' => $field['field_type'],
                'widget' => $field['widget'] ?? null,
                'required' => $field['required'] ?? false,
                'multiple' => $field['multiple'] ?? false,
                'searchable' => $field['searchable'] ?? false,
                'default_value' => $field['default_value'] ?? null,
                'settings' => $field['settings'] ?? null,
                'widget_settings' => $field['widget_settings'] ?? null,
            ]);
        }
    }
}

/**
 * Run migration from command line
 */
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    $dsn = $argv[1] ?? 'mysql:host=localhost;dbname=monkeyscms';
    $user = $argv[2] ?? 'root';
    $pass = $argv[3] ?? '';

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $migration = new FieldMigration($pdo);

        $action = $argv[4] ?? 'up';

        switch ($action) {
            case 'up':
                echo "Running field migrations...\n";
                $migration->up();
                echo "Migrations completed successfully.\n";
                break;

            case 'down':
                echo "Rolling back field migrations...\n";
                $migration->down();
                echo "Rollback completed successfully.\n";
                break;

            case 'seed':
                echo "Seeding default fields...\n";
                $migration->seed();
                echo "Seeding completed successfully.\n";
                break;

            case 'fresh':
                echo "Fresh migration (down + up + seed)...\n";
                $migration->down();
                $migration->up();
                $migration->seed();
                echo "Fresh migration completed successfully.\n";
                break;

            default:
                echo "Unknown action: {$action}\n";
                echo "Usage: php FieldMigration.php [dsn] [user] [pass] [up|down|seed|fresh]\n";
                exit(1);
        }
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
