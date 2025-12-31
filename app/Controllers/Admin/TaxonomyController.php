<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Entities\Vocabulary;
use App\Modules\Core\Entities\Term;
use App\Modules\Core\Services\TaxonomyService;
use App\Modules\Core\Services\MenuService;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use MonkeysLegion\Template\MLView;

/**
 * TaxonomyController - Admin UI for vocabulary and term management
 */
final class TaxonomyController extends BaseAdminController
{
    public function __construct(
        protected readonly TaxonomyService $taxonomy,
        MLView $view,
        MenuService $menuService,
    ) {
        parent::__construct($view, $menuService);
    }

    // ─────────────────────────────────────────────────────────────
    // Vocabulary Management
    // ─────────────────────────────────────────────────────────────

    /**
     * List all vocabularies
     */
    #[Route('GET', '/admin/taxonomies')]
    public function index(): ResponseInterface
    {
        $vocabularies = $this->taxonomy->getAllVocabularies();

        // Calculate term counts for display
        $vocabulariesWithCounts = array_map(function ($v) {
            $v->term_count = count($this->taxonomy->getTerms($v->id));
            return $v;
        }, $vocabularies);

        return $this->render('admin.taxonomy.index', [
            'vocabularies' => $vocabulariesWithCounts,
        ]);
    }

    /**
     * Create Vocabulary Form
     */
    #[Route('GET', '/admin/taxonomies/create')]
    public function create(): ResponseInterface
    {
        return $this->render('admin.taxonomy.form', [
            'vocabulary' => new Vocabulary(),
            'isNew' => true,
        ]);
    }

    /**
     * Store Vocabulary
     */
    #[Route('POST', '/admin/taxonomies')]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        if (empty($data)) { $data = $_POST; }

        if (empty($data['name'])) {
            // TODO: Flash error
            return $this->redirect('/admin/taxonomies/create');
        }

        $machineName = !empty($data['machine_name'])
            ? $data['machine_name']
            : strtolower(preg_replace('/[^a-z0-9]+/', '_', $data['name']));

        if ($this->taxonomy->getVocabularyByMachineName($machineName)) {
            // TODO: Flash error
            return $this->redirect('/admin/taxonomies/create');
        }

        $vocabulary = new Vocabulary();
        $vocabulary->name = $data['name'];
        $vocabulary->machine_name = $machineName;
        $vocabulary->description = $data['description'] ?? '';
        $vocabulary->hierarchical = isset($data['hierarchical']); // Checkbox
        $vocabulary->multiple = isset($data['multiple']);
        $vocabulary->required = isset($data['required']);

        $this->taxonomy->saveVocabulary($vocabulary);

        return $this->redirect('/admin/taxonomies');
    }

    /**
     * Edit Vocabulary Form
     */
    #[Route('GET', '/admin/taxonomies/{id}/edit')]
    public function edit(int $id): ResponseInterface
    {
        $vocabulary = $this->taxonomy->getVocabulary($id);

        if (!$vocabulary) {
            return $this->redirect('/admin/taxonomies');
        }

        return $this->render('admin.taxonomy.form', [
            'vocabulary' => $vocabulary,
            'isNew' => false,
        ]);
    }

    /**
     * Update Vocabulary
     */
    #[Route('POST', '/admin/taxonomies/{id}')]
    public function update(int $id, ServerRequestInterface $request): ResponseInterface
    {
        $vocabulary = $this->taxonomy->getVocabulary($id);
        if (!$vocabulary) {
            return $this->redirect('/admin/taxonomies');
        }

        $data = (array) $request->getParsedBody();
        if (empty($data)) { $data = $_POST; }

        if (!empty($data['name'])) {
            $vocabulary->name = $data['name'];
        }
        
        if (!empty($data['machine_name'])) {
             // Check if changed and unique
             if ($data['machine_name'] !== $vocabulary->machine_name) {
                 if ($this->taxonomy->getVocabularyByMachineName($data['machine_name'])) {
                    // Unique error
                    return $this->redirect("/admin/taxonomies/{$id}/edit");
                 }
                 $vocabulary->machine_name = $data['machine_name'];
             }
        }

        $vocabulary->description = $data['description'] ?? '';
        $vocabulary->hierarchical = isset($data['hierarchical']);
        $vocabulary->multiple = isset($data['multiple']);
        $vocabulary->required = isset($data['required']);

        $this->taxonomy->saveVocabulary($vocabulary);

        return $this->redirect('/admin/taxonomies');
    }

    /**
     * Delete Vocabulary
     */
    #[Route('GET', '/admin/taxonomies/{id}/delete')]
    public function destroy(int $id): ResponseInterface
    {
        $vocabulary = $this->taxonomy->getVocabulary($id);
        if ($vocabulary) {
            $this->taxonomy->deleteVocabulary($vocabulary);
        }
        return $this->redirect('/admin/taxonomies');
    }

    // ─────────────────────────────────────────────────────────────
    // Term Management
    // ─────────────────────────────────────────────────────────────

    /**
     * List Terms for Vocabulary
     */
    #[Route('GET', '/admin/taxonomies/{vocabularyId}/terms')]
    public function termsIndex(int $vocabularyId): ResponseInterface
    {
        $vocabulary = $this->taxonomy->getVocabulary($vocabularyId);
        if (!$vocabulary) {
            return $this->redirect('/admin/taxonomies');
        }

        $tree = $this->taxonomy->getTermTree($vocabularyId);
        $flatTerms = $this->flattenTree($tree);

        return $this->render('admin.taxonomy.terms.index', [
            'vocabulary' => $vocabulary,
            'terms' => $flatTerms,
        ]);
    }

    /**
     * Create Term Form
     */
    #[Route('GET', '/admin/taxonomies/{vocabularyId}/terms/create')]
    public function termCreate(int $vocabularyId): ResponseInterface
    {
        $vocabulary = $this->taxonomy->getVocabulary($vocabularyId);
        if (!$vocabulary) {
            return $this->redirect('/admin/taxonomies');
        }

        // Get options for parent selection
        $termOptions = $this->taxonomy->getTermOptions($vocabularyId, true);

        return $this->render('admin.taxonomy.terms.form', [
            'vocabulary' => $vocabulary,
            'term' => new Term(),
            'isNew' => true,
            'termOptions' => $termOptions, // For parent select
        ]);
    }

    /**
     * Store Term
     */
    #[Route('POST', '/admin/taxonomies/{vocabularyId}/terms')]
    public function termStore(int $vocabularyId, ServerRequestInterface $request): ResponseInterface
    {
        $vocabulary = $this->taxonomy->getVocabulary($vocabularyId);
        if (!$vocabulary) {
            return $this->redirect('/admin/taxonomies');
        }

        $data = (array) $request->getParsedBody();
        if (empty($data)) { $data = $_POST; }

        if (empty($data['name'])) {
            return $this->redirect("/admin/taxonomies/{$vocabularyId}/terms/create");
        }

        $term = new Term();
        $term->vocabulary_id = $vocabularyId;
        $term->name = $data['name'];
        $term->slug = !empty($data['slug']) ? $data['slug'] : strtolower(preg_replace('/[^a-z0-9]+/', '-', $data['name']));
        $term->description = $data['description'] ?? '';
        $term->parent_id = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        $term->weight = (int) ($data['weight'] ?? 0);
        $term->is_published = isset($data['is_published']);

        $this->taxonomy->saveTerm($term);

        return $this->redirect("/admin/taxonomies/{$vocabularyId}/terms");
    }

    /**
     * Edit Term Form
     */
    #[Route('GET', '/admin/taxonomies/{vocabularyId}/terms/{id}/edit')]
    public function termEdit(int $vocabularyId, int $id): ResponseInterface
    {
        $vocabulary = $this->taxonomy->getVocabulary($vocabularyId);
        $term = $this->taxonomy->getTerm($id);

        if (!$vocabulary || !$term || $term->vocabulary_id !== $vocabularyId) {
            return $this->redirect("/admin/taxonomies/{$vocabularyId}/terms");
        }

        $termOptions = $this->taxonomy->getTermOptions($vocabularyId, true);

        return $this->render('admin.taxonomy.terms.form', [
            'vocabulary' => $vocabulary,
            'term' => $term,
            'isNew' => false,
            'termOptions' => $termOptions,
        ]);
    }

    /**
     * Update Term
     */
    #[Route('POST', '/admin/taxonomies/{vocabularyId}/terms/{id}')]
    public function termUpdate(int $vocabularyId, int $id, ServerRequestInterface $request): ResponseInterface
    {
        $term = $this->taxonomy->getTerm($id);
        if (!$term || $term->vocabulary_id !== $vocabularyId) {
            return $this->redirect("/admin/taxonomies/{$vocabularyId}/terms");
        }

        $data = (array) $request->getParsedBody();
        if (empty($data)) { $data = $_POST; }

        $term->name = $data['name'];
        if (!empty($data['slug'])) {
            $term->slug = $data['slug'];
        }
        $term->description = $data['description'] ?? '';
        $term->parent_id = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        $term->weight = (int) ($data['weight'] ?? 0);
        $term->is_published = isset($data['is_published']);

        // Prevent setting parent to itself
        if ($term->parent_id === $term->id) {
            $term->parent_id = null;
        }

        $this->taxonomy->saveTerm($term);

        return $this->redirect("/admin/taxonomies/{$vocabularyId}/terms");
    }

    /**
     * Delete Term
     */
    #[Route('GET', '/admin/taxonomies/{vocabularyId}/terms/{id}/delete')]
    public function termDestroy(int $vocabularyId, int $id): ResponseInterface
    {
        $term = $this->taxonomy->getTerm($id);
        if ($term && $term->vocabulary_id === $vocabularyId) {
            $this->taxonomy->deleteTerm($term);
        }
        return $this->redirect("/admin/taxonomies/{$vocabularyId}/terms");
    }

    private function redirect(string $url): ResponseInterface
    {
        return new RedirectResponse($url);
    }


    /**
     * Helper: Flatten tree to array with depth
     */
    private function flattenTree(array $terms, int $depth = 0): array
    {
        $result = [];
        foreach ($terms as $term) {
            $term->depth = $depth; // Ensure depth is set from tree structure
            $children = $term->children ?? [];
            $term->children = []; // clear children for flat view to avoid confusion if printed
            
            $result[] = $term;
            
            if (!empty($children)) {
                $result = array_merge($result, $this->flattenTree($children, $depth + 1));
            }
        }
        return $result;
    }
    /**
     * Reorder Terms (Live Update)
     */
    #[Route('POST', '/admin/taxonomies/{vocabularyId}/terms/reorder')]
    public function reorder(int $vocabularyId, ServerRequestInterface $request): ResponseInterface
    {
        $vocabulary = $this->taxonomy->getVocabulary($vocabularyId);
        if (!$vocabulary) {
            return new \Laminas\Diactoros\Response\JsonResponse(['error' => 'Vocabulary not found'], 404);
        }

        $data = json_decode((string) $request->getBody(), true);
        $orderedIds = $data['terms'] ?? [];

        if (!empty($orderedIds)) {
            foreach ($orderedIds as $index => $id) {
                $term = $this->taxonomy->getTerm((int)$id);
                if ($term && $term->vocabulary_id === $vocabularyId) {
                    $term->weight = $index;
                    $this->taxonomy->saveTerm($term);
                }
            }
        }

        return new \Laminas\Diactoros\Response\JsonResponse(['status' => 'ok']);
    }
}

