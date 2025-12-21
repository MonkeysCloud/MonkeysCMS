<?php

declare(strict_types=1);

/**
 * Database Configuration
 * 
 * Copy this file to database.php and update with your settings.
 */

return [
    // Database driver (mysql, pgsql, sqlite)
    'driver' => 'mysql',

    // Database host
    'host' => getenv('DB_HOST') ?: 'localhost',

    // Database port
    'port' => (int) (getenv('DB_PORT') ?: 3306),

    // Database name
    'database' => getenv('DB_DATABASE') ?: 'monkeyscms',

    // Database username
    'username' => getenv('DB_USERNAME') ?: 'root',

    // Database password
    'password' => getenv('DB_PASSWORD') ?: '',

    // Character set
    'charset' => 'utf8mb4',

    // Collation
    'collation' => 'utf8mb4_unicode_ci',

    // Table prefix (optional)
    'prefix' => '',

    // PDO options
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
