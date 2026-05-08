<?php

declare(strict_types=1);

namespace App\Providers;

use App\Cms\Menu\MenuService;

use App\Cms\Service\CacheSettingsService;
use App\Cms\Theme\AssetRenderer;
use App\Cms\Theme\AssetResolver;
use App\Cms\Theme\PageAssets;
use App\Cms\Theme\ThemeManager;
use MonkeysLegion\Cache\CacheStoreInterface;
use MonkeysLegion\Cache\Stores\ArrayStore;
use MonkeysLegion\Cache\Stores\DatabaseStore;
use MonkeysLegion\Cache\Stores\FileStore;
use MonkeysLegion\Cache\Stores\NullStore;
use MonkeysLegion\Cache\Stores\RedisStore;
use MonkeysLegion\Contracts\AbstractServiceProvider;
use MonkeysLegion\Core\Attribute\Provider;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\DevTools\DevToolsServiceProvider;
use MonkeysLegion\DI\Contracts\ContainerInterface;
use MonkeysLegion\Mlc\Config as MlcConfig;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Application-level service provider.
 *
 * Register custom bindings, tagged services, or
 * interface-to-concrete mappings here.
 *
 * This provider is auto-discovered by ProviderScanner because it:
 *   1. Extends AbstractServiceProvider (implements ServiceProviderInterface)
 *   2. Has the #[Provider] attribute for discovery metadata
 */
#[Provider]
class AppProvider extends AbstractServiceProvider
{
    /**
     * Return DI definitions for this application.
     *
     * @return array<string, callable|object>
     */
    public function getDefinitions(): array
    {
        return [
            // ── Theme & Asset System ────────────────────────────────────
            ThemeManager::class => function (PsrContainerInterface $c): ThemeManager {
                $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : dirname(__DIR__, 2);
                return new ThemeManager(
                    $basePath,
                    $_ENV['CMS_THEME'] ?? 'front',
                    $_ENV['CMS_ADMIN_THEME'] ?? 'admin',
                );
            },

            AssetResolver::class => function (PsrContainerInterface $c): AssetResolver {
                $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : dirname(__DIR__, 2);
                return new AssetResolver(
                    $basePath,
                    ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
                    $_ENV['VITE_DEV_URL'] ?? 'http://localhost:5173',
                );
            },

            AssetRenderer::class => fn(): AssetRenderer => new AssetRenderer(),

            PageAssets::class => fn(): PageAssets => new PageAssets(),
        ];
    }

    /**
     * Imperative registration — wires cache system + DevTools bridges.
     *
     * Called by Application::boot() after the container is built, using
     * $container->call([$provider, 'register']) which auto-resolves the
     * ContainerInterface parameter.
     */
    public function register(ContainerInterface $container): void
    {
        $this->wireCacheFromAdminSettings($container);
        $this->wireDevTools($container);
    }

    /**
     * Rebind CacheInterface based on admin DB settings.
     *
     * Priority: Admin settings → .mlc config → hardcoded defaults.
     * If cache is disabled, binds NullStore. Otherwise creates the
     * appropriate store for the selected driver.
     */
    private function wireCacheFromAdminSettings(ContainerInterface $container): void
    {
        try {
            $pdo = $container->get(\PDO::class);
            $mlc = $container->get(MlcConfig::class);

            $settings = new CacheSettingsService($pdo, $mlc);

            // Register the service so controllers can use it
            $container->set(CacheSettingsService::class, $settings);

            // ── Menu Service (global singleton) ─────────────────────
            $menuService = new MenuService($pdo);
            $container->set(MenuService::class, $menuService);

            // ── Template Directives ─────────────────────────────────
            // Register @render and @menu directives into the Compiler registry.
            if ($container->has(\MonkeysLegion\Template\Compiler::class)) {
                $compiler = $container->get(\MonkeysLegion\Template\Compiler::class);
                $compilerRegistry = $compiler->getRegistry();
                $compilerRegistry->addDirective('render', function (string $expression) {
                    return "<?php echo (is_object({$expression}) && {$expression} instanceof \App\Cms\Render\RenderableInterface) ? {$expression}->render(\$this) : {$expression}; ?>";
                });
                $compilerRegistry->addDirective('menu', function (string $expression) {
                    return "<?php echo \App\Cms\Menu\MenuService::getInstance()?->renderMenu({$expression}) ?? ''; ?>";
                });
                $container->set(\MonkeysLegion\Template\Compiler::class, $compiler);
            }

            // ── View Cache ───────────────────────────────────────────────
            // If the view_cache is disabled in DB, force the Renderer to disable its cache
            if ($container->has(\MonkeysLegion\Template\Renderer::class)) {
                $renderer = $container->get(\MonkeysLegion\Template\Renderer::class);
                
                if (!$settings->isEnabled() || !$settings->isLayerEnabled('view_cache')) {
                    (new \ReflectionProperty($renderer, 'cacheEnabled'))->setValue($renderer, false);
                }

                $registry = $renderer->getRegistry();
                $registry->addDirective('render', function (string $expression) {
                    return "<?php echo (is_object({$expression}) && {$expression} instanceof \App\Cms\Render\RenderableInterface) ? {$expression}->render(\$this) : {$expression}; ?>";
                });
                $registry->addDirective('menu', function (string $expression) {
                    return "<?php echo \App\Cms\Menu\MenuService::getInstance()?->renderMenu({$expression}) ?? ''; ?>";
                });
                
                // Rebind as singleton so controllers get the same instance with directives
                $container->set(\MonkeysLegion\Template\Renderer::class, $renderer);
            }

            // ── Disabled → NullStore (no-op) ─────────────────────────
            if (!$settings->isEnabled()) {
                $container->set(CacheInterface::class, new NullStore());
                $container->set(CacheStoreInterface::class, new NullStore());
                return;
            }

            // ── Build the correct store based on admin-selected driver ─
            $driver = $settings->getDriver();
            $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : dirname(__DIR__, 2);

            $store = match ($driver) {
                'redis' => $this->createRedisStore($settings),
                'database' => new DatabaseStore(
                    pdo: $pdo,
                    table: 'cache_entries',
                    prefix: $mlc->getString('cache.prefix', '') ?? '',
                ),
                'array' => new ArrayStore(),
                default => new FileStore(
                    directory: $basePath . '/var/cache/data',
                    prefix: $mlc->getString('cache.prefix', '') ?? '',
                ),
            };

            $container->set(CacheInterface::class, $store);
            $container->set(CacheStoreInterface::class, $store);

        } catch (\Throwable $e) { file_put_contents("/tmp/cache_debug.log", "wireCacheErr: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "
" . $e->getTraceAsString() . "
", FILE_APPEND);
            // During install or if DB unavailable — keep framework defaults
        }
    }

    /**
     * Create a RedisStore from admin settings.
     */
    private function createRedisStore(CacheSettingsService $settings): RedisStore
    {
        $config = $settings->getRedisConfig();

        $redis = new \Redis();
        $redis->connect($config['host'], $config['port']);

        if ($config['password'] !== null && $config['password'] !== '') {
            $redis->auth($config['password']);
        }

        if ($config['database'] > 0) {
            $redis->select($config['database']);
        }

        return new RedisStore(redis: $redis);
    }

    /**
     * Wire DevTools bridges to framework services.
     *
     * Connects the four DevTools collectors to their data sources:
     * - Database queries → QueryCollector (via DatabaseEventBridge)
     * - Event dispatches → EventCollector (via EventInterceptorBridge)
     * - Cache operations → CacheCollector (via CacheBridge decorator)
     * - Uncaught exceptions → ExceptionCollector (via ExceptionBridge handler)
     */
    private function wireDevTools(ContainerInterface $container): void
    {
        try {
            if (!class_exists(DevToolsServiceProvider::class)) {
                return;
            }
            if (!$container->has(DevToolsServiceProvider::class)) {
                return;
            }

            $devtools = $container->get(DevToolsServiceProvider::class);

            if (!$devtools->booted) {
                $mlc = $container->get(MlcConfig::class);
                $config = $mlc->get('devtools', []);
                $devtools->boot(is_array($config) ? $config : []);
            }

            // ── Database → QueryCollector ────────────────────────────
            $dbBridge = $devtools->createDatabaseBridge();
            if ($dbBridge !== null) {
                $conn = $container->get(ConnectionInterface::class);
                $conn->eventDispatcher = $dbBridge;
            }

            // ── Events → EventCollector ──────────────────────────────
            $eventBridge = $devtools->createEventBridge();
            if ($eventBridge !== null && $container->has(EventDispatcherInterface::class)) {
                $dispatcher = $container->get(EventDispatcherInterface::class);
                if (method_exists($dispatcher, 'addInterceptor')) {
                    $dispatcher->addInterceptor($eventBridge);
                }
            }

            // ── Cache → CacheCollector ───────────────────────────────
            if ($container->has(CacheInterface::class)) {
                $realCache = $container->get(CacheInterface::class);
                
                $driverName = 'file';
                if ($container->has(\App\Cms\Service\CacheSettingsService::class)) {
                    $settings = $container->get(\App\Cms\Service\CacheSettingsService::class);
                    $driverName = $settings->isEnabled() ? $settings->getDriver() : 'disabled';
                }

                $wrappedCache = $devtools->createCacheBridge($realCache, $driverName);
                if ($wrappedCache !== null) {
                    $container->set(CacheInterface::class, $wrappedCache);
                }
            }

            // ── Exceptions → ExceptionCollector ──────────────────────
            $devtools->createExceptionBridge();

        } catch (\Throwable $e) { file_put_contents("/tmp/cache_debug.log", "wireCacheErr: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "
" . $e->getTraceAsString() . "
", FILE_APPEND);
            // DevTools or dependent packages not available — silently skip
        }
    }
}
