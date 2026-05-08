<?php
declare(strict_types=1);

use MonkeysLegion\Framework\Application;
use MonkeysLegion\Router\ControllerScanner;

define('ML_BASE_PATH', dirname(__DIR__));
require ML_BASE_PATH . '/vendor/autoload.php';

$app = Application::create(basePath: ML_BASE_PATH);

// Load user DI bindings (interface overrides + content services)
$userBindings = require ML_BASE_PATH . '/config/app.php';
$app->withBindings($userBindings);

// Boot the framework (registers providers, middleware, etc.)
$container = $app->boot();

// ── CMS Controller Registration ─────────────────────────────────────────
// The framework auto-scans app/Controller/ for routes.
// CMS controllers live in app/Cms/Controller/ and need to be registered too.
if ($container->has(ControllerScanner::class)) {
    /** @var ControllerScanner $scanner */
    $scanner = $container->get(ControllerScanner::class);
    $cmsDir = ML_BASE_PATH . '/app/Cms/Controller';
    if (is_dir($cmsDir)) {
        $scanner->scan($cmsDir, 'App\\Cms\\Controller');
    }
}

// Run the HTTP kernel
$app->run();