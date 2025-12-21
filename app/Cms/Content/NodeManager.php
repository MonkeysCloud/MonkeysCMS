<?php

declare(strict_types=1);

namespace App\Cms\Content;

use App\Cms\Entity\EntityManager;
use App\Cms\Entity\EntityQuery;
use App\Cms\Fields\Storage\FieldValueStorage;
use App\Cms\Fields\Storage\FieldValueStorageInterface;

/**
 * NodeManager - Content operations manager
 *
 * Provides high-level operations for managing content nodes:
 * - CRUD with field value integration
 * - Revision management
 * - Publishing workflow
 * - Content querying
 *
 * Usage:
 * ```php
 * $manager = new NodeManager($entityManager, $fieldStorage);
 *
 * // Create node with fields
 * $node = $manager->create('article', [
 *     'title' => 'Hello World',
 *     'body' => 'Content here...',
 *     'tags' => ['news', 'featured'],
 * ], $userId);
 *
 * // Publish
 * $manager->publish($node);
 *
 * // Query
 * $articles = $manager->findPublished('article', 10);
 * ```
 */
class NodeManager
{
    private EntityManager $em;
    private ?FieldValueStorageInterface $fieldStorage;
    private bool $createRevisions = true;

    public function __construct(
        EntityManager $em,
        ?FieldValueStorageInterface $fieldStorage = null
    ) {
        $this->em = $em;
        $this->fieldStorage = $fieldStorage;
    }

    // =========================================================================
    // CRUD Operations
    // =========================================================================

    /**
     * Create a new node
     *
     * @param string $type Content type machine name
     * @param array<string, mixed> $data Node data including field values
     * @param int|null $authorId Author user ID
     */
    public function create(string $type, array $data = [], ?int $authorId = null): Node
    {
        // Separate core fields from custom fields
        $coreFields = ['title', 'slug', 'status', 'author_id', 'published_at'];
        $nodeData = [];
        $fieldData = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $coreFields)) {
                $nodeData[$key] = $value;
            } else {
                $fieldData[$key] = $value;
            }
        }

        // Create node
        $node = new Node($nodeData);
        $node->setType($type);
        $node->setAuthorId($authorId ?? ($nodeData['author_id'] ?? null));

        // Generate slug if not provided
        if (empty($node->getSlug()) && !empty($node->getTitle())) {
            $node->setSlug($this->generateUniqueSlug($node));
        }

        // Save node
        $this->em->save($node);

        // Save field values
        if (!empty($fieldData) && $this->fieldStorage) {
            $this->saveFieldValues($node, $fieldData);
        }

        // Store fields on node for return
        $node->setFields($fieldData);

        return $node;
    }

    /**
     * Update a node
     *
     * @param Node $node Node to update
     * @param array<string, mixed> $data Updated data
     * @param string|null $revisionMessage Revision log message
     */
    public function update(Node $node, array $data, ?string $revisionMessage = null): Node
    {
        // Create revision before update
        if ($this->createRevisions) {
            $this->createRevision($node, $revisionMessage);
        }

        // Separate core fields from custom fields
        $coreFields = ['title', 'slug', 'status', 'author_id', 'published_at'];
        $fieldData = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $coreFields)) {
                $node->$key = $value;
            } else {
                $fieldData[$key] = $value;
            }
        }

        // Update slug if title changed
        if (isset($data['title']) && empty($data['slug'])) {
            $node->setSlug($this->generateUniqueSlug($node));
        }

        // Save node
        $this->em->save($node);

        // Update field values
        if (!empty($fieldData) && $this->fieldStorage) {
            $this->saveFieldValues($node, $fieldData);
        }

        // Update fields on node
        $node->setFields(array_merge($node->getFields(), $fieldData));

        return $node;
    }

    /**
     * Delete a node (soft delete)
     */
    public function delete(Node $node): void
    {
        $this->em->delete($node);
    }

    /**
     * Force delete a node (permanent)
     */
    public function forceDelete(Node $node): void
    {
        // Delete field values first
        if ($this->fieldStorage) {
            $this->fieldStorage->deleteEntityValues('node', $node->getId());
        }

        // Delete revisions
        $this->em->deleteBy(NodeRevision::class, ['node_id' => $node->getId()]);

        // Delete node
        $this->em->forceDelete($node);
    }

    /**
     * Restore a soft-deleted node
     */
    public function restore(Node $node): void
    {
        $this->em->restore($node);
    }

    // =========================================================================
    // Finding Nodes
    // =========================================================================

    /**
     * Find node by ID
     */
    public function find(int $id): ?Node
    {
        /** @var Node|null $node */
        $node = $this->em->find(Node::class, $id);

        if ($node && $this->fieldStorage) {
            $this->loadFieldValues($node);
        }

        return $node;
    }

    /**
     * Find node by ID or throw
     *
     * @throws \App\Cms\Entity\EntityNotFoundException
     */
    public function findOrFail(int $id): Node
    {
        /** @var Node $node */
        $node = $this->em->findOrFail(Node::class, $id);

        if ($this->fieldStorage) {
            $this->loadFieldValues($node);
        }

        return $node;
    }

    /**
     * Find node by slug
     */
    public function findBySlug(string $slug): ?Node
    {
        /** @var Node|null $node */
        $node = $this->em->findOneBy(Node::class, ['slug' => $slug]);

        if ($node && $this->fieldStorage) {
            $this->loadFieldValues($node);
        }

        return $node;
    }

    /**
     * Find published nodes
     *
     * @param string|null $type Content type filter
     * @param int|null $limit Result limit
     * @return Node[]
     */
    public function findPublished(?string $type = null, ?int $limit = null): array
    {
        $query = $this->query()
            ->where('status', NodeStatus::PUBLISHED)
            ->orderBy('published_at', 'DESC');

        if ($type) {
            $query->where('type', $type);
        }

        if ($limit) {
            $query->limit($limit);
        }

        $nodes = $query->get();

        return $this->loadFieldValuesForMany($nodes);
    }

    /**
     * Find nodes by type
     *
     * @return Node[]
     */
    public function findByType(string $type, ?int $limit = null): array
    {
        $query = $this->query()
            ->where('type', $type)
            ->orderBy('created_at', 'DESC');

        if ($limit) {
            $query->limit($limit);
        }

        $nodes = $query->get();

        return $this->loadFieldValuesForMany($nodes);
    }

    /**
     * Find nodes by author
     *
     * @return Node[]
     */
    public function findByAuthor(int $authorId, ?int $limit = null): array
    {
        $query = $this->query()
            ->where('author_id', $authorId)
            ->orderBy('created_at', 'DESC');

        if ($limit) {
            $query->limit($limit);
        }

        $nodes = $query->get();

        return $this->loadFieldValuesForMany($nodes);
    }

    /**
     * Search nodes
     */
    public function search(string $term, ?string $type = null, int $limit = 20): array
    {
        $query = $this->query()
            ->whereLike('title', "%{$term}%")
            ->where('status', NodeStatus::PUBLISHED)
            ->orderBy('published_at', 'DESC')
            ->limit($limit);

        if ($type) {
            $query->where('type', $type);
        }

        $nodes = $query->get();

        return $this->loadFieldValuesForMany($nodes);
    }

    /**
     * Get paginated nodes
     *
     * @return array{data: Node[], total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 15,
        ?string $type = null,
        ?string $status = null,
        ?string $orderBy = 'created_at',
        string $orderDir = 'DESC'
    ): array {
        $query = $this->query()->orderBy($orderBy, $orderDir);

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $result = $query->paginate($perPage, $page);
        $result['data'] = $this->loadFieldValuesForMany($result['data']);

        return $result;
    }

    /**
     * Create a query builder
     */
    public function query(): EntityQuery
    {
        return $this->em->query(Node::class);
    }

    // =========================================================================
    // Publishing Workflow
    // =========================================================================

    /**
     * Publish a node
     */
    public function publish(Node $node): Node
    {
        if ($this->createRevisions) {
            $this->createRevision($node, 'Published');
        }

        $node->publish();
        $this->em->save($node);

        return $node;
    }

    /**
     * Unpublish a node
     */
    public function unpublish(Node $node): Node
    {
        if ($this->createRevisions) {
            $this->createRevision($node, 'Unpublished');
        }

        $node->unpublish();
        $this->em->save($node);

        return $node;
    }

    /**
     * Archive a node
     */
    public function archive(Node $node): Node
    {
        if ($this->createRevisions) {
            $this->createRevision($node, 'Archived');
        }

        $node->archive();
        $this->em->save($node);

        return $node;
    }

    /**
     * Schedule a node for publishing
     */
    public function schedule(Node $node, \DateTimeImmutable $publishAt): Node
    {
        $node->setStatus(NodeStatus::SCHEDULED);
        $node->setPublishedAt($publishAt);
        $this->em->save($node);

        return $node;
    }

    /**
     * Process scheduled nodes (call from cron)
     */
    public function processScheduled(): int
    {
        $now = new \DateTimeImmutable();

        $nodes = $this->query()
            ->where('status', NodeStatus::SCHEDULED)
            ->where('published_at', '<=', $now->format('Y-m-d H:i:s'))
            ->get();

        foreach ($nodes as $node) {
            $node->setStatus(NodeStatus::PUBLISHED);
            $this->em->save($node);
        }

        return count($nodes);
    }

    // =========================================================================
    // Revisions
    // =========================================================================

    /**
     * Create a revision of the current node state
     */
    public function createRevision(Node $node, ?string $message = null): NodeRevision
    {
        // Load field values if not loaded
        if (empty($node->getFields()) && $this->fieldStorage) {
            $this->loadFieldValues($node);
        }

        $revision = NodeRevision::fromNode($node, $message);
        $this->em->save($revision);

        return $revision;
    }

    /**
     * Get revisions for a node
     *
     * @return NodeRevision[]
     */
    public function getRevisions(Node $node): array
    {
        return $this->em->query(NodeRevision::class)
            ->where('node_id', $node->getId())
            ->orderBy('revision_id', 'DESC')
            ->get();
    }

    /**
     * Get a specific revision
     */
    public function getRevision(Node $node, int $revisionId): ?NodeRevision
    {
        return $this->em->findOneBy(NodeRevision::class, [
            'node_id' => $node->getId(),
            'revision_id' => $revisionId,
        ]);
    }

    /**
     * Restore node from revision
     */
    public function restoreRevision(Node $node, int $revisionId): Node
    {
        $revision = $this->getRevision($node, $revisionId);

        if (!$revision) {
            throw new \InvalidArgumentException("Revision {$revisionId} not found");
        }

        // Create revision of current state before restore
        $this->createRevision($node, "Before restore to revision {$revisionId}");

        // Restore data
        $data = $revision->getData();

        $node->setTitle($data['title'] ?? $node->getTitle());
        $node->setSlug($data['slug'] ?? $node->getSlug());
        // Don't restore status - keep current

        $this->em->save($node);

        // Restore field values
        if ($this->fieldStorage && isset($data['fields'])) {
            $this->saveFieldValues($node, $data['fields']);
            $node->setFields($data['fields']);
        }

        return $node;
    }

    /**
     * Enable/disable revision creation
     */
    public function setCreateRevisions(bool $create): void
    {
        $this->createRevisions = $create;
    }

    // =========================================================================
    // Field Values
    // =========================================================================

    /**
     * Load field values for a node
     */
    private function loadFieldValues(Node $node): void
    {
        if (!$this->fieldStorage || !$node->getId()) {
            return;
        }

        $values = $this->fieldStorage->getEntityValues('node', $node->getId());
        $node->setFields($values);
    }

    /**
     * Load field values for multiple nodes
     *
     * @param Node[] $nodes
     * @return Node[]
     */
    private function loadFieldValuesForMany(array $nodes): array
    {
        if (!$this->fieldStorage) {
            return $nodes;
        }

        foreach ($nodes as $node) {
            $this->loadFieldValues($node);
        }

        return $nodes;
    }

    /**
     * Save field values for a node
     *
     * @param array<string, mixed> $fields
     */
    private function saveFieldValues(Node $node, array $fields): void
    {
        if (!$this->fieldStorage || !$node->getId()) {
            return;
        }

        // For now, store all fields as JSON in a single field
        // In production, this should map to field definitions
        foreach ($fields as $fieldName => $value) {
            // Get field ID from field definition (simplified)
            $fieldId = $this->getFieldId($fieldName);
            if ($fieldId) {
                $this->fieldStorage->setValue($fieldId, 'node', $node->getId(), $value);
            }
        }
    }

    /**
     * Get field ID by name (simplified - should use FieldRepository)
     */
    private function getFieldId(string $fieldName): ?int
    {
        // This is a simplified implementation
        // In production, query the field_definitions table
        static $fieldMap = [];

        if (isset($fieldMap[$fieldName])) {
            return $fieldMap[$fieldName];
        }

        // Query database for field ID
        $stmt = $this->em->getConnection()->prepare(
            "SELECT id FROM field_definitions WHERE machine_name = :name LIMIT 1"
        );
        $stmt->execute(['name' => $fieldName]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $fieldMap[$fieldName] = $row ? (int) $row['id'] : null;

        return $fieldMap[$fieldName];
    }

    // =========================================================================
    // Slug Generation
    // =========================================================================

    /**
     * Generate a unique slug for a node
     */
    public function generateUniqueSlug(Node $node): string
    {
        $baseSlug = $node->generateSlug();
        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug, $node->getId())) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     */
    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = $this->query()->where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    // =========================================================================
    // Statistics
    // =========================================================================

    /**
     * Get content statistics
     *
     * @return array<string, int>
     */
    public function getStatistics(): array
    {
        $connection = $this->em->getConnection();

        // Total by status
        $stmt = $connection->query(
            "SELECT status, COUNT(*) as count FROM nodes WHERE deleted_at IS NULL GROUP BY status"
        );
        $statusCounts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $statusCounts[$row['status']] = (int) $row['count'];
        }

        // Total by type
        $stmt = $connection->query(
            "SELECT type, COUNT(*) as count FROM nodes WHERE deleted_at IS NULL GROUP BY type"
        );
        $typeCounts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $typeCounts[$row['type']] = (int) $row['count'];
        }

        return [
            'total' => array_sum($statusCounts),
            'published' => $statusCounts[NodeStatus::PUBLISHED] ?? 0,
            'draft' => $statusCounts[NodeStatus::DRAFT] ?? 0,
            'archived' => $statusCounts[NodeStatus::ARCHIVED] ?? 0,
            'by_type' => $typeCounts,
        ];
    }

    // =========================================================================
    // Utilities
    // =========================================================================

    /**
     * Get the entity manager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->em;
    }

    /**
     * Get the field storage
     */
    public function getFieldStorage(): ?FieldValueStorageInterface
    {
        return $this->fieldStorage;
    }

    /**
     * Clone a node
     */
    public function duplicate(Node $node, ?string $newTitle = null): Node
    {
        // Load field values if not loaded
        if (empty($node->getFields()) && $this->fieldStorage) {
            $this->loadFieldValues($node);
        }

        $data = $node->toArray();
        unset($data['id']);

        $data['title'] = $newTitle ?? $data['title'] . ' (Copy)';
        $data['slug'] = null; // Will be regenerated
        $data['status'] = NodeStatus::DRAFT;
        $data['published_at'] = null;

        return $this->create($node->getType(), array_merge($data, $node->getFields()));
    }
}
