<?php

declare(strict_types=1);

namespace App\Cms\Plugin;

use Psr\Container\ContainerInterface;

/**
 * AbstractPlugin — Base class for all MonkeysCMS plugins.
 *
 * Provides sensible defaults for the lifecycle methods.
 * Plugins should extend this class instead of implementing PluginInterface directly.
 *
 * Usage:
 *   final class MyPlugin extends AbstractPlugin
 *   {
 *       public function register(ContainerInterface $container, HookManager $hooks): void
 *       {
 *           $hooks->on('content.after_save', function ($node) { ... });
 *           $hooks->filter('admin.menu', function (array $items) { ... return $items; });
 *       }
 *   }
 */
abstract class AbstractPlugin implements PluginInterface
{
    /** Plugin metadata (populated by PluginManager after discovery) */
    public PluginMetadata $metadata {
        set => $this->metadata = $value;
    }

    public function register(ContainerInterface $container, HookManager $hooks): void
    {
        // Override in plugin
    }

    public function boot(ContainerInterface $container): void
    {
        // Override in plugin
    }

    public function activate(ContainerInterface $container): void
    {
        // Override in plugin — default: run migrations
    }

    public function deactivate(ContainerInterface $container): void
    {
        // Override in plugin
    }

    public function uninstall(ContainerInterface $container): void
    {
        // Override in plugin
    }
}
