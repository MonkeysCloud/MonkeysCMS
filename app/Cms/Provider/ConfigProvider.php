<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Config\Collector\ContentTypesCollector;
use App\Cms\Config\Collector\MenusCollector;
use App\Cms\Config\Collector\PluginsCollector;
use App\Cms\Config\Collector\RolesCollector;
use App\Cms\Config\Collector\SettingsCollector;
use App\Cms\Config\Collector\TaxonomiesCollector;
use App\Cms\Config\ConfigManager;
use Psr\Container\ContainerInterface;
use PDO;

/**
 * ConfigProvider — Registers ConfigManager and all built-in collectors.
 *
 * Auto-discovers additional collectors from plugins and themes at runtime.
 */
final class ConfigProvider
{
    public static function getDefinitions(): array
    {
        return [
            ConfigManager::class => function (ContainerInterface $c): ConfigManager {
                $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);

                // Optional dependencies (may not exist during install)
                $pluginManager = null;
                $themeManager = null;

                try {
                    $pluginManager = $c->get(\App\Cms\Plugin\PluginManager::class);
                } catch (\Throwable) {}

                try {
                    $themeManager = $c->get(\App\Cms\Theme\ThemeManager::class);
                } catch (\Throwable) {}

                $manager = new ConfigManager($basePath, $pluginManager, $themeManager);
                $pdo = $c->get(PDO::class);

                // Register core collectors
                $manager->registerCollector(new SettingsCollector($pdo));
                $manager->registerCollector(new ContentTypesCollector($pdo));
                $manager->registerCollector(new TaxonomiesCollector($pdo));
                $manager->registerCollector(new MenusCollector($pdo));
                $manager->registerCollector(new RolesCollector($pdo));
                $manager->registerCollector(new PluginsCollector($pdo));

                return $manager;
            },
        ];
    }
}
