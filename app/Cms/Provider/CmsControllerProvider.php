<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use MonkeysLegion\DI\Container;
use MonkeysLegion\Router\ControllerScanner;

/**
 * Registers CMS controllers (App\Cms\Controller) with the router scanner.
 *
 * The framework's kernel auto-scans app/Controller/ but not app/Cms/Controller/.
 * This provider bridges the gap so all CMS routes are discovered.
 */
final class CmsControllerProvider
{
    public function __invoke(Container $container): void
    {
        if (!$container->has(ControllerScanner::class)) {
            return;
        }

        /** @var ControllerScanner $scanner */
        $scanner = $container->get(ControllerScanner::class);

        $basePath = $container->get('basePath') ?? dirname(__DIR__, 3);
        $cmsControllerDir = $basePath . '/app/Cms/Controller';

        if (is_dir($cmsControllerDir)) {
            $scanner->scan($cmsControllerDir, 'App\\Cms\\Controller');
        }
    }
}
