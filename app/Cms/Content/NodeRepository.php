<?php

declare(strict_types=1);

namespace App\Cms\Content;

use App\Cms\Entity\EntityManager;
use App\Cms\Entity\EntityQuery;
use App\Cms\Entity\ScopedRepository;

/**
 * NodeRepository - Specialized repository for content nodes
 *
 * Provides common queries and scopes for nodes.
 *
 * @extends ScopedRepository<Node>
 */
class NodeRepository extends ScopedRepository
{
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, Node::class);
    }

    /**
     * Define query scopes
     *
     * @return array<string, callable(EntityQuery): EntityQuery>
     */
    protected function scopes(): array
    {
        return [
            'published' => fn(EntityQuery $q) => $q
                ->where('status', NodeStatus::PUBLISHED)
                ->whereNotNull('published_at'),

            'draft' => fn(EntityQuery $q) => $q
                ->where('status', NodeStatus::DRAFT),

            'archived' => fn(EntityQuery $q) => $q
                ->where('status', NodeStatus::ARCHIVED),

            'scheduled' => fn(EntityQuery $q) => $q
                ->where('status', NodeStatus::SCHEDULED),

            'recent' => fn(EntityQuery $q) => $q
                ->orderBy('created_at', 'DESC')
                ->limit(10),

            'popular' => fn(EntityQuery $q) => $q
                ->where('status', NodeStatus::PUBLISHED)
                ->orderBy('views', 'DESC')
                ->limit(10),
        ];
    }

    // =========================================================================
    // Custom Queries
    // =========================================================================

    /**
     * Find published nodes
     *
     * @return Node[]
     */
    public function findPublished(int $limit = 10): array
    {
        return $this->withScope('published')
            ->orderBy('published_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Find by type
     *
     * @return Node[]
     */
    public function findByType(string $type, ?string $status = null): array
    {
        $query = $this->createQuery()->where('type', $type);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'DESC')->get();
    }

    /**
     * Find by author
     *
     * @return Node[]
     */
    public function findByAuthor(int $authorId, ?string $status = null): array
    {
        $query = $this->createQuery()->where('author_id', $authorId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'DESC')->get();
    }

    /**
     * Find by slug
     */
    public function findBySlug(string $slug): ?Node
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find scheduled nodes ready to publish
     *
     * @return Node[]
     */
    public function findDueForPublishing(): array
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        return $this->createQuery()
            ->where('status', NodeStatus::SCHEDULED)
            ->where('published_at', '<=', $now)
            ->get();
    }

    /**
     * Search nodes
     *
     * @return Node[]
     */
    public function search(string $term, array $options = []): array
    {
        $query = $this->createQuery()
            ->whereLike('title', "%{$term}%");

        if (isset($options['type'])) {
            $query->where('type', $options['type']);
        }

        if (isset($options['status'])) {
            $query->where('status', $options['status']);
        } else {
            $query->where('status', NodeStatus::PUBLISHED);
        }

        $limit = $options['limit'] ?? 20;
        $query->orderBy('published_at', 'DESC')->limit($limit);

        return $query->get();
    }

    /**
     * Get recent nodes
     *
     * @return Node[]
     */
    public function getRecent(int $limit = 10, ?string $type = null): array
    {
        $query = $this->createQuery()
            ->orderBy('created_at', 'DESC')
            ->limit($limit);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    /**
     * Get nodes by multiple types
     *
     * @param string[] $types
     * @return Node[]
     */
    public function findByTypes(array $types, int $limit = 20): array
    {
        return $this->createQuery()
            ->whereIn('type', $types)
            ->where('status', NodeStatus::PUBLISHED)
            ->orderBy('published_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get nodes in date range
     *
     * @return Node[]
     */
    public function findInDateRange(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?string $type = null
    ): array {
        $query = $this->createQuery()
            ->whereBetween(
                'created_at',
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s')
            )
            ->orderBy('created_at', 'DESC');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    /**
     * Get content type counts
     *
     * @return array<string, int>
     */
    public function getTypeCounts(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $table = $this->getTableName();

        $stmt = $connection->query(
            "SELECT type, COUNT(*) as count 
             FROM {$table} 
             WHERE deleted_at IS NULL 
             GROUP BY type"
        );

        $counts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $counts[$row['type']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get status counts
     *
     * @return array<string, int>
     */
    public function getStatusCounts(?string $type = null): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $table = $this->getTableName();

        $sql = "SELECT status, COUNT(*) as count 
                FROM {$table} 
                WHERE deleted_at IS NULL";

        if ($type) {
            $sql .= " AND type = :type";
        }

        $sql .= " GROUP BY status";

        $stmt = $connection->prepare($sql);
        $stmt->execute($type ? ['type' => $type] : []);

        $counts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get nodes by tag (via taxonomy)
     *
     * @return Node[]
     */
    public function findByTag(string $tag, int $limit = 20): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $sql = "SELECT n.* FROM nodes n
                INNER JOIN entity_terms et ON et.entity_type = 'node' AND et.entity_id = n.id
                INNER JOIN terms t ON t.id = et.term_id
                WHERE t.name = :tag AND n.status = 'published' AND n.deleted_at IS NULL
                ORDER BY n.published_at DESC
                LIMIT :limit";

        $stmt = $connection->prepare($sql);
        $stmt->bindValue('tag', $tag);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $nodes = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $nodes[] = Node::fromDatabase($row);
        }

        return $nodes;
    }

    /**
     * Check if slug is unique
     */
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool
    {
        $query = $this->createQuery()->where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    /**
     * Generate unique slug
     */
    public function generateUniqueSlug(string $baseSlug, ?int $excludeId = null): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while (!$this->isSlugUnique($slug, $excludeId)) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
