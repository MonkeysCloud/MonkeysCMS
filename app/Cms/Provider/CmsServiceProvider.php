<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Auth\AuthServiceProvider;
use Psr\Container\ContainerInterface;
use MonkeysLegion\Router\Router;
use MonkeysLegion\Files\FilesManager;
use MonkeysLegion\Files\Storage\LocalStorage;
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

        // Get config to determine environment
        $config = [];
        if ($this->container->has('config')) {
            $appConfig = $this->container->get('config');
            $env = $appConfig->get('app.env', 'production');
            
            // Disable secure cookies in local environment
            $config['session_secure'] = ($env !== 'local' && $env !== 'development');
        }

        // Initialize AuthServiceProvider with database connection and config
        AuthServiceProvider::init($pdo, $config);
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
                \MonkeysLegion\Mlc\Config::class => function (ContainerInterface $c) {
                    return $c->get('config');
                },
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
                    
                    // View paths in priority order:
                    // 1. Admin theme views (with parent fallback like admin-default)
                    // 2. Frontend theme views (with parent fallback)
                    $viewPaths = array_merge(
                        $themeManager->getAdminViewPaths(),  // Admin theme + parent fallback
                        $themeManager->getViewPaths()         // Frontend theme + parent fallback
                    );
                    
                    return new \App\Cms\Theme\CascadingLoader(
                        array_unique($viewPaths),
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
                \App\Cms\Auth\Middleware\AuthenticationMiddlewareAdapter::class => function (ContainerInterface $c) {
                    return new \App\Cms\Auth\Middleware\AuthenticationMiddlewareAdapter(
                        $c->get(\MonkeysLegion\Auth\Middleware\AuthenticationMiddleware::class),
                        $c->get(\App\Cms\Security\PermissionService::class)
                    );
                },
                // File Storage
                FilesManager::class => function (ContainerInterface $c) {
                    $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : dirname(__DIR__, 3);
                    $config = $c->get('config');
                    
                    $filesRoot = $config->get('files.disks.local.root') ?? 'storage/files';
                    $uploadsRoot = $config->get('files.disks.public.root') ?? 'storage/uploads';

                    // Ensure absolute paths
                    $localPath = str_starts_with($filesRoot, '/') ? $filesRoot : $basePath . '/' . $filesRoot;
                    $publicPath = str_starts_with($uploadsRoot, '/') ? $uploadsRoot : $basePath . '/' . $uploadsRoot;

                    // Create directories if not exist
                    if (!is_dir($localPath)) {
                         @mkdir($localPath, 0755, true);
                    }
                    if (!is_dir($publicPath)) {
                         @mkdir($publicPath, 0755, true);
                    }

                    // Build full config with proper paths to override defaults
                    $filesConfig = [
                        'default' => $config->get('files.default') ?? 'local',
                        'disks' => [
                            'local' => [
                                'driver' => 'local',
                                'path' => $localPath,
                                'url' => $config->get('files.disks.local.url') ?? '/files',
                                'visibility' => $config->get('files.disks.local.visibility') ?? 'private',
                                'permissions' => [
                                    'dir' => 0755,
                                    'file' => 0644,
                                ],
                            ],
                            'public' => [
                                'driver' => 'local',
                                'path' => $publicPath,
                                'url' => $config->get('files.disks.public.url') ?? '/uploads',
                                'visibility' => $config->get('files.disks.public.visibility') ?? 'public',
                                'permissions' => [
                                    'dir' => 0755,
                                    'file' => 0644,
                                ],
                            ],
                        ],
                    ];
                    
                    return new FilesManager($filesConfig);
                },
                \MonkeysLegion\Files\Upload\ChunkedUploadManager::class => function (ContainerInterface $c) {
                    $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : dirname(__DIR__, 3);
                    $config = $c->get('config');

                    /** @var \MonkeysLegion\Files\FilesManager $filesManager */
                    $filesManager = $c->get(FilesManager::class);

                    /** @var \MonkeysLegion\Cache\CacheManager $cacheManager */
                    $cacheManager = $c->get(\MonkeysLegion\Cache\CacheManager::class);

                    // Use public disk for final storage
                    $publicDisk = $config->get('files.public_disk') ?? 'public';
                    $storage = $filesManager->disk($publicDisk);

                    // Temp directory configuration
                    $tempDirConfig = $config->get('files.upload.temp_dir') ?? 'storage/tmp/uploads';
                    $tempDir = str_starts_with($tempDirConfig, '/') ? $tempDirConfig : $basePath . '/' . $tempDirConfig;
                    
                    if (!is_dir($tempDir)) {
                         @mkdir($tempDir, 0755, true);
                    }

                    return new \MonkeysLegion\Files\Upload\ChunkedUploadManager(
                        $storage,
                        $tempDir,
                        $cacheManager->store(),
                        (int) ($config->get('files.upload.chunk_size') ?? 5 * 1024 * 1024),
                        (int) ($config->get('files.upload.chunk_expiry') ?? 86400)
                    );
                },
                \MonkeysLegion\Files\Image\ImageProcessor::class => function (ContainerInterface $c) {
                    $config = $c->get('config');
                    return new \MonkeysLegion\Files\Image\ImageProcessor(
                        $config->get('files.image.driver') ?? 'gd',
                        (int) ($config->get('files.image.quality') ?? 85)
                    );
                },

                // Asset Manager
                \App\Cms\Assets\AssetManager::class => function (ContainerInterface $c) {
                    $config = $c->get('config');
                    $assetsConfig = $config->get('assets', []);
                    $collection = new \App\Cms\Fields\Rendering\AssetCollection();
                    $manager = new \App\Cms\Assets\AssetManager($assetsConfig, $collection);
                    return $manager;
                },
                
                // Block Manager
                \App\Cms\Blocks\BlockManager::class => function (ContainerInterface $c) {
                    $manager = new \App\Cms\Blocks\BlockManager(
                        $c->get(\MonkeysLegion\Database\Contracts\ConnectionInterface::class),
                        $c->get(\MonkeysLegion\Cache\CacheManager::class)
                    );
                    
                    // Register Code Types
                    $manager->registerType(new \App\Cms\Blocks\Types\HtmlBlock());
                    
                    return $manager;
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
        
        // Pre-register middleware instances to support DI
        if ($this->container->has(\App\Cms\Auth\Middleware\AdminAccessMiddleware::class)) {
            $router->registerMiddleware(
                \App\Cms\Auth\Middleware\AdminAccessMiddleware::class,
                $this->container->get(\App\Cms\Auth\Middleware\AdminAccessMiddleware::class)
            );
        }
        
        if ($this->container->has(\App\Cms\Auth\Middleware\AuthenticationMiddlewareAdapter::class)) {
            $router->registerMiddleware(
                \App\Cms\Auth\Middleware\AuthenticationMiddlewareAdapter::class,
                $this->container->get(\App\Cms\Auth\Middleware\AuthenticationMiddlewareAdapter::class)
            );
        }

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
        $controllers = $this->discoverControllers();
        $this->registerControllerRoutes($router, $controllers);
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
            
            // 1. Controller Attributes
            $prefix = '';
            $controllerMiddleware = [];

            // RoutePrefix
            $prefixAttr = $reflection->getAttributes(\MonkeysLegion\Router\Attributes\RoutePrefix::class)[0] ?? null;
            if ($prefixAttr) {
                $prefix = $prefixAttr->newInstance()->prefix;
            } else {
                // Fallback: check "Route" attribute used as prefix container (legacy behavior check)
                $routeAttr = $reflection->getAttributes(\MonkeysLegion\Router\Attributes\Route::class)[0] ?? null;
                if ($routeAttr) {
                     $prefix = $routeAttr->newInstance()->path; 
                }
            }

            // Middleware (Class Level - including parents)
            $class = $reflection;
            while ($class) {
                foreach ($class->getAttributes(\MonkeysLegion\Router\Attributes\Middleware::class) as $mwAttr) {
                    // Prepend parent middleware so it runs first? Or merge? 
                    // Usually parent middleware (like 'admin') should effectively be present. Order might matter.
                    // array_merge appends. If we want parents first, we should prepend.
                    // But typically attribute order on same class matters. Across inheritance, parent is usually "base" scope.
                    $controllerMiddleware = array_merge($mwAttr->newInstance()->middleware, $controllerMiddleware);
                }
                $class = $class->getParentClass();
            }

            // 2. Methods
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // Route Attributes
                $routeAttributes = $method->getAttributes(\MonkeysLegion\Router\Attributes\Route::class);
                
                // Method Middleware
                $methodMiddleware = $controllerMiddleware;
                foreach ($method->getAttributes(\MonkeysLegion\Router\Attributes\Middleware::class) as $mwAttr) {
                    $methodMiddleware = array_merge($methodMiddleware, $mwAttr->newInstance()->middleware);
                }

                foreach ($routeAttributes as $attribute) {
                    $route = $attribute->newInstance();
                    $path = $prefix . $route->path;
                    
                    // Normalize path
                    if ($path !== '/' && str_ends_with($path, '/')) {
                        $path = rtrim($path, '/');
                    }
                    
                    $handler = $this->resolveHandler([$controllerClass, $method->getName()]);
                    
                    // Merge middleware from attributes with route definition
                    $finalMiddleware = array_merge($methodMiddleware, $route->middleware ?? []);

                    foreach ($route->methods as $httpMethod) {
                         $router->add(
                             $httpMethod, 
                             $path, 
                             $handler,
                             $route->name,
                             $finalMiddleware // Pass middleware to router
                         );
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
                
                // Inject AssetManager (Property Injection for BaseAdminController)
                if (method_exists($controller, 'setAssetManager') && $this->container->has(\App\Cms\Assets\AssetManager::class)) {
                    $controller->setAssetManager($this->container->get(\App\Cms\Assets\AssetManager::class));
                }

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
    
    /**
     * Automatically discover controllers in app/Controllers and app/Modules
     * @return array<string>
     */
    private function discoverControllers(): array
    {
        $baseDir = defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd();
        $directories = [
            $baseDir . '/app/Controllers',
            $baseDir . '/app/Modules'
        ];
        
        $controllers = [];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                
                // Only process files ending in Controller.php
                if (!str_ends_with($file->getFilename(), 'Controller.php')) {
                    continue;
                }

                $className = $this->getClassFromPath($file->getPathname(), $baseDir);
                
                if ($className && class_exists($className)) {
                    $reflection = new \ReflectionClass($className);
                    // Must be instantiable and not abstract
                    if (!$reflection->isAbstract()) {
                        $controllers[] = $className;
                    }
                }
            }
        }
        
        return array_unique($controllers);
    }

    /**
     * Convert file path to class name based on PSR-4 App\ -> app/ mapping
     */
    private function getClassFromPath(string $path, string $basePath): ?string
    {
        // Convert /path/to/app/Controllers/Foo.php -> App\Controllers\Foo
        $rel = str_replace($basePath . '/app/', '', $path);
        $rel = str_replace('.php', '', $rel);
        $class = 'App\\' . str_replace('/', '\\', $rel);
        return $class;
    }
}

