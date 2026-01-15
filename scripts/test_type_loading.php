<?php
/**
 * Debug Script - Test ContentTypeManager Loading
 * 
 * Usage: php scripts/test_type_loading.php
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
    
    echo "Connected to database: $database @ $host\n\n";
    
    // Test the exact query ContentTypeManager uses
    echo "Testing loadDatabaseTypes query...\n";
    $stmt = $pdo->query("SELECT * FROM content_types WHERE enabled = 1 ORDER BY weight, label");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($rows) . " content types:\n\n";
    foreach ($rows as $row) {
        echo "  ID: " . $row['id'] . "\n";
        echo "  Type ID: " . $row['type_id'] . "\n";
        echo "  Label: " . $row['label'] . "\n";
        echo "  Enabled: " . $row['enabled'] . "\n";
        echo "\n";
        
        // Test hydrate
        echo "Testing ContentTypeEntity hydration...\n";
        $entity = new \App\Cms\ContentTypes\ContentTypeEntity();
        $entity->hydrate($row);
        echo "  Entity type_id: " . $entity->type_id . "\n";
        echo "  Entity label: " . $entity->label . "\n";
        echo "  Hydration SUCCESS\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
