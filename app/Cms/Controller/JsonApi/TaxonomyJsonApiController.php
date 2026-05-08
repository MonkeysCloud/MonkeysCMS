<?php

declare(strict_types=1);

namespace App\Cms\Controller\JsonApi;

use App\Cms\Api\JsonApiFormatter;
use App\Cms\Api\QueryParser;
use App\Cms\Taxonomy\TaxonomyRepository;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * TaxonomyJsonApiController — Public JSON:API for vocabularies and terms.
 */
#[RoutePrefix('/api/v1/taxonomy')]
final class TaxonomyJsonApiController
{
    private readonly JsonApiFormatter $jsonApi;

    public function __construct(
        private readonly TaxonomyRepository $taxonomyRepo,
    ) {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/api/v1';
        $this->jsonApi = new JsonApiFormatter($baseUrl);
    }

    #[Route('GET', '/vocabularies', name: 'api.v1.vocabularies.index')]
    public function vocabularies(ServerRequestInterface $request): Response
    {
        $vocabs = $this->taxonomyRepo->findAllVocabularies();

        $data = array_map(fn($v) => [
            'id' => $v->id,
            'attributes' => $v->toArray(),
        ], $vocabs);

        return Response::json($this->jsonApi->collection('vocabularies', $data));
    }

    #[Route('GET', '/vocabularies/{name:[a-z0-9_-]+}', name: 'api.v1.vocabularies.show')]
    public function vocabulary(ServerRequestInterface $request, string $name): Response
    {
        $vocab = $this->taxonomyRepo->findVocabulary($name);

        if (!$vocab) {
            return Response::json($this->jsonApi->error(404, 'Not Found', "Vocabulary '{$name}' does not exist."), 404);
        }

        return Response::json($this->jsonApi->resource('vocabularies', $vocab->id, $vocab->toArray()));
    }

    #[Route('GET', '/vocabularies/{name:[a-z0-9_-]+}/terms', name: 'api.v1.vocabularies.terms')]
    public function terms(ServerRequestInterface $request, string $name): Response
    {
        $vocab = $this->taxonomyRepo->findVocabulary($name);

        if (!$vocab) {
            return Response::json($this->jsonApi->error(404, 'Not Found'), 404);
        }

        $terms = $this->taxonomyRepo->findTermsByVocabulary($vocab->id);

        // Flatten the tree for JSON:API (each term includes parent_id for reconstruction)
        $flat = [];
        $this->flattenTerms($terms, $flat);

        $data = array_map(fn($t) => [
            'id' => $t->id,
            'attributes' => $t->toArray(),
        ], $flat);

        return Response::json($this->jsonApi->collection(
            'terms',
            $data,
            ['total' => count($flat), 'vocabulary' => $name, 'hierarchical' => $vocab->hierarchical],
        ));
    }

    #[Route('GET', '/terms/{id:\d+}', name: 'api.v1.terms.show')]
    public function term(ServerRequestInterface $request, string $id): Response
    {
        $term = $this->taxonomyRepo->findTerm((int) $id);

        if (!$term) {
            return Response::json($this->jsonApi->error(404, 'Not Found'), 404);
        }

        $relationships = [];
        if ($term->parent_id) {
            $relationships['parent'] = ['type' => 'terms', 'id' => $term->parent_id];
        }

        return Response::json($this->jsonApi->resource('terms', $term->id, $term->toArray(), $relationships));
    }

    private function flattenTerms(array $terms, array &$flat): void
    {
        foreach ($terms as $term) {
            $flat[] = $term;
            if (!empty($term->children)) {
                $this->flattenTerms($term->children, $flat);
            }
        }
    }
}
