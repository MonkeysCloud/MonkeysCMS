<?php

declare(strict_types=1);

namespace App\Cms\Database;

/**
 * MigrationConfig — Parsed migration entry from migrations.mlc.
 */
final class MigrationConfig
{
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly string $file,
        public readonly string $module,
        public readonly string $version,
        public readonly array $requires,
        public readonly bool $reversible,
        public readonly ?string $rollbackFile,
    ) {}
}
