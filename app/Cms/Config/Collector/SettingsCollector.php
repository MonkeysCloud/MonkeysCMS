<?php

declare(strict_types=1);

namespace App\Cms\Config\Collector;

use App\Cms\Config\ConfigCollectorInterface;
use App\Cms\Config\ImportResult;
use PDO;

/**
 * SettingsCollector — Exports/imports CMS settings grouped by category.
 *
 * Files: settings.general.mlc, settings.appearance.mlc, etc.
 */
final class SettingsCollector implements ConfigCollectorInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function getKey(): string { return 'settings'; }
    public function getLabel(): string { return 'Settings'; }
    public function getDependencies(): array { return []; }

    public function export(): array
    {
        $stmt = $this->pdo->query('SELECT `group`, `key`, `value`, `type`, `autoload` FROM settings ORDER BY `group`, `key`');
        $groups = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $group = $row['group'] ?: 'general';
            $groups[$group][$row['key']] = $row['value'];
        }

        return $groups;
    }

    public function import(array $data, bool $overwrite = false): ImportResult
    {
        $result = new ImportResult();

        foreach ($data as $group => $settings) {
            foreach ($settings as $key => $value) {
                // Check if exists
                $stmt = $this->pdo->prepare('SELECT `key` FROM settings WHERE `key` = :key');
                $stmt->execute(['key' => $key]);
                $exists = (bool) $stmt->fetch();

                if ($exists && !$overwrite) {
                    $result->addSkipped("settings.{$group}.{$key}");
                    continue;
                }

                $stmt = $this->pdo->prepare(
                    'INSERT INTO settings (`group`, `key`, `value`, `type`, `autoload`)
                     VALUES (:group, :key, :value, :type, 1)
                     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group` = VALUES(`group`)'
                );
                $stmt->execute([
                    'group' => $group,
                    'key'   => $key,
                    'value' => $value,
                    'type'  => 'string',
                ]);

                $exists
                    ? $result->addUpdated("settings.{$group}.{$key}")
                    : $result->addCreated("settings.{$group}.{$key}");
            }
        }

        return $result;
    }
}
