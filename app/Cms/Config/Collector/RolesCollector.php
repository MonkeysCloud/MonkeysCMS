<?php

declare(strict_types=1);

namespace App\Cms\Config\Collector;

use App\Cms\Config\ConfigCollectorInterface;
use App\Cms\Config\ImportResult;
use PDO;

/**
 * RolesCollector — Exports/imports CMS roles and their permissions.
 *
 * Files: role.admin.mlc, role.editor.mlc, etc.
 */
final class RolesCollector implements ConfigCollectorInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function getKey(): string { return 'role'; }
    public function getLabel(): string { return 'Roles & Permissions'; }
    public function getDependencies(): array { return []; }

    public function export(): array
    {
        $roles = $this->pdo->query('SELECT * FROM cms_roles ORDER BY weight ASC')->fetchAll(PDO::FETCH_ASSOC);
        $result = [];

        foreach ($roles as $r) {
            $permissions = is_string($r['permissions'])
                ? (json_decode($r['permissions'], true) ?? [])
                : ($r['permissions'] ?? []);

            $result[$r['machine_name']] = [
                'label'          => $r['label'],
                'description'    => $r['description'] ?? '',
                'is_super_admin' => (bool) $r['is_super_admin'],
                'is_system'      => (bool) $r['is_system'],
                'weight'         => (int) $r['weight'],
                'permissions'    => $permissions,
            ];
        }

        return $result;
    }

    public function import(array $data, bool $overwrite = false): ImportResult
    {
        $result = new ImportResult();

        foreach ($data as $machineName => $values) {
            $stmt = $this->pdo->prepare('SELECT id FROM cms_roles WHERE machine_name = :mn');
            $stmt->execute(['mn' => $machineName]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing && !$overwrite) {
                $result->addSkipped("role.{$machineName}");
                continue;
            }

            $now = date('Y-m-d H:i:s');
            $permissions = json_encode($values['permissions'] ?? []);

            if ($existing) {
                $this->pdo->prepare(
                    'UPDATE cms_roles SET label=:l, description=:d, is_super_admin=:sa,
                     is_system=:sys, weight=:w, permissions=:p, updated_at=:u WHERE machine_name=:mn'
                )->execute([
                    'mn' => $machineName, 'l' => $values['label'] ?? '',
                    'd' => $values['description'] ?? '',
                    'sa' => (int) ($values['is_super_admin'] ?? false),
                    'sys' => (int) ($values['is_system'] ?? false),
                    'w' => (int) ($values['weight'] ?? 0),
                    'p' => $permissions, 'u' => $now,
                ]);
                $result->addUpdated("role.{$machineName}");
            } else {
                $this->pdo->prepare(
                    'INSERT INTO cms_roles (machine_name, label, description, is_super_admin, is_system, weight, permissions, created_at, updated_at)
                     VALUES (:mn, :l, :d, :sa, :sys, :w, :p, :c, :u)'
                )->execute([
                    'mn' => $machineName, 'l' => $values['label'] ?? '',
                    'd' => $values['description'] ?? '',
                    'sa' => (int) ($values['is_super_admin'] ?? false),
                    'sys' => (int) ($values['is_system'] ?? false),
                    'w' => (int) ($values['weight'] ?? 0),
                    'p' => $permissions, 'c' => $now, 'u' => $now,
                ]);
                $result->addCreated("role.{$machineName}");
            }
        }

        return $result;
    }
}
