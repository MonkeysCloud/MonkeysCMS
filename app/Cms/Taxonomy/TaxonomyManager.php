<?php

declare(strict_types=1);

namespace App\Cms\Taxonomy;

use App\Cms\Fields\FieldDefinition;
use App\Modules\Core\Entities\TaxonomyTerm;
use MonkeysLegion\Database\Connection;
use MonkeysLegion\Cache\CacheManager;

/**
 * TaxonomyManager - Registry and manager for taxonomy vocabularies and terms
 *
 * Manages both:
 * - Code-defined vocabularies (registered programmatically)
 * - Database-defined vocabularies (stored in vocabularies table)
 *
 * Vocabularies can have custom fields attached to their terms.
 *
 * @example
 * ```php
 * // Register a code-defined vocabulary
 * $manager->registerVocabulary([
 *     'id' => 'tags',
 *     'name' => 'Tags',
 *     'hierarchical' => false,
 * ]);
 *
 * // Create a database vocabulary with custom fields
 * $manager->createDatabaseVocabulary([
 *     'name' => 'Product Categories',
 *     'hierarchical' => true,
 *     'fields' => [
 *         ['name' => 'Image', 'type' => 'image'],
 *         ['name' => 'Color', 'type' => 'color'],
 *     ]
 * ]);
 *
 * // Get terms
 * $terms = $manager->getTerms('tags');
 * $tree = $manager->getTermTree('categories');
 * ```
 */
final class TaxonomyManager
{
    private const CACHE_KEY = 'cms:vocabularies';
    private const CACHE_TTL = 86400;

    /** @var array<string, array> Code-defined vocabularies */
    private array $codeVocabularies = [];

    /** @var array<string, VocabularyEntity> Database-defined vocabularies */
    private array $dbVocabularies = [];

    private bool $initialized = false;

    public function __construct(
        private readonly Connection $connection,
        private readonly ?CacheManager $cache = null,
    ) {
    }

    // =========================================================================
    // Vocabulary Management
    // =========================================================================

    /**
     * Register a code-defined vocabulary
     */
    public function registerVocabulary(array $config): void
    {
        $id = $config['id'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '_', $config['name']));

        $this->codeVocabularies[$id] = [
            'id' => $id,
            'name' => $config['name'],
            'description' => $config['description'] ?? '',
            'icon' => $config['icon'] ?? '🏷️',
            'hierarchical' => $config['hierarchical'] ?? true,
            'multiple' => $config['multiple'] ?? true,
            'required' => $config['required'] ?? false,
            'max_depth' => $config['max_depth'] ?? 0,
            'source' => 'code',
            'fields' => $config['fields'] ?? [],
        ];
    }

    /**
     * Get all vocabularies
     */
    public function getVocabularies(): array
    {
        $this->ensureInitialized();

        $vocabularies = [];

        // Code-defined
        foreach ($this->codeVocabularies as $id => $vocab) {
            $vocabularies[$id] = $vocab;
        }

        // Database-defined
        foreach ($this->dbVocabularies as $id => $vocab) {
            $vocabularies[$id] = [
                'id' => $id,
                'name' => $vocab->name,
                'description' => $vocab->description ?? '',
                'icon' => $vocab->icon,
                'hierarchical' => $vocab->hierarchical,
                'multiple' => $vocab->multiple,
                'required' => $vocab->required,
                'max_depth' => $vocab->max_depth,
                'source' => 'database',
                'fields' => $this->getFieldsArray($vocab),
                'entity' => $vocab,
            ];
        }

        // Sort by name
        uasort($vocabularies, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $vocabularies;
    }

    /**
     * Get a specific vocabulary
     */
    public function getVocabulary(string $id): ?array
    {
        $this->ensureInitialized();

        if (isset($this->codeVocabularies[$id])) {
            return $this->codeVocabularies[$id];
        }

        if (isset($this->dbVocabularies[$id])) {
            $vocab = $this->dbVocabularies[$id];
            return [
                'id' => $id,
                'name' => $vocab->name,
                'description' => $vocab->description ?? '',
                'icon' => $vocab->icon,
                'hierarchical' => $vocab->hierarchical,
                'multiple' => $vocab->multiple,
                'required' => $vocab->required,
                'max_depth' => $vocab->max_depth,
                'source' => 'database',
                'fields' => $this->getFieldsArray($vocab),
                'entity' => $vocab,
            ];
        }

        return null;
    }

    /**
     * Check if vocabulary exists
     */
    public function hasVocabulary(string $id): bool
    {
        $this->ensureInitialized();
        return isset($this->codeVocabularies[$id]) || isset($this->dbVocabularies[$id]);
    }

    /**
     * Create a database-defined vocabulary
     */
    public function createDatabaseVocabulary(array $data): VocabularyEntity
    {
        $entity = new VocabularyEntity();
        $entity->name = $data['name'];
        $entity->vocabulary_id = $data['vocabulary_id'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '_', $data['name']));
        $entity->description = $data['description'] ?? null;
        $entity->icon = $data['icon'] ?? '🏷️';
        $entity->hierarchical = $data['hierarchical'] ?? true;
        $entity->multiple = $data['multiple'] ?? true;
        $entity->required = $data['required'] ?? false;
        $entity->max_depth = $data['max_depth'] ?? 0;
        $entity->settings = $data['settings'] ?? [];
        $entity->allowed_content_types = $data['allowed_content_types'] ?? [];
        $entity->enabled = $data['enabled'] ?? true;

        $entity->prePersist();

        $stmt = $this->connection->prepare("
            INSERT INTO vocabularies (
                vocabulary_id, name, description, icon, is_system, enabled,
                hierarchical, multiple, required, max_depth, settings,
                allowed_content_types, weight, created_at, updated_at
            ) VALUES (
                :vocabulary_id, :name, :description, :icon, :is_system, :enabled,
                :hierarchical, :multiple, :required, :max_depth, :settings,
                :allowed_content_types, :weight, :created_at, :updated_at
            )
        ");

        $stmt->execute([
            'vocabulary_id' => $entity->vocabulary_id,
            'name' => $entity->name,
            'description' => $entity->description,
            'icon' => $entity->icon,
            'is_system' => $entity->is_system ? 1 : 0,
            'enabled' => $entity->enabled ? 1 : 0,
            'hierarchical' => $entity->hierarchical ? 1 : 0,
            'multiple' => $entity->multiple ? 1 : 0,
            'required' => $entity->required ? 1 : 0,
            'max_depth' => $entity->max_depth,
            'settings' => json_encode($entity->settings),
            'allowed_content_types' => json_encode($entity->allowed_content_types),
            'weight' => $entity->weight,
            'created_at' => $entity->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $entity->updated_at->format('Y-m-d H:i:s'),
        ]);

        $entity->id = (int) $this->connection->lastInsertId();

        // Add fields if provided
        if (!empty($data['fields'])) {
            foreach ($data['fields'] as $fieldData) {
                $this->addFieldToVocabulary($entity->id, $fieldData);
            }
            $entity->fields = $this->loadVocabularyFields($entity->id);
        }

        $this->dbVocabularies[$entity->vocabulary_id] = $entity;
        $this->invalidateCache();

        return $entity;
    }

    /**
     * Update a database vocabulary
     */
    public function updateDatabaseVocabulary(int $id, array $data): ?VocabularyEntity
    {
        $entity = $this->getVocabularyEntityById($id);
        if (!$entity) {
            return null;
        }

        if (isset($data['name'])) {
            $entity->name = $data['name'];
        }
        if (isset($data['description'])) {
            $entity->description = $data['description'];
        }
        if (isset($data['icon'])) {
            $entity->icon = $data['icon'];
        }
        if (isset($data['hierarchical'])) {
            $entity->hierarchical = $data['hierarchical'];
        }
        if (isset($data['multiple'])) {
            $entity->multiple = $data['multiple'];
        }
        if (isset($data['required'])) {
            $entity->required = $data['required'];
        }
        if (isset($data['max_depth'])) {
            $entity->max_depth = $data['max_depth'];
        }
        if (isset($data['settings'])) {
            $entity->settings = $data['settings'];
        }
        if (isset($data['allowed_content_types'])) {
            $entity->allowed_content_types = $data['allowed_content_types'];
        }
        if (isset($data['enabled'])) {
            $entity->enabled = $data['enabled'];
        }

        $entity->updated_at = new \DateTimeImmutable();

        $stmt = $this->connection->prepare("
            UPDATE vocabularies SET
                name = :name, description = :description, icon = :icon,
                hierarchical = :hierarchical, multiple = :multiple, required = :required,
                max_depth = :max_depth, settings = :settings, allowed_content_types = :allowed_content_types,
                enabled = :enabled, updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $entity->id,
            'name' => $entity->name,
            'description' => $entity->description,
            'icon' => $entity->icon,
            'hierarchical' => $entity->hierarchical ? 1 : 0,
            'multiple' => $entity->multiple ? 1 : 0,
            'required' => $entity->required ? 1 : 0,
            'max_depth' => $entity->max_depth,
            'settings' => json_encode($entity->settings),
            'allowed_content_types' => json_encode($entity->allowed_content_types),
            'enabled' => $entity->enabled ? 1 : 0,
            'updated_at' => $entity->updated_at->format('Y-m-d H:i:s'),
        ]);

        $this->dbVocabularies[$entity->vocabulary_id] = $entity;
        $this->invalidateCache();

        return $entity;
    }

    /**
     * Delete a database vocabulary
     */
    public function deleteDatabaseVocabulary(int $id, bool $deleteTerms = true): bool
    {
        $entity = $this->getVocabularyEntityById($id);
        if (!$entity || $entity->is_system) {
            return false;
        }

        // Delete terms if requested
        if ($deleteTerms) {
            $stmt = $this->connection->prepare(
                "DELETE FROM taxonomy_terms WHERE vocabulary_id = :vocab_id"
            );
            $stmt->execute(['vocab_id' => $entity->vocabulary_id]);
        }

        // Delete field definitions
        $stmt = $this->connection->prepare(
            "DELETE FROM vocabulary_fields WHERE vocabulary_id = :id"
        );
        $stmt->execute(['id' => $id]);

        // Delete the vocabulary
        $stmt = $this->connection->prepare(
            "DELETE FROM vocabularies WHERE id = :id AND is_system = 0"
        );
        $stmt->execute(['id' => $id]);

        unset($this->dbVocabularies[$entity->vocabulary_id]);
        $this->invalidateCache();

        return true;
    }

    /**
     * Add a field to a vocabulary (for custom term fields)
     */
    public function addFieldToVocabulary(int $vocabularyId, array $fieldData): FieldDefinition
    {
        $field = new FieldDefinition();
        $field->name = $fieldData['name'];
        $field->machine_name = $fieldData['machine_name'] ?? 'field_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $fieldData['name']));
        $field->field_type = $fieldData['type'] ?? 'string';
        $field->description = $fieldData['description'] ?? null;
        $field->help_text = $fieldData['help_text'] ?? null;
        $field->widget = $fieldData['widget'] ?? null;
        $field->required = $fieldData['required'] ?? false;
        $field->multiple = $fieldData['multiple'] ?? false;
        $field->cardinality = $fieldData['cardinality'] ?? 1;
        $field->default_value = $fieldData['default'] ?? null;
        $field->settings = $fieldData['settings'] ?? [];
        $field->validation = $fieldData['validation'] ?? [];
        $field->widget_settings = $fieldData['widget_settings'] ?? [];
        $field->weight = $fieldData['weight'] ?? 0;

        $field->prePersist();

        $stmt = $this->connection->prepare("
            INSERT INTO vocabulary_fields (
                vocabulary_id, name, machine_name, field_type, description, help_text,
                widget, required, multiple, cardinality, default_value, settings,
                validation, widget_settings, weight, created_at, updated_at
            ) VALUES (
                :vocabulary_id, :name, :machine_name, :field_type, :description, :help_text,
                :widget, :required, :multiple, :cardinality, :default_value, :settings,
                :validation, :widget_settings, :weight, :created_at, :updated_at
            )
        ");

        $stmt->execute([
            'vocabulary_id' => $vocabularyId,
            'name' => $field->name,
            'machine_name' => $field->machine_name,
            'field_type' => $field->field_type,
            'description' => $field->description,
            'help_text' => $field->help_text,
            'widget' => $field->widget,
            'required' => $field->required ? 1 : 0,
            'multiple' => $field->multiple ? 1 : 0,
            'cardinality' => $field->cardinality,
            'default_value' => $field->default_value,
            'settings' => json_encode($field->settings),
            'validation' => json_encode($field->validation),
            'widget_settings' => json_encode($field->widget_settings),
            'weight' => $field->weight,
            'created_at' => $field->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $field->updated_at->format('Y-m-d H:i:s'),
        ]);

        $field->id = (int) $this->connection->lastInsertId();
        $this->invalidateCache();

        return $field;
    }

    /**
     * Remove a field from a vocabulary
     */
    public function removeFieldFromVocabulary(int $vocabularyId, string $machineName): bool
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM vocabulary_fields WHERE vocabulary_id = :vocab_id AND machine_name = :machine_name"
        );
        $stmt->execute(['vocab_id' => $vocabularyId, 'machine_name' => $machineName]);

        $this->invalidateCache();

        return $stmt->rowCount() > 0;
    }

    // =========================================================================
    // Term Management
    // =========================================================================

    /**
     * Get terms for a vocabulary
     */
    public function getTerms(string $vocabularyId, array $options = []): array
    {
        $sql = "SELECT * FROM taxonomy_terms WHERE vocabulary_id = :vocab_id";
        $params = ['vocab_id' => $vocabularyId];

        if (isset($options['parent_id'])) {
            $sql .= " AND parent_id = :parent_id";
            $params['parent_id'] = $options['parent_id'];
        }

        if (isset($options['status']) && $options['status'] !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $options['status'];
        }

        if (isset($options['search']) && $options['search']) {
            $sql .= " AND name LIKE :search";
            $params['search'] = '%' . $options['search'] . '%';
        }

        $sql .= " ORDER BY weight ASC, name ASC";

        if (isset($options['limit'])) {
            $sql .= " LIMIT " . (int) $options['limit'];
            if (isset($options['offset'])) {
                $sql .= " OFFSET " . (int) $options['offset'];
            }
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        $terms = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $term = new TaxonomyTerm();
            $term->hydrate($row);
            $terms[] = $term;
        }

        return $terms;
    }

    /**
     * Get terms as a hierarchical tree
     */
    public function getTermTree(string $vocabularyId, ?int $parentId = null): array
    {
        $allTerms = $this->getTerms($vocabularyId);
        return $this->buildTree($allTerms, $parentId);
    }

    /**
     * Get a single term by ID
     */
    public function getTerm(int $termId): ?TaxonomyTerm
    {
        $stmt = $this->connection->prepare("SELECT * FROM taxonomy_terms WHERE id = :id");
        $stmt->execute(['id' => $termId]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $term = new TaxonomyTerm();
        $term->hydrate($row);
        return $term;
    }

    /**
     * Get a term by slug
     */
    public function getTermBySlug(string $vocabularyId, string $slug): ?TaxonomyTerm
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM taxonomy_terms WHERE vocabulary_id = :vocab_id AND slug = :slug"
        );
        $stmt->execute(['vocab_id' => $vocabularyId, 'slug' => $slug]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $term = new TaxonomyTerm();
        $term->hydrate($row);
        return $term;
    }

    /**
     * Create a term
     */
    public function createTerm(string $vocabularyId, array $data): TaxonomyTerm
    {
        $term = new TaxonomyTerm();
        $term->vocabulary_id = $vocabularyId;
        $term->name = $data['name'];
        $term->slug = $data['slug'] ?? $this->generateSlug($data['name']);
        $term->description = $data['description'] ?? null;
        $term->parent_id = $data['parent_id'] ?? null;
        $term->weight = $data['weight'] ?? 0;
        $term->status = $data['status'] ?? 'active';
        $term->metadata = $data['metadata'] ?? [];

        // Store custom field values in metadata
        $vocab = $this->getVocabulary($vocabularyId);
        if ($vocab && !empty($vocab['fields'])) {
            foreach ($vocab['fields'] as $fieldName => $fieldDef) {
                if (isset($data[$fieldName])) {
                    $term->metadata[$fieldName] = $data[$fieldName];
                }
            }
        }

        $term->prePersist();

        $stmt = $this->connection->prepare("
            INSERT INTO taxonomy_terms (
                vocabulary_id, name, slug, description, parent_id,
                weight, status, metadata, created_at, updated_at
            ) VALUES (
                :vocabulary_id, :name, :slug, :description, :parent_id,
                :weight, :status, :metadata, :created_at, :updated_at
            )
        ");

        $stmt->execute([
            'vocabulary_id' => $term->vocabulary_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'parent_id' => $term->parent_id,
            'weight' => $term->weight,
            'status' => $term->status,
            'metadata' => json_encode($term->metadata),
            'created_at' => $term->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $term->updated_at->format('Y-m-d H:i:s'),
        ]);

        $term->id = (int) $this->connection->lastInsertId();

        return $term;
    }

    /**
     * Update a term
     */
    public function updateTerm(int $termId, array $data): ?TaxonomyTerm
    {
        $term = $this->getTerm($termId);
        if (!$term) {
            return null;
        }

        if (isset($data['name'])) {
            $term->name = $data['name'];
        }
        if (isset($data['slug'])) {
            $term->slug = $data['slug'];
        }
        if (isset($data['description'])) {
            $term->description = $data['description'];
        }
        if (isset($data['parent_id'])) {
            $term->parent_id = $data['parent_id'];
        }
        if (isset($data['weight'])) {
            $term->weight = $data['weight'];
        }
        if (isset($data['status'])) {
            $term->status = $data['status'];
        }

        // Update custom field values
        $vocab = $this->getVocabulary($term->vocabulary_id);
        if ($vocab && !empty($vocab['fields'])) {
            foreach ($vocab['fields'] as $fieldName => $fieldDef) {
                if (array_key_exists($fieldName, $data)) {
                    $term->metadata[$fieldName] = $data[$fieldName];
                }
            }
        }

        if (isset($data['metadata'])) {
            $term->metadata = array_merge($term->metadata, $data['metadata']);
        }

        $term->updated_at = new \DateTimeImmutable();

        $stmt = $this->connection->prepare("
            UPDATE taxonomy_terms SET
                name = :name, slug = :slug, description = :description,
                parent_id = :parent_id, weight = :weight, status = :status,
                metadata = :metadata, updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $term->id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'parent_id' => $term->parent_id,
            'weight' => $term->weight,
            'status' => $term->status,
            'metadata' => json_encode($term->metadata),
            'updated_at' => $term->updated_at->format('Y-m-d H:i:s'),
        ]);

        return $term;
    }

    /**
     * Delete a term
     */
    public function deleteTerm(int $termId, bool $deleteChildren = false): bool
    {
        if ($deleteChildren) {
            // Get all child terms recursively
            $childIds = $this->getChildTermIds($termId);
            if (!empty($childIds)) {
                $placeholders = implode(',', array_fill(0, count($childIds), '?'));
                $stmt = $this->connection->prepare("DELETE FROM taxonomy_terms WHERE id IN ({$placeholders})");
                $stmt->execute($childIds);
            }
        } else {
            // Orphan children (set parent_id to null)
            $stmt = $this->connection->prepare("UPDATE taxonomy_terms SET parent_id = NULL WHERE parent_id = :id");
            $stmt->execute(['id' => $termId]);
        }

        // Delete the term
        $stmt = $this->connection->prepare("DELETE FROM taxonomy_terms WHERE id = :id");
        $stmt->execute(['id' => $termId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Reorder terms
     */
    public function reorderTerms(array $order): void
    {
        foreach ($order as $index => $termId) {
            $stmt = $this->connection->prepare("UPDATE taxonomy_terms SET weight = :weight WHERE id = :id");
            $stmt->execute(['weight' => $index, 'id' => $termId]);
        }
    }

    /**
     * Move a term to a new parent
     */
    public function moveTerm(int $termId, ?int $newParentId): bool
    {
        // Prevent circular reference
        if ($newParentId !== null) {
            $childIds = $this->getChildTermIds($termId);
            if (in_array($newParentId, $childIds, true)) {
                return false;
            }
        }

        $stmt = $this->connection->prepare("UPDATE taxonomy_terms SET parent_id = :parent_id WHERE id = :id");
        $stmt->execute(['parent_id' => $newParentId, 'id' => $termId]);

        return true;
    }

    /**
     * Get term count for a vocabulary
     */
    public function getTermCount(string $vocabularyId): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) FROM taxonomy_terms WHERE vocabulary_id = :vocab_id"
        );
        $stmt->execute(['vocab_id' => $vocabularyId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get content items tagged with a term
     */
    public function getContentByTerm(int $termId, string $contentType, array $options = []): array
    {
        $sql = "
            SELECT c.* FROM content_{$contentType} c
            INNER JOIN content_taxonomy ct ON ct.content_id = c.id AND ct.content_type = :content_type
            WHERE ct.term_id = :term_id
        ";
        $params = ['term_id' => $termId, 'content_type' => $contentType];

        $sql .= " ORDER BY c.created_at DESC";

        if (isset($options['limit'])) {
            $sql .= " LIMIT " . (int) $options['limit'];
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Attach terms to content
     */
    public function attachTermsToContent(int $contentId, string $contentType, array $termIds): void
    {
        // Remove existing
        $stmt = $this->connection->prepare(
            "DELETE FROM content_taxonomy WHERE content_id = :content_id AND content_type = :content_type"
        );
        $stmt->execute(['content_id' => $contentId, 'content_type' => $contentType]);

        // Add new
        $stmt = $this->connection->prepare("
            INSERT INTO content_taxonomy (content_id, content_type, term_id)
            VALUES (:content_id, :content_type, :term_id)
        ");

        foreach ($termIds as $termId) {
            $stmt->execute([
                'content_id' => $contentId,
                'content_type' => $contentType,
                'term_id' => $termId,
            ]);
        }
    }

    /**
     * Get terms for content
     */
    public function getTermsForContent(int $contentId, string $contentType, ?string $vocabularyId = null): array
    {
        $sql = "
            SELECT t.* FROM taxonomy_terms t
            INNER JOIN content_taxonomy ct ON ct.term_id = t.id
            WHERE ct.content_id = :content_id AND ct.content_type = :content_type
        ";
        $params = ['content_id' => $contentId, 'content_type' => $contentType];

        if ($vocabularyId) {
            $sql .= " AND t.vocabulary_id = :vocab_id";
            $params['vocab_id'] = $vocabularyId;
        }

        $sql .= " ORDER BY t.vocabulary_id, t.weight, t.name";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        $terms = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $term = new TaxonomyTerm();
            $term->hydrate($row);
            $terms[] = $term;
        }

        return $terms;
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    private function buildTree(array $terms, ?int $parentId): array
    {
        $tree = [];
        foreach ($terms as $term) {
            if ($term->parent_id === $parentId) {
                $node = [
                    'term' => $term,
                    'children' => $this->buildTree($terms, $term->id),
                ];
                $tree[] = $node;
            }
        }
        return $tree;
    }

    private function getChildTermIds(int $parentId): array
    {
        $ids = [];

        $stmt = $this->connection->prepare("SELECT id FROM taxonomy_terms WHERE parent_id = :parent_id");
        $stmt->execute(['parent_id' => $parentId]);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = (int) $row['id'];
            $ids = array_merge($ids, $this->getChildTermIds((int) $row['id']));
        }

        return $ids;
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'term';
    }

    private function loadDatabaseVocabularies(): void
    {
        if ($this->cache) {
            $cached = $this->cache->store()->get(self::CACHE_KEY);
            if ($cached !== null) {
                $this->dbVocabularies = $cached;
                return;
            }
        }

        try {
            $stmt = $this->connection->query(
                "SELECT * FROM vocabularies WHERE enabled = 1 ORDER BY weight, name"
            );

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $entity = new VocabularyEntity();
                $entity->hydrate($row);
                $entity->fields = $this->loadVocabularyFields($entity->id);
                $this->dbVocabularies[$entity->vocabulary_id] = $entity;
            }

            if ($this->cache) {
                $this->cache->store()->set(self::CACHE_KEY, $this->dbVocabularies, self::CACHE_TTL);
            }
        } catch (\Exception $e) {
            // Table might not exist yet
        }
    }

    private function loadVocabularyFields(int $vocabularyId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM vocabulary_fields WHERE vocabulary_id = :vocab_id ORDER BY weight, name"
        );
        $stmt->execute(['vocab_id' => $vocabularyId]);

        $fields = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $field = new FieldDefinition();
            $field->hydrate($row);
            $fields[] = $field;
        }

        return $fields;
    }

    private function getVocabularyEntityById(int $id): ?VocabularyEntity
    {
        $stmt = $this->connection->prepare("SELECT * FROM vocabularies WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $entity = new VocabularyEntity();
        $entity->hydrate($row);
        $entity->fields = $this->loadVocabularyFields($entity->id);

        return $entity;
    }

    private function getFieldsArray(VocabularyEntity $vocab): array
    {
        $fields = [];
        foreach ($vocab->fields as $field) {
            $fields[$field->machine_name] = [
                'type' => $field->field_type,
                'label' => $field->name,
                'required' => $field->required,
                'description' => $field->description,
                'default' => $field->default_value,
                'widget' => $field->getWidget(),
                'settings' => $field->settings,
            ];
        }
        return $fields;
    }

    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            $this->loadDatabaseVocabularies();
            $this->initialized = true;
        }
    }

    private function invalidateCache(): void
    {
        if ($this->cache) {
            $this->cache->store()->delete(self::CACHE_KEY);
        }
        $this->initialized = false;
        $this->dbVocabularies = [];
    }

    /**
     * Get SQL for creating vocabularies table
     */
    public static function getTableSql(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS vocabularies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vocabulary_id VARCHAR(100) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                icon VARCHAR(50) DEFAULT '🏷️',
                is_system TINYINT(1) DEFAULT 0,
                enabled TINYINT(1) DEFAULT 1,
                hierarchical TINYINT(1) DEFAULT 1,
                multiple TINYINT(1) DEFAULT 1,
                required TINYINT(1) DEFAULT 0,
                max_depth INT DEFAULT 0,
                settings JSON,
                allowed_content_types JSON,
                weight INT DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_vocabulary_id (vocabulary_id),
                INDEX idx_enabled (enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }

    /**
     * Get SQL for creating vocabulary_fields table
     */
    public static function getFieldsTableSql(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS vocabulary_fields (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vocabulary_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                machine_name VARCHAR(100) NOT NULL,
                field_type VARCHAR(50) NOT NULL,
                description VARCHAR(500),
                help_text VARCHAR(500),
                widget VARCHAR(50),
                required TINYINT(1) DEFAULT 0,
                multiple TINYINT(1) DEFAULT 0,
                cardinality INT DEFAULT 1,
                default_value TEXT,
                settings JSON,
                validation JSON,
                widget_settings JSON,
                weight INT DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                FOREIGN KEY (vocabulary_id) REFERENCES vocabularies(id) ON DELETE CASCADE,
                UNIQUE KEY uk_vocab_field (vocabulary_id, machine_name),
                INDEX idx_field_type (field_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }

    /**
     * Get SQL for content_taxonomy junction table
     */
    public static function getContentTaxonomyTableSql(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS content_taxonomy (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content_id INT NOT NULL,
                content_type VARCHAR(100) NOT NULL,
                term_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (term_id) REFERENCES taxonomy_terms(id) ON DELETE CASCADE,
                UNIQUE KEY uk_content_term (content_id, content_type, term_id),
                INDEX idx_content (content_id, content_type),
                INDEX idx_term (term_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }
}
