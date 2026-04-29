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
}
