<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;
use MonkeysLegion\DI\ContainerBuilder;
use MonkeysLegion\Mlc\Loader;
use MonkeysLegion\Mlc\Parser;
use MonkeysLegion\Http\CoreRequestHandler;
use MonkeysLegion\Http\Emitter\SapiEmitter;
use MonkeysLegion\Router\Router;
use App\Cms\Provider\CmsServiceProvider;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\ResponseFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Container\ContainerInterface;
use MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface;
use PDO;
use Throwable;

class Kernel
{
    private string $basePath;
    private ?ContainerInterface $container = null;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        // Ensure output buffering is on so we can discard partial output on error
        ob_start();
        // Disable default PHP error output so we can style it ourselves
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    }

    public function run(): void
    {
        try {
            $this->bootstrap();
            $response = $this->handleRequest();
            $this->emit($response);
        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    public function bootstrap(): void
    {
        // 1. Load Environment Variables
        $dotenv = Dotenv::createImmutable($this->basePath);
        $dotenv->safeLoad();

        // 2. Load Configuration
        $loader = new Loader(new Parser(), $this->basePath . '/config');
        $rawConfig = $loader->load(['app', 'database', 'cache'])->all();
        $config = new \MonkeysLegion\Mlc\Config($this->resolveEnvVars($rawConfig));

        // 3. Build DI Container
        $builder = new ContainerBuilder();
        $this->registerServices($builder, $config);
        $this->container = $builder->build();
        
        // Boot CMS Services
        $cmsProvider = new CmsServiceProvider($this->container);
        $cmsProvider->boot();
    }

    private function handleRequest()
    {
        $request = ServerRequestFactory::fromGlobals();
        $path = $request->getUri()->getPath();

        // Always route installer requests to the installer handler
        if (str_starts_with($path, '/install')) {
            return $this->handleInstaller($request, null);
        }

        try {
            // CMS Services are already booted in bootstrap() if it was called via run()
            // However, run() calls bootstrap() which boots.
            // If we want handleRequest to be safe to call on its own (e.g. tests), check if booted?
            // For now, removing it here since run() controls the flow.
            
            $handler = $this->container->get(CoreRequestHandler::class);
            return $handler->handle($request);
            
        } catch (\PDOException $e) {
            // Database connection failed -> Show Installer
            return $this->handleInstaller($request, $e);
        }
    }

    private function handleInstaller(ServerRequestInterface $request, ?\Exception $e = null)
    {
        // Boot Template Engine manually for Installer
        $tplParser = new \MonkeysLegion\Template\Parser();
        $tplCompiler = new \MonkeysLegion\Template\Compiler($tplParser);
        $tplLoader = new \MonkeysLegion\Template\Loader(
            $this->basePath . '/app/Views',
            $this->basePath . '/var/cache/views'
        );
        $renderer = new \MonkeysLegion\Template\Renderer(
            $tplParser,
            $tplCompiler,
            $tplLoader,
            true, // cache enabled
            $this->basePath . '/var/cache/views'
        );

        $installer = new \App\Installer\InstallerRequestHandler($this->basePath, $renderer);
        return $installer->handle($request);
    }

    private function emit($response): void
    {
        (new SapiEmitter())->emit($response);
    }

    private function handleError(Throwable $e): void
    {
        // Log the error
        if ($this->container && $this->container->has(MonkeysLoggerInterface::class)) {
            try {
                $logger = $this->container->get(MonkeysLoggerInterface::class);
                $logger->error($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            } catch (\Throwable $loggingError) {
                // Silently fail if logging fails to capture the original error
            }
        } else {
             // Fallback logger if container not ready
             error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        }

        // Discard any existing output
        if (ob_get_level()) ob_clean();
        
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        
        $isDebug = ($_ENV['APP_DEBUG'] ?? false) === 'true' || ($_ENV['APP_ENV'] ?? 'production') !== 'production';

        // Include the error view logic
        // We can keep it inline here or separate, but keeping inline matches the "styled" requirement
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Application Error - MonkeysCMS</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <style>
                :root { --primary: #ea8a0a; --secondary: #15225a; --bg-body: #f3f4f6; }
                body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg-body); color: #1f2937; }
            </style>
        </head>
        <body class="min-h-screen flex items-center justify-center p-6">
            <div class="w-full max-w-4xl bg-white rounded-xl shadow-2xl overflow-hidden border border-gray-200">
                <div class="bg-red-50 border-b border-red-100 p-6 flex items-center gap-4">
                    <div class="p-3 bg-red-100 text-red-600 rounded-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Application Error</h1>
                        <p class="text-red-700 font-medium">Something went wrong while processing your request.</p>
                    </div>
                </div>
                
                <div class="p-8">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-2">Error Message</h2>
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 font-mono text-sm text-red-600 break-words">
                            <?= htmlspecialchars($e->getMessage()) ?>
                        </div>
                    </div>

                    <?php if ($isDebug): ?>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 mb-2">Stack Trace</h2>
                            <div class="p-4 bg-gray-900 rounded-lg overflow-x-auto text-gray-300 font-mono text-xs leading-relaxed">
                                <pre><?= htmlspecialchars($e->getTraceAsString()) ?></pre>
                            </div>
                        </div>
                        <div class="mt-6 pt-6 border-t border-gray-100 flex gap-4 text-sm text-gray-500">
                            <div><span class="font-bold text-gray-700">File:</span> <?= $e->getFile() ?></div>
                            <div><span class="font-bold text-gray-700">Line:</span> <?= $e->getLine() ?></div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <p class="text-gray-500">Please contact the system administrator if this problem persists.</p>
                            <a href="/" class="mt-4 inline-block px-6 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition">Return Home</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    private function resolveEnvVars($configData)
    {
        $resolveEnv = function ($value) use (&$resolveEnv) {
            if (is_array($value)) {
                return array_map($resolveEnv, $value);
            }
            if (is_string($value)) {
                return preg_replace_callback('/\$\{([A-Z0-9_]+)(?::([^}]*))?\}/', function ($matches) {
                    $envKey = $matches[1];
                    $default = $matches[2] ?? '';
                    $val = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey);
                    return ($val !== false) ? $val : $default;
                }, $value);
            }
            return $value;
        };
        return $resolveEnv($configData);
    }
    
    // Services Definition (Moved from index.php)
    private function registerServices(ContainerBuilder $builder, $config): void
    {
         $builder->addDefinitions([
            'config' => $config,
            Router::class => fn() => new Router(new \MonkeysLegion\Router\RouteCollection()),
            ResponseFactoryInterface::class => fn() => new ResponseFactory(),
            
            PDO::class => function (ContainerInterface $c) {
                $config = $c->get('config');
                $default = $config->get('database.default');
                $dbConfig = $config->get("database.connections.{$default}");
                
                try {
                    $builder = \MonkeysLegion\Database\DSN\DsnBuilderFactory::createByString($dbConfig['driver']);
                    if ($builder instanceof \MonkeysLegion\Database\DSN\MySQLDsnBuilder) {
                        $builder->host($dbConfig['host'])
                                ->port((int)$dbConfig['port'])
                                ->database($dbConfig['database'])
                                ->charset($dbConfig['charset'] ?? 'utf8mb4');
                    } elseif ($builder instanceof \MonkeysLegion\Database\DSN\PostgreSQLDsnBuilder) {
                        $builder->host($dbConfig['host'])->port((int)$dbConfig['port'])->database($dbConfig['database']);
                    } elseif ($builder instanceof \MonkeysLegion\Database\DSN\SQLiteDsnBuilder) {
                        $builder->file($dbConfig['database']);
                    }
                    $dsn = $builder->build();
                } catch (\InvalidArgumentException $e) {
                    if (($dbConfig['driver'] ?? '') === 'sqlsrv') {
                         $dsn = sprintf('sqlsrv:Server=%s,%s;Database=%s', $dbConfig['host'], $dbConfig['port'] ?? 1433, $dbConfig['database']);
                    } else {
                         $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset'] ?? 'utf8mb4');
                    }
                }
                return new PDO($dsn, $dbConfig['username'] ?? null, $dbConfig['password'] ?? null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            },

            CoreRequestHandler::class => function (ContainerInterface $c) {
                $router = $c->get(Router::class);
                $routerHandler = new class($router) implements \Psr\Http\Server\RequestHandlerInterface {
                    private $router;
                    public function __construct($router) { $this->router = $router; }
                    public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface { return $this->router->dispatch($request); }
                };
                return new CoreRequestHandler($routerHandler, $c->get(ResponseFactoryInterface::class));
            },

            // Logger Service
            MonkeysLoggerInterface::class => function (ContainerInterface $c) {
                $config = [
                    'channels' => [
                        'daily' => [
                            'driver' => 'file',
                            'path' => $this->basePath . '/var/logs/app.log',
                            'daily' => true,
                        ]
                    ]
                ];
                $factory = new \MonkeysLegion\Logger\Factory\LoggerFactory($config);
                return $factory->make('daily');
            },
        ]);
        
        $builder->addDefinitions(CmsServiceProvider::getDefinitions());
    }
}
