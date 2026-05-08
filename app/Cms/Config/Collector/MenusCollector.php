<?php

declare(strict_types=1);

namespace App\Cms\Config\Collector;

use App\Cms\Config\ConfigCollectorInterface;
use App\Cms\Config\ImportResult;
use PDO;

/**
 * MenusCollector — Exports/imports menu definitions and items.
 *
 * Files: menu.main.mlc, menu.footer.mlc, etc.
 */
final class MenusCollector implements ConfigCollectorInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function getKey(): string { return 'menu'; }
    public function getLabel(): string { return 'Menus'; }
    public function getDependencies(): array { return []; }

    public function export(): array
    {
        $menus = $this->pdo->query('SELECT * FROM menus ORDER BY label ASC')->fetchAll(PDO::FETCH_ASSOC);
        $result = [];

        foreach ($menus as $m) {
            $data = [
                'label'       => $m['label'],
                'description' => $m['description'] ?? '',
                'enabled'     => (bool) $m['enabled'],
            ];

            // Export items
            $items = $this->pdo->prepare(
                'SELECT title, url, route_name, target, icon, weight, enabled, parent_id
                 FROM menu_items WHERE menu_id = :mid ORDER BY weight ASC'
            );
            $items->execute(['mid' => (int) $m['id']]);

            $itemList = [];
            foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $itemData = [
                    'title'   => $item['title'],
                    'url'     => $item['url'] ?? '',
                    'weight'  => (int) $item['weight'],
                    'enabled' => (bool) $item['enabled'],
                ];
                if (!empty($item['icon'])) $itemData['icon'] = $item['icon'];
                if (!empty($item['target'])) $itemData['target'] = $item['target'];
                if (!empty($item['route_name'])) $itemData['route_name'] = $item['route_name'];
                $itemList[] = $itemData;
            }

            if (!empty($itemList)) {
                $data['items'] = $itemList;
            }

            $result[$m['machine_name']] = $data;
        }

        return $result;
    }

    public function import(array $data, bool $overwrite = false): ImportResult
    {
        $result = new ImportResult();

        foreach ($data as $machineName => $values) {
            $stmt = $this->pdo->prepare('SELECT id FROM menus WHERE machine_name = :mn');
            $stmt->execute(['mn' => $machineName]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            $items = $values['items'] ?? [];
            unset($values['items']);

            $now = date('Y-m-d H:i:s');

            if ($existing && !$overwrite) {
                $result->addSkipped("menu.{$machineName}");
                continue;
            }

            if ($existing) {
                $this->pdo->prepare(
                    'UPDATE menus SET label=:l, description=:d, enabled=:e, updated_at=:u WHERE machine_name=:mn'
                )->execute([
                    'mn' => $machineName, 'l' => $values['label'] ?? '',
                    'd' => $values['description'] ?? '', 'e' => (int) ($values['enabled'] ?? true), 'u' => $now,
                ]);
                $menuId = (int) $existing['id'];
                $result->addUpdated("menu.{$machineName}");
            } else {
                $this->pdo->prepare(
                    'INSERT INTO menus (machine_name,label,description,enabled,created_at,updated_at) VALUES (:mn,:l,:d,:e,:c,:u)'
                )->execute([
                    'mn' => $machineName, 'l' => $values['label'] ?? '',
                    'd' => $values['description'] ?? '', 'e' => (int) ($values['enabled'] ?? true),
                    'c' => $now, 'u' => $now,
                ]);
                $menuId = (int) $this->pdo->lastInsertId();
                $result->addCreated("menu.{$machineName}");
            }

            // Import items
            if (!empty($items) && (!$existing || $overwrite)) {
                foreach ($items as $item) {
                    $iStmt = $this->pdo->prepare('SELECT id FROM menu_items WHERE menu_id = :mid AND title = :t AND url = :u');
                    $iStmt->execute(['mid' => $menuId, 't' => $item['title'], 'u' => $item['url'] ?? '']);

                    if (!$iStmt->fetch()) {
                        $this->pdo->prepare(
                            'INSERT INTO menu_items (menu_id, title, url, route_name, target, icon, weight, enabled)
                             VALUES (:mid, :t, :u, :rn, :tg, :i, :w, :e)'
                        )->execute([
                            'mid' => $menuId, 't' => $item['title'], 'u' => $item['url'] ?? '',
                            'rn' => $item['route_name'] ?? null, 'tg' => $item['target'] ?? null,
                            'i' => $item['icon'] ?? null, 'w' => (int) ($item['weight'] ?? 0),
                            'e' => (int) ($item['enabled'] ?? true),
                        ]);
                    }
                }
            }
        }

        return $result;
    }
}
