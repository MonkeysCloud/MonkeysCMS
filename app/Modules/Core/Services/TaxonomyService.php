<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Entities\Vocabulary;
use App\Modules\Core\Entities\Term;
use App\Modules\Core\Entities\EntityTerm;
use App\Cms\Core\BaseEntity;
use MonkeysLegion\Database\Connection;
use MonkeysLegion\Cache\CacheManager;

/**
 * TaxonomyService - Manages vocabularies, terms, and entity-term relationships
 * 
 * Uses MonkeysLegion-Cache for vocabulary and term caching.
 */
final class TaxonomyService
{
    private const CACHE_TTL = 86400; // 24 hours
    private const CACHE_TAG = 'taxonomy';
    
    public function __construct(
        private readonly Connection $connection,
        private readonly ?CacheManager $cache = null,
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Vocabulary Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Get all vocabularies
     * 
     * @return Vocabulary[]
     */
    public function getAllVocabularies(): array
    {
        $stmt = $this->connection->query(
            "SELECT * FROM vocabularies ORDER BY weight, name"
        );

        $vocabularies = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $vocabulary = new Vocabulary();
            $vocabulary->hydrate($row);
            $vocabularies[] = $vocabulary;
        }

        return $vocabularies;
    }

    /**
     * Get vocabulary by ID
     */
    public function getVocabulary(int $id): ?Vocabulary
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM vocabularies WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $vocabulary = new Vocabulary();
        $vocabulary->hydrate($row);
        return $vocabulary;
    }

    /**
     * Get vocabulary by machine name
     */
    public function getVocabularyByMachineName(string $machineName): ?Vocabulary
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM vocabularies WHERE machine_name = :machine_name"
        );
        $stmt->execute(['machine_name' => $machineName]);
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $vocabulary = new Vocabulary();
        $vocabulary->hydrate($row);
        return $vocabulary;
    }

    /**
     * Get vocabularies for an entity type
     * 
     * @return Vocabulary[]
     */
    public function getVocabulariesForEntity(string $entityType): array
    {
        $stmt = $this->connection->query(
            "SELECT * FROM vocabularies ORDER BY weight, name"
        );

        $vocabularies = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $vocabulary = new Vocabulary();
            $vocabulary->hydrate($row);
            
            if ($vocabulary->allowsEntityType($entityType)) {
                $vocabularies[] = $vocabulary;
            }
        }

        return $vocabularies;
    }

    /**
     * Save vocabulary
     */
    public function saveVocabulary(Vocabulary $vocabulary): Vocabulary
    {
        $vocabulary->prePersist();

        if ($vocabulary->isNew()) {
            $stmt = $this->connection->prepare("
                INSERT INTO vocabularies (name, machine_name, description, hierarchical, `multiple`, required, weight, entity_types, settings, created_at, updated_at)
                VALUES (:name, :machine_name, :description, :hierarchical, :multiple, :required, :weight, :entity_types, :settings, :created_at, :updated_at)
            ");
            $stmt->execute([
                'name' => $vocabulary->name,
                'machine_name' => $vocabulary->machine_name,
                'description' => $vocabulary->description,
                'hierarchical' => $vocabulary->hierarchical ? 1 : 0,
                'multiple' => $vocabulary->multiple ? 1 : 0,
                'required' => $vocabulary->required ? 1 : 0,
                'weight' => $vocabulary->weight,
                'entity_types' => json_encode($vocabulary->entity_types),
                'settings' => json_encode($vocabulary->settings),
                'created_at' => $vocabulary->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $vocabulary->updated_at->format('Y-m-d H:i:s'),
            ]);
            $vocabulary->id = (int) $this->connection->lastInsertId();
        } else {
            $stmt = $this->connection->prepare("
                UPDATE vocabularies SET
                    name = :name,
                    machine_name = :machine_name,
                    description = :description,
                    hierarchical = :hierarchical,
                    `multiple` = :multiple,
                    required = :required,
                    weight = :weight,
                    entity_types = :entity_types,
                    settings = :settings,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $vocabulary->id,
                'name' => $vocabulary->name,
                'machine_name' => $vocabulary->machine_name,
                'description' => $vocabulary->description,
                'hierarchical' => $vocabulary->hierarchical ? 1 : 0,
                'multiple' => $vocabulary->multiple ? 1 : 0,
                'required' => $vocabulary->required ? 1 : 0,
                'weight' => $vocabulary->weight,
                'entity_types' => json_encode($vocabulary->entity_types),
                'settings' => json_encode($vocabulary->settings),
                'updated_at' => $vocabulary->updated_at->format('Y-m-d H:i:s'),
            ]);
        }

        return $vocabulary;
    }

    /**
     * Delete vocabulary and all its terms
     */
    public function deleteVocabulary(Vocabulary $vocabulary): void
    {
        // Delete all entity-term links for this vocabulary
        $stmt = $this->connection->prepare(
            "DELETE FROM entity_terms WHERE vocabulary_id = :vocabulary_id"
        );
        $stmt->execute(['vocabulary_id' => $vocabulary->id]);

        // Delete all terms
        $stmt = $this->connection->prepare(
            "DELETE FROM terms WHERE vocabulary_id = :vocabulary_id"
        );
        $stmt->execute(['vocabulary_id' => $vocabulary->id]);

        // Delete vocabulary
        $stmt = $this->connection->prepare(
            "DELETE FROM vocabularies WHERE id = :id"
        );
        $stmt->execute(['id' => $vocabulary->id]);
    }

    // ─────────────────────────────────────────────────────────────
    // Term Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Get term by ID
     */
    public function getTerm(int $id): ?Term
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM terms WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $term = new Term();
        $term->hydrate($row);
        return $term;
    }

    /**
     * Get term by slug within vocabulary
     */
    public function getTermBySlug(int $vocabularyId, string $slug): ?Term
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM terms WHERE vocabulary_id = :vocabulary_id AND slug = :slug"
        );
        $stmt->execute([
            'vocabulary_id' => $vocabularyId,
            'slug' => $slug,
        ]);
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $term = new Term();
        $term->hydrate($row);
        return $term;
    }

    /**
     * Get all terms for a vocabulary
     * 
     * @return Term[]
     */
    public function getTerms(int $vocabularyId, bool $publishedOnly = false): array
    {
        $sql = "SELECT * FROM terms WHERE vocabulary_id = :vocabulary_id";
        if ($publishedOnly) {
            $sql .= " AND is_published = 1";
        }
        $sql .= " ORDER BY weight, name";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['vocabulary_id' => $vocabularyId]);

        $terms = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $term = new Term();
            $term->hydrate($row);
            $terms[] = $term;
        }

        return $terms;
    }

    /**
     * Get terms as hierarchical tree
     * 
     * @return Term[]
     */
    public function getTermTree(int $vocabularyId, bool $publishedOnly = false): array
    {
        $terms = $this->getTerms($vocabularyId, $publishedOnly);
        return $this->buildTree($terms);
    }

    /**
     * Build hierarchical tree from flat term list
     * 
     * @param Term[] $terms
     * @return Term[]
     */
    private function buildTree(array $terms): array
    {
        $tree = [];
        $termMap = [];

        // Index terms by ID
        foreach ($terms as $term) {
            $termMap[$term->id] = $term;
            $term->children = [];
        }

        // Build tree
        foreach ($terms as $term) {
            if ($term->parent_id === null) {
                $tree[] = $term;
            } elseif (isset($termMap[$term->parent_id])) {
                $termMap[$term->parent_id]->children[] = $term;
            }
        }

        return $tree;
    }

    /**
     * Get child terms
     * 
     * @return Term[]
     */
    public function getChildTerms(int $parentId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM terms WHERE parent_id = :parent_id ORDER BY weight, name"
        );
        $stmt->execute(['parent_id' => $parentId]);

        $terms = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $term = new Term();
            $term->hydrate($row);
            $terms[] = $term;
        }

        return $terms;
    }

    /**
     * Save term
     */
    public function saveTerm(Term $term): Term
    {
        $term->prePersist();

        // Calculate depth based on parent
        if ($term->parent_id !== null) {
            $parent = $this->getTerm($term->parent_id);
            if ($parent) {
                $term->depth = $parent->depth + 1;
                $term->parent = $parent;
            }
        } else {
            $term->depth = 0;
        }

        if ($term->isNew()) {
            $stmt = $this->connection->prepare("
                INSERT INTO terms (vocabulary_id, parent_id, name, slug, description, color, icon, image, weight, depth, path, is_published, metadata, meta_title, meta_description, created_at, updated_at)
                VALUES (:vocabulary_id, :parent_id, :name, :slug, :description, :color, :icon, :image, :weight, :depth, :path, :is_published, :metadata, :meta_title, :meta_description, :created_at, :updated_at)
            ");
            $stmt->execute([
                'vocabulary_id' => $term->vocabulary_id,
                'parent_id' => $term->parent_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'color' => $term->color,
                'icon' => $term->icon,
                'image' => $term->image,
                'weight' => $term->weight,
                'depth' => $term->depth,
                'path' => $term->path,
                'is_published' => $term->is_published ? 1 : 0,
                'metadata' => json_encode($term->metadata),
                'meta_title' => $term->meta_title,
                'meta_description' => $term->meta_description,
                'created_at' => $term->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $term->updated_at->format('Y-m-d H:i:s'),
            ]);
            $term->id = (int) $this->connection->lastInsertId();

            // Update path with actual ID
            $term->updatePath();
            $stmt = $this->connection->prepare(
                "UPDATE terms SET path = :path WHERE id = :id"
            );
            $stmt->execute(['path' => $term->path, 'id' => $term->id]);
        } else {
            $stmt = $this->connection->prepare("
                UPDATE terms SET
                    vocabulary_id = :vocabulary_id,
                    parent_id = :parent_id,
                    name = :name,
                    slug = :slug,
                    description = :description,
                    color = :color,
                    icon = :icon,
                    image = :image,
                    weight = :weight,
                    depth = :depth,
                    path = :path,
                    is_published = :is_published,
                    metadata = :metadata,
                    meta_title = :meta_title,
                    meta_description = :meta_description,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $term->id,
                'vocabulary_id' => $term->vocabulary_id,
                'parent_id' => $term->parent_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'color' => $term->color,
                'icon' => $term->icon,
                'image' => $term->image,
                'weight' => $term->weight,
                'depth' => $term->depth,
                'path' => $term->path,
                'is_published' => $term->is_published ? 1 : 0,
                'metadata' => json_encode($term->metadata),
                'meta_title' => $term->meta_title,
                'meta_description' => $term->meta_description,
                'updated_at' => $term->updated_at->format('Y-m-d H:i:s'),
            ]);
        }

        return $term;
    }

    /**
     * Delete term and optionally its children
     */
    public function deleteTerm(Term $term, bool $deleteChildren = false): void
    {
        if ($deleteChildren) {
            // Delete all descendants
            $stmt = $this->connection->prepare(
                "DELETE FROM entity_terms WHERE term_id IN (SELECT id FROM terms WHERE path LIKE :path)"
            );
            $stmt->execute(['path' => $term->path . '/%']);

            $stmt = $this->connection->prepare(
                "DELETE FROM terms WHERE path LIKE :path"
            );
            $stmt->execute(['path' => $term->path . '/%']);
        } else {
            // Move children to parent
            $stmt = $this->connection->prepare(
                "UPDATE terms SET parent_id = :parent_id WHERE parent_id = :term_id"
            );
            $stmt->execute([
                'parent_id' => $term->parent_id,
                'term_id' => $term->id,
            ]);
        }

        // Delete entity-term links
        $stmt = $this->connection->prepare(
            "DELETE FROM entity_terms WHERE term_id = :term_id"
        );
        $stmt->execute(['term_id' => $term->id]);

        // Delete term
        $stmt = $this->connection->prepare(
            "DELETE FROM terms WHERE id = :id"
        );
        $stmt->execute(['id' => $term->id]);

        // Recalculate paths for remaining terms
        $this->recalculatePaths($term->vocabulary_id);
    }

    /**
     * Recalculate all term paths in a vocabulary
     */
    public function recalculatePaths(int $vocabularyId): void
    {
        $terms = $this->getTerms($vocabularyId);
        $termMap = [];
        
        foreach ($terms as $term) {
            $termMap[$term->id] = $term;
        }

        foreach ($terms as $term) {
            if ($term->parent_id === null) {
                $term->path = '/' . $term->id;
                $term->depth = 0;
            } else {
                $parent = $termMap[$term->parent_id] ?? null;
                if ($parent) {
                    $term->path = $parent->path . '/' . $term->id;
                    $term->depth = $parent->depth + 1;
                }
            }

            $stmt = $this->connection->prepare(
                "UPDATE terms SET path = :path, depth = :depth WHERE id = :id"
            );
            $stmt->execute([
                'path' => $term->path,
                'depth' => $term->depth,
                'id' => $term->id,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Entity-Term Relationship Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Get terms for an entity
     * 
     * @return Term[]
     */
    public function getEntityTerms(string $entityType, int $entityId, ?int $vocabularyId = null): array
    {
        $sql = "
            SELECT t.*, et.weight as link_weight FROM terms t
            INNER JOIN entity_terms et ON t.id = et.term_id
            WHERE et.entity_type = :entity_type AND et.entity_id = :entity_id
        ";
        $params = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ];

        if ($vocabularyId !== null) {
            $sql .= " AND et.vocabulary_id = :vocabulary_id";
            $params['vocabulary_id'] = $vocabularyId;
        }

        $sql .= " ORDER BY et.vocabulary_id, et.weight, t.name";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        $terms = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $term = new Term();
            $term->hydrate($row);
            $terms[] = $term;
        }

        return $terms;
    }

    /**
     * Get entities for a term
     * 
     * @return array<array{entity_type: string, entity_id: int}>
     */
    public function getTermEntities(int $termId, ?string $entityType = null): array
    {
        $sql = "SELECT entity_type, entity_id FROM entity_terms WHERE term_id = :term_id";
        $params = ['term_id' => $termId];

        if ($entityType !== null) {
            $sql .= " AND entity_type = :entity_type";
            $params['entity_type'] = $entityType;
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Add term to entity
     */
    public function addTermToEntity(
        string $entityType,
        int $entityId,
        int $termId,
        ?int $weight = null
    ): void {
        $term = $this->getTerm($termId);
        if (!$term) {
            throw new \InvalidArgumentException("Term {$termId} not found");
        }

        // Check if already linked
        $stmt = $this->connection->prepare("
            SELECT id FROM entity_terms 
            WHERE entity_type = :entity_type AND entity_id = :entity_id AND term_id = :term_id
        ");
        $stmt->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'term_id' => $termId,
        ]);

        if ($stmt->fetch()) {
            return; // Already linked
        }

        // Get next weight if not specified
        if ($weight === null) {
            $stmt = $this->connection->prepare("
                SELECT MAX(weight) as max_weight FROM entity_terms 
                WHERE entity_type = :entity_type AND entity_id = :entity_id AND vocabulary_id = :vocabulary_id
            ");
            $stmt->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'vocabulary_id' => $term->vocabulary_id,
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $weight = ($row['max_weight'] ?? -1) + 1;
        }

        $stmt = $this->connection->prepare("
            INSERT INTO entity_terms (entity_type, entity_id, term_id, vocabulary_id, weight, created_at)
            VALUES (:entity_type, :entity_id, :term_id, :vocabulary_id, :weight, :created_at)
        ");
        $stmt->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'term_id' => $termId,
            'vocabulary_id' => $term->vocabulary_id,
            'weight' => $weight,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Remove term from entity
     */
    public function removeTermFromEntity(string $entityType, int $entityId, int $termId): void
    {
        $stmt = $this->connection->prepare("
            DELETE FROM entity_terms 
            WHERE entity_type = :entity_type AND entity_id = :entity_id AND term_id = :term_id
        ");
        $stmt->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'term_id' => $termId,
        ]);
    }

    /**
     * Set entity terms for a vocabulary (replaces existing)
     * 
     * @param int[] $termIds
     */
    public function setEntityTerms(
        string $entityType,
        int $entityId,
        int $vocabularyId,
        array $termIds
    ): void {
        // Remove existing terms for this vocabulary
        $stmt = $this->connection->prepare("
            DELETE FROM entity_terms 
            WHERE entity_type = :entity_type AND entity_id = :entity_id AND vocabulary_id = :vocabulary_id
        ");
        $stmt->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'vocabulary_id' => $vocabularyId,
        ]);

        // Add new terms
        foreach ($termIds as $weight => $termId) {
            $stmt = $this->connection->prepare("
                INSERT INTO entity_terms (entity_type, entity_id, term_id, vocabulary_id, weight, created_at)
                VALUES (:entity_type, :entity_id, :term_id, :vocabulary_id, :weight, :created_at)
            ");
            $stmt->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'term_id' => $termId,
                'vocabulary_id' => $vocabularyId,
                'weight' => $weight,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Clear all terms from entity
     */
    public function clearEntityTerms(string $entityType, int $entityId): void
    {
        $stmt = $this->connection->prepare("
            DELETE FROM entity_terms 
            WHERE entity_type = :entity_type AND entity_id = :entity_id
        ");
        $stmt->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    /**
     * Count entities with a term
     */
    public function countTermEntities(int $termId, ?string $entityType = null): int
    {
        $sql = "SELECT COUNT(*) FROM entity_terms WHERE term_id = :term_id";
        $params = ['term_id' => $termId];

        if ($entityType !== null) {
            $sql .= " AND entity_type = :entity_type";
            $params['entity_type'] = $entityType;
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Search terms by name
     * 
     * @return Term[]
     */
    public function searchTerms(string $query, ?int $vocabularyId = null, int $limit = 20): array
    {
        $sql = "SELECT * FROM terms WHERE name LIKE :query";
        $params = ['query' => '%' . $query . '%'];

        if ($vocabularyId !== null) {
            $sql .= " AND vocabulary_id = :vocabulary_id";
            $params['vocabulary_id'] = $vocabularyId;
        }

        $sql .= " ORDER BY name LIMIT :limit";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('query', '%' . $query . '%', \PDO::PARAM_STR);
        if ($vocabularyId !== null) {
            $stmt->bindValue('vocabulary_id', $vocabularyId, \PDO::PARAM_INT);
        }
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $terms = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $term = new Term();
            $term->hydrate($row);
            $terms[] = $term;
        }

        return $terms;
    }

    /**
     * Get or create term by name
     */
    public function getOrCreateTerm(int $vocabularyId, string $name): Term
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
        $slug = trim($slug, '-');

        $existing = $this->getTermBySlug($vocabularyId, $slug);
        if ($existing) {
            return $existing;
        }

        $term = new Term();
        $term->vocabulary_id = $vocabularyId;
        $term->name = $name;
        $term->slug = $slug;

        return $this->saveTerm($term);
    }

    /**
     * Get terms as options for select widget
     * 
     * @return array<array{value: int, label: string, depth: int}>
     */
    public function getTermOptions(int $vocabularyId, bool $hierarchical = true): array
    {
        if ($hierarchical) {
            $terms = $this->getTermTree($vocabularyId, true);
            return $this->flattenTreeToOptions($terms);
        }

        $terms = $this->getTerms($vocabularyId, true);
        return array_map(fn($t) => $t->toOption(), $terms);
    }

    /**
     * Flatten tree to options array
     */
    private function flattenTreeToOptions(array $terms, array &$options = []): array
    {
        foreach ($terms as $term) {
            $options[] = $term->toOption();
            if (!empty($term->children)) {
                $this->flattenTreeToOptions($term->children, $options);
            }
        }
        return $options;
    }
}
