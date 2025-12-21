<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Entities\Vocabulary;
use App\Modules\Core\Entities\Term;
use App\Modules\Core\Services\TaxonomyService;
use MonkeysLegion\Http\Attribute\Route;
use MonkeysLegion\Http\Request;
use MonkeysLegion\Http\JsonResponse;

/**
 * TaxonomyController - Admin API for vocabulary and term management
 */
final class TaxonomyController
{
    public function __construct(
        private readonly TaxonomyService $taxonomy,
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Vocabulary Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * List all vocabularies
     */
    #[Route('GET', '/admin/vocabularies')]
    public function listVocabularies(): JsonResponse
    {
        $vocabularies = $this->taxonomy->getAllVocabularies();

        return new JsonResponse([
            'vocabularies' => array_map(fn($v) => array_merge($v->toArray(), [
                'term_count' => count($this->taxonomy->getTerms($v->id)),
            ]), $vocabularies),
        ]);
    }

    /**
     * Get single vocabulary
     */
    #[Route('GET', '/admin/vocabularies/{id}')]
    public function showVocabulary(int $id): JsonResponse
    {
        $vocabulary = $this->taxonomy->getVocabulary($id);

        if (!$vocabulary) {
            return new JsonResponse(['error' => 'Vocabulary not found'], 404);
        }

        $vocabulary->terms = $this->taxonomy->getTerms($id);

        return new JsonResponse(array_merge($vocabulary->toArray(), [
            'terms' => array_map(fn($t) => $t->toArray(), $vocabulary->terms),
            'term_tree' => $this->formatTermTree($vocabulary->getTermTree()),
        ]));
    }

    /**
     * Get vocabulary by machine name
     */
    #[Route('GET', '/admin/vocabularies/by-name/{machineName}')]
    public function showVocabularyByName(string $machineName): JsonResponse
    {
        $vocabulary = $this->taxonomy->getVocabularyByMachineName($machineName);

        if (!$vocabulary) {
            return new JsonResponse(['error' => 'Vocabulary not found'], 404);
        }

        $vocabulary->terms = $this->taxonomy->getTerms($vocabulary->id);

        return new JsonResponse(array_merge($vocabulary->toArray(), [
            'terms' => array_map(fn($t) => $t->toArray(), $vocabulary->terms),
        ]));
    }

    /**
     * Create vocabulary
     */
    #[Route('POST', '/admin/vocabularies')]
    public function createVocabulary(Request $request): JsonResponse
    {
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return new JsonResponse(['errors' => ['name' => 'Name is required']], 422);
        }

        // Generate machine name if not provided
        $machineName = $data['machine_name'] ?? strtolower(preg_replace('/[^a-z0-9]+/', '_', $data['name']));

        // Check uniqueness
        $existing = $this->taxonomy->getVocabularyByMachineName($machineName);
        if ($existing) {
            return new JsonResponse(['errors' => ['machine_name' => 'Machine name already exists']], 422);
        }

        $vocabulary = new Vocabulary();
        $vocabulary->name = $data['name'];
        $vocabulary->machine_name = $machineName;
        $vocabulary->description = $data['description'] ?? '';
        $vocabulary->hierarchical = $data['hierarchical'] ?? false;
        $vocabulary->multiple = $data['multiple'] ?? true;
        $vocabulary->required = $data['required'] ?? false;
        $vocabulary->weight = $data['weight'] ?? 0;
        $vocabulary->entity_types = $data['entity_types'] ?? [];
        $vocabulary->settings = $data['settings'] ?? [];

        $this->taxonomy->saveVocabulary($vocabulary);

        return new JsonResponse([
            'success' => true,
            'message' => 'Vocabulary created successfully',
            'vocabulary' => $vocabulary->toArray(),
        ], 201);
    }

    /**
     * Update vocabulary
     */
    #[Route('PUT', '/admin/vocabularies/{id}')]
    public function updateVocabulary(int $id, Request $request): JsonResponse
    {
        $vocabulary = $this->taxonomy->getVocabulary($id);

        if (!$vocabulary) {
            return new JsonResponse(['error' => 'Vocabulary not found'], 404);
        }

        $data = $request->getParsedBody();

        if (isset($data['name'])) {
            $vocabulary->name = $data['name'];
        }
        if (isset($data['machine_name']) && $data['machine_name'] !== $vocabulary->machine_name) {
            // Check uniqueness
            $existing = $this->taxonomy->getVocabularyByMachineName($data['machine_name']);
            if ($existing) {
                return new JsonResponse(['errors' => ['machine_name' => 'Machine name already exists']], 422);
            }
            $vocabulary->machine_name = $data['machine_name'];
        }
        if (isset($data['description'])) {
            $vocabulary->description = $data['description'];
        }
        if (isset($data['hierarchical'])) {
            $vocabulary->hierarchical = $data['hierarchical'];
        }
        if (isset($data['multiple'])) {
            $vocabulary->multiple = $data['multiple'];
        }
        if (isset($data['required'])) {
            $vocabulary->required = $data['required'];
        }
        if (isset($data['weight'])) {
            $vocabulary->weight = $data['weight'];
        }
        if (isset($data['entity_types'])) {
            $vocabulary->entity_types = $data['entity_types'];
        }
        if (isset($data['settings'])) {
            $vocabulary->settings = array_merge($vocabulary->settings, $data['settings']);
        }

        $this->taxonomy->saveVocabulary($vocabulary);

        return new JsonResponse([
            'success' => true,
            'message' => 'Vocabulary updated successfully',
            'vocabulary' => $vocabulary->toArray(),
        ]);
    }

    /**
     * Delete vocabulary
     */
    #[Route('DELETE', '/admin/vocabularies/{id}')]
    public function deleteVocabulary(int $id): JsonResponse
    {
        $vocabulary = $this->taxonomy->getVocabulary($id);

        if (!$vocabulary) {
            return new JsonResponse(['error' => 'Vocabulary not found'], 404);
        }

        $this->taxonomy->deleteVocabulary($vocabulary);

        return new JsonResponse([
            'success' => true,
            'message' => 'Vocabulary deleted successfully',
        ]);
    }

    /**
     * Get vocabularies for an entity type
     */
    #[Route('GET', '/admin/vocabularies/for-entity/{entityType}')]
    public function getVocabulariesForEntity(string $entityType): JsonResponse
    {
        $vocabularies = $this->taxonomy->getVocabulariesForEntity($entityType);

        return new JsonResponse([
            'entity_type' => $entityType,
            'vocabularies' => array_map(fn($v) => array_merge($v->toArray(), [
                'options' => $this->taxonomy->getTermOptions($v->id, $v->hierarchical),
            ]), $vocabularies),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Term Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * List terms in vocabulary
     */
    #[Route('GET', '/admin/vocabularies/{vocabularyId}/terms')]
    public function listTerms(int $vocabularyId, Request $request): JsonResponse
    {
        $vocabulary = $this->taxonomy->getVocabulary($vocabularyId);

        if (!$vocabulary) {
            return new JsonResponse(['error' => 'Vocabulary not found'], 404);
        }

        $flat = $request->getQueryParams()['flat'] ?? null;

        if ($flat) {
            $terms = $this->taxonomy->getTerms($vocabularyId);
            return new JsonResponse([
                'vocabulary_id' => $vocabularyId,
                'terms' => array_map(fn($t) => $t->toArray(), $terms),
            ]);
        }

        // Return as tree
        $tree = $this->taxonomy->getTermTree($vocabularyId);

        return new JsonResponse([
            'vocabulary_id' => $vocabularyId,
            'vocabulary_name' => $vocabulary->name,
            'hierarchical' => $vocabulary->hierarchical,
            'terms' => $this->formatTermTree($tree),
        ]);
    }

    /**
     * Get single term
     */
    #[Route('GET', '/admin/terms/{id}')]
    public function showTerm(int $id): JsonResponse
    {
        $term = $this->taxonomy->getTerm($id);

        if (!$term) {
            return new JsonResponse(['error' => 'Term not found'], 404);
        }

        // Load children
        $term->children = $this->taxonomy->getChildTerms($id);

        return new JsonResponse(array_merge($term->toArray(), [
            'children' => array_map(fn($t) => $t->toArray(), $term->children),
            'usage_count' => $this->taxonomy->countTermEntities($id),
        ]));
    }

    /**
     * Create term
     */
    #[Route('POST', '/admin/vocabularies/{vocabularyId}/terms')]
    public function createTerm(int $vocabularyId, Request $request): JsonResponse
    {
        $vocabulary = $this->taxonomy->getVocabulary($vocabularyId);

        if (!$vocabulary) {
            return new JsonResponse(['error' => 'Vocabulary not found'], 404);
        }

        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return new JsonResponse(['errors' => ['name' => 'Name is required']], 422);
        }

        // Validate parent if hierarchical
        if (!empty($data['parent_id'])) {
            if (!$vocabulary->hierarchical) {
                return new JsonResponse(['errors' => ['parent_id' => 'Vocabulary does not support hierarchy']], 422);
            }

            $parent = $this->taxonomy->getTerm($data['parent_id']);
            if (!$parent || $parent->vocabulary_id !== $vocabularyId) {
                return new JsonResponse(['errors' => ['parent_id' => 'Invalid parent term']], 422);
            }
        }

        $term = new Term();
        $term->vocabulary_id = $vocabularyId;
        $term->parent_id = $data['parent_id'] ?? null;
        $term->name = $data['name'];
        $term->slug = $data['slug'] ?? '';
        $term->description = $data['description'] ?? '';
        $term->color = $data['color'] ?? null;
        $term->icon = $data['icon'] ?? null;
        $term->image = $data['image'] ?? null;
        $term->weight = $data['weight'] ?? 0;
        $term->is_published = $data['is_published'] ?? true;
        $term->metadata = $data['metadata'] ?? [];
        $term->meta_title = $data['meta_title'] ?? null;
        $term->meta_description = $data['meta_description'] ?? null;

        $this->taxonomy->saveTerm($term);

        return new JsonResponse([
            'success' => true,
            'message' => 'Term created successfully',
            'term' => $term->toArray(),
        ], 201);
    }

    /**
     * Update term
     */
    #[Route('PUT', '/admin/terms/{id}')]
    public function updateTerm(int $id, Request $request): JsonResponse
    {
        $term = $this->taxonomy->getTerm($id);

        if (!$term) {
            return new JsonResponse(['error' => 'Term not found'], 404);
        }

        $data = $request->getParsedBody();

        // Validate parent change
        if (isset($data['parent_id']) && $data['parent_id'] !== $term->parent_id) {
            $vocabulary = $this->taxonomy->getVocabulary($term->vocabulary_id);

            if ($data['parent_id'] !== null) {
                if (!$vocabulary->hierarchical) {
                    return new JsonResponse(['errors' => ['parent_id' => 'Vocabulary does not support hierarchy']], 422);
                }

                $parent = $this->taxonomy->getTerm($data['parent_id']);
                if (!$parent || $parent->vocabulary_id !== $term->vocabulary_id) {
                    return new JsonResponse(['errors' => ['parent_id' => 'Invalid parent term']], 422);
                }

                // Prevent circular reference
                if ($parent->isDescendantOf($term)) {
                    return new JsonResponse(['errors' => ['parent_id' => 'Cannot set descendant as parent']], 422);
                }
            }

            $term->parent_id = $data['parent_id'];
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
        if (isset($data['color'])) {
            $term->color = $data['color'];
        }
        if (isset($data['icon'])) {
            $term->icon = $data['icon'];
        }
        if (isset($data['image'])) {
            $term->image = $data['image'];
        }
        if (isset($data['weight'])) {
            $term->weight = $data['weight'];
        }
        if (isset($data['is_published'])) {
            $term->is_published = $data['is_published'];
        }
        if (isset($data['metadata'])) {
            $term->metadata = array_merge($term->metadata, $data['metadata']);
        }
        if (isset($data['meta_title'])) {
            $term->meta_title = $data['meta_title'];
        }
        if (isset($data['meta_description'])) {
            $term->meta_description = $data['meta_description'];
        }

        $this->taxonomy->saveTerm($term);

        return new JsonResponse([
            'success' => true,
            'message' => 'Term updated successfully',
            'term' => $term->toArray(),
        ]);
    }

    /**
     * Delete term
     */
    #[Route('DELETE', '/admin/terms/{id}')]
    public function deleteTerm(int $id, Request $request): JsonResponse
    {
        $term = $this->taxonomy->getTerm($id);

        if (!$term) {
            return new JsonResponse(['error' => 'Term not found'], 404);
        }

        $deleteChildren = filter_var(
            $request->getQueryParams()['delete_children'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $this->taxonomy->deleteTerm($term, $deleteChildren);

        return new JsonResponse([
            'success' => true,
            'message' => 'Term deleted successfully',
        ]);
    }

    /**
     * Search terms
     */
    #[Route('GET', '/admin/terms/search')]
    public function searchTerms(Request $request): JsonResponse
    {
        $query = $request->getQueryParams()['q'] ?? '';
        $vocabularyId = isset($request->getQueryParams()['vocabulary_id'])
            ? (int) $request->getQueryParams()['vocabulary_id']
            : null;
        $limit = (int) ($request->getQueryParams()['limit'] ?? 20);

        if (empty($query)) {
            return new JsonResponse(['terms' => []]);
        }

        $terms = $this->taxonomy->searchTerms($query, $vocabularyId, $limit);

        return new JsonResponse([
            'terms' => array_map(fn($t) => $t->toArray(), $terms),
        ]);
    }

    /**
     * Reorder terms
     */
    #[Route('PUT', '/admin/vocabularies/{vocabularyId}/terms/reorder')]
    public function reorderTerms(int $vocabularyId, Request $request): JsonResponse
    {
        $vocabulary = $this->taxonomy->getVocabulary($vocabularyId);

        if (!$vocabulary) {
            return new JsonResponse(['error' => 'Vocabulary not found'], 404);
        }

        $data = $request->getParsedBody();
        $order = $data['order'] ?? [];

        // Update weights based on order
        foreach ($order as $weight => $termId) {
            $term = $this->taxonomy->getTerm((int) $termId);
            if ($term && $term->vocabulary_id === $vocabularyId) {
                $term->weight = $weight;
                $this->taxonomy->saveTerm($term);
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Terms reordered successfully',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Entity-Term Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * Get terms for an entity
     */
    #[Route('GET', '/admin/entity-terms/{entityType}/{entityId}')]
    public function getEntityTerms(string $entityType, int $entityId): JsonResponse
    {
        $terms = $this->taxonomy->getEntityTerms($entityType, $entityId);

        // Group by vocabulary
        $grouped = [];
        foreach ($terms as $term) {
            $grouped[$term->vocabulary_id][] = $term->toArray();
        }

        return new JsonResponse([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'terms' => array_map(fn($t) => $t->toArray(), $terms),
            'by_vocabulary' => $grouped,
        ]);
    }

    /**
     * Set terms for an entity (by vocabulary)
     */
    #[Route('PUT', '/admin/entity-terms/{entityType}/{entityId}/{vocabularyId}')]
    public function setEntityTerms(
        string $entityType,
        int $entityId,
        int $vocabularyId,
        Request $request
    ): JsonResponse {
        $vocabulary = $this->taxonomy->getVocabulary($vocabularyId);

        if (!$vocabulary) {
            return new JsonResponse(['error' => 'Vocabulary not found'], 404);
        }

        $data = $request->getParsedBody();
        $termIds = $data['term_ids'] ?? [];

        $this->taxonomy->setEntityTerms($entityType, $entityId, $vocabularyId, $termIds);

        return new JsonResponse([
            'success' => true,
            'message' => 'Entity terms updated',
        ]);
    }

    /**
     * Add term to entity
     */
    #[Route('POST', '/admin/entity-terms/{entityType}/{entityId}')]
    public function addTermToEntity(string $entityType, int $entityId, Request $request): JsonResponse
    {
        $data = $request->getParsedBody();

        if (empty($data['term_id'])) {
            return new JsonResponse(['errors' => ['term_id' => 'Term ID is required']], 422);
        }

        $this->taxonomy->addTermToEntity($entityType, $entityId, $data['term_id'], $data['weight'] ?? null);

        return new JsonResponse([
            'success' => true,
            'message' => 'Term added to entity',
        ]);
    }

    /**
     * Remove term from entity
     */
    #[Route('DELETE', '/admin/entity-terms/{entityType}/{entityId}/{termId}')]
    public function removeTermFromEntity(string $entityType, int $entityId, int $termId): JsonResponse
    {
        $this->taxonomy->removeTermFromEntity($entityType, $entityId, $termId);

        return new JsonResponse([
            'success' => true,
            'message' => 'Term removed from entity',
        ]);
    }

    /**
     * Get term options for forms
     */
    #[Route('GET', '/admin/vocabularies/{vocabularyId}/options')]
    public function getTermOptions(int $vocabularyId): JsonResponse
    {
        $vocabulary = $this->taxonomy->getVocabulary($vocabularyId);

        if (!$vocabulary) {
            return new JsonResponse(['error' => 'Vocabulary not found'], 404);
        }

        $options = $this->taxonomy->getTermOptions($vocabularyId, $vocabulary->hierarchical);

        return new JsonResponse([
            'vocabulary_id' => $vocabularyId,
            'vocabulary_name' => $vocabulary->name,
            'multiple' => $vocabulary->multiple,
            'required' => $vocabulary->required,
            'options' => $options,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Format term tree for JSON response
     */
    private function formatTermTree(array $terms): array
    {
        return array_map(function (Term $term) {
            $data = $term->toArray();
            if (!empty($term->children)) {
                $data['children'] = $this->formatTermTree($term->children);
            }
            return $data;
        }, $terms);
    }
}
