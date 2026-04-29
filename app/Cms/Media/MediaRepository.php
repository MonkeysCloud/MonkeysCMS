<?php

declare(strict_types=1);

namespace App\Cms\Media;

use PDO;

/**
 * MediaRepository — CRUD for the media library.
 */
final class MediaRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function find(int $id): ?MediaEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM media WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new MediaEntity())->hydrate($row) : null;
    }

    public function findOrFail(int $id): MediaEntity
    {
        return $this->find($id) ?? throw new \RuntimeException("Media #{$id} not found.");
    }

    /**
     * @return MediaEntity[]
     */
    public function findAll(
        ?string $type = null,
        int $limit = 50,
        int $offset = 0,
        string $orderBy = 'created_at',
        string $direction = 'DESC',
    ): array {
        $sql = 'SELECT * FROM media WHERE 1=1';
        $params = [];

        if ($type) {
            $sql .= " AND mime_type LIKE :type";
            $params['type'] = "{$type}/%";
        }

        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$dir} LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn(array $row) => (new MediaEntity())->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function persist(MediaEntity $entity): MediaEntity
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($entity->id !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE media SET filename = :filename, original_name = :original_name,
                 mime_type = :mime_type, path = :path, url = :url, alt = :alt, title = :title,
                 description = :description, size = :size, width = :width, height = :height,
                 metadata = :metadata, updated_at = :updated_at WHERE id = :id'
            );
            $stmt->execute([
                'id' => $entity->id,
                'filename' => $entity->filename,
                'original_name' => $entity->original_name,
                'mime_type' => $entity->mime_type,
                'path' => $entity->path,
                'url' => $entity->url,
                'alt' => $entity->alt,
                'title' => $entity->title,
                'description' => $entity->description,
                'size' => $entity->size,
                'width' => $entity->width,
                'height' => $entity->height,
                'metadata' => json_encode($entity->metadata),
                'updated_at' => $now,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO media (filename, original_name, mime_type, path, url, alt, title,
                 description, size, width, height, metadata, uploaded_by, created_at, updated_at)
                 VALUES (:filename, :original_name, :mime_type, :path, :url, :alt, :title,
                 :description, :size, :width, :height, :metadata, :uploaded_by, :created_at, :updated_at)'
            );
            $stmt->execute([
                'filename' => $entity->filename,
                'original_name' => $entity->original_name,
                'mime_type' => $entity->mime_type,
                'path' => $entity->path,
                'url' => $entity->url,
                'alt' => $entity->alt,
                'title' => $entity->title,
                'description' => $entity->description,
                'size' => $entity->size,
                'width' => $entity->width,
                'height' => $entity->height,
                'metadata' => json_encode($entity->metadata),
                'uploaded_by' => $entity->uploaded_by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $entity->id = (int) $this->pdo->lastInsertId();
        }

        return $entity;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM media WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function count(?string $type = null): int
    {
        $sql = 'SELECT COUNT(*) FROM media WHERE 1=1';
        $params = [];

        if ($type) {
            $sql .= " AND mime_type LIKE :type";
            $params['type'] = "{$type}/%";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
