<?php

declare(strict_types=1);

namespace App\Cms\Config;

/**
 * ConfigCollectorInterface — Contract for config export/import participants.
 *
 * Each collector handles one "section" of configuration (settings, content types,
 * menus, etc.). The ConfigManager auto-discovers collectors from:
 *   1. Core built-in collectors (always registered)
 *   2. Plugins implementing this interface or returning collectors from getConfigCollectors()
 *   3. Themes with `config {}` blocks in their theme.mlc
 */
interface ConfigCollectorInterface
{
    /**
     * Unique key for this collector (e.g. 'settings', 'content_types', 'menu.main').
     * Used as the MLC file prefix: "{key}.mlc" or "{key}.{id}.mlc"
     */
    public function getKey(): string;

    /**
     * Human-readable label for the admin UI.
     */
    public function getLabel(): string;

    /**
     * Export configuration data.
     *
     * Returns an array of items, each keyed by an identifier.
     * Each item is an associative array of config values.
     *
     * @return array<string, array<string, mixed>>
     */
    public function export(): array;

    /**
     * Import configuration data.
     *
     * @param array<string, array<string, mixed>> $data  The data to import
     * @param bool $overwrite  If true, replace existing; if false, skip existing
     */
    public function import(array $data, bool $overwrite = false): ImportResult;

    /**
     * Other collector keys that must be imported before this one.
     *
     * @return string[]
     */
    public function getDependencies(): array;
}
