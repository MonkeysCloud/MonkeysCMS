<?php
/**
 * Debug Script - Test Content Type Creation
 * 
 * Usage: php scripts/test_content_type_creation.php
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
    
    // Test data
    $now = date('Y-m-d H:i:s');
    $data = [
        'type_id' => 'article',
        'label' => 'Article',
        'label_plural' => 'Articles',
        'description' => 'Standard article content type for news, blog posts, and general content.',
        'icon' => '📝',
        'is_system' => 0,
        'enabled' => 1,
        'publishable' => 1,
        'revisionable' => 0,
        'translatable' => 0,
        'has_author' => 1,
        'has_taxonomy' => 1,
        'has_media' => 1,
        'title_field' => 'title',
        'slug_field' => 'slug',
        'url_pattern' => '/articles/{slug}',
        'default_values' => json_encode([]),
        'settings' => json_encode([]),
        'allowed_vocabularies' => json_encode([]),
        'weight' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    
    echo "Attempting to insert content type...\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO content_types (
            type_id, label, label_plural, description, icon, is_system, enabled,
            publishable, revisionable, translatable, has_author, has_taxonomy, has_media,
            title_field, slug_field, url_pattern, default_values, settings,
            allowed_vocabularies, weight, created_at, updated_at
        ) VALUES (
            :type_id, :label, :label_plural, :description, :icon, :is_system, :enabled,
            :publishable, :revisionable, :translatable, :has_author, :has_taxonomy, :has_media,
            :title_field, :slug_field, :url_pattern, :default_values, :settings,
            :allowed_vocabularies, :weight, :created_at, :updated_at
        )
    ");
    
    $stmt->execute($data);
    $id = $pdo->lastInsertId();
    
    echo "✅ Successfully created content type!\n";
    echo "   ID: $id\n";
    echo "   Type ID: article\n";
    echo "   Label: Article\n";
    
    // Verify
    $result = $pdo->query("SELECT * FROM content_types WHERE type_id = 'article'")->fetch();
    echo "\nVerification:\n";
    print_r($result);
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "   Code: " . $e->getCode() . "\n";
    exit(1);
}
