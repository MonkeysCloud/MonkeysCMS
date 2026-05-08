<?php

declare(strict_types=1);

namespace App\Cms\Config\Collector;

use App\Cms\Config\ConfigCollectorInterface;
use App\Cms\Config\ImportResult;
use PDO;

/**
 * TaxonomiesCollector — Exports/imports vocabularies and their terms.
 *
 * Files: vocabulary.tags.mlc, vocabulary.categories.mlc, etc.
 */
final class TaxonomiesCollector implements ConfigCollectorInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function getKey(): string { return 'vocabulary'; }
    public function getLabel(): string { return 'Taxonomies'; }
    public function getDependencies(): array { return []; }

    public function export(): array
    {
        $vocabs = $this->pdo->query('SELECT * FROM vocabularies ORDER BY weight ASC, label ASC')->fetchAll(PDO::FETCH_ASSOC);
        $result = [];

        foreach ($vocabs as $v) {
            $data = [
                'label'        => $v['label'],
                'description'  => $v['description'] ?? '',
                'hierarchical' => (bool) $v['hierarchical'],
                'multiple'     => (bool) $v['multiple'],
                'weight'       => (int) $v['weight'],
            ];

            // Export terms
            $terms = $this->pdo->prepare(
                'SELECT name, slug, description, parent_id, weight FROM terms WHERE vocabulary_id = :vid ORDER BY weight ASC, name ASC'
            );
            $terms->execute(['vid' => (int) $v['id']]);

            $termList = [];
            foreach ($terms->fetchAll(PDO::FETCH_ASSOC) as $t) {
                $termList[] = [
                    'name'        => $t['name'],
                    'slug'        => $t['slug'] ?? '',
                    'description' => $t['description'] ?? '',
                    'weight'      => (int) $t['weight'],
                ];
            }

            if (!empty($termList)) {
                $data['terms'] = $termList;
            }

            $result[$v['machine_name']] = $data;
        }

        return $result;
    }

    public function import(array $data, bool $overwrite = false): ImportResult
    {
        $result = new ImportResult();

        foreach ($data as $machineName => $values) {
            $stmt = $this->pdo->prepare('SELECT id FROM vocabularies WHERE machine_name = :mn');
            $stmt->execute(['mn' => $machineName]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            $terms = $values['terms'] ?? [];
            unset($values['terms']);

            $now = date('Y-m-d H:i:s');

            if ($existing && !$overwrite) {
                $result->addSkipped("vocabulary.{$machineName}");
                continue;
            }

            if ($existing) {
                $this->pdo->prepare(
                    'UPDATE vocabularies SET label=:l, description=:d, hierarchical=:h, multiple=:m, weight=:w, updated_at=:u WHERE machine_name=:mn'
                )->execute([
                    'mn' => $machineName, 'l' => $values['label'] ?? '', 'd' => $values['description'] ?? '',
                    'h' => (int) ($values['hierarchical'] ?? false), 'm' => (int) ($values['multiple'] ?? false),
                    'w' => (int) ($values['weight'] ?? 0), 'u' => $now,
                ]);
                $vocabId = (int) $existing['id'];
                $result->addUpdated("vocabulary.{$machineName}");
            } else {
                $this->pdo->prepare(
                    'INSERT INTO vocabularies (machine_name,label,description,hierarchical,multiple,weight,created_at,updated_at) VALUES (:mn,:l,:d,:h,:m,:w,:c,:u)'
                )->execute([
                    'mn' => $machineName, 'l' => $values['label'] ?? '', 'd' => $values['description'] ?? '',
                    'h' => (int) ($values['hierarchical'] ?? false), 'm' => (int) ($values['multiple'] ?? false),
                    'w' => (int) ($values['weight'] ?? 0), 'c' => $now, 'u' => $now,
                ]);
                $vocabId = (int) $this->pdo->lastInsertId();
                $result->addCreated("vocabulary.{$machineName}");
            }

            // Import terms (only if not existing or overwrite)
            if (!empty($terms) && (!$existing || $overwrite)) {
                foreach ($terms as $term) {
                    $tStmt = $this->pdo->prepare('SELECT id FROM terms WHERE vocabulary_id = :vid AND name = :name');
                    $tStmt->execute(['vid' => $vocabId, 'name' => $term['name']]);

                    if (!$tStmt->fetch()) {
                        $this->pdo->prepare(
                            'INSERT INTO terms (vocabulary_id, name, slug, description, weight, created_at, updated_at) VALUES (:vid, :n, :s, :d, :w, :c, :u)'
                        )->execute([
                            'vid' => $vocabId, 'n' => $term['name'], 's' => $term['slug'] ?? '',
                            'd' => $term['description'] ?? '', 'w' => (int) ($term['weight'] ?? 0),
                            'c' => $now, 'u' => $now,
                        ]);
                    }
                }
            }
        }

        return $result;
    }
}
