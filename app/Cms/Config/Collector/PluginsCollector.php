<?php

declare(strict_types=1);

namespace App\Cms\Config\Collector;

use App\Cms\Config\ConfigCollectorInterface;
use App\Cms\Config\ImportResult;
use PDO;

/**
 * PluginsCollector — Exports/imports plugin enabled/disabled state.
 *
 * Files: plugin.vendor--name.mlc
 */
final class PluginsCollector implements ConfigCollectorInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function getKey(): string { return 'plugin'; }
    public function getLabel(): string { return 'Plugins'; }
    public function getDependencies(): array { return []; }

    public function export(): array
    {
        try {
            $rows = $this->pdo->query('SELECT * FROM cms_plugins ORDER BY vendor, name')
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return [];
        }

        $result = [];
        foreach ($rows as $r) {
            $key = ($r['vendor'] ?? '') . '--' . ($r['name'] ?? '');
            $result[$key] = [
                'vendor'    => $r['vendor'] ?? '',
                'name'      => $r['name'] ?? '',
                'version'   => $r['version'] ?? '',
                'namespace' => $r['namespace'] ?? '',
                'enabled'   => (bool) ($r['enabled'] ?? false),
            ];
        }

        return $result;
    }

    public function import(array $data, bool $overwrite = false): ImportResult
    {
        $result = new ImportResult();

        foreach ($data as $key => $values) {
            $vendor = $values['vendor'] ?? '';
            $name = $values['name'] ?? '';

            if (!$vendor || !$name) {
                $result->addError("Invalid plugin entry: {$key}");
                continue;
            }

            try {
                $stmt = $this->pdo->prepare(
                    'SELECT id FROM cms_plugins WHERE vendor = :v AND name = :n'
                );
                $stmt->execute(['v' => $vendor, 'n' => $name]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing && !$overwrite) {
                    $result->addSkipped("plugin.{$key}");
                    continue;
                }

                $now = date('Y-m-d H:i:s');

                if ($existing) {
                    $this->pdo->prepare(
                        'UPDATE cms_plugins SET enabled=:e, version=:v, updated_at=:u WHERE id=:id'
                    )->execute([
                        'id' => (int) $existing['id'],
                        'e' => (int) ($values['enabled'] ?? false),
                        'v' => $values['version'] ?? '',
                        'u' => $now,
                    ]);
                    $result->addUpdated("plugin.{$key}");
                } else {
                    $this->pdo->prepare(
                        'INSERT INTO cms_plugins (vendor, name, version, namespace, enabled, installed_at)
                         VALUES (:vendor, :name, :version, :ns, :enabled, :now)'
                    )->execute([
                        'vendor'  => $vendor,
                        'name'    => $name,
                        'version' => $values['version'] ?? '',
                        'ns'      => $values['namespace'] ?? '',
                        'enabled' => (int) ($values['enabled'] ?? false),
                        'now'     => $now,
                    ]);
                    $result->addCreated("plugin.{$key}");
                }
            } catch (\PDOException $e) {
                $result->addError("Plugin {$key}: " . $e->getMessage());
            }
        }

        return $result;
    }
}
