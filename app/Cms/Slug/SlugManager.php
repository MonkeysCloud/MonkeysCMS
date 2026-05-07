<?php

declare(strict_types=1);

namespace App\Cms\Slug;

use App\Cms\Content\ContentEntity;
use App\Cms\Content\ContentRepository;
use App\Cms\Taxonomy\TermEntity;
use MonkeysLegion\DI\Attributes\Singleton;

/**
 * SlugManager — Core service for URL alias pattern management.
 *
 * Handles CRUD for slug patterns, slug generation with token
 * replacement, uniqueness enforcement, and bulk regeneration.
 */
#[Singleton]
final class SlugManager
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly SlugTokenizer $tokenizer,
        private readonly ContentRepository $contentRepo,
    ) {}

    // ── Pattern CRUD ────────────────────────────────────────────────

    /**
     * Get the slug pattern for an entity type + bundle.
     */
    public function getPattern(string $entityType, string $bundle): ?SlugPattern
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM slug_patterns WHERE entity_type = :et AND bundle = :b LIMIT 1'
        );
        $stmt->execute(['et' => $entityType, 'b' => $bundle]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? SlugPattern::fromRow($row) : null;
    }

    /**
     * Get all patterns, optionally filtered by entity type.
     *
     * @return list<SlugPattern>
     */
    public function getAllPatterns(?string $entityType = null): array
    {
        if ($entityType) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM slug_patterns WHERE entity_type = :et ORDER BY weight, bundle'
            );
            $stmt->execute(['et' => $entityType]);
        } else {
            $stmt = $this->pdo->query('SELECT * FROM slug_patterns ORDER BY entity_type, weight, bundle');
        }

        return array_map(
            static fn(array $row) => SlugPattern::fromRow($row),
            $stmt->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    /**
     * Save or update a slug pattern for a specific entity type + bundle.
     */
    public function savePattern(string $entityType, string $bundle, string $pattern): SlugPattern
    {
        $existing = $this->getPattern($entityType, $bundle);

        if ($existing) {
            $this->pdo->prepare(
                'UPDATE slug_patterns SET pattern = :p, updated_at = NOW() WHERE id = :id'
            )->execute(['p' => $pattern, 'id' => $existing->id]);

            $existing->pattern = $pattern;
            return $existing;
        }

        $this->pdo->prepare(
            'INSERT INTO slug_patterns (entity_type, bundle, pattern) VALUES (:et, :b, :p)'
        )->execute(['et' => $entityType, 'b' => $bundle, 'p' => $pattern]);

        return new SlugPattern(
            id: (int) $this->pdo->lastInsertId(),
            entity_type: $entityType,
            bundle: $bundle,
            pattern: $pattern,
        );
    }

    /**
     * Delete a slug pattern.
     */
    public function deletePattern(int $id): void
    {
        $this->pdo->prepare('DELETE FROM slug_patterns WHERE id = :id')
            ->execute(['id' => $id]);
    }

    // ── Slug Generation ─────────────────────────────────────────────

    /**
     * Generate a slug for a content node based on its type's pattern.
     *
     * Falls back to slugifying the title if no pattern is defined.
     */
    public function generateSlug(ContentEntity $node): string
    {
        $pattern = $this->getPattern('node', $node->content_type ?? '');

        if ($pattern) {
            $slug = $this->tokenizer->tokenizeNode($pattern->pattern, $node);
        } else {
            $slug = $this->tokenizer->slugify($node->title ?? 'untitled');
        }

        return $slug ?: 'untitled';
    }

    /**
     * Generate a slug for a taxonomy term.
     */
    public function generateTermSlug(TermEntity $term, string $vocabularyName = ''): string
    {
        $pattern = $this->getPattern('term', $vocabularyName);

        if ($pattern) {
            // Resolve parent term name if the term has a parent
            $parentName = null;
            if ($term->parent_id) {
                $parentName = $this->resolveTermName($term->parent_id);
            }

            $slug = $this->tokenizer->tokenizeTerm($pattern->pattern, $term, $vocabularyName, $parentName);
        } else {
            $slug = $this->tokenizer->slugify($term->name ?? 'untitled');
        }

        return $slug ?: 'untitled';
    }

    /**
     * Resolve a term name by its ID.
     */
    private function resolveTermName(int $termId): ?string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT name FROM terms WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $termId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row['name'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Convert an arbitrary string into a slug.
     */
    public function slugify(string $text): string
    {
        return $this->tokenizer->slugify($text);
    }

    /**
     * Ensure a slug is unique within its content type.
     *
     * Appends -1, -2, etc. if a duplicate exists.
     */
    public function ensureUnique(string $slug, string $contentType, ?int $excludeId = null): string
    {
        $baseSlug = $slug;
        $counter = 0;

        while ($this->slugExists($slug, $contentType, $excludeId)) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        return $slug;
    }

    /**
     * Ensure a term slug is unique within its vocabulary.
     */
    public function ensureTermUnique(string $slug, int $vocabularyId, ?int $excludeId = null): string
    {
        $baseSlug = $slug;
        $counter = 0;

        while ($this->termSlugExists($slug, $vocabularyId, $excludeId)) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        return $slug;
    }

    // ── Bulk Regeneration ───────────────────────────────────────────

    /**
     * Regenerate all slugs for a given entity type + bundle.
     *
     * @return int Number of updated records
     */
    public function regenerateAll(string $entityType = 'node', ?string $bundle = null): int
    {
        if ($entityType === 'node') {
            return $this->regenerateNodeSlugs($bundle);
        }

        if ($entityType === 'term') {
            return $this->regenerateTermSlugs($bundle);
        }

        return 0;
    }

    /**
     * Get paginated node aliases for the admin listing.
     *
     * @return array{items: list<object>, pagination: array}
     */
    public function getNodeAliases(?string $contentType = null, int $page = 1, int $perPage = 25): array
    {
        $where = 'WHERE deleted_at IS NULL';
        $params = [];

        if ($contentType) {
            $where .= ' AND content_type = :ct';
            $params['ct'] = $contentType;
        }

        // Count total
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM nodes {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch page
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT id, title, content_type, slug FROM nodes {$where} ORDER BY content_type, title ASC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $pages = max(1, (int) ceil($total / $perPage));

        return [
            'items' => $items,
            'pagination' => [
                'page'     => $page,
                'pages'    => $pages,
                'total'    => $total,
                'per_page' => $perPage,
                'from'     => $total > 0 ? $offset + 1 : 0,
                'to'       => min($offset + $perPage, $total),
                'has_prev' => $page > 1,
                'has_next' => $page < $pages,
            ],
        ];
    }

    /**
     * Get paginated term aliases for the admin listing.
     *
     * @return array{items: list<object>, pagination: array}
     */
    public function getTermAliases(?string $vocabularyName = null, int $page = 1, int $perPage = 25): array
    {
        try {
            $where = '';
            $params = [];

            if ($vocabularyName) {
                $where = ' WHERE v.machine_name = :vn';
                $params['vn'] = $vocabularyName;
            }

            $base = 'FROM terms t JOIN vocabularies v ON v.id = t.vocabulary_id' . $where;

            // Count total
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) {$base}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            // Fetch page
            $offset = ($page - 1) * $perPage;
            $sql = "SELECT t.id, t.name, t.slug, v.id AS vocabulary_id, v.machine_name AS vocabulary, v.label AS vocabulary_label {$base} ORDER BY v.label, t.name ASC LIMIT {$perPage} OFFSET {$offset}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_OBJ);

            $pages = max(1, (int) ceil($total / $perPage));

            return [
                'items' => $items,
                'pagination' => [
                    'page'     => $page,
                    'pages'    => $pages,
                    'total'    => $total,
                    'per_page' => $perPage,
                    'from'     => $total > 0 ? $offset + 1 : 0,
                    'to'       => min($offset + $perPage, $total),
                    'has_prev' => $page > 1,
                    'has_next' => $page < $pages,
                ],
            ];
        } catch (\Throwable) {
            return [
                'items' => [],
                'pagination' => ['page' => 1, 'pages' => 1, 'total' => 0, 'per_page' => $perPage, 'from' => 0, 'to' => 0, 'has_prev' => false, 'has_next' => false],
            ];
        }
    }

    /**
     * Count all node aliases.
     */
    public function countNodeAliases(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM nodes WHERE deleted_at IS NULL')->fetchColumn();
    }

    /**
     * Count all term aliases.
     */
    public function countTermAliases(): int
    {
        try {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM terms')->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Update a single node's slug.
     */
    public function updateNodeSlug(int $nodeId, string $newSlug): void
    {
        $this->pdo->prepare('UPDATE nodes SET slug = :slug, updated_at = NOW() WHERE id = :id')
            ->execute(['slug' => $newSlug, 'id' => $nodeId]);
    }

    // ── Private Helpers ─────────────────────────────────────────────

    private function slugExists(string $slug, string $contentType, ?int $excludeId): bool
    {
        $sql = 'SELECT COUNT(*) FROM nodes WHERE slug = :slug AND content_type = :ct AND deleted_at IS NULL';
        $params = ['slug' => $slug, 'ct' => $contentType];

        if ($excludeId !== null) {
            $sql .= ' AND id != :eid';
            $params['eid'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function termSlugExists(string $slug, int $vocabularyId, ?int $excludeId): bool
    {
        $sql = 'SELECT COUNT(*) FROM terms WHERE slug = :slug AND vocabulary_id = :vid';
        $params = ['slug' => $slug, 'vid' => $vocabularyId];

        if ($excludeId !== null) {
            $sql .= ' AND id != :eid';
            $params['eid'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function regenerateNodeSlugs(?string $contentType): int
    {
        $sql = 'SELECT * FROM nodes WHERE deleted_at IS NULL';
        $params = [];

        if ($contentType) {
            $sql .= ' AND content_type = :ct';
            $params['ct'] = $contentType;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $count = 0;
        $updateStmt = $this->pdo->prepare(
            'UPDATE nodes SET slug = :slug, updated_at = NOW() WHERE id = :id'
        );

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $node = (new ContentEntity())->hydrate($row);
            $newSlug = $this->generateSlug($node);
            $newSlug = $this->ensureUnique($newSlug, $node->content_type, $node->id);

            if ($newSlug !== $node->slug) {
                $updateStmt->execute(['slug' => $newSlug, 'id' => $node->id]);
                $count++;
            }
        }

        return $count;
    }

    private function regenerateTermSlugs(?string $vocabularyName): int
    {
        $sql = 'SELECT t.*, v.machine_name AS vocab_machine_name FROM terms t JOIN vocabularies v ON v.id = t.vocabulary_id';
        $params = [];

        if ($vocabularyName) {
            $sql .= ' WHERE v.machine_name = :vn';
            $params['vn'] = $vocabularyName;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\Throwable) {
            return 0; // Terms table may not exist yet
        }

        $count = 0;
        $updateStmt = $this->pdo->prepare(
            'UPDATE terms SET slug = :slug, updated_at = NOW() WHERE id = :id'
        );

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $term = new TermEntity();
            $term->hydrate($row);

            $newSlug = $this->generateTermSlug($term, $row['vocab_machine_name'] ?? '');
            $newSlug = $this->ensureTermUnique($newSlug, $term->vocabulary_id, $term->id);

            if ($newSlug !== $term->slug) {
                $updateStmt->execute(['slug' => $newSlug, 'id' => $term->id]);
                $count++;
            }
        }

        return $count;
    }
}
