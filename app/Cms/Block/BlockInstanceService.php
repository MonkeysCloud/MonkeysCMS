<?php

declare(strict_types=1);

namespace App\Cms\Block;

use PDO;

/**
 * BlockInstanceService — CRUD for pre-filled, reusable block instances.
 *
 * Block instances are concrete, data-filled blocks that can be placed
 * multiple times across Mosaic layouts. For example, a "Footer CTA"
 * instance using the "Call to Action" block type, pre-filled with
 * specific heading, body text, and button configuration.
 *
 * Each instance tracks its usage count so editors know which instances
 * are actively deployed in layouts.
 */
final class BlockInstanceService
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Listing ────────────────────────────────────────────────────────

    /**
     * Get all block instances, optionally filtered.
     *
     * @return list<array>
     */
    public function getAll(?string $blockType = null, ?string $status = null, int $limit = 100, int $offset = 0): array
    {
        try {
            $where = [];
            $params = [];

            if ($blockType) {
                $where[] = 'block_type = :block_type';
                $params['block_type'] = $blockType;
            }

            if ($status) {
                $where[] = 'status = :status';
                $params['status'] = $status;
            }

            $sql = 'SELECT * FROM block_instances';
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY label ASC LIMIT :limit OFFSET :offset';

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return array_map([$this, 'hydrateRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Count block instances, optionally filtered.
     */
    public function count(?string $blockType = null, ?string $status = null): int
    {
        try {
            $where = [];
            $params = [];

            if ($blockType) {
                $where[] = 'block_type = :block_type';
                $params['block_type'] = $blockType;
            }

            if ($status) {
                $where[] = 'status = :status';
                $params['status'] = $status;
            }

            $sql = 'SELECT COUNT(*) FROM block_instances';
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    // ── CRUD ───────────────────────────────────────────────────────────

    /**
     * Get a single block instance by ID.
     */
    public function get(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM block_instances WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateRow($row) : null;
    }

    /**
     * Get or throw.
     */
    public function getOrFail(int $id): array
    {
        return $this->get($id) ?? throw new \RuntimeException("Block instance #{$id} not found.");
    }

    /**
     * Create a new block instance.
     */
    public function create(array $data): array
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO block_instances (block_type, label, description, data, settings, status, author_id, usage_count, created_at, updated_at)
             VALUES (:block_type, :label, :description, :data, :settings, :status, :author_id, 0, :created_at, :updated_at)'
        );

        $stmt->execute([
            'block_type'  => $data['block_type'] ?? throw new \InvalidArgumentException('block_type required'),
            'label'       => $data['label'] ?? throw new \InvalidArgumentException('label required'),
            'description' => $data['description'] ?? null,
            'data'        => json_encode($data['data'] ?? []),
            'settings'    => json_encode($data['settings'] ?? []),
            'status'      => $data['status'] ?? 'published',
            'author_id'   => $data['author_id'] ?? null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return $this->getOrFail((int) $this->pdo->lastInsertId());
    }

    /**
     * Update a block instance.
     */
    public function update(int $id, array $data): array
    {
        $existing = $this->getOrFail($id);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'UPDATE block_instances SET
                label = :label, description = :description, data = :data,
                settings = :settings, status = :status, updated_at = :updated_at
             WHERE id = :id'
        );

        $stmt->execute([
            'id'          => $id,
            'label'       => $data['label'] ?? $existing['label'],
            'description' => $data['description'] ?? $existing['description'],
            'data'        => json_encode($data['data'] ?? $existing['data']),
            'settings'    => json_encode($data['settings'] ?? $existing['settings']),
            'status'      => $data['status'] ?? $existing['status'],
            'updated_at'  => $now,
        ]);

        return $this->getOrFail($id);
    }

    /**
     * Delete a block instance.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM block_instances WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    // ── Usage Tracking ─────────────────────────────────────────────────

    /**
     * Increment the usage counter when an instance is placed in a layout.
     */
    public function incrementUsage(int $id): void
    {
        $this->pdo->prepare('UPDATE block_instances SET usage_count = usage_count + 1 WHERE id = :id')
            ->execute(['id' => $id]);
    }

    /**
     * Decrement the usage counter when an instance is removed from a layout.
     */
    public function decrementUsage(int $id): void
    {
        $this->pdo->prepare('UPDATE block_instances SET usage_count = GREATEST(0, usage_count - 1) WHERE id = :id')
            ->execute(['id' => $id]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Get all block types that have instances.
     *
     * @return list<array{block_type: string, count: int}>
     */
    public function getTypeBreakdown(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT block_type, COUNT(*) as count FROM block_instances GROUP BY block_type ORDER BY count DESC'
            );

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Hydrate a DB row into a typed array.
     */
    private function hydrateRow(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'block_type'  => $row['block_type'],
            'label'       => $row['label'],
            'description' => $row['description'] ?? null,
            'data'        => json_decode($row['data'] ?? '{}', true),
            'settings'    => json_decode($row['settings'] ?? '{}', true),
            'status'      => $row['status'],
            'language'    => $row['language'] ?? 'en',
            'author_id'   => $row['author_id'] ? (int) $row['author_id'] : null,
            'usage_count' => (int) ($row['usage_count'] ?? 0),
            'created_at'  => $row['created_at'],
            'updated_at'  => $row['updated_at'],
        ];
    }

    /**
     * Get block instances filtered by language.
     *
     * @return list<array>
     */
    public function getForLanguage(string $lang, ?string $blockType = null): array
    {
        $where = ['language = :lang'];
        $params = ['lang' => $lang];

        if ($blockType) {
            $where[] = 'block_type = :block_type';
            $params['block_type'] = $blockType;
        }

        $sql = 'SELECT * FROM block_instances WHERE ' . implode(' AND ', $where) . ' ORDER BY label ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'hydrateRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
