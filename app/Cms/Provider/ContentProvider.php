<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Block\BlockTemplateRenderer;
use App\Cms\Block\BlockTypeRegistry;
use App\Cms\Block\FieldFormRenderer;
use App\Cms\Block\Types\ButtonBlock;
use App\Cms\Block\Types\DividerBlock;
use App\Cms\Block\Types\FieldBlock;
use App\Cms\Block\Types\HeadingBlock;
use App\Cms\Block\Types\HtmlBlock as HtmlBlockType;
use App\Cms\Block\Types\ImageBlock;
use App\Cms\Block\Types\SpacerBlock;
use App\Cms\Block\Types\TextBlock;
use App\Cms\Block\Types\VideoBlock;
use App\Cms\Content\ContentRepository;
use App\Cms\Content\ContentRouter;
use App\Cms\Content\ContentTypeManager;
use App\Cms\Field\FieldRepository;
use App\Cms\Field\Widget\WidgetRegistry;
use App\Cms\Mosaic\MosaicManager;
use App\Cms\Mosaic\MosaicRenderer;
use App\Cms\Url\ContentUrlResolver;
use App\Cms\Url\UrlManager;
use Psr\Container\ContainerInterface;
use PDO;

/**
 * ContentProvider — Registers all CMS services in the DI container.
 *
 * Services:
 *   - ContentTypeManager: content type resolution (DB + MLC)
 *   - ContentRepository: node CRUD
 *   - FieldRepository: field definition CRUD
 *   - WidgetRegistry: GLOBAL field type → widget mapping (used across all CMS subsystems)
 *   - MosaicManager: mosaic layout CRUD + revision snapshotting
 *   - BlockTypeRegistry: block type discovery + rendering
 *   - FieldFormRenderer: Mosaic inspector form rendering (delegates to WidgetRegistry)
 */
final class ContentProvider
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function boot(): void
    {
        // Content types are lazy-loaded on first access
    }

    /**
     * DI definitions for all CMS services.
     */
    public static function getDefinitions(): array
    {
        return [
            ContentTypeManager::class => fn($c) => new ContentTypeManager(
                pdo: $c->get(PDO::class),
                fieldRepo: $c->get(FieldRepository::class),
                configPath: base_path('config/content-types'),
            ),

            ContentRepository::class => fn($c) => new ContentRepository(
                pdo: $c->get(PDO::class),
            ),

            FieldRepository::class => fn($c) => new FieldRepository(
                pdo: $c->get(PDO::class),
            ),

            // ── Global Widget Registry ───────────────────────────────────
            // Single registry used by: content forms, taxonomy, Mosaic, custom modules.
            // Built-in widgets auto-register on construction.
            // Custom modules add their own widgets via $registry->register().
            WidgetRegistry::class => fn() => new WidgetRegistry(),

            MosaicManager::class => fn($c) => new MosaicManager(
                pdo: $c->get(PDO::class),
            ),

            // ── Field Form Renderer (Mosaic inspector forms) ──────────────
            // Uses the global WidgetRegistry, NOT a separate block field system.
            FieldFormRenderer::class => function ($c) {
                $langService = null;
                try {
                    $langService = $c->get(\App\Cms\I18n\LanguageService::class);
                } catch (\Throwable) {
                    // LanguageService may not be available during install
                }

                return new FieldFormRenderer(
                    widgetRegistry: $c->get(WidgetRegistry::class),
                    languageService: $langService,
                );
            },

            BlockTypeRegistry::class => function ($c) {
                $registry = new BlockTypeRegistry();
                $registry->registerMany([
                    new TextBlock(),
                    new HeadingBlock(),
                    new ImageBlock(),
                    new VideoBlock(),
                    new ButtonBlock(),
                    new DividerBlock(),
                    new SpacerBlock(),
                    new HtmlBlockType(),
                    new FieldBlock(),
                ]);

                // Merge DB-defined blocks (code-defined take precedence)
                $registry->loadFromDatabase($c->get(PDO::class));
                
                // Merge theme-defined blocks
                $envManager = new \MonkeysLegion\Env\EnvManager(
                    loader: new \MonkeysLegion\Env\Loaders\DotenvLoader(),
                    repository: new \MonkeysLegion\Env\Repositories\NativeEnvRepository(),
                );
                $mlcParser = new \MonkeysLegion\Mlc\Parsers\MlcParser($envManager, dirname(__DIR__, 4));
                $registry->loadFromTheme($c->get(\App\Cms\Theme\ThemeManager::class), $mlcParser);

                // Wire theme template renderer so all blocks render via .ml.php templates
                try {
                    $registry->setTemplateRenderer($c->get(BlockTemplateRenderer::class));
                } catch (\Throwable) {
                    // Template renderer may not be available during install
                }

                return $registry;
            },

            BlockTemplateRenderer::class => fn($c) => new BlockTemplateRenderer(
                renderer: $c->get(\MonkeysLegion\Template\Renderer::class),
                activeTheme: 'front', // TODO: resolve from ThemeResolver when multi-theme is active
            ),

            ContentRouter::class => fn($c) => new ContentRouter(
                typeManager: $c->get(ContentTypeManager::class),
                contentRepo: $c->get(ContentRepository::class),
            ),

            MosaicRenderer::class => fn($c) => new MosaicRenderer(
                renderer: $c->get(\MonkeysLegion\Template\Renderer::class),
                blockRegistry: $c->get(BlockTypeRegistry::class),
                mosaicManager: $c->get(MosaicManager::class),
                contentRepo: $c->get(ContentRepository::class),
                fieldRepo: $c->get(FieldRepository::class),
                blockTemplatePath: 'blocks',
            ),

            ContentUrlResolver::class => fn($c) => new ContentUrlResolver(
                typeManager: $c->get(ContentTypeManager::class),
                contentRepo: $c->get(ContentRepository::class),
            ),

            UrlManager::class => function ($c) {
                $manager = new UrlManager();

                // Register content node resolver
                $manager->register($c->get(ContentUrlResolver::class));

                // Map entity classes to resolver types
                $manager->mapClass(\App\Cms\Content\ContentEntity::class, 'node');

                // Inject language service for locale-prefixed URLs
                try {
                    $manager->setLanguageService($c->get(\App\Cms\I18n\LanguageService::class));
                } catch (\Throwable) {
                    // LanguageService may not be available during install
                }

                return $manager;
            },
        ];
    }
}
