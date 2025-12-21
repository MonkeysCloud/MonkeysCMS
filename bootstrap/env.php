<?php

declare(strict_types=1);

/**
 * Environment Bootstrap
 * 
 * This file loads environment variables before the application boots.
 * It's called by both HTTP and CLI entry points.
 */

use Dotenv\Dotenv;

// Determine base path
$basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : dirname(__DIR__);

// Load environment variables if .env exists
$envFile = $basePath . '/.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv::createImmutable($basePath);
    $dotenv->safeLoad();
}

// Set error reporting based on environment
$debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}

// Set default timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

// Return environment array for use in configuration
return [
    'app_name' => $_ENV['APP_NAME'] ?? 'MonkeysCMS',
    'app_env' => $_ENV['APP_ENV'] ?? 'production',
    'app_debug' => $debug,
    'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
];
