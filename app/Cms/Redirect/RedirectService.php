<?php

declare(strict_types=1);

namespace App\Cms\Redirect;

use PDO;

/**
 * RedirectService — CRUD for URL redirects.
 *
 * Manages 301/302 redirects, auto-creates redirects when slugs change,
 * and tracks hit counts for analytics.
 */
final class RedirectService
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * Find a redirect by source path.
     *
     * @return array{id: int, source_path: string, target_path: string, status_code: int}|null
     */
    public function findBySource(string $sourcePath): ?array
    {
        $sourcePath = '/' . ltrim($sourcePath, '/');

        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM redirects WHERE source_path = :source LIMIT 1'
            );
            $stmt->execute(['source' => $sourcePath]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get all redirects, paginated.
     *
     * @return list<array>
     */
    public function getAll(int $limit = 50, int $offset = 0, ?string $search = null): array
    {
        try {
            $sql = 'SELECT * FROM redirects';
            $params = [];

            if ($search !== null && $search !== '') {
                $sql .= ' WHERE source_path LIKE :q OR target_path LIKE :q';
                $params['q'] = "%{$search}%";
            }

            $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Count total redirects.
     */
    public function count(?string $search = null): int
    {
        try {
            $sql = 'SELECT COUNT(*) FROM redirects';
            $params = [];

            if ($search !== null && $search !== '') {
                $sql .= ' WHERE source_path LIKE :q OR target_path LIKE :q';
                $params['q'] = "%{$search}%";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Create a new redirect.
     */
    public function create(string $sourcePath, string $targetPath, int $statusCode = 301): void
    {
        $sourcePath = '/' . ltrim($sourcePath, '/');
        $targetPath = '/' . ltrim($targetPath, '/');

        // Prevent circular redirects
        if ($sourcePath === $targetPath) {
            return;
        }

        // Check if source already exists — update target instead
        $existing = $this->findBySource($sourcePath);
        if ($existing) {
            $this->update($existing['id'], $targetPath, $statusCode);
            return;
        }

        $this->pdo->prepare(
            'INSERT INTO redirects (source_path, target_path, status_code, created_at) VALUES (:source, :target, :code, NOW())'
        )->execute([
            'source' => $sourcePath,
            'target' => $targetPath,
            'code'   => $statusCode,
        ]);
    }

    /**
     * Update an existing redirect.
     */
    public function update(int $id, string $targetPath, int $statusCode = 301): void
    {
        $this->pdo->prepare(
            'UPDATE redirects SET target_path = :target, status_code = :code WHERE id = :id'
        )->execute([
            'id'     => $id,
            'target' => '/' . ltrim($targetPath, '/'),
            'code'   => $statusCode,
        ]);
    }

    /**
     * Delete a redirect.
     */
    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM redirects WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Increment the hit counter for a redirect.
     */
    public function recordHit(int $id): void
    {
        $this->pdo->prepare(
            'UPDATE redirects SET hits = hits + 1, last_hit_at = NOW() WHERE id = :id'
        )->execute(['id' => $id]);
    }
}
