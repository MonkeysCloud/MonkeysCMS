<?php

declare(strict_types=1);

namespace App\Modules\Custom\Ecommerce;

/**
 * Ecommerce Module Loader
 *
 * This class is called by the ModuleManager during module lifecycle events.
 * Use it to:
 * - Register routes specific to this module
 * - Add event listeners
 * - Seed initial data
 * - Run custom installation logic
 *
 * Unlike WordPress plugin activation hooks (which run once and can fail silently),
 * these methods are called predictably and can be re-run safely.
 *
 * Unlike Drupal's hook_install() (which runs during database operations),
 * this runs AFTER schema sync is complete, so you can safely insert data.
 */
final class Loader
{
    /**
     * Called when the module is enabled
     *
     * This runs AFTER the database tables have been created.
     * Safe to insert seed data here.
     */
    public function onEnable(): void
    {
        // Register module-specific routes
        $this->registerRoutes();

        // Register event listeners
        $this->registerEventListeners();

        // Log activation
        error_log('[Ecommerce] Module enabled successfully');
    }

    /**
     * Called when the module is disabled
     *
     * Clean up any resources, but DO NOT delete data by default.
     * Data deletion should only happen with explicit user confirmation.
     */
    public function onDisable(): void
    {
        // Unregister any cached data
        // Leave database tables intact for safety

        error_log('[Ecommerce] Module disabled');
    }

    /**
     * Register routes for this module
     *
     * In a full implementation, this would use the RouterRegistrar
     * or add routes to a routes configuration file.
     */
    private function registerRoutes(): void
    {
        // Routes are typically auto-discovered from Controllers,
        // but you can register programmatic routes here

        // Example:
        // $router->addRoute('GET', '/shop', [ProductController::class, 'index']);
        // $router->addRoute('GET', '/shop/{slug}', [ProductController::class, 'show']);
        // $router->addRoute('POST', '/cart/add', [CartController::class, 'add']);
    }

    /**
     * Register event listeners for this module
     */
    private function registerEventListeners(): void
    {
        // Example event registrations:

        // Listen for order creation
        // $dispatcher->listen(OrderCreated::class, SendOrderConfirmation::class);

        // Listen for low stock
        // $dispatcher->listen(LowStockAlert::class, NotifyAdmin::class);

        // Listen for payment received
        // $dispatcher->listen(PaymentReceived::class, UpdateInventory::class);
    }

    /**
     * Seed initial data (called manually or via CLI)
     *
     * This is NOT called automatically - use a seeder command
     */
    public function seed(): void
    {
        // Seed sample products for development
        // This would typically be in a separate Seeder class
    }

    /**
     * Get module dependencies
     *
     * @return array<string> List of required modules
     */
    public static function getDependencies(): array
    {
        return [
            // 'Custom/Inventory', // Example dependency
        ];
    }

    /**
     * Get provided services for DI container
     *
     * @return array<string, callable>
     */
    public static function getServices(): array
    {
        return [
            // Services that should be registered in the DI container
            // ProductService::class => fn($c) => new ProductService($c->get(CmsRepository::class)),
            // CartService::class => fn($c) => new CartService($c->get(Session::class)),
        ];
    }
}
