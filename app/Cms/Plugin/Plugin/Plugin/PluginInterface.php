<?php

declare(strict_types=1);

namespace App\Cms\Plugin;

use Psr\Container\ContainerInterface;

/**
 * PluginInterface — Lifecycle contract for MonkeysCMS plugins.
 *
 * Plugins are discovered automatically from the plugins/ directory.
 * They follow a Drupal-module-like architecture: auto-discovered,
 * with a standard directory structure and MLC metadata.
 *
 * Directory layout:
 *   plugins/{custom|contrib}/vendor-name/plugin-name/
 *     ├── plugin-name.plugin.mlc  # Metadata (Drupal-style naming)
 *     ├── src/
 *     │   └── PluginProvider.php  # implements PluginInterface
 *     ├── views/                 # Plugin .ml.php templates
 *     ├── assets/                # CSS/JS (auto-loaded)
 *     └── migrations/            # MLC migration files
 */
interface PluginInterface
{
    /**
     * Register DI bindings, hook listeners, and routes.
     *
     * Called once when the plugin is loaded during the boot sequence.
     * This is where you wire services, register hooks, and add menu items.
     */
    public function register(ContainerInterface $container, HookManager $hooks): void;

    /**
     * Boot the plugin after ALL plugins have been registered.
     *
     * Use this for logic that depends on other plugins being present.
     */
    public function boot(ContainerInterface $container): void;

    /**
     * Called once when the plugin is first activated.
     *
     * Run migrations, seed default data, create config entries.
     */
    public function activate(ContainerInterface $container): void;

    /**
     * Called when the plugin is disabled (data is preserved).
     */
    public function deactivate(ContainerInterface $container): void;

    /**
     * Called when the plugin is fully removed (cleanup data).
     */
    public function uninstall(ContainerInterface $container): void;
}
