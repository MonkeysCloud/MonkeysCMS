<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Kernel;
use PDO;

$kernel = new Kernel(dirname(__DIR__));
$kernel->bootstrap();
$container = $kernel->getContainer();
$pdo = $container->get(PDO::class);

echo "--- Database Settings ---\n";
try {
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE `key` LIKE 'auth.session%'");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo "{$row['key']}: {$row['value']} (Type: {$row['type']})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n--- PHP Session Config (Default) ---\n";
echo "session.gc_maxlifetime: " . ini_get('session.gc_maxlifetime') . "\n";
echo "session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "\n";
echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "session.save_handler: " . ini_get('session.save_handler') . "\n";

echo "\n--- Session Directory Check ---\n";
$basePath = dirname(__DIR__);
$sessionPath = $basePath . '/storage/sessions';
echo "Target Path: $sessionPath\n";
echo "Exists: " . (is_dir($sessionPath) ? 'Yes' : 'No') . "\n";
echo "Writable: " . (is_writable($sessionPath) ? 'Yes' : 'No') . "\n";


echo "\n--- Test Auth Boot Logic ---\n";
// Simulate the logic in CmsServiceProvider
$config = []; // Default empty
// Logic copy-paste from provider
$stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
$stmt->execute(['auth.session_lifetime']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$val = $row ? (int)$row['value'] : 'Not Found';
echo "Computed session_lifetime: $val\n";
