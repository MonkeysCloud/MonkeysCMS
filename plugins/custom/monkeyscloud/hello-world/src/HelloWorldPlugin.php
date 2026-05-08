<?php

declare(strict_types=1);

namespace MonkeysCloud\HelloWorld;

use App\Cms\Plugin\AbstractPlugin;
use App\Cms\Plugin\HookManager;
use Psr\Container\ContainerInterface;

/**
 * HelloWorldPlugin — Sample plugin demonstrating the MonkeysCMS plugin system.
 *
 * Capabilities demonstrated:
 *   1. Hook into admin sidebar menu (admin.menu filter)
 *   2. Hook into content lifecycle (content.after_save event)
 *   3. Hook into dashboard widgets (admin.dashboard.widgets filter)
 *   4. Register custom services in the DI container
 *   5. Register custom controllers (auto-discovered by router)
 *
 * Plugins can also:
 *   - Define new content types via MLC config
 *   - Create new entities with custom tables
 *   - Extend existing services via DI decoration
 *   - Add custom field widgets
 *   - Register middleware
 */
final class HelloWorldPlugin extends AbstractPlugin
{
    public function register(ContainerInterface $container, HookManager $hooks): void
    {
        // ── 1. Add menu item to admin sidebar ──────────────────────────
        $hooks->filter('admin.menu', function (array $items): array {
            $items[] = [
                'label'  => 'Hello World',
                'url'    => '/admin/hello-world',
                'icon'   => 'hand-metal',
                'weight' => 90,
                'group'  => 'plugins',
            ];
            return $items;
        });

        // ── 2. Hook into content save lifecycle ────────────────────────
        $hooks->on('content.after_save', function (object $node): void {
            // Example: log or modify saved content
            // In a real plugin, you'd do something useful here
            error_log("[HelloWorld] Content saved: {$node->title} (ID: {$node->id})");
        });

        // ── 3. Add a dashboard widget ──────────────────────────────────
        $hooks->filter('admin.dashboard.widgets', function (array $widgets): array {
            $widgets[] = [
                'id'       => 'hello-world-widget',
                'title'    => 'Hello World',
                'template' => 'hello-world::dashboard-widget',
                'weight'   => 50,
                'data'     => [
                    'greeting' => 'Hello from the plugin system!',
                ],
            ];
            return $widgets;
        });

        // ── 4. Register custom services ────────────────────────────────
        // Plugins have full access to the DI container and can register
        // or decorate any service:
        //
        //   $container->set(MyService::class, fn() => new MyService());
        //
        // For now, this sample plugin doesn't need custom services.
    }

    public function boot(ContainerInterface $container): void
    {
        // Boot runs after ALL plugins are registered.
        // Use this for cross-plugin dependencies or late initialization.
    }

    public function activate(ContainerInterface $container): void
    {
        // Called once when the plugin is first enabled.
        // Run migrations, seed data, create default settings.
        //
        // Example: create a custom table
        // $pdo = $container->get(\PDO::class);
        // $pdo->exec('CREATE TABLE IF NOT EXISTS hello_world_greetings (...)');
    }

    public function deactivate(ContainerInterface $container): void
    {
        // Called when the plugin is disabled (data preserved).
    }

    public function uninstall(ContainerInterface $container): void
    {
        // Called when the plugin is fully removed.
        // Drop custom tables, clean up settings.
        //
        // Example: drop custom table
        // $pdo = $container->get(\PDO::class);
        // $pdo->exec('DROP TABLE IF EXISTS hello_world_greetings');
    }
}
