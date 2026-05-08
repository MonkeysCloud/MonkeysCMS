<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Plugin\HookManager;
use App\Cms\Plugin\PluginManager;
use App\Cms\Plugin\PluginSettingsService;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * PluginProvider — Registers the Plugin system in the DI container.
 *
 * Provides:
 *   - HookManager: event/filter dispatch system (shared singleton)
 *   - PluginManager: auto-discovery and lifecycle management
 *
 * The plugin boot sequence is:
 *   1. HookManager created (empty)
 *   2. PluginManager created, discovers plugins/ directory
 *   3. Enabled plugins are loaded (register phase)
 *   4. All loaded plugins are booted (boot phase)
 */
final class PluginProvider
{
    private static ?PluginManager $pluginManager = null;
    /**
     * DI definitions for plugin system.
     */
    public static function getDefinitions(): array
    {
        return [
            HookManager::class => function (ContainerInterface $c): HookManager {
                $hooks = new HookManager();

                // Eagerly boot the PluginManager so hooks are populated
                // before any middleware or controller requests HookManager.
                try {
                    $logger = null;
                    try {
                        $logger = $c->get(LoggerInterface::class);
                    } catch (\Throwable) {}

                    $manager = new PluginManager(
                        pdo: $c->get(PDO::class),
                        hooks: $hooks,
                        basePath: base_path(),
                        logger: $logger,
                    );

                    $manager->discover();
                    $manager->loadEnabled($c);
                    $manager->bootAll($c);

                    // Store the manager for later DI resolution
                    self::$pluginManager = $manager;
                } catch (\Throwable $e) {
                    // Silently fail during install or pre-migration
                    error_log('[PluginProvider] Boot failed: ' . $e->getMessage());
                }

                return $hooks;
            },

            PluginManager::class => function (ContainerInterface $c): PluginManager {
                // Ensure HookManager (and thus PluginManager) is booted
                $c->get(HookManager::class);

                if (self::$pluginManager !== null) {
                    return self::$pluginManager;
                }

                // Fallback: create an empty manager
                return new PluginManager(
                    pdo: $c->get(PDO::class),
                    hooks: $c->get(HookManager::class),
                    basePath: base_path(),
                );
            },

            PluginSettingsService::class => fn(ContainerInterface $c): PluginSettingsService => new PluginSettingsService(
                pdo: $c->get(PDO::class),
            ),
        ];
    }
}
