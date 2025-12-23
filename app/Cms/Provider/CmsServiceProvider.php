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
        $definitions = array_merge(
            AuthServiceProvider::getDefinitions(),
            [
                \MonkeysLegion\Database\Contracts\ConnectionInterface::class => function (ContainerInterface $c) {
                    return new \App\Cms\Database\PDOConnectionAdapter($c->get(PDO::class));
                },
                \MonkeysLegion\Cache\CacheManager::class => function (ContainerInterface $c) {
                    $config = $c->get('config'); // \MonkeysLegion\Mlc\Config
                    // get('cache') returns the array from cache.mlc
                    return new \MonkeysLegion\Cache\CacheManager($config->get('cache', []));
                },
                \App\Installer\InstallerService::class => function (ContainerInterface $c) {
                    return new \App\Installer\InstallerService(
                        $c->get(PDO::class),
                        $c->get(\App\Cms\Modules\ModuleManager::class),
                        $c->get(\App\Cms\Core\SchemaGenerator::class)
                    );
                },

                // Template Engine Services
                \App\Cms\Themes\ThemeManager::class => function (ContainerInterface $c) {
                    $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd();
                    return new \App\Cms\Themes\ThemeManager(
                        $basePath . '/themes/contrib',
                        $basePath . '/themes/custom',
                        'default', // active theme
                        'admin',   // admin theme
                        $basePath . '/var/cache/themes',
                        true
                    );
                },

                \MonkeysLegion\Template\Loader::class => function (ContainerInterface $c) {
                    $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd();
                    $themeManager = $c->get(\App\Cms\Themes\ThemeManager::class);
                    
                    // Paths: 1. Active Theme (Frontend), 2. Admin Theme
                    // This allows finding both 'home/index' (frontend) and 'admin/dashboard' (admin)
                    $viewPaths = $themeManager->getViewPaths();
                    $viewPaths[] = $themeManager->findThemePath('admin') . '/views';
                    
                    return new \App\Cms\Theme\CascadingLoader(
                        $viewPaths,
                        $basePath . '/var/cache/views'
                    );
                },
                \MonkeysLegion\Template\Parser::class => function (ContainerInterface $c) {
                    return new \MonkeysLegion\Template\Parser();
                },
                \MonkeysLegion\Template\Compiler::class => function (ContainerInterface $c) {
                    return new \MonkeysLegion\Template\Compiler($c->get(\MonkeysLegion\Template\Parser::class));
                },
                \MonkeysLegion\Template\Renderer::class => function (ContainerInterface $c) {
                    $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd();
                    return new \MonkeysLegion\Template\Renderer(
                        $c->get(\MonkeysLegion\Template\Parser::class),
                        $c->get(\MonkeysLegion\Template\Compiler::class),
                        $c->get(\MonkeysLegion\Template\Loader::class),
                        true, // cache enabled
                        $basePath . '/var/cache/views'
                    );
                },
                \MonkeysLegion\Template\MLView::class => function (ContainerInterface $c) {
                    $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd();
                    return new \MonkeysLegion\Template\MLView(
                        $c->get(\MonkeysLegion\Template\Loader::class),
                        $c->get(\MonkeysLegion\Template\Compiler::class),
                        $c->get(\MonkeysLegion\Template\Renderer::class),
                        $basePath . '/var/cache/views'
                    );
                },
            ]
        );

        return array_merge($definitions, self::discoverModuleServices());
    }

    /**
     * Discover services from module.mlc files
     * @return array<string, callable>
     */
    private static function discoverModuleServices(): array
    {
        $services = [];
        $baseDir = defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd();
        $modulesDir = $baseDir . '/app/Modules';
        
        // Find all .module.mlc files recursively (max depth 2 for efficiency)
        // Core/Core.module.mlc, Custom/Ecommerce/Ecommerce.module.mlc
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modulesDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $parser = new \MonkeysLegion\Mlc\Parser();

        foreach ($files as $file) {
            if ($file->getExtension() !== 'mlc' || !str_ends_with($file->getFilename(), '.module.mlc')) {
                continue;
            }

            try {
                $config = $parser->parseFile($file->getPathname());
                if (isset($config['services']) && is_array($config['services'])) {
                    foreach ($config['services'] as $alias => $class) {
                        // Register alias to point to the class
                        // usage: $container->get('permission') -> returns PermissionService instance
                        $services[$alias] = function (ContainerInterface $c) use ($class) {
                            return $c->get($class);
                        };
                    }
                }
            } catch (\Throwable $e) {
                // Ignore parsing errors during discovery to prevent crash
                error_log("Failed to parse services from " . $file->getFilename() . ": " . $e->getMessage());
            }
        }

        return $services;
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
            \App\Controllers\Admin\MenuController::class,
            \App\Controllers\Admin\MenuItemController::class,
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
                    $path = $prefix . $route->path;
                    if ($path !== '/' && str_ends_with($path, '/')) {
                        $path = rtrim($path, '/');
                    }
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
            return function (ServerRequestInterface $request, ...$args) use ($controllerClass, $method) {
                $controller = $this->container->get($controllerClass);
                $reflection = (new \ReflectionClass($controller))->getMethod($method);
                
                $pass = [];
                $routeArgs = array_values($args);
                
                foreach ($reflection->getParameters() as $param) {
                    $type = $param->getType();
                    
                    // Handle Request Injection
                    if ($type instanceof \ReflectionNamedType && 
                        (is_a($type->getName(), \Psr\Http\Message\ServerRequestInterface::class, true) || 
                         $type->getName() === 'Psr\Http\Message\ServerRequestInterface')) {
                        $pass[] = $request;
                        continue;
                    }
                    
                    // Handle Route Params
                    if (!empty($routeArgs)) {
                        $val = array_shift($routeArgs);
                        
                        if ($type instanceof \ReflectionNamedType) {
                            $typeName = $type->getName();
                            if ($typeName === 'int') {
                                $val = (int) $val;
                            } elseif ($typeName === 'float') {
                                $val = (float) $val;
                            } elseif ($typeName === 'bool') {
                                $val = (bool) $val;
                            }
                        }
                        $pass[] = $val;
                    } elseif ($param->isDefaultValueAvailable()) {
                        $pass[] = $param->getDefaultValue();
                    }
                }
                
                // Pass remaining args if any (varargs)
                if (!empty($routeArgs)) {
                    array_push($pass, ...$routeArgs);
                }

                return $controller->$method(...$pass);
            };
        }
        
        return $handler;
    }
}
