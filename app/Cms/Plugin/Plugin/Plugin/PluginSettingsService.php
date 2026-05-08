<?php

declare(strict_types=1);

namespace App\Cms\Plugin;

use MonkeysLegion\DI\Attributes\Singleton;
use PDO;

/**
 * PluginSettingsService — Manages plugin settings storage.
 *
 * Settings are defined in {name}.plugin.mlc under the `settings { }` block.
 * Values are stored in the `cms_plugin_settings` table.
 */
#[Singleton]
final class PluginSettingsService
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Read / Write ───────────────────────────────────────────────────

    /**
     * Get a single setting value.
     */
    public function get(string $machineName, string $key, ?string $default = null): ?string
    {
        $pluginId = $this->resolvePluginId($machineName);
        if ($pluginId === null) {
            return $default;
        }

        $stmt = $this->pdo->prepare(
            "SELECT value FROM cms_plugin_settings WHERE plugin_id = :pid AND `key` = :key LIMIT 1"
        );
        $stmt->execute([':pid' => $pluginId, ':key' => $key]);
        $val = $stmt->fetchColumn();

        return $val !== false ? (string) $val : $default;
    }

    /**
     * Get all settings for a plugin.
     *
     * @return array<string, string>
     */
    public function getAll(string $machineName): array
    {
        $pluginId = $this->resolvePluginId($machineName);
        if ($pluginId === null) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT `key`, value FROM cms_plugin_settings WHERE plugin_id = :pid"
        );
        $stmt->execute([':pid' => $pluginId]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['key']] = $row['value'];
        }

        return $result;
    }

    /**
     * Set a single setting value.
     */
    public function set(string $machineName, string $key, string $value): void
    {
        $pluginId = $this->resolvePluginId($machineName);
        if ($pluginId === null) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO cms_plugin_settings (plugin_id, `key`, value)
            VALUES (:pid, :key, :val)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ");
        $stmt->execute([':pid' => $pluginId, ':key' => $key, ':val' => $value]);
    }

    /**
     * Delete a single setting.
     */
    public function delete(string $machineName, string $key): void
    {
        $pluginId = $this->resolvePluginId($machineName);
        if ($pluginId === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "DELETE FROM cms_plugin_settings WHERE plugin_id = :pid AND `key` = :key"
        );
        $stmt->execute([':pid' => $pluginId, ':key' => $key]);
    }

    /**
     * Delete all settings for a plugin.
     */
    public function deleteAll(string $machineName): void
    {
        $pluginId = $this->resolvePluginId($machineName);
        if ($pluginId === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "DELETE FROM cms_plugin_settings WHERE plugin_id = :pid"
        );
        $stmt->execute([':pid' => $pluginId]);
    }

    // ── Settings Definition Parser ─────────────────────────────────────

    /**
     * Parse the `settings { }` block from a plugin.mlc file.
     *
     * Returns an array of setting definitions:
     *   ['key' => ['type' => 'string', 'label' => 'API Key', 'default' => '', 'required' => false]]
     *
     * @return array<string, array{type: string, label: string, default: string, required: bool}>
     */
    public function parseSettingsDefinition(string $pluginPath): array
    {
        // Find the *.plugin.mlc file
        $mlcPath = null;
        foreach (new \DirectoryIterator($pluginPath) as $file) {
            if (!$file->isDot() && str_ends_with($file->getFilename(), '.plugin.mlc')) {
                $mlcPath = $file->getPathname();
                break;
            }
        }

        if ($mlcPath === null || !file_exists($mlcPath)) {
            return [];
        }

        $content = file_get_contents($mlcPath);

        // Extract settings block
        if (!preg_match('/settings\s*\{(.*?)\n\}/s', $content, $m)) {
            return [];
        }

        $settingsBlock = $m[1];
        $settings = [];

        // Parse each setting line: key = { type = "...", label = "...", default = "..." }
        preg_match_all('/(\w+)\s*=\s*\{([^}]+)\}/', $settingsBlock, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match[1];
            $props = $match[2];

            $get = static function (string $prop) use ($props): string {
                if (preg_match('/' . preg_quote($prop) . '\s*=\s*"([^"]*)"/', $props, $m)) {
                    return $m[1];
                }
                if (preg_match('/' . preg_quote($prop) . '\s*=\s*(\w+)/', $props, $m)) {
                    return $m[1];
                }
                return '';
            };

            $settings[$key] = [
                'type'     => $get('type') ?: 'string',
                'label'    => $get('label') ?: ucfirst(str_replace('_', ' ', $key)),
                'default'  => $get('default'),
                'required' => strtolower($get('required')) === 'true',
            ];
        }

        return $settings;
    }

    // ── Internal ───────────────────────────────────────────────────────

    private function resolvePluginId(string $machineName): ?int
    {
        $parts = explode('/', $machineName, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id FROM cms_plugins WHERE vendor = :vendor AND name = :name LIMIT 1"
        );
        $stmt->execute([':vendor' => $parts[0], ':name' => $parts[1]]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }
}
