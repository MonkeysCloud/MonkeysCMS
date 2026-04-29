<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use App\Cms\Content\ContentRepository;
use App\Cms\Content\ContentEntity;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ContentApiController — Admin REST API for content nodes.
 */
#[RoutePrefix('/admin/api/content')]
final class ContentApiController
{
    public function __construct(
        private readonly ContentRepository $contentRepo,
    ) {}

    #[Route('GET', '/', name: 'admin.api.content.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $type = $params['type'] ?? null;
        $status = $params['status'] ?? 'all';
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['per_page'] ?? 25)));
        $offset = ($page - 1) * $limit;
        $search = $params['q'] ?? '';

        if ($search) {
            $nodes = $this->contentRepo->search($search, $type, $limit);
            return Response::json(['data' => array_map(fn(ContentEntity $n) => $n->toArray(), $nodes)]);
        }

        if (!$type) {
            return Response::json(['error' => 'Content type required'], 422);
        }

        $nodes = $this->contentRepo->findByType($type, $status, $limit, $offset);
        $total = $this->contentRepo->countByType($type, $status);

        return Response::json([
            'data' => array_map(fn(ContentEntity $n) => $n->toArray(), $nodes),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $limit,
                'last_page' => (int) ceil($total / $limit),
            ],
        ]);
    }

    #[Route('GET', '/{id:\d+}', name: 'admin.api.content.show')]
    public function show(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->find((int) $id);
        if (!$node) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json(['data' => $node->toArray()]);
    }

    #[Route('POST', '/', name: 'admin.api.content.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true);
        if (!$body) {
            return Response::json(['error' => 'Invalid JSON'], 422);
        }

        $node = new ContentEntity();
        $node->hydrate($body);
        $node->status = $body['status'] ?? 'draft';
        $node = $this->contentRepo->persist($node);

        return Response::json(['data' => $node->toArray(), 'meta' => ['created' => true]], 201);
    }

    #[Route('PUT', '/{id:\d+}', name: 'admin.api.content.update')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->find((int) $id);
        if (!$node) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $body = json_decode((string) $request->getBody(), true);
        if (!$body) {
            return Response::json(['error' => 'Invalid JSON'], 422);
        }

        $node->hydrate($body);
        $node = $this->contentRepo->persist($node);

        return Response::json(['data' => $node->toArray(), 'meta' => ['updated' => true]]);
    }

    #[Route('DELETE', '/{id:\d+}', name: 'admin.api.content.delete')]
    public function delete(ServerRequestInterface $request, string $id): Response
    {
        $deleted = $this->contentRepo->delete((int) $id);
        return $deleted
            ? Response::json(['meta' => ['deleted' => true]])
            : Response::json(['error' => 'Not found'], 404);
    }

    #[Route('POST', '/{id:\d+}/publish', name: 'admin.api.content.publish')]
    public function publish(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->find((int) $id);
        if (!$node) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $node->status = 'published';
        $node->published_at = new \DateTimeImmutable();
        $this->contentRepo->persist($node);

        return Response::json(['data' => $node->toArray(), 'meta' => ['published' => true]]);
    }

    #[Route('POST', '/{id:\d+}/unpublish', name: 'admin.api.content.unpublish')]
    public function unpublish(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->find((int) $id);
        if (!$node) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $node->status = 'draft';
        $this->contentRepo->persist($node);

        return Response::json(['data' => $node->toArray(), 'meta' => ['unpublished' => true]]);
    }
}
