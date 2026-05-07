<?php

declare(strict_types=1);

namespace App\Cms\Content;

use PDO;

/**
 * ContentRepository — CRUD for content nodes.
 */
final class ContentRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function find(int $id): ?ContentEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM nodes WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new ContentEntity())->hydrate($row) : null;
    }

    public function findOrFail(int $id): ContentEntity
    {
        return $this->find($id) ?? throw new \RuntimeException("Content node #{$id} not found.");
    }

    public function findBySlug(string $slug, string $contentType): ?ContentEntity
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM nodes WHERE slug = :slug AND content_type = :type AND deleted_at IS NULL'
        );
        $stmt->execute(['slug' => $slug, 'type' => $contentType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new ContentEntity())->hydrate($row) : null;
    }

    /**
     * Find a published node by slug across all content types.
     *
     * Used by the dynamic catch-all route when no pattern-based match is found.
     */
    public function findBySlugGlobal(string $slug): ?ContentEntity
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM nodes WHERE slug = :slug AND status = :status AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['slug' => $slug, 'status' => 'published']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new ContentEntity())->hydrate($row) : null;
    }

    /**
     * Find a node by slug regardless of status (any status).
     *
     * Used for legacy URL redirects — the destination route handles auth/preview.
     */
    public function findBySlugAny(string $slug): ?ContentEntity
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM nodes WHERE slug = :slug AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new ContentEntity())->hydrate($row) : null;
    }

    /**
     * @return ContentEntity[]
     */
    public function findByType(
        string $contentType,
        string $status = 'published',
        int $limit = 25,
        int $offset = 0,
        string $orderBy = 'created_at',
        string $direction = 'DESC',
    ): array {
        $allowed = ['created_at', 'updated_at', 'published_at', 'title', 'weight'];
        $orderCol = in_array($orderBy, $allowed, true) ? $orderBy : 'created_at';
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM nodes WHERE content_type = :type AND deleted_at IS NULL";
        $params = ['type' => $contentType];

        if ($status !== 'all') {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY {$orderCol} {$dir} LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn(array $row) => (new ContentEntity())->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function countByType(string $contentType, string $status = 'all'): int
    {
        $sql = "SELECT COUNT(*) FROM nodes WHERE content_type = :type AND deleted_at IS NULL";
        $params = ['type' => $contentType];

        if ($status !== 'all') {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function persist(ContentEntity $entity): ContentEntity
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($entity->id !== null) {
            // Check for slug change — create redirect if slug differs
            $oldSlugStmt = $this->pdo->prepare('SELECT slug FROM nodes WHERE id = :id');
            $oldSlugStmt->execute(['id' => $entity->id]);
            $oldSlug = $oldSlugStmt->fetchColumn();

            if ($oldSlug !== false && $oldSlug !== $entity->slug && $oldSlug !== '') {
                try {
                    $redirectStmt = $this->pdo->prepare(
                        'INSERT INTO redirects (source_path, target_path, status_code, created_at)
                         VALUES (:source, :target, 301, NOW())
                         ON DUPLICATE KEY UPDATE target_path = VALUES(target_path)'
                    );
                    $redirectStmt->execute([
                        'source' => '/' . ltrim($oldSlug, '/'),
                        'target' => '/' . ltrim($entity->slug, '/'),
                    ]);
                } catch (\Throwable) {
                    // Silently ignore if redirects table doesn't exist
                }
            }

            // Update
            $stmt = $this->pdo->prepare(
                'UPDATE nodes SET title = :title, slug = :slug, content_type = :content_type,
                 status = :status, author_id = :author_id, body = :body, summary = :summary,
                 meta_title = :meta_title, meta_description = :meta_description, meta_image = :meta_image,
                 featured_image_id = :featured_image_id, fields = :fields, mosaic_mode = :mosaic_mode,
                 revision = revision + 1, language = :language, weight = :weight,
                 published_at = :published_at, updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $entity->id,
                'title' => $entity->title,
                'slug' => $entity->slug,
                'content_type' => $entity->content_type,
                'status' => $entity->status,
                'author_id' => $entity->author_id,
                'body' => $entity->body,
                'summary' => $entity->summary,
                'meta_title' => $entity->meta_title,
                'meta_description' => $entity->meta_description,
                'meta_image' => $entity->meta_image,
                'featured_image_id' => $entity->featured_image_id,
                'fields' => json_encode($entity->fields),
                'mosaic_mode' => (int) $entity->mosaic_mode,
                'language' => $entity->language,
                'weight' => $entity->weight,
                'published_at' => $entity->published_at?->format('Y-m-d H:i:s'),
                'updated_at' => $now,
            ]);
        } else {
            // Insert
            $stmt = $this->pdo->prepare(
                'INSERT INTO nodes (title, slug, content_type, status, author_id, body, summary,
                 meta_title, meta_description, meta_image, featured_image_id, fields, mosaic_mode,
                 language, weight, published_at, created_at, updated_at)
                 VALUES (:title, :slug, :content_type, :status, :author_id, :body, :summary,
                 :meta_title, :meta_description, :meta_image, :featured_image_id, :fields, :mosaic_mode,
                 :language, :weight, :published_at, :created_at, :updated_at)'
            );
            $stmt->execute([
                'title' => $entity->title,
                'slug' => $entity->slug,
                'content_type' => $entity->content_type,
                'status' => $entity->status,
                'author_id' => $entity->author_id,
                'body' => $entity->body,
                'summary' => $entity->summary,
                'meta_title' => $entity->meta_title,
                'meta_description' => $entity->meta_description,
                'meta_image' => $entity->meta_image,
                'featured_image_id' => $entity->featured_image_id,
                'fields' => json_encode($entity->fields),
                'mosaic_mode' => (int) $entity->mosaic_mode,
                'language' => $entity->language,
                'weight' => $entity->weight,
                'published_at' => $entity->published_at?->format('Y-m-d H:i:s'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $entity->id = (int) $this->pdo->lastInsertId();
        }

        return $entity;
    }

    public function delete(int $id): bool
    {
        // Soft delete
        $stmt = $this->pdo->prepare('UPDATE nodes SET deleted_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function forceDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM nodes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return ContentEntity[]
     */
    public function search(string $query, ?string $contentType = null, int $limit = 25): array
    {
        $sql = "SELECT * FROM nodes WHERE deleted_at IS NULL AND (title LIKE :q OR body LIKE :q)";
        $params = ['q' => "%{$query}%"];

        if ($contentType) {
            $sql .= " AND content_type = :type";
            $params['type'] = $contentType;
        }

        $sql .= " ORDER BY created_at DESC LIMIT " . (int) $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(
            fn(array $row) => (new ContentEntity())->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    // ── Enhanced Methods ────────────────────────────────────────────────

    /**
     * Find a node with its EAV field values loaded.
     */
    public function findWithFields(int $id): ?ContentEntity
    {
        $entity = $this->find($id);
        if ($entity === null) {
            return null;
        }

        $entity->fields = $this->loadFields($entity->id);
        return $entity;
    }

    /**
     * Paginated content listing with type, status, search, author filters and sorting.
     */
    public function paginate(
        ?string $contentType = null,
        string $status = 'all',
        int $page = 1,
        int $perPage = 25,
        string $orderBy = 'updated_at',
        string $direction = 'DESC',
        ?string $search = null,
        ?int $authorId = null,
    ): PaginatedResult {
        $allowed = ['created_at', 'updated_at', 'published_at', 'title', 'weight'];
        $orderCol = in_array($orderBy, $allowed, true) ? $orderBy : 'updated_at';
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        // Build WHERE clause
        $where = 'deleted_at IS NULL';
        $params = [];

        if ($contentType !== null) {
            $where .= ' AND content_type = :type';
            $params['type'] = $contentType;
        }

        if ($status !== 'all') {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }

        if ($search !== null && $search !== '') {
            $where .= ' AND (title LIKE :search1 OR body LIKE :search2)';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }

        if ($authorId !== null) {
            $where .= ' AND author_id = :author_id';
            $params['author_id'] = $authorId;
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM nodes WHERE {$where}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch page
        $offset = ($page - 1) * $perPage;
        $dataSql = "SELECT n.*, cu.name AS author_name FROM nodes n LEFT JOIN cms_users cu ON n.author_id = cu.id WHERE n.{$where} ORDER BY n.{$orderCol} {$dir} LIMIT :limit OFFSET :offset";
        $dataStmt = $this->pdo->prepare($dataSql);

        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $items = array_map(
            fn(array $row) => (new ContentEntity())->hydrate($row),
            $dataStmt->fetchAll(PDO::FETCH_ASSOC),
        );

        return new PaginatedResult($items, $total, $page, $perPage);
    }

    /**
     * Save a content entity with its dynamic field values.
     *
     * @param ContentEntity        $entity     The node to save
     * @param array<string, mixed> $fieldValues  Key-value map of field machine_name => value
     */
    public function save(ContentEntity $entity, array $fieldValues = []): ContentEntity
    {
        // Persist the node itself
        $entity = $this->persist($entity);

        // Persist EAV field values
        if (!empty($fieldValues) && $entity->id !== null) {
            $this->saveFields($entity->id, $fieldValues, $entity->language);
        }

        return $entity;
    }

    /**
     * Update only the status of a node.
     */
    public function updateStatus(int $id, ContentStatus $status): bool
    {
        $params = ['id' => $id, 'status' => $status->value];
        $publishedAt = null;

        if ($status === ContentStatus::PUBLISHED) {
            $publishedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE nodes SET status = :status, published_at = :published_at, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'id'           => $id,
            'status'       => $status->value,
            'published_at' => $publishedAt,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Bulk soft-delete multiple nodes.
     *
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("UPDATE nodes SET deleted_at = NOW() WHERE id IN ({$placeholders})");
        $stmt->execute(array_values($ids));

        return $stmt->rowCount();
    }

    /**
     * Bulk update status for multiple nodes.
     *
     * @param list<int> $ids
     */
    public function bulkUpdateStatus(array $ids, ContentStatus $status): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $this->pdo->prepare(
            "UPDATE nodes SET status = ?, updated_at = NOW() WHERE id IN ({$placeholders})"
        );

        // Status first (matches the first ?), then IDs
        $stmt->execute([$status->value, ...array_values($ids)]);

        return $stmt->rowCount();
    }

    // ── Field Value Helpers ─────────────────────────────────────────────

    /**
     * Load EAV field values for a node.
     *
     * @return array<string, mixed>
     */
    private function loadFields(int $nodeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT field_name, field_value, delta FROM node_fields WHERE node_id = :id ORDER BY field_name, delta'
        );
        $stmt->execute(['id' => $nodeId]);

        $fields = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = $row['field_name'];
            if (isset($fields[$name]) && !is_array($fields[$name])) {
                // Convert to array for multi-value fields
                $fields[$name] = [$fields[$name]];
            }
            if (isset($fields[$name]) && is_array($fields[$name])) {
                $fields[$name][] = $row['field_value'];
            } else {
                $fields[$name] = $row['field_value'];
            }
        }

        return $fields;
    }

    /**
     * Save EAV field values for a node (delete + re-insert).
     *
     * @param array<string, mixed> $fieldValues
     */
    private function saveFields(int $nodeId, array $fieldValues, string $language = 'en'): void
    {
        // Clear existing
        $this->pdo->prepare('DELETE FROM node_fields WHERE node_id = :id')
            ->execute(['id' => $nodeId]);

        // Re-insert
        $stmt = $this->pdo->prepare(
            'INSERT INTO node_fields (node_id, field_name, field_value, delta, language)
             VALUES (:node_id, :field_name, :field_value, :delta, :language)'
        );

        foreach ($fieldValues as $fieldName => $value) {
            if (is_array($value)) {
                foreach ($value as $delta => $v) {
                    $stmt->execute([
                        'node_id'     => $nodeId,
                        'field_name'  => $fieldName,
                        'field_value' => is_scalar($v) ? (string) $v : json_encode($v),
                        'delta'       => $delta,
                        'language'    => $language,
                    ]);
                }
            } else {
                $stmt->execute([
                    'node_id'     => $nodeId,
                    'field_name'  => $fieldName,
                    'field_value' => is_scalar($value) ? (string) $value : json_encode($value),
                    'delta'       => 0,
                    'language'    => $language,
                ]);
            }
        }
    }
}

