<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Block\BlockTypeRegistry;
use App\Cms\Block\Types\ButtonBlock;
use App\Cms\Block\Types\DividerBlock;
use App\Cms\Block\Types\HeadingBlock;
use App\Cms\Block\Types\HtmlBlock;
use App\Cms\Block\Types\ImageBlock;
use App\Cms\Block\Types\SpacerBlock;
use App\Cms\Block\Types\TextBlock;
use App\Cms\Block\Types\VideoBlock;
use App\Cms\Mosaic\MosaicManager;
use Psr\Container\ContainerInterface;
use PDO;

/**
 * MosaicProvider — Registers the Mosaic visual page builder services.
 *
 * Manages the block type registry, Mosaic manager, and connects
 * the editor API routes.
 */
final class MosaicProvider
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function boot(): void
    {
        // Register built-in block types
        if ($this->container->has(BlockTypeRegistry::class)) {
            $registry = $this->container->get(BlockTypeRegistry::class);
            $registry->registerMany([
                // Content blocks
                new TextBlock(),
                new HeadingBlock(),
                new ButtonBlock(),
                // Media blocks
                new ImageBlock(),
                new VideoBlock(),
                // Layout blocks
                new SpacerBlock(),
                new DividerBlock(),
                // Advanced blocks
                new HtmlBlock(),
            ]);
        }
    }

    /**
     * DI definitions for Mosaic services
     */
    public static function getDefinitions(): array
    {
        return [
            BlockTypeRegistry::class => function (ContainerInterface $c): BlockTypeRegistry {
                return new BlockTypeRegistry();
            },

            MosaicManager::class => function (ContainerInterface $c): MosaicManager {
                return new MosaicManager($c->get(PDO::class));
            },
        ];
    }
}
