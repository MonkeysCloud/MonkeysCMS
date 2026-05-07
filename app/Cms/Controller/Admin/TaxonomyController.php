<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Form\FormBuilder;
use App\Cms\Form\FormRenderer;
use App\Cms\I18n\LanguageService;
use App\Cms\Slug\SlugManager;
use App\Cms\Taxonomy\TaxonomyRepository;
use App\Cms\Taxonomy\TermEntity;
use App\Cms\Taxonomy\VocabularyEntity;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * TaxonomyController — Admin UI for vocabularies and terms.
 */
#[RoutePrefix('/admin/taxonomy')]
final class TaxonomyController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly TaxonomyRepository $repo,
        private readonly SlugManager $slugManager,
        private readonly FormRenderer $formRenderer,
        private readonly SessionManager $session,
        private readonly LanguageService $languageService,
    ) {}

    // ══════════════════════════════════════════════════════════════════════
    // Vocabulary CRUD
    // ══════════════════════════════════════════════════════════════════════

    #[Route('GET', '/', name: 'admin::taxonomy.index')]
    public function index(): Response
    {
        $vocabularies = $this->repo->findAllVocabularies();

        $counts = [];
        foreach ($vocabularies as $vocab) {
            $counts[$vocab->id] = $this->repo->countTermsByVocabulary($vocab->id);
        }

        return Response::html($this->renderer->render('admin::taxonomy.index', [
            'title'        => 'Taxonomy',
            'vocabularies' => $vocabularies,
            'termCounts'   => $counts,
        ]));
    }

    #[Route('GET', '/create', name: 'admin::taxonomy.create')]
    public function create(): Response
    {
        $form = $this->buildVocabularyForm(null);

        return Response::html($this->renderer->render('admin::taxonomy.form', [
            'title'    => 'Create Vocabulary',
            'formHtml' => $this->formRenderer->render($form),
            'isNew'    => true,
        ]));
    }

    #[Route('POST', '/', name: 'admin::taxonomy.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $data = (array) $request->getParsedBody();
        $vocab = new VocabularyEntity();
        $vocab->hydrate([
            'machine_name' => $this->machineNameFromLabel($data['label'] ?? ''),
            'label'        => $data['label'] ?? '',
            'description'  => $data['description'] ?? null,
            'hierarchical' => !empty($data['hierarchical']),
            'multiple'     => !empty($data['multiple']),
            'weight'       => (int) ($data['weight'] ?? 0),
        ]);

        $this->repo->persistVocabulary($vocab);

        return Response::redirect('/admin/taxonomy?success=' . urlencode('Vocabulary created.'));
    }

    #[Route('GET', '/{id:\d+}/edit', name: 'admin::taxonomy.edit')]
    public function edit(ServerRequestInterface $request, string $id): Response
    {
        $vocab = $this->repo->findVocabularyById((int) $id);
        if (!$vocab) {
            return Response::redirect('/admin/taxonomy?error=' . urlencode('Vocabulary not found.'));
        }

        $form = $this->buildVocabularyForm($vocab);

        return Response::html($this->renderer->render('admin::taxonomy.form', [
            'title'      => 'Edit Vocabulary: ' . $vocab->label,
            'vocabulary' => $vocab,
            'formHtml'   => $this->formRenderer->render($form),
            'isNew'      => false,
        ]));
    }

    #[Route('POST', '/{id:\d+}', name: 'admin::taxonomy.update')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        $vocab = $this->repo->findVocabularyById((int) $id);
        if (!$vocab) {
            return Response::redirect('/admin/taxonomy?error=' . urlencode('Vocabulary not found.'));
        }

        $data = (array) $request->getParsedBody();
        $vocab->hydrate([
            'label'        => $data['label'] ?? $vocab->label,
            'description'  => $data['description'] ?? $vocab->description,
            'hierarchical' => !empty($data['hierarchical']),
            'multiple'     => !empty($data['multiple']),
            'weight'       => (int) ($data['weight'] ?? $vocab->weight),
        ]);

        $this->repo->persistVocabulary($vocab);

        return Response::redirect('/admin/taxonomy?success=' . urlencode('Vocabulary updated.'));
    }

    #[Route('POST', '/{id:\d+}/delete', name: 'admin::taxonomy.delete')]
    public function destroy(ServerRequestInterface $request, string $id): Response
    {
        $this->repo->deleteVocabulary((int) $id);
        return Response::redirect('/admin/taxonomy?success=' . urlencode('Vocabulary deleted.'));
    }

    // ══════════════════════════════════════════════════════════════════════
    // Term CRUD
    // ══════════════════════════════════════════════════════════════════════

    #[Route('GET', '/{vocabId:\d+}/terms', name: 'admin::taxonomy.terms')]
    public function terms(ServerRequestInterface $request, string $vocabId): Response
    {
        $vocab = $this->repo->findVocabularyById((int) $vocabId);
        if (!$vocab) {
            return Response::redirect('/admin/taxonomy?error=' . urlencode('Vocabulary not found.'));
        }

        $terms = $this->repo->findTermsByVocabulary($vocab->id);

        return Response::html($this->renderer->render('admin::taxonomy.terms', [
            'title'      => $vocab->label . ' — Terms',
            'vocabulary' => $vocab,
            'terms'      => $terms,
        ]));
    }

    #[Route('GET', '/{vocabId:\d+}/terms/create', name: 'admin::taxonomy.terms.create')]
    public function createTerm(ServerRequestInterface $request, string $vocabId): Response
    {
        $vocab = $this->repo->findVocabularyById((int) $vocabId);
        if (!$vocab) {
            return Response::redirect('/admin/taxonomy?error=' . urlencode('Vocabulary not found.'));
        }

        $allTermsTree = $this->repo->findTermsByVocabulary($vocab->id);
        $form = $this->buildTermForm($vocab, null, $allTermsTree);

        return Response::html($this->renderer->render('admin::taxonomy.term-form', [
            'title'      => 'Add Term to ' . $vocab->label,
            'vocabulary' => $vocab,
            'term'       => null,
            'formHtml'   => $this->formRenderer->render($form),
            'isNew'      => true,
        ]));
    }

    #[Route('POST', '/{vocabId:\d+}/terms', name: 'admin::taxonomy.terms.store')]
    public function storeTerm(ServerRequestInterface $request, string $vocabId): Response
    {
        $vocab = $this->repo->findVocabularyById((int) $vocabId);
        if (!$vocab) {
            return Response::redirect('/admin/taxonomy?error=' . urlencode('Vocabulary not found.'));
        }

        $data = (array) $request->getParsedBody();
        $term = new TermEntity();
        $term->hydrate([
            'vocabulary_id' => $vocab->id,
            'parent_id'     => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            'name'          => $data['name'] ?? '',
            'description'   => $data['description'] ?? null,
            'language'      => $data['language'] ?? $this->languageService->getDefaultCode(),
            'weight'        => (int) ($data['weight'] ?? 0),
        ]);

        // Generate slug
        $slug = $this->slugManager->generateTermSlug($term, $vocab->machine_name);
        $slug = $this->slugManager->ensureTermUnique($slug, $vocab->id);
        $term->slug = $slug;

        $this->repo->persistTerm($term);

        return Response::redirect("/admin/taxonomy/{$vocab->id}/terms?success=" . urlencode('Term created.'));
    }

    #[Route('GET', '/{vocabId:\d+}/terms/{id:\d+}/edit', name: 'admin::taxonomy.terms.edit')]
    public function editTerm(ServerRequestInterface $request, string $vocabId, string $id): Response
    {
        $vocab = $this->repo->findVocabularyById((int) $vocabId);
        if (!$vocab) {
            return Response::redirect('/admin/taxonomy?error=' . urlencode('Vocabulary not found.'));
        }

        $term = $this->repo->findTerm((int) $id);
        if (!$term) {
            return Response::redirect("/admin/taxonomy/{$vocab->id}/terms?error=" . urlencode('Term not found.'));
        }

        $allTermsTree = $this->repo->findTermsByVocabulary($vocab->id);
        $form = $this->buildTermForm($vocab, $term, $allTermsTree);

        return Response::html($this->renderer->render('admin::taxonomy.term-form', [
            'title'      => 'Edit Term: ' . $term->name,
            'vocabulary' => $vocab,
            'term'       => $term,
            'formHtml'   => $this->formRenderer->render($form),
            'isNew'      => false,
        ]));
    }

    #[Route('POST', '/{vocabId:\d+}/terms/{id:\d+}', name: 'admin::taxonomy.terms.update')]
    public function updateTerm(ServerRequestInterface $request, string $vocabId, string $id): Response
    {
        $vocab = $this->repo->findVocabularyById((int) $vocabId);
        $term = $this->repo->findTerm((int) $id);

        if (!$vocab || !$term) {
            return Response::redirect('/admin/taxonomy?error=' . urlencode('Not found.'));
        }

        $data = (array) $request->getParsedBody();
        $term->hydrate([
            'parent_id'   => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            'name'        => $data['name'] ?? $term->name,
            'description' => $data['description'] ?? $term->description,
            'language'    => $data['language'] ?? $term->language,
            'weight'      => (int) ($data['weight'] ?? $term->weight),
        ]);

        // Regenerate slug if name changed or manual slug provided
        if (!empty($data['slug'])) {
            $term->slug = $data['slug'];
        } else {
            $slug = $this->slugManager->generateTermSlug($term, $vocab->machine_name);
            $slug = $this->slugManager->ensureTermUnique($slug, $vocab->id, $term->id);
            $term->slug = $slug;
        }

        $this->repo->persistTerm($term);

        return Response::redirect("/admin/taxonomy/{$vocab->id}/terms?success=" . urlencode('Term updated.'));
    }

    #[Route('POST', '/{vocabId:\d+}/terms/{id:\d+}/delete', name: 'admin::taxonomy.terms.delete')]
    public function deleteTerm(ServerRequestInterface $request, string $vocabId, string $id): Response
    {
        $this->repo->deleteTerm((int) $id);
        return Response::redirect("/admin/taxonomy/{$vocabId}/terms?success=" . urlencode('Term deleted.'));
    }

    #[Route('POST', '/{vocabId:\d+}/terms/reorder', name: 'admin::taxonomy.terms.reorder')]
    public function reorderTerms(ServerRequestInterface $request, string $vocabId): Response
    {
        $body = json_decode((string) $request->getBody(), true);
        $items = $body['items'] ?? [];

        if (is_array($items) && !empty($items)) {
            $this->repo->reorderTree($items);
        }

        return Response::json(['ok' => true]);
    }

    /**
     * POST /admin/taxonomy/{vocabId}/terms/bulk — JSON API for bulk term creation (AI).
     */
    #[Route('POST', '/{vocabId:\d+}/terms/bulk', name: 'admin::taxonomy.terms.bulk')]
    public function bulkStoreTerms(ServerRequestInterface $request, string $vocabId): Response
    {
        $vocab = $this->repo->findVocabularyById((int) $vocabId);
        if (!$vocab) {
            return Response::json(['error' => 'Vocabulary not found'], 404);
        }

        $body = json_decode((string) $request->getBody(), true);
        $terms = $body['terms'] ?? [];

        if (!is_array($terms) || empty($terms)) {
            return Response::json(['error' => 'No terms provided'], 422);
        }

        $created = 0;
        $errors = [];
        // Map term name → persisted ID for parent resolution
        $nameToId = [];

        // First pass: create root terms (no parent)
        // Second pass: create children (with parent)
        $roots = [];
        $children = [];

        foreach ($terms as $i => $termData) {
            $parentName = $termData['parent'] ?? null;
            if (empty($parentName)) {
                $roots[] = ['data' => $termData, 'index' => $i];
            } else {
                $children[] = ['data' => $termData, 'index' => $i, 'parentName' => $parentName];
            }
        }

        // Create roots first
        foreach ($roots as $item) {
            try {
                $saved = $this->createSingleTerm($vocab, $item['data'], null, $item['index']);
                $nameToId[strtolower($saved->name)] = $saved->id;
                $created++;
            } catch (\Exception $e) {
                $errors[] = "Error creating root term '{$item['data']['name']}': " . $e->getMessage();
            }
        }

        // Create children, resolving parent name → ID
        foreach ($children as $item) {
            try {
                $parentNameLower = strtolower($item['parentName']);
                $parentId = $nameToId[$parentNameLower] ?? null;

                // If parent wasn't in this batch, try finding it in DB
                if (!$parentId) {
                    $existing = $this->repo->findTermBySlug($vocab->id, $this->slugManager->slugify($item['parentName']));
                    if ($existing) {
                        $parentId = $existing->id;
                    }
                }
                
                $saved = $this->createSingleTerm($vocab, $item['data'], $parentId, $item['index']);
                $nameToId[strtolower($saved->name)] = $saved->id;
                $created++;
            } catch (\Exception $e) {
                $errors[] = "Error creating child term '{$item['data']['name']}': " . $e->getMessage();
            }
        }

        return Response::json([
            'message' => "Created $created terms",
            'created' => $created,
            'errors'  => $errors,
        ]);
    }

    private function createSingleTerm(VocabularyEntity $vocab, array $data, ?int $parentId, int $weight): TermEntity
    {
        $term = new TermEntity();
        $term->hydrate([
            'vocabulary_id' => $vocab->id,
            'parent_id'     => $parentId,
            'name'          => $data['name'] ?? '',
            'description'   => $data['description'] ?? null,
            'weight'        => $weight,
        ]);

        $slug = $this->slugManager->generateTermSlug($term, $vocab->machine_name);
        $term->slug = $this->slugManager->ensureTermUnique($slug, $vocab->id);

        return $this->repo->persistTerm($term);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Form Builders
    // ══════════════════════════════════════════════════════════════════════

    private function buildVocabularyForm(?VocabularyEntity $vocab): \App\Cms\Form\Form
    {
        $isNew = $vocab === null;
        $action = $isNew ? '/admin/taxonomy' : "/admin/taxonomy/{$vocab->id}";

        $builder = FormBuilder::create($action, 'POST')
            ->layout('two-column')
            ->group('details', 'Vocabulary Details', 'tag')
            ->group('settings', 'Settings', 'settings')

            // Details group
            ->text('label', 'Name')
                ->value($vocab->label ?? '')
                ->placeholder('e.g. Categories, Tags, Topics...')
                ->help('Human-readable name for this vocabulary.')
                ->required()
                ->inGroup('details')

            ->textarea('description', 'Description')
                ->value($vocab->description ?? '')
                ->placeholder('Brief description of this vocabulary...')
                ->inGroup('details');

        if (!$isNew) {
            $builder->html(
                '<div class="form-group">'
                . '<label class="form-label">Machine Name</label>'
                . '<div class="form-static"><code>' . htmlspecialchars($vocab->machine_name) . '</code></div>'
                . '<span class="form-hint">Used internally. Cannot be changed after creation.</span>'
                . '</div>'
            )->inGroup('details');
        }

        $builder
            // Settings group
            ->toggle('hierarchical', 'Hierarchical')
                ->value($vocab->hierarchical ?? false)
                ->help('Allow terms to have parent/child relationships (like categories).')
                ->inGroup('settings')

            ->toggle('multiple', 'Allow Multiple')
                ->value($vocab->multiple ?? true)
                ->help('Allow content to select multiple terms from this vocabulary.')
                ->inGroup('settings')

            ->number('weight', 'Weight')
                ->value($vocab->weight ?? 0)
                ->help('Lower weight = appears first in listings.')
                ->inGroup('settings')

            ->submit($isNew ? 'Create Vocabulary' : 'Save Changes', 'save')
            ->cancel('/admin/taxonomy');

        return $builder->build($this->session);
    }

    /**
     * @param TermEntity[] $allTermsTree  Hierarchical list for parent select
     */
    private function buildTermForm(VocabularyEntity $vocab, ?TermEntity $term, array $allTermsTree): \App\Cms\Form\Form
    {
        $isNew = $term === null;
        $action = $isNew
            ? "/admin/taxonomy/{$vocab->id}/terms"
            : "/admin/taxonomy/{$vocab->id}/terms/{$term->id}";

        $builder = FormBuilder::create($action, 'POST')
            ->layout('two-column')
            ->group('main', 'Term Details', 'tag')
            ->group('meta', 'Options', 'settings')

            // Main group
            ->text('name', 'Name')
                ->value($term->name ?? '')
                ->placeholder('Term name...')
                ->required()
                ->help('The name displayed for this term.')
                ->inGroup('main')

            ->textarea('description', 'Description')
                ->value($term->description ?? '')
                ->placeholder('Optional description...')
                ->inGroup('main');

        // URL Alias (slug) — show in meta sidebar
        if (!$isNew && $term) {
            $builder->html(
                '<div class="form-group">'
                . '<label class="form-label"><i data-lucide="link" class="w-3.5 h-3.5" style="display:inline;vertical-align:-2px;margin-right:4px;"></i>URL Alias</label>'
                . '<div class="slug-input" style="display:flex;align-items:center;background:rgba(0,0,0,0.15);border:1px solid rgba(255,255,255,0.08);border-radius:8px;overflow:hidden;">'
                . '<span style="padding:0 0.5rem 0 0.75rem;color:#64748b;font-family:monospace;font-size:0.85rem;white-space:nowrap;">/' . htmlspecialchars($vocab->machine_name) . '/</span>'
                . '<input type="text" name="slug" class="form-input" value="' . htmlspecialchars($term->slug ?? '') . '" placeholder="auto-generated" style="border:none!important;background:transparent!important;font-family:\'JetBrains Mono\',monospace;font-size:0.85rem;padding-left:0!important;">'
                . '</div>'
                . '<span class="form-hint">Leave blank to auto-generate from name using the URL pattern.</span>'
                . '</div>'
            )->inGroup('meta');
        } else {
            $builder->text('slug', 'Slug')
                ->value('')
                ->placeholder('Leave blank to auto-generate')
                ->help('URL-friendly version. Auto-generated from name if left empty.')
                ->inGroup('main');
        }

        // Parent select (only for hierarchical vocabularies)
        if ($vocab->hierarchical) {
            $parentOptions = ['' => '— No parent (root level) —'];
            $parentOptions += $this->flattenTreeForSelect($allTermsTree, $term);

            $builder->select('parent_id', 'Parent Term', $parentOptions)
                ->value($term->parent_id ?? '')
                ->help('Choose a parent to nest this term.')
                ->inGroup('meta');
        }

        $builder
            ->number('weight', 'Weight')
                ->value($term->weight ?? 0)
                ->help('Controls display order. Lower = first.')
                ->inGroup('meta');

        // Language select (only when multilingual is enabled)
        if ($this->languageService->isEnabled()) {
            $langOptions = [];
            foreach ($this->languageService->getEnabled() as $lang) {
                $langOptions[$lang->code] = $lang->flagEmoji . ' ' . $lang->native . ' (' . $lang->code . ')';
            }
            $builder->select('language', 'Language', $langOptions)
                ->value($term->language ?? $this->languageService->getDefaultCode())
                ->help('Language for this term.')
                ->inGroup('meta');
        }

        $builder
            ->submit($isNew ? 'Create Term' : 'Save Changes', 'save')
            ->cancel("/admin/taxonomy/{$vocab->id}/terms");

        return $builder->build($this->session);
    }

    // ── Private helpers ─────────────────────────────────────────────────

    /**
     * Flatten a tree of terms into a depth-prefixed associative array for select dropdowns.
     * @param TermEntity[] $tree
     */
    private function flattenTreeForSelect(array $tree, ?TermEntity $currentTerm, int $depth = 0): array
    {
        $options = [];
        $prefix = str_repeat('— ', $depth);
        if ($depth > 0) {
            $prefix = str_repeat('&nbsp;&nbsp;&nbsp;', $depth) . '↳ ';
        }

        foreach ($tree as $node) {
            if ($currentTerm && $node->id === $currentTerm->id) {
                // Prevent selecting self (or children) as parent
                continue;
            }
            $options[(string) $node->id] = html_entity_decode($prefix) . $node->name;
            if (!empty($node->children)) {
                $options += $this->flattenTreeForSelect($node->children, $currentTerm, $depth + 1);
            }
        }
        return $options;
    }

    private function machineNameFromLabel(string $label): string
    {
        $name = strtolower(trim(preg_replace('/[^a-z0-9_]+/', '_', strtolower($label)), '_'));
        return $name ?: 'vocabulary';
    }
}
