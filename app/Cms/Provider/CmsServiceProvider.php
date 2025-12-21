<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Auth\AuthServiceProvider;
use Psr\Container\ContainerInterface;
use MonkeysLegion\Router\Router;
use PDO;

/**
 * CmsServiceProvider - Bootstraps CMS Core Services
 */
final class CmsServiceProvider
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Boot the CMS services
     */
    public function boot(): void
    {
        $this->bootAuth();
        $this->registerRoutes();
    }

    /**
     * Initialize Authentication Service
     */
    private function bootAuth(): void
    {
        if (!$this->container->has(PDO::class)) {
            // If PDO is not in container, we cannot init auth
            return;
        }

        $pdo = $this->container->get(PDO::class);

        // Initialize AuthServiceProvider with database connection
        // Config will be pulled from env vars by default
        AuthServiceProvider::init($pdo);
    }

    /**
     * Get DI definitions
     *
     * @return array<string, callable>
     */
    public static function getDefinitions(): array
    {
        return AuthServiceProvider::getDefinitions();
    }

    /**
     * Register CMS Routes
     */
    private function registerRoutes(): void
    {
        if (!$this->container->has(Router::class)) {
            return;
        }

        $router = $this->container->get(Router::class);
        $routes = AuthServiceProvider::getAllRoutes();

        foreach ($routes as $route) {
            $router->add(
                $route['method'],
                $route['path'],
                $route['handler']
            );
        }

        // Register installer redirect (only active when DB is connected/app is installed)
        $router->add('GET', '/install', [\App\Controllers\InstallRedirectController::class, 'index']);
    }
}
