<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Content\ContentTypeManager;
use App\Cms\Slug\SlugManager;
use App\Cms\Slug\SlugTokenizer;
use App\Cms\Taxonomy\TaxonomyRepository;
use App\Cms\Url\ContentUrlResolver;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * SlugController — Admin UI for URL alias pattern management.
 *
 * Provides CRUD for per-type slug patterns, bulk regeneration,
 * and a browsable list of all node/term URL aliases.
 */
final class SlugController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly SlugManager $slugManager,
        private readonly ContentTypeManager $typeManager,
        private readonly ContentUrlResolver $urlResolver,
        private readonly TaxonomyRepository $taxonomyRepo,
        private readonly SessionManager $session,
    ) {}

    /**
     * GET /admin/url-aliases — Main management page with tabs.
     */
    #[Route('GET', '/admin/url-aliases', name: 'admin.slug.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $tab = $params['tab'] ?? 'content';
        $page = max(1, (int) ($params['page'] ?? 1));
        $filterType = $params['type'] ?? null;
        $perPage = 25;

        // Load content types and their patterns
        $contentTypes = $this->typeManager->getEnabled();
        $patterns = $this->slugManager->getAllPatterns();

        // Index patterns by entity_type:bundle for easy lookup
        $patternMap = [];
        foreach ($patterns as $p) {
            $patternMap[$p->entity_type . ':' . $p->bundle] = $p;
        }

        // Available tokens for the UI reference
        $tokens = SlugTokenizer::TOKENS;

        // Get compiled URL patterns for preview
        $resolvedPatterns = $this->urlResolver->getPatterns();

        // Load all vocabularies for taxonomy patterns
        $vocabularies = $this->taxonomyRepo->findAllVocabularies();

        // Load paginated data based on active tab
        $nodeData = $this->slugManager->getNodeAliases($filterType, $tab === 'content' ? $page : 1, $perPage);
        $termData = $this->slugManager->getTermAliases(null, $tab === 'terms' ? $page : 1, $perPage);

        return Response::html($this->renderer->render('slug.index', [
            'contentTypes'      => $contentTypes,
            'patternMap'        => $patternMap,
            'aliases'           => $nodeData['items'],
            'nodePagination'    => $nodeData['pagination'],
            'termAliases'       => $termData['items'],
            'termPagination'    => $termData['pagination'],
            'tokens'            => $tokens,
            'filterType'        => $filterType,
            'resolvedPatterns'  => $resolvedPatterns,
            'vocabularies'      => $vocabularies,
            'activeTab'         => $tab,
            'flashSuccess'      => $this->session->getFlash('slug_success'),
            'flashError'        => $this->session->getFlash('slug_error'),
        ]));
    }

    /**
     * POST /admin/url-aliases/patterns — Save pattern for a type.
     */
    #[Route('POST', '/admin/url-aliases/patterns', name: 'admin.slug.save_pattern')]
    public function savePattern(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);

        $entityType = $body['entity_type'] ?? 'node';
        $bundle = $body['bundle'] ?? '';
        $pattern = trim($body['pattern'] ?? '[title]');

        if (empty($bundle)) {
            $this->session->flash('slug_error', 'Bundle is required.');
            return Response::redirect('/admin/url-aliases');
        }

        // Validate pattern has at least one token (supports colon-separated names)
        if (!preg_match('/\[[a-z_:]+]/', $pattern)) {
            $this->session->flash('slug_error', 'Pattern must contain at least one token.');
            return Response::redirect('/admin/url-aliases');
        }

        $this->slugManager->savePattern($entityType, $bundle, $pattern);

        $this->session->flash('slug_success', 'Pattern saved successfully.');
        return Response::redirect('/admin/url-aliases');
    }

    /**
     * POST /admin/url-aliases/regenerate — Bulk regenerate slugs.
     */
    #[Route('POST', '/admin/url-aliases/regenerate', name: 'admin.slug.regenerate')]
    public function regenerate(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);

        $entityType = $body['entity_type'] ?? 'node';
        $bundle = $body['bundle'] ?? null;

        $count = $this->slugManager->regenerateAll($entityType, $bundle ?: null);

        $typeLabel = $bundle ? " ({$bundle})" : '';
        $this->session->flash('slug_success', "Regenerated {$count} slug(s){$typeLabel}.");

        return Response::redirect('/admin/url-aliases');
    }

    /**
     * POST /admin/url-aliases/{id}/update — Update a single node slug.
     */
    #[Route('POST', '/admin/url-aliases/{id:\d+}/update', name: 'admin.slug.update_single')]
    public function updateSingle(ServerRequestInterface $request, string $id): Response
    {
        $body = $this->parseBody($request);
        $newSlug = trim($body['slug'] ?? '');

        if (empty($newSlug)) {
            $this->session->flash('slug_error', 'Slug cannot be empty.');
            return Response::redirect('/admin/url-aliases');
        }

        $this->slugManager->updateNodeSlug((int) $id, $newSlug);

        $this->session->flash('slug_success', 'Slug updated.');
        return Response::redirect('/admin/url-aliases');
    }

    /**
     * POST /admin/url-aliases/patterns/{id}/delete — Remove a pattern.
     */
    #[Route('POST', '/admin/url-aliases/patterns/{id:\d+}/delete', name: 'admin.slug.delete_pattern')]
    public function deletePattern(ServerRequestInterface $request, string $id): Response
    {
        $this->slugManager->deletePattern((int) $id);

        $this->session->flash('slug_success', 'Pattern removed. Default will be used.');
        return Response::redirect('/admin/url-aliases');
    }

    // ── Private ─────────────────────────────────────────────────────

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody() ?? [];

        if (empty($body)) {
            $stream = $request->getBody();
            $stream->rewind();
            $raw = $stream->getContents();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return $body;
    }
}
