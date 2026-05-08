<?php

declare(strict_types=1);

namespace App\Cms\Controller\JsonApi;

use App\Cms\Api\JsonApiFormatter;
use App\Cms\Api\QueryParser;
use App\Cms\Content\ContentEntity;
use App\Cms\Content\ContentRepository;
use App\Cms\Taxonomy\TaxonomyRepository;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ContentJsonApiController — Public JSON:API for content nodes.
 *
 * Endpoints:
 *   GET  /api/v1/nodes             — Collection (filterable, sortable, paginated)
 *   GET  /api/v1/nodes/{id}        — Single resource
 *   GET  /api/v1/nodes/{id}/terms  — Related taxonomy terms
 */
#[RoutePrefix('/api/v1/nodes')]
final class ContentJsonApiController
{
    private readonly JsonApiFormatter $jsonApi;

    public function __construct(
        private readonly ContentRepository $contentRepo,
        private readonly TaxonomyRepository $taxonomyRepo,
    ) {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/api/v1';
        $this->jsonApi = new JsonApiFormatter($baseUrl);
    }

    #[Route('GET', '/', name: 'api.v1.nodes.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $query = new QueryParser($request);

        $type = $query->getFilter('type', $query->getFilter('content_type'));
        $status = $query->getFilter('status', 'published');

        if (!$type) {
            return Response::json(
                $this->jsonApi->error(422, 'Missing filter', 'filter[type] is required.', '/data/filter/type'),
                422
            );
        }

        $nodes = $this->contentRepo->findByType(
            $type,
            $status,
            $query->perPage,
            $query->getOffset(),
            $query->getSortColumn(),
            $query->getSortDirection(),
        );
        $total = $this->contentRepo->countByType($type, $status);
        $lastPage = (int) ceil($total / $query->perPage);

        $items = array_map(fn(ContentEntity $n) => [
            'id' => $n->id,
            'attributes' => $query->sparseFields('nodes', $n->toArray()['attributes'] ?? $n->toArray()),
        ], $nodes);

        $included = [];
        if ($query->shouldInclude('terms')) {
            foreach ($nodes as $node) {
                $terms = $this->taxonomyRepo->findTermsForNode($node->id);
                foreach ($terms as $term) {
                    $included[] = [
                        'type' => 'terms',
                        'id' => (string) $term->id,
                        'attributes' => $term->toArray(),
                    ];
                }
            }
            // Deduplicate
            $seen = [];
            $included = array_values(array_filter($included, function ($i) use (&$seen) {
                $key = $i['type'] . ':' . $i['id'];
                if (isset($seen[$key])) return false;
                $seen[$key] = true;
                return true;
            }));
        }

        return Response::json($this->jsonApi->collection(
            'nodes',
            $items,
            $this->jsonApi->paginationMeta($total, $query->page, $query->perPage, $lastPage),
            $this->jsonApi->paginationLinks('nodes', $query->page, $lastPage, ['filter[type]' => $type]),
            $included,
        ));
    }

    #[Route('GET', '/{id:\d+}', name: 'api.v1.nodes.show')]
    public function show(ServerRequestInterface $request, string $id): Response
    {
        $query = new QueryParser($request);
        $node = $this->contentRepo->find((int) $id);

        if (!$node) {
            return Response::json($this->jsonApi->error(404, 'Not Found', "Node #{$id} does not exist."), 404);
        }

        $relationships = [];
        $included = [];

        if ($query->shouldInclude('terms')) {
            $terms = $this->taxonomyRepo->findTermsForNode($node->id);
            $relationships['terms'] = array_map(fn($t) => ['type' => 'terms', 'id' => $t->id], $terms);
            foreach ($terms as $term) {
                $included[] = ['type' => 'terms', 'id' => (string) $term->id, 'attributes' => $term->toArray()];
            }
        }

        $attributes = $query->sparseFields('nodes', $node->toArray()['attributes'] ?? $node->toArray());
        $response = $this->jsonApi->resource('nodes', $node->id, $attributes, $relationships);

        if ($included) {
            $response['included'] = $included;
        }

        return Response::json($response);
    }

    #[Route('GET', '/{id:\d+}/terms', name: 'api.v1.nodes.terms')]
    public function terms(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->find((int) $id);
        if (!$node) {
            return Response::json($this->jsonApi->error(404, 'Not Found'), 404);
        }

        $terms = $this->taxonomyRepo->findTermsForNode($node->id);
        $items = array_map(fn($t) => ['id' => $t->id, 'attributes' => $t->toArray()], $terms);

        return Response::json($this->jsonApi->collection('terms', $items));
    }
}
