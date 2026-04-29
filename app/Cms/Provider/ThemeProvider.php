<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Theme\AssetResolver;
use App\Cms\Theme\ThemeManager;
use Psr\Container\ContainerInterface;

/**
 * ThemeProvider — Manages theme discovery, loading, and asset resolution.
 *
 * Supports the tiered theme hierarchy:
 *   themes/core/   — Built-in (admin + front)
 *   themes/contrib/ — Community themes
 *   themes/custom/  — User-created themes
 */
final class ThemeProvider
{
    public static function getDefinitions(): array
    {
        return [
            ThemeManager::class => function (ContainerInterface $c): ThemeManager {
                $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
                $activeTheme = $_ENV['CMS_THEME'] ?? 'front';
                $adminTheme = $_ENV['CMS_ADMIN_THEME'] ?? 'admin';

                return new ThemeManager($basePath, $activeTheme, $adminTheme);
            },

            AssetResolver::class => function (ContainerInterface $c): AssetResolver {
                $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
                $isDev = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
                $devUrl = $_ENV['VITE_DEV_URL'] ?? 'http://localhost:5173';

                return new AssetResolver($basePath, $isDev, $devUrl);
            },
        ];
    }
}
