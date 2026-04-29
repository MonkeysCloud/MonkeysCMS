<?php

declare(strict_types=1);

namespace App\Cms\Controller\JsonApi;

use App\Cms\Api\JsonApiFormatter;
use App\Cms\Api\QueryParser;
use App\Cms\Content\ContentEntity;
use App\Cms\Content\ContentRepository;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * SearchJsonApiController — Public JSON:API for full-text content search.
 *
 * Supports:
 *   - Full-text search across title and body
 *   - Content type filtering
 *   - Pagination
 */
#[RoutePrefix('/api/v1/search')]
final class SearchJsonApiController
{
    private readonly JsonApiFormatter $jsonApi;

    public function __construct(
        private readonly ContentRepository $contentRepo,
    ) {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/api/v1';
        $this->jsonApi = new JsonApiFormatter($baseUrl);
    }

    #[Route('GET', '/', name: 'api.v1.search')]
    public function search(ServerRequestInterface $request): Response
    {
        $query = new QueryParser($request);
        $params = $request->getQueryParams();

        $searchQuery = trim($params['q'] ?? '');

        if (strlen($searchQuery) < 2) {
            return Response::json(
                $this->jsonApi->error(422, 'Invalid Query', 'Search query must be at least 2 characters.', '/data/q'),
                422
            );
        }

        $type = $query->getFilter('type');
        $limit = min(50, $query->perPage);

        $nodes = $this->contentRepo->search($searchQuery, $type, $limit);

        $data = array_map(fn(ContentEntity $n) => [
            'id' => $n->id,
            'attributes' => $query->sparseFields('nodes', array_merge(
                $n->toArray()['attributes'] ?? $n->toArray(),
                ['_score' => $this->relevanceScore($n, $searchQuery)],
            )),
        ], $nodes);

        return Response::json($this->jsonApi->collection(
            'nodes',
            $data,
            [
                'query' => $searchQuery,
                'total' => count($data),
                'type_filter' => $type,
            ],
        ));
    }

    /**
     * Compute a basic relevance score for search results
     */
    private function relevanceScore(ContentEntity $node, string $query): float
    {
        $score = 0.0;
        $q = strtolower($query);

        // Title match (highest weight)
        if (str_contains(strtolower($node->title), $q)) {
            $score += 10.0;
            if (strtolower($node->title) === $q) $score += 5.0; // exact match bonus
        }

        // Body match
        if ($node->body && str_contains(strtolower($node->body), $q)) {
            $score += 3.0;
            // More occurrences = higher score
            $score += min(5, substr_count(strtolower($node->body), $q)) * 0.5;
        }

        // Summary match
        if ($node->summary && str_contains(strtolower($node->summary), $q)) {
            $score += 2.0;
        }

        // Recency boost (max 2 points for content from the last 7 days)
        if ($node->published_at) {
            $daysSince = max(0, (time() - $node->published_at->getTimestamp()) / 86400);
            $score += max(0, 2.0 - ($daysSince / 3.5));
        }

        return round($score, 2);
    }
}
