<?php

declare(strict_types=1);

/**
 * MonkeysCMS - Application Entry Point
 * 
 * This file serves as the front controller for all HTTP requests.
 * All requests are routed through this file by the web server.
 */

// Define the base path constant
define('ML_BASE_PATH', dirname(__DIR__));

// Load Composer autoloader
require_once ML_BASE_PATH . '/vendor/autoload.php';

use App\Core\Kernel;

// Initialize and Boot the Application Kernel
$kernel = new Kernel(ML_BASE_PATH);
$kernel->run();
