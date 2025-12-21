<?php

declare(strict_types=1);

/**
 * MonkeysCMS - Application Entry Point
 * 
 * This file serves as the front controller for all HTTP requests.
 * All requests are routed through this file by the web server.
 */

// Define the base path constant
define('ML_BASE_PATH', dirname(__DIR__));

// Load Composer autoloader
require_once ML_BASE_PATH . '/vendor/autoload.php';

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

try {
    // 1. Load Environment Variables
    $dotenv = Dotenv::createImmutable(ML_BASE_PATH);
    $dotenv->safeLoad();

    // 2. Load Configuration
    // We load 'app' and 'database' definitions from config/*.mlc
    $loader = new Loader(new Parser(), ML_BASE_PATH . '/config');
    $rawConfig = $loader->load(['app', 'database'])->all();

    // Helper to interpolate environment variables: ${VAR:default}
    $resolveEnv = function ($value) use (&$resolveEnv) {
        if (is_array($value)) {
            return array_map($resolveEnv, $value);
        }
        if (is_string($value)) {
            return preg_replace_callback('/\$\{([A-Z0-9_]+)(?::([^}]*))?\}/', function ($matches) {
                $envKey = $matches[1];
                $default = $matches[2] ?? '';
                $val = getenv($envKey);
                // getenv returns false if not found
                return ($val !== false) ? $val : $default;
            }, $value);
        }
        return $value;
    };
    
    $configData = $resolveEnv($rawConfig);
    $config = new \MonkeysLegion\Mlc\Config($configData);

    // 3. Build DI Container
    $builder = new ContainerBuilder();

    // Register Core Services
    $builder->addDefinitions([
        // Configuration
        'config' => $config,

        // Router
        Router::class => fn() => new Router(new \MonkeysLegion\Router\RouteCollection()),

        // PSR-17 Response Factory
        ResponseFactoryInterface::class => fn() => new ResponseFactory(),

        // Database Connection (PDO)
        PDO::class => function (ContainerInterface $c) {
            $config = $c->get('config');
            
            // 1. Get default connection name (mysql, pgsql, sqlite, etc.)
            $default = $config->get('database.default');
            
            // 2. Get config for that specific connection
            $dbConfig = $config->get("database.connections.{$default}");
            
            // 3. Build DSN using Builder Factory (similar to installer)
            try {
                // Determine driver alias (e.g. 'pgsql' -> 'postgresql' enum if needed, or string)
                // The DsnBuilderFactory::createByString handles mapping 'pgsql' -> POSTGRESQL
                $builder = \MonkeysLegion\Database\DSN\DsnBuilderFactory::createByString($dbConfig['driver']);
                
                if ($builder instanceof \MonkeysLegion\Database\DSN\MySQLDsnBuilder) {
                    $builder->host($dbConfig['host'])
                            ->port((int)$dbConfig['port'])
                            ->database($dbConfig['database'])
                            ->charset($dbConfig['charset'] ?? 'utf8mb4');
                } elseif ($builder instanceof \MonkeysLegion\Database\DSN\PostgreSQLDsnBuilder) {
                    $builder->host($dbConfig['host'])
                            ->port((int)$dbConfig['port'])
                            ->database($dbConfig['database']);
                } elseif ($builder instanceof \MonkeysLegion\Database\DSN\SQLiteDsnBuilder) {
                    $builder->file($dbConfig['database']); // In SQLite config 'database' holds path
                }
                
                $dsn = $builder->build();
                
            } catch (\InvalidArgumentException $e) {
                // Fallback for sqlsrv or unsupported drivers
                if (($dbConfig['driver'] ?? '') === 'sqlsrv') {
                     $dsn = sprintf(
                        'sqlsrv:Server=%s,%s;Database=%s',
                        $dbConfig['host'],
                        $dbConfig['port'] ?? 1433,
                        $dbConfig['database']
                    );
                } else {
                    // Fallback to MySQL legacy generation if builder fails
                     $dsn = sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                        $dbConfig['host'],
                        $dbConfig['port'],
                        $dbConfig['database'],
                        $dbConfig['charset'] ?? 'utf8mb4'
                    );
                }
            }
            
            return new PDO($dsn, $dbConfig['username'] ?? null, $dbConfig['password'] ?? null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        },

        // Core Request Handler (The "Kernel")
        CoreRequestHandler::class => function (ContainerInterface $c) {
            return new CoreRequestHandler(
                $c->get(Router::class),
                $c->get(ResponseFactoryInterface::class)
            );
        },
    ]);

    // Register CMS Services (Auth, Controllers, etc.)
    $builder->addDefinitions(CmsServiceProvider::getDefinitions());

    $container = $builder->build();

    // 5. Handle Request
    // Check if we can connect to the DB by effectively booting CMS
    try {
        // CmsServiceProvider initializes Auth and registers routes which requires DB
        $cmsProvider = new CmsServiceProvider($container);
        $cmsProvider->boot();
        
        $request = ServerRequestFactory::fromGlobals();
        $handler = $container->get(CoreRequestHandler::class);
        $response = $handler->handle($request);
    } catch (\PDOException $e) {
        // Database connection failed -> Show Installer
        // Only if it's a "Connection refused" or "Unknown database" or similar config error
        // But for now, any PDO error during boot triggers installer
        
        $request = ServerRequestFactory::fromGlobals();

        // Boot Template Engine manually for Installer
        $tplParser = new \MonkeysLegion\Template\Parser();
        $tplCompiler = new \MonkeysLegion\Template\Compiler($tplParser);
        $tplLoader = new \MonkeysLegion\Template\Loader(
            ML_BASE_PATH . '/app/Views',
            ML_BASE_PATH . '/var/cache/views'
        );
        $renderer = new \MonkeysLegion\Template\Renderer(
            $tplParser,
            $tplCompiler,
            $tplLoader,
            true, // cache enabled
            ML_BASE_PATH . '/var/cache/views'
        );

        $installer = new \App\Installer\InstallerRequestHandler(ML_BASE_PATH, $renderer);
        $response = $installer->handle($request);
    }

    // 6. Emit Response
    (new SapiEmitter())->emit($response);

} catch (Throwable $e) {
    // Fallback error handler
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Internal Server Error:\n";
    echo $e->getMessage();
    echo "\n\nStack Trace:\n";
    echo $e->getTraceAsString();
}
