<?php

declare(strict_types=1);

/**
 * MonkeysCMS Application Configuration Bootstrap
 * 
 * This file bootstraps the MLC configuration system and sets up
 * the dependency injection container.
 */

use MonkeysLegion\Mlc\Loader;
use MonkeysLegion\Mlc\Parser;
use MonkeysLegion\Mlc\Config;
use MonkeysLegion\Http\SimpleFileCache;
use MonkeysLegion\Mlc\Validator\SchemaValidator;
use MonkeysLegion\Database\Connection;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Database\ConnectionFactory;
use MonkeysLegion\Template\Renderer;
use MonkeysLegion\Template\Compiler;
use MonkeysLegion\Template\Parser as TemplateParser;

// MonkeysLegion Cache
use MonkeysLegion\Cache\CacheManager;
use MonkeysLegion\Cache\Cache;
use MonkeysLegion\Cache\Contracts\CacheInterface;

// CMS Core
use App\Cms\Core\SchemaGenerator;
use App\Cms\Modules\ModuleManager;
use App\Cms\Repository\CmsRepository;
use App\Cms\Themes\ThemeManager;
use App\Cms\Security\PermissionService;
use App\Cms\Cache\CmsCacheService;

// Core Module Services
use App\Modules\Core\Services\TaxonomyService;
use App\Modules\Core\Services\MenuService;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Core\Services\MediaService;

// MonkeysLegion Files
use MonkeysLegion\Files\Storage\StorageManager;
use MonkeysLegion\Files\Storage\LocalStorage;
use MonkeysLegion\Files\Storage\S3Storage;
use MonkeysLegion\Files\Upload\ChunkedUploadManager;
use MonkeysLegion\Files\Upload\UploadValidator;
use MonkeysLegion\Files\Image\ImageProcessor;
use MonkeysLegion\Files\Scanner\ClamAvScanner;
use MonkeysLegion\Files\Scanner\VirusScanner;

// Admin Controllers
use App\Controllers\Admin\ModuleController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ContentController;
use App\Controllers\Admin\ThemeController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\TaxonomyController;
use App\Controllers\Admin\MenuController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\MediaController;
use App\Controllers\Admin\CacheController;
use App\Controllers\Admin\BlockTypeController;
use App\Controllers\Admin\ContentTypeController;
use App\Controllers\Admin\ContentTypePageController;

// Block System
use App\Cms\Blocks\BlockManager;
use App\Cms\Blocks\BlockRenderer;
use App\Cms\Blocks\Types\HtmlBlock;
use App\Cms\Blocks\Types\TextBlock;
use App\Cms\Blocks\Types\ImageBlock;
use App\Cms\Blocks\Types\GalleryBlock;
use App\Cms\Blocks\Types\MenuBlock;
use App\Cms\Blocks\Types\ViewsBlock;
use App\Cms\Blocks\Types\VideoBlock;

// Content Type System
use App\Cms\ContentTypes\ContentTypeManager;

// Taxonomy System
use App\Cms\Taxonomy\TaxonomyManager;

// Field System
use App\Cms\Fields\Widgets\FieldWidgetManager;
use App\Cms\Forms\FormBuilder;
use App\Controllers\Admin\FieldController;

// Middleware
use App\Middleware\AuthMiddleware;
use App\Middleware\RequirePermissionMiddleware;

// Define base path
$basePath = dirname(__DIR__);

// Initialize MLC configuration loader with caching
$configCache = new SimpleFileCache(
    $basePath . '/var/cache/config'
);

$configLoader = new Loader(
    new Parser(),
    $basePath . '/config',
    envDir: $basePath,
    cache: $configCache
);

// Load all configuration files
$config = $configLoader->load(['app', 'database', 'cache', 'files']);

// Validate configuration in development
$debug = filter_var($config->get('app.debug'), FILTER_VALIDATE_BOOL);
if ($debug) {
    $schema = require __DIR__ . '/schema.php';
    $validator = new SchemaValidator($schema);
    $errors = $validator->validate($config->all());
    if (!empty($errors)) {
        throw new RuntimeException(
            "Configuration validation failed:\n" . implode("\n", $errors)
        );
    }
}

// Freeze config in production
if ($config->getString('app.env') === 'production') {
    $config->freeze();
}

return [
    // ─────────────────────────────────────────────────────────────
    // Core Configuration
    // ─────────────────────────────────────────────────────────────
    
    Config::class => $config,
    'config' => $config,
    
    // ─────────────────────────────────────────────────────────────
    // Database Connection
    // ─────────────────────────────────────────────────────────────
    
    Connection::class => function () use ($config): Connection {
        $default = $config->getString('database.default', 'mysql');
        $connConfig = $config->getArray("database.connections.{$default}");
        
        return ConnectionFactory::create($connConfig);
    },
    
    // ─────────────────────────────────────────────────────────────
    // Cache (MonkeysLegion-Cache with PSR-16 compliance)
    // ─────────────────────────────────────────────────────────────
    
    CacheManager::class => function () use ($config, $basePath): CacheManager {
        $cacheConfig = [
            'default' => $config->getString('cache.default', 'file'),
            'prefix' => $config->getString('cache.prefix', 'monkeyscms'),
            'stores' => [
                'file' => [
                    'driver' => 'file',
                    'path' => $basePath . '/' . $config->getString('cache.stores.file.path', 'storage/cache'),
                    'prefix' => $config->getString('cache.stores.file.prefix', 'ml_cache'),
                ],
                'redis' => [
                    'driver' => 'redis',
                    'host' => $config->getString('cache.stores.redis.host', '127.0.0.1'),
                    'port' => $config->getInt('cache.stores.redis.port', 6379),
                    'password' => $config->getString('cache.stores.redis.password') ?: null,
                    'database' => $config->getInt('cache.stores.redis.database', 1),
                    'prefix' => $config->getString('cache.stores.redis.prefix', 'ml_cache'),
                    'timeout' => $config->getFloat('cache.stores.redis.timeout', 2.5),
                ],
                'memcached' => [
                    'driver' => 'memcached',
                    'persistent_id' => $config->getString('cache.stores.memcached.persistent_id', 'monkeyscms'),
                    'prefix' => $config->getString('cache.stores.memcached.prefix', 'ml_cache'),
                    'servers' => $config->getArray('cache.stores.memcached.servers', [
                        ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100],
                    ]),
                ],
                'array' => [
                    'driver' => 'array',
                    'prefix' => $config->getString('cache.stores.array.prefix', 'ml_cache'),
                ],
            ],
        ];
        
        $manager = new CacheManager($cacheConfig);
        
        // Set the Cache facade instance for static access
        Cache::setInstance($manager);
        
        return $manager;
    },
    
    // Alias for PSR-16 interface
    CacheInterface::class => function (CacheManager $manager): CacheInterface {
        return $manager->store();
    },
    
    // CMS Cache Service
    CmsCacheService::class => function (CacheManager $manager): CmsCacheService {
        return new CmsCacheService($manager);
    },
    
    // ─────────────────────────────────────────────────────────────
    // Template / View Engine
    // ─────────────────────────────────────────────────────────────
    
    Renderer::class => function () use ($config, $basePath): Renderer {
        $viewPaths = $config->getArray('view.paths', ['app/Views']);
        $componentPaths = $config->getArray('view.components.paths', ['app/Views/components']);
        $cachePath = $basePath . '/' . $config->getString('view.cache.path', 'var/cache/views');
        $cacheEnabled = $config->getBool('view.cache.enabled', true);
        
        $resolvedViewPaths = array_map(fn($p) => $basePath . '/' . $p, $viewPaths);
        $resolvedComponentPaths = array_map(fn($p) => $basePath . '/' . $p, $componentPaths);
        
        $compiler = new Compiler();
        $parser = new TemplateParser();
        
        return new Renderer(
            viewPaths: $resolvedViewPaths,
            componentPaths: $resolvedComponentPaths,
            cachePath: $cachePath,
            compiler: $compiler,
            parser: $parser,
            cacheEnabled: $cacheEnabled
        );
    },
    
    // ─────────────────────────────────────────────────────────────
    // Theme Manager
    // ─────────────────────────────────────────────────────────────
    
    ThemeManager::class => function () use ($config, $basePath): ThemeManager {
        return new ThemeManager(
            contribPath: $basePath . '/' . $config->getString('themes.paths.contrib', 'themes/contrib'),
            customPath: $basePath . '/' . $config->getString('themes.paths.custom', 'themes/custom'),
            activeTheme: $config->getString('themes.active', 'default'),
            adminTheme: $config->getString('themes.admin_theme', 'admin-default'),
            cachePath: $basePath . '/' . $config->getString('themes.cache.path', 'var/cache/themes'),
            cacheEnabled: $config->getBool('themes.cache.enabled', true)
        );
    },
    
    // ─────────────────────────────────────────────────────────────
    // CMS Core Services
    // ─────────────────────────────────────────────────────────────
    
    SchemaGenerator::class => function (Connection $conn): SchemaGenerator {
        return new SchemaGenerator($conn);
    },
    
    ModuleManager::class => function (
        Connection $conn,
        SchemaGenerator $schema
    ) use ($config, $basePath): ModuleManager {
        $modulePaths = $config->getArray('cms.modules.paths', [
            'app/Modules/Contrib',
            'app/Modules/Custom',
            'app/Modules/Core'
        ]);
        
        return new ModuleManager(
            connection: $conn,
            schemaGenerator: $schema,
            modulePaths: array_map(fn($p) => $basePath . '/' . $p, $modulePaths),
            storagePath: $basePath . '/storage'
        );
    },
    
    CmsRepository::class => function (Connection $conn): CmsRepository {
        return new CmsRepository($conn);
    },
    
    // ─────────────────────────────────────────────────────────────
    // Security & Permissions
    // ─────────────────────────────────────────────────────────────
    
    PermissionService::class => function (Connection $conn): PermissionService {
        return new PermissionService($conn);
    },
    
    // ─────────────────────────────────────────────────────────────
    // Core Module Services
    // ─────────────────────────────────────────────────────────────
    
    TaxonomyService::class => function (Connection $conn, CacheManager $cache): TaxonomyService {
        return new TaxonomyService($conn, $cache);
    },
    
    MenuService::class => function (Connection $conn, CacheManager $cache): MenuService {
        return new MenuService($conn, $cache);
    },
    
    SettingsService::class => function (Connection $conn, CacheManager $cache): SettingsService {
        return new SettingsService($conn, $cache);
    },
    
    // ─────────────────────────────────────────────────────────────
    // File Storage (MonkeysLegion-Files)
    // ─────────────────────────────────────────────────────────────
    
    StorageManager::class => function () use ($config, $basePath): StorageManager {
        $manager = new StorageManager();
        
        // Register local disk
        $localRoot = $basePath . '/' . $config->getString('files.disks.local.root', 'storage/files');
        $manager->registerDisk('local', new LocalStorage(
            root: $localRoot,
            url: $config->getString('files.disks.local.url'),
            visibility: $config->getString('files.disks.local.visibility', 'private')
        ));
        
        // Register public disk
        $publicRoot = $basePath . '/' . $config->getString('files.disks.public.root', 'storage/uploads');
        $manager->registerDisk('public', new LocalStorage(
            root: $publicRoot,
            url: $config->getString('files.disks.public.url', '/uploads'),
            visibility: $config->getString('files.disks.public.visibility', 'public')
        ));
        
        // Register S3 if configured
        $s3Key = $config->getString('files.disks.s3.key', '');
        if (!empty($s3Key)) {
            $manager->registerDisk('s3', new S3Storage(
                bucket: $config->getString('files.disks.s3.bucket'),
                region: $config->getString('files.disks.s3.region', 'us-east-1'),
                endpoint: $config->getString('files.disks.s3.endpoint') ?: null,
                accessKey: $s3Key,
                secretKey: $config->getString('files.disks.s3.secret'),
                visibility: $config->getString('files.disks.s3.visibility', 'private')
            ));
        }
        
        // Register MinIO if configured
        $minioKey = $config->getString('files.disks.minio.key', '');
        if (!empty($minioKey)) {
            $manager->registerDisk('minio', new S3Storage(
                bucket: $config->getString('files.disks.minio.bucket'),
                region: 'us-east-1',
                endpoint: $config->getString('files.disks.minio.endpoint'),
                accessKey: $minioKey,
                secretKey: $config->getString('files.disks.minio.secret'),
                visibility: 'private',
                options: ['path_style' => true]
            ));
        }
        
        // Set default disk
        $manager->setDefaultDisk($config->getString('files.default', 'local'));
        
        return $manager;
    },
    
    ChunkedUploadManager::class => function (CacheManager $cache) use ($config, $basePath): ChunkedUploadManager {
        return new ChunkedUploadManager(
            tempDir: $basePath . '/' . $config->getString('files.upload.temp_dir', 'storage/tmp/uploads'),
            chunkSize: $config->getInt('files.upload.chunk_size', 5 * 1024 * 1024),
            expiry: $config->getInt('files.upload.chunk_expiry', 86400),
            cache: $cache->store() // Get the default store
        );
    },
    
    UploadValidator::class => function () use ($config): UploadValidator {
        return new UploadValidator(
            maxSize: $config->getInt('files.upload.max_size', 50 * 1024 * 1024),
            allowedMimes: $config->getArray('files.upload.allowed_mimes', []),
            blockedMimes: $config->getArray('files.upload.blocked_mimes', []),
            verifyMime: $config->getBool('files.upload.verify_mime', true)
        );
    },
    
    ImageProcessor::class => function () use ($config): ImageProcessor {
        return new ImageProcessor(
            driver: $config->getString('files.image.driver', 'gd'),
            quality: $config->getInt('files.image.quality', 85),
            stripMetadata: $config->getBool('files.image.strip_metadata', true),
            autoOrient: $config->getBool('files.image.auto_orient', true)
        );
    },
    
    VirusScanner::class => function () use ($config): ?VirusScanner {
        if (!$config->getBool('files.virusscan.enabled', false)) {
            return null;
        }
        
        $driver = $config->getString('files.virusscan.driver', 'clamav');
        
        if ($driver === 'clamav') {
            return new ClamAvScanner(
                socket: $config->getString('files.virusscan.clamav.socket'),
                host: $config->getString('files.virusscan.clamav.host', '127.0.0.1'),
                port: $config->getInt('files.virusscan.clamav.port', 3310),
                timeout: $config->getInt('files.virusscan.clamav.timeout', 30)
            );
        }
        
        return null;
    },
    
    MediaService::class => function (
        CmsRepository $repo,
        StorageManager $storage,
        ChunkedUploadManager $chunked,
        UploadValidator $validator,
        ImageProcessor $imageProcessor,
        ?VirusScanner $scanner
    ) use ($config): MediaService {
        return new MediaService(
            repository: $repo,
            storage: $storage,
            chunkedUpload: $chunked,
            validator: $validator,
            imageProcessor: $imageProcessor,
            virusScanner: $scanner,
            config: $config
        );
    },
    
    // ─────────────────────────────────────────────────────────────
    // Middleware
    // ─────────────────────────────────────────────────────────────
    
    AuthMiddleware::class => function (
        PermissionService $permissions,
        CmsRepository $repo
    ) use ($config): AuthMiddleware {
        return new AuthMiddleware(
            $permissions,
            $repo,
            $config->getString('app.jwt_secret', $_ENV['JWT_SECRET'] ?? 'change-me')
        );
    },
    
    // ─────────────────────────────────────────────────────────────
    // Admin Controllers
    // ─────────────────────────────────────────────────────────────
    
    ModuleController::class => function (ModuleManager $mm, SchemaGenerator $sg): ModuleController {
        return new ModuleController($mm, $sg);
    },
    
    DashboardController::class => function (
        ModuleManager $mm,
        CmsRepository $repo
    ) use ($config): DashboardController {
        return new DashboardController($mm, $repo, $config);
    },
    
    ContentController::class => function (
        CmsRepository $repo,
        ModuleManager $mm,
        PermissionService $permissions,
        TaxonomyService $taxonomy
    ) use ($config): ContentController {
        return new ContentController($repo, $mm, $permissions, $taxonomy, $config);
    },
    
    ThemeController::class => function (ThemeManager $tm): ThemeController {
        return new ThemeController($tm);
    },
    
    UserController::class => function (
        CmsRepository $repo,
        PermissionService $permissions
    ): UserController {
        return new UserController($repo, $permissions);
    },
    
    RoleController::class => function (
        CmsRepository $repo,
        PermissionService $permissions
    ): RoleController {
        return new RoleController($repo, $permissions);
    },
    
    TaxonomyController::class => function (TaxonomyService $taxonomy): TaxonomyController {
        return new TaxonomyController($taxonomy);
    },
    
    MenuController::class => function (MenuService $menus): MenuController {
        return new MenuController($menus);
    },
    
    SettingsController::class => function (SettingsService $settings): SettingsController {
        return new SettingsController($settings);
    },
    
    MediaController::class => function (
        MediaService $media,
        PermissionService $permissions
    ): MediaController {
        return new MediaController($media, $permissions);
    },
    
    CacheController::class => function (CacheManager $cache): CacheController {
        return new CacheController($cache);
    },
    
    // ─────────────────────────────────────────────────────────────
    // Block System
    // ─────────────────────────────────────────────────────────────
    
    BlockManager::class => function (Connection $conn, ?CacheManager $cache): BlockManager {
        $manager = new BlockManager($conn, $cache);
        
        // Register code-defined block types
        $manager->registerTypes([
            new HtmlBlock(),
            new TextBlock(),
            new ImageBlock(),
            new GalleryBlock(),
            new MenuBlock(),
            new ViewsBlock(),
            new VideoBlock(),
        ]);
        
        return $manager;
    },
    
    BlockRenderer::class => function (
        BlockManager $blockManager,
        Connection $conn,
        ?CacheManager $cache,
        ?Renderer $renderer
    ): BlockRenderer {
        return new BlockRenderer($blockManager, $conn, $cache, $renderer);
    },
    
    BlockTypeController::class => function (
        BlockManager $blockManager,
        ?BlockRenderer $renderer
    ): BlockTypeController {
        return new BlockTypeController($blockManager, $renderer);
    },
    
    // ─────────────────────────────────────────────────────────────
    // Content Type System
    // ─────────────────────────────────────────────────────────────
    
    ContentTypeManager::class => function (
        ConnectionInterface $conn,
        ?ModuleManager $mm,
        ?SchemaGenerator $sg,
        ?CacheManager $cache
    ): ContentTypeManager {
        $manager = new ContentTypeManager($conn, $mm, $sg, $cache);
        
        // Register entity types from Core module
        $manager->registerModuleTypes(dirname(__DIR__) . '/app/Modules/Core');
        
        // Ensure default content types (Article) exist
        try {
            $manager->ensureDefaultTypes();
        } catch (\Exception $e) {
            error_log('ContentTypeManager::ensureDefaultTypes failed: ' . $e->getMessage());
        }
        
        return $manager;
    },
    
    ContentTypeController::class => function (ContentTypeManager $manager): ContentTypeController {
        return new ContentTypeController($manager);
    },
    
    // ContentTypePageController uses auto-wiring for all dependencies
    // (MLView, MenuService, SessionManager, AssetManager, ContentTypeManager)
    
    // ─────────────────────────────────────────────────────────────
    // Enhanced Taxonomy System
    // ─────────────────────────────────────────────────────────────
    
    TaxonomyManager::class => function (Connection $conn, ?CacheManager $cache): TaxonomyManager {
        $manager = new TaxonomyManager($conn, $cache);
        
        // Register default code-defined vocabularies
        $manager->registerVocabulary([
            'id' => 'tags',
            'name' => 'Tags',
            'description' => 'Free-form content tags',
            'icon' => '🏷️',
            'hierarchical' => false,
            'multiple' => true,
        ]);
        
        $manager->registerVocabulary([
            'id' => 'categories',
            'name' => 'Categories',
            'description' => 'Hierarchical content categories',
            'icon' => '📂',
            'hierarchical' => true,
            'multiple' => true,
        ]);
        
        return $manager;
    },
    
    // ─────────────────────────────────────────────────────────────
    // Field Widget System
    // ─────────────────────────────────────────────────────────────
    
    FieldWidgetManager::class => function (): FieldWidgetManager {
        $manager = new FieldWidgetManager();
        
        // Module widgets are auto-registered via registerModuleWidgets()
        // when modules are loaded
        
        return $manager;
    },
    
    FormBuilder::class => function (FieldWidgetManager $widgets): FormBuilder {
        return new FormBuilder($widgets);
    },
    
    FieldController::class => function (FieldWidgetManager $widgets): FieldController {
        return new FieldController($widgets);
    },
    
    // ─────────────────────────────────────────────────────────────
    // Router Configuration
    // ─────────────────────────────────────────────────────────────
    
    'middleware' => [
        'global' => $config->getArray('middleware.global', []),
        'admin' => array_merge(
            [AuthMiddleware::class],
            $config->getArray('middleware.admin', [])
        ),
        'api' => $config->getArray('middleware.api', []),
    ],
    
    'controller_paths' => array_map(
        fn($p) => $basePath . '/' . $p,
        $config->getArray('controllers.paths', ['app/Controllers'])
    ),
];
