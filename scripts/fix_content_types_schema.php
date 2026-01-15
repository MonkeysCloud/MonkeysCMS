<?php
/**
 * Database Fix Script for Content Types
 * 
 * Run this script to recreate the content_types tables with the correct schema.
 * 
 * Usage: php scripts/fix_content_types_schema.php
 */

require __DIR__ . '/../vendor/autoload.php';

// Load .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value, '"\'');
            putenv("$key=$value");
        }
    }
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'monkeyscms';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "Connected to database: $database @ $host\n";
    
    // Drop old tables
    echo "Dropping old tables...\n";
    $pdo->exec("DROP TABLE IF EXISTS content_type_fields");
    $pdo->exec("DROP TABLE IF EXISTS content_types");
    
    // Create new tables with correct schema
    echo "Creating content_types table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS content_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_id VARCHAR(100) NOT NULL,
            label VARCHAR(255) NOT NULL,
            label_plural VARCHAR(255) NOT NULL,
            description TEXT NULL,
            icon VARCHAR(50) DEFAULT '📄',
            
            is_system TINYINT(1) DEFAULT 0,
            enabled TINYINT(1) DEFAULT 1,
            
            publishable TINYINT(1) DEFAULT 1,
            revisionable TINYINT(1) DEFAULT 0,
            translatable TINYINT(1) DEFAULT 0,
            has_author TINYINT(1) DEFAULT 1,
            has_taxonomy TINYINT(1) DEFAULT 1,
            has_media TINYINT(1) DEFAULT 1,
            
            title_field VARCHAR(50) DEFAULT 'title',
            slug_field VARCHAR(50) DEFAULT 'slug',
            url_pattern VARCHAR(255) NULL,
            
            default_values JSON NULL,
            settings JSON NULL,
            allowed_vocabularies JSON NULL,
            
            weight INT DEFAULT 0,
            
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_type_id (type_id),
            INDEX idx_weight (weight),
            INDEX idx_enabled (enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "Creating content_type_fields table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS content_type_fields (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content_type_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            machine_name VARCHAR(100) NOT NULL,
            field_type VARCHAR(50) NOT NULL,
            description TEXT NULL,
            help_text TEXT NULL,
            widget VARCHAR(50) NULL,
            
            required TINYINT(1) DEFAULT 0,
            multiple TINYINT(1) DEFAULT 0,
            cardinality INT DEFAULT 1,
            default_value TEXT NULL,
            
            settings JSON NULL,
            validation JSON NULL,
            widget_settings JSON NULL,
            
            weight INT DEFAULT 0,
            searchable TINYINT(1) DEFAULT 0,
            translatable TINYINT(1) DEFAULT 0,
            
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (content_type_id) REFERENCES content_types(id) ON DELETE CASCADE,
            UNIQUE KEY unique_field_per_type (content_type_id, machine_name),
            INDEX idx_content_type (content_type_id),
            INDEX idx_weight (weight)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "\n✅ Tables created successfully!\n";
    echo "Now refresh the Content Types page - the Article type should be auto-created.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
