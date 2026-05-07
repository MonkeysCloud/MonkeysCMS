<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use App\Cms\Content\ContentEntity;
use App\Cms\Content\ContentRepository;
use App\Cms\Content\ContentStatus;
use App\Cms\Content\ContentTypeManager;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ContentApiController — JSON REST API for content nodes.
 *
 * Serves both the admin AJAX operations and headless consumers.
 */
#[RoutePrefix('/api/cms/content')]
final class ContentApiController
{
    public function __construct(
        private readonly ContentRepository $contentRepo,
        private readonly ContentTypeManager $typeManager,
    ) {}

    #[Route('GET', '/', name: 'api::content.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();

        // Search mode
        $search = $params['q'] ?? '';
        if ($search) {
            $nodes = $this->contentRepo->search($search, $params['type'] ?? null);
            return Response::json([
                'data' => array_map(fn(ContentEntity $n) => $n->toArray(), $nodes),
                'meta' => ['total' => count($nodes)],
            ]);
        }

        $result = $this->contentRepo->paginate(
            contentType: $params['type'] ?? null,
            status: $params['status'] ?? 'all',
            page: max(1, (int) ($params['page'] ?? 1)),
            perPage: min(100, max(1, (int) ($params['per_page'] ?? 25))),
            orderBy: $params['sort'] ?? 'updated_at',
            direction: $params['order'] ?? 'DESC',
        );

        return Response::json([
            'data' => array_map(fn(ContentEntity $n) => $n->toArray(), $result->items),
            'meta' => $result->meta(),
        ]);
    }

    #[Route('GET', '/{id:\d+}', name: 'api::content.show')]
    public function show(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->findWithFields((int) $id);
        if (!$node) {
            return Response::json(['error' => 'Not found'], 404);
        }

        return Response::json(['data' => $node->toArray()]);
    }

    #[Route('POST', '/', name: 'api::content.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);

        $entity = new ContentEntity();
        $entity->title        = $body['title'] ?? '';
        $entity->slug         = $body['slug'] ?? $body['title'] ?? '';
        $entity->content_type = $body['content_type'] ?? '';
        $entity->status       = $body['status'] ?? 'draft';
        $entity->body         = $body['body'] ?? null;
        $entity->summary      = $body['summary'] ?? null;
        $entity->meta_title   = $body['meta_title'] ?? null;
        $entity->meta_description = $body['meta_description'] ?? null;

        $fieldValues = $body['fields'] ?? [];
        $entity = $this->contentRepo->save($entity, $fieldValues);

        return Response::json(['data' => $entity->toArray()], 201);
    }

    #[Route('PUT', '/{id:\d+}', name: 'api::content.update')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->findOrFail((int) $id);
        $body = $this->parseBody($request);

        if (isset($body['title']))            $node->title = $body['title'];
        if (isset($body['slug']))             $node->slug = $body['slug'];
        if (isset($body['content_type']))     $node->content_type = $body['content_type'];
        if (isset($body['status']))           $node->status = $body['status'];
        if (isset($body['body']))             $node->body = $body['body'];
        if (isset($body['summary']))          $node->summary = $body['summary'];
        if (isset($body['meta_title']))       $node->meta_title = $body['meta_title'];
        if (isset($body['meta_description'])) $node->meta_description = $body['meta_description'];

        $fieldValues = $body['fields'] ?? [];
        $node = $this->contentRepo->save($node, $fieldValues);

        return Response::json(['data' => $node->toArray()]);
    }

    #[Route('DELETE', '/{id:\d+}', name: 'api::content.destroy')]
    public function destroy(ServerRequestInterface $request, string $id): Response
    {
        $deleted = $this->contentRepo->delete((int) $id);
        if (!$deleted) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::noContent();
    }

    #[Route('POST', '/{id:\d+}/status', name: 'api::content.status')]
    public function changeStatus(ServerRequestInterface $request, string $id): Response
    {
        $body = $this->parseBody($request);
        $status = ContentStatus::tryFrom($body['status'] ?? '');

        if (!$status) {
            return Response::json(['error' => 'Invalid status'], 422);
        }

        $this->contentRepo->updateStatus((int) $id, $status);

        return Response::json(['success' => true, 'status' => $status->value]);
    }

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody() ?? [];
        if (empty($body)) {
            $stream = $request->getBody();
            $stream->rewind();
            $decoded = json_decode($stream->getContents(), true);
            if (is_array($decoded)) $body = $decoded;
        }
        return $body;
    }
}
