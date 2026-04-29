<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Content\ContentTypeEntity;
use Psr\Container\ContainerInterface;
use PDO;

/**
 * ContentProvider — Registers content type management services.
 */
final class ContentProvider
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function boot(): void
    {
        // Content types will be auto-discovered from the database
        // during request handling, not at boot time
    }

    /**
     * DI definitions for content services
     */
    public static function getDefinitions(): array
    {
        return [
            // ContentTypeManager will be added in Phase 2 completion
        ];
    }
}
