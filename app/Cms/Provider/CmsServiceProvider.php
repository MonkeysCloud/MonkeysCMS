<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Auth\AuthServiceProvider;
use Psr\Container\ContainerInterface;
use MonkeysLegion\Router\Router;
use Psr\Http\Message\ServerRequestInterface;
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
        return array_merge(
            AuthServiceProvider::getDefinitions(),
            [
                \App\Installer\InstallerService::class => function (ContainerInterface $c) {
                    return new \App\Installer\InstallerService(
                        $c->get(PDO::class),
                        $c->get(\App\Cms\Modules\ModuleManager::class),
                        $c->get(\App\Cms\Core\SchemaGenerator::class)
                    );
                },
            ]
        );
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
            $handler = $this->resolveHandler($route['handler']);
            $router->add(
                $route['method'],
                $route['path'],
                $handler
            );
        }

        // Register installer redirect (only active when DB is connected/app is installed)
        $installHandler = $this->resolveHandler([\App\Controllers\InstallRedirectController::class, 'index']);
        $router->add('GET', '/install', $installHandler); /* @phpstan-ignore-line */

        // Home Page
        $homeHandler = $this->resolveHandler([\App\Controllers\Cms\HomeController::class, 'index']);
        $router->add('GET', '/', $homeHandler);

        // Register Metadata/Attribute Routes
        $this->registerControllerRoutes($router, [
            \App\Controllers\Admin\DashboardController::class,
            // Add other controllers here or implement auto-discovery later
        ]);
    }

    /**
     * Register routes from controller attributes
     */
    private function registerControllerRoutes(Router $router, array $controllers): void
    {
        foreach ($controllers as $controllerClass) {
            if (!class_exists($controllerClass)) {
                continue;
            }

            $reflection = new \ReflectionClass($controllerClass);
            
            // distinct route prefixes?
            $classRouteAttr = $reflection->getAttributes(\MonkeysLegion\Router\Attributes\Route::class)[0] ?? null;
            $prefix = '';
            if ($classRouteAttr) {
                // Route attribute uses public properties, not getters
                $prefix = $classRouteAttr->newInstance()->path; 
            }

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(\MonkeysLegion\Router\Attributes\Route::class);
                
                foreach ($attributes as $attribute) {
                    $route = $attribute->newInstance();
                    $path = $prefix . $route->path; // public property
                    $handler = $this->resolveHandler([$controllerClass, $method->getName()]);
                    
                    // Route attribute 'methods' is an array of strings
                    foreach ($route->methods as $httpMethod) {
                         $router->add($httpMethod, $path, $handler);
                    }
                }
            }
        }
    }

    /**
     * Resolve handler to callable
     */
    private function resolveHandler(mixed $handler): callable
    {
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            [$controllerClass, $method] = $handler;
            return function (ServerRequestInterface $request) use ($controllerClass, $method) {
                $controller = $this->container->get($controllerClass);
                return $controller->$method($request);
            };
        }
        
        return $handler;
    }
}
