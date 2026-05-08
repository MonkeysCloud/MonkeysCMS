<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use App\Cms\Search\SearchManager;
use App\Cms\Search\SearchQuery;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * SearchApiController — Public REST API for search.
 *
 * Provides JSON endpoints for frontend integration:
 * - GET  /api/search         → Full search with pagination, facets, highlights
 * - GET  /api/search/suggest  → Autocomplete suggestions
 * - GET  /api/search/facets   → Available facets for filtering UI
 *
 * CORS-friendly, framework-agnostic JSON responses.
 * Designed for integration with any JS frontend, SPA, or mobile app.
 */
#[RoutePrefix('/api/search')]
final class SearchApiController
{
    private readonly SearchManager $searchManager;

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->searchManager = $this->buildSearchManager();
    }

    // ── Full Search ─────────────────────────────────────────────────────

    /**
     * GET /api/search?q=query&type=article&page=1&per_page=10&sort=date&facets=1
     *
     * Full search endpoint with pagination, filtering, facets, and highlights.
     *
     * Query Parameters:
     *   q         - Search text (required for results)
     *   type      - Content type filter (article, page, etc.)
     *   status    - Status filter (default: published)
     *   page      - Page number (default: 1)
     *   per_page  - Results per page (default: 12, max: 100)
     *   sort      - Sort field: relevance|date|title (default: relevance)
     *   order     - Sort direction: asc|desc (default: desc)
     *   facets    - Include facets: 1|0 (default: 0)
     *   highlight - Include highlights: 1|0 (default: 1)
     *   lang      - Language filter
     *   fields    - Comma-separated search fields override
     */
    #[Route('GET', '/', name: 'api.search')]
    public function search(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $queryText = trim($params['q'] ?? '');
        $contentType = $params['type'] ?? null;
        $status = $params['status'] ?? 'published';
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 12)));
        $sort = $params['sort'] ?? 'relevance';
        $order = strtoupper($params['order'] ?? 'DESC');
        $includeFacets = ($params['facets'] ?? '0') === '1';
        $includeHighlight = ($params['highlight'] ?? '1') !== '0';
        $language = $params['lang'] ?? null;
        $fieldsParam = $params['fields'] ?? null;

        // Build query
        $query = new SearchQuery(
            text: $queryText,
            filters: array_filter([
                'status' => ($status !== 'all') ? $status : null,
                'content_type' => ($contentType && $contentType !== '') ? $contentType : null,
            ]),
            facetFields: $includeFacets ? ['content_type', 'status'] : [],
            sortField: match ($sort) {
                'date' => 'published_at',
                'title' => 'title',
                'created' => 'created_at',
                default => '_score',
            },
            sortDirection: in_array($order, ['ASC', 'DESC']) ? $order : 'DESC',
            highlight: $includeHighlight,
            highlightLength: 250,
            language: $language,
        );

        // Override search fields if specified
        if ($fieldsParam) {
            $fields = array_filter(array_map('trim', explode(',', $fieldsParam)));
            if ($fields !== []) {
                $query = $query->withSearchFields(...$fields);
            }
        }

        $query = $query->withPage($page, $perPage);

        // Empty query → return empty structured response
        if ($queryText === '') {
            return $this->jsonResponse([
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'page' => 1,
                    'per_page' => $perPage,
                    'total_pages' => 0,
                    'took_ms' => 0,
                    'query' => '',
                    'engine' => $this->searchManager->engine()->name(),
                ],
            ]);
        }

        $result = $this->searchManager->search($query);

        return $this->jsonResponse([
            'data' => array_map(fn($hit) => [
                'id' => $hit->id,
                'type' => $hit->type,
                'title' => $hit->title,
                'url' => $hit->url,
                'excerpt' => $hit->excerpt(250),
                'score' => round($hit->score, 4),
                'highlights' => $hit->highlights,
                'published_at' => $hit->publishedAt?->format('c'),
                'author' => $hit->author,
            ], $result->hits),
            'meta' => [
                'total' => $result->total,
                'page' => $result->currentPage(),
                'per_page' => $perPage,
                'total_pages' => $result->totalPages(),
                'took_ms' => $result->took,
                'query' => $queryText,
                'engine' => $result->engine,
            ],
            'facets' => $includeFacets ? $result->facets : null,
            'suggestions' => $result->suggestions ?: null,
            'links' => [
                'self' => $this->buildPaginationUrl($params, $page),
                'next' => $result->hasNextPage() ? $this->buildPaginationUrl($params, $page + 1) : null,
                'prev' => $result->hasPrevPage() ? $this->buildPaginationUrl($params, $page - 1) : null,
            ],
        ]);
    }

    // ── Autocomplete / Suggest ──────────────────────────────────────────

    /**
     * GET /api/search/suggest?q=prefix&limit=8
     *
     * Returns title suggestions for autocomplete inputs.
     */
    #[Route('GET', '/suggest', name: 'api.search.suggest')]
    public function suggest(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $prefix = trim($params['q'] ?? '');
        $limit = min(20, max(1, (int) ($params['limit'] ?? 8)));

        if ($prefix === '' || mb_strlen($prefix) < 2) {
            return $this->jsonResponse(['suggestions' => []]);
        }

        $suggestions = $this->searchManager->suggest($prefix, $limit);

        return $this->jsonResponse(['suggestions' => $suggestions]);
    }

    // ── Facets ──────────────────────────────────────────────────────────

    /**
     * GET /api/search/facets
     *
     * Returns available content types and counts for building filter UIs.
     */
    #[Route('GET', '/facets', name: 'api.search.facets')]
    public function facets(ServerRequestInterface $request): Response
    {
        try {
            $typeStmt = $this->pdo->query(
                "SELECT content_type, COUNT(*) AS count
                 FROM nodes
                 WHERE status = 'published' AND deleted_at IS NULL
                 GROUP BY content_type
                 ORDER BY count DESC"
            );
            $types = $typeStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $types = [];
        }

        return $this->jsonResponse([
            'content_types' => array_map(fn($row) => [
                'value' => $row['content_type'],
                'label' => ucfirst($row['content_type']),
                'count' => (int) $row['count'],
            ], $types),
        ]);
    }

    // ── Engine Info ─────────────────────────────────────────────────────

    /**
     * GET /api/search/info
     *
     * Returns search engine name and capabilities (for client feature detection).
     */
    #[Route('GET', '/info', name: 'api.search.info')]
    public function info(ServerRequestInterface $request): Response
    {
        $engine = $this->searchManager->engine();

        return $this->jsonResponse([
            'engine' => $engine->name(),
            'display_name' => $engine->displayName(),
            'available' => $engine->isAvailable(),
            'features' => [
                'suggest' => true,
                'facets' => true,
                'highlight' => true,
                'fuzzy' => $engine->name() === 'elasticsearch',
                'spellcheck' => in_array($engine->name(), ['elasticsearch', 'solr']),
            ],
        ]);
    }

    // ── Internal Helpers ────────────────────────────────────────────────

    private function buildSearchManager(): SearchManager
    {
        // Load search settings from DB
        $settings = [];
        try {
            $stmt = $this->pdo->query(
                "SELECT setting_key, setting_value FROM settings
                 WHERE setting_key LIKE 'search_%' OR setting_key LIKE 'elasticsearch_%' OR setting_key LIKE 'solr_%'"
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable) {}

        $config = ['engine' => $settings['search_engine'] ?? 'database'];

        if (!empty($settings['elasticsearch_host'])) {
            $config['elasticsearch'] = [
                'host' => $settings['elasticsearch_host'],
                'index' => $settings['elasticsearch_index'] ?? 'monkeyscms_content',
                'api_key' => $settings['elasticsearch_api_key'] ?: null,
            ];
        }

        if (!empty($settings['solr_host'])) {
            $config['solr'] = [
                'host' => $settings['solr_host'],
                'core' => $settings['solr_core'] ?? 'monkeyscms',
            ];
        }

        return SearchManager::create($this->pdo, $config);
    }

    private function jsonResponse(array $data, int $statusCode = 200): Response
    {
        $response = Response::json($data, $statusCode);

        return $response
            ->withHeader('Cache-Control', 'public, max-age=60')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept');
    }

    private function buildPaginationUrl(array $params, int $page): string
    {
        $params['page'] = $page;
        return '/api/search?' . http_build_query($params);
    }
}
