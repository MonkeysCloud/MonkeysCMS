<?php

declare(strict_types=1);

namespace App\Cms\Theme;

/**
 * ThemeLibrary — Parsed global library from config/libraries.mlc.
 */
final class ThemeLibrary
{
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly array $css,
        public readonly array $js,
        public readonly int $weight,
        public readonly bool $required,
        public readonly bool $module,
    ) {}
}
