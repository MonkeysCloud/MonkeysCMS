<?php
/**
 * DB Connection Debugger
 * 
 * Access at /debug_db.php to see what DB the web server is connecting to.
 */

// Define base path
define('ML_BASE_PATH', dirname(__DIR__));

// Load Composer
require ML_BASE_PATH . '/vendor/autoload.php';

use App\Core\Kernel;
use MonkeysLegion\Database\Connection;
use PDO;

echo "<h1>Database Diagnostics</h1>";

try {
    // Boot Kernel
    $kernel = new Kernel(ML_BASE_PATH);
    $kernel->bootstrap();
    $container = $kernel->getContainer();
    
    echo "<p>✅ Kernel bootstrapped successfully</p>";
    
    // Check Config
    $config = $container->get('config');
    echo "<h2>Environment</h2>";
    echo "<ul>";
    echo "<li>DB_DATABASE (env): " . $_ENV['DB_DATABASE'] . "</li>";
    echo "<li>DB_DATABASE (config): " . $config->get('database.connections.mysql.database') . "</li>";
    echo "<li>APP_DEBUG: " . ($config->get('app.debug') ? 'true' : 'false') . "</li>";
    echo "</ul>";

    // Get PDO
    $pdo = $container->get(PDO::class);
    
    echo "<h3>PDO Connection Details</h3>";
    echo "<ul>";
    echo "<li>Client Version: " . $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION) . "</li>";
    echo "<li>Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "</li>";
    echo "<li>Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "</li>";
    echo "</ul>";
    
    // Check Content Types via PDO (Direct)
    echo "<h3>Content Types Table (Direct Query)</h3>";
    $stmt = $pdo->query("SELECT * FROM content_types");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($types) . " types in database.</p>";
    if (count($types) > 0) {
        echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Type ID</th><th>Label</th><th>Enabled</th></tr>";
        foreach ($types as $type) {
            echo "<tr>";
            echo "<td>{$type['id']}</td>";
            echo "<td>{$type['type_id']}</td>";
            echo "<td>{$type['label']}</td>";
            echo "<td>{$type['enabled']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red'>No content types found in table!</p>";
    }

    // Check ContentTypeManager (Service)
    echo "<h3>ContentTypeManager Service Check</h3>";
    if ($container->has(\App\Cms\ContentTypes\ContentTypeManager::class)) {
        try {
            $manager = $container->get(\App\Cms\ContentTypes\ContentTypeManager::class);
            $serviceTypes = $manager->getTypes();
            echo "<p>Manager reports " . count($serviceTypes) . " types.</p>";
            
            if (count($serviceTypes) === 0 && count($types) > 0) {
                 echo "<p style='color:red; font-weight:bold'>MISMATCH: Database has types, but Manager sees none!</p>";
            } elseif (count($serviceTypes) > 0) {
                echo "<ul>";
                foreach ($serviceTypes as $t) {
                    echo "<li>" . $t->type_id . " (" . $t->label . ")</li>";
                }
                echo "</ul>";
            }
        } catch (Exception $e) {
            echo "<p style='color:red'>Manager Error: " . $e->getMessage() . "</p>";
        }
    } else {
         echo "<p>ContentTypeManager service not found in container.</p>";
    }

} catch (Throwable $e) {
    echo "<div style='background:#fee; color:red; padding:10px; border:1px solid red'>";
    echo "<h3>Fatal Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
