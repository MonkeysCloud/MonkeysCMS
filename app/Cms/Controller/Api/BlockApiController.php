<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use App\Cms\Block\BlockService;
use App\Cms\Block\BlockInstanceService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * BlockApiController — RESTful API for block type and instance management.
 *
 * Provides a programmatic interface for custom modules to register,
 * modify, and query block types and instances.
 *
 * All responses follow JSON:API-inspired format:
 *   { "data": ..., "meta": { "total": N } }
 */
#[RoutePrefix('/api/v1')]
final class BlockApiController
{
    public function __construct(
        private readonly BlockService $blockService,
        private readonly BlockInstanceService $instanceService,
    ) {}

    // ═══ Block Types ═══════════════════════════════════════════════════

    #[Route('GET', '/block-types', name: 'api::block-types.index', summary: 'List all block types', tags: ['Blocks'])]
    public function listTypes(ServerRequestInterface $request): Response
    {
        $types = $this->blockService->getAll();

        return Response::json([
            'data' => $types,
            'meta' => ['total' => count($types)],
        ]);
    }

    #[Route('GET', '/block-types/{id}', name: 'api::block-types.show', summary: 'Get block type', tags: ['Blocks'])]
    public function showType(ServerRequestInterface $request, string $id): Response
    {
        $type = $this->blockService->get($id);
        if (!$type) {
            return Response::json(['error' => 'Block type not found'], 404);
        }

        return Response::json(['data' => $type]);
    }

    #[Route('POST', '/block-types', name: 'api::block-types.create', summary: 'Create block type', tags: ['Blocks'])]
    public function createType(ServerRequestInterface $request): Response
    {
        try {
            $body = json_decode((string) $request->getBody(), true) ?? [];
            $type = $this->blockService->create($body);

            return Response::json(['data' => $type], 201);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('PUT', '/block-types/{id}', name: 'api::block-types.update', summary: 'Update block type', tags: ['Blocks'])]
    public function updateType(ServerRequestInterface $request, string $id): Response
    {
        try {
            $body = json_decode((string) $request->getBody(), true) ?? [];
            $type = $this->blockService->update($id, $body);

            return Response::json(['data' => $type]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('DELETE', '/block-types/{id}', name: 'api::block-types.delete', summary: 'Delete block type', tags: ['Blocks'])]
    public function deleteType(ServerRequestInterface $request, string $id): Response
    {
        try {
            $this->blockService->delete($id);
            return Response::json(null, 204);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    // ═══ Block Type Fields ═════════════════════════════════════════════

    #[Route('POST', '/block-types/{id}/fields', name: 'api::block-types.fields.add', summary: 'Add field to block type', tags: ['Blocks'])]
    public function addField(ServerRequestInterface $request, string $id): Response
    {
        try {
            $body = json_decode((string) $request->getBody(), true) ?? [];
            $fieldName = $body['name'] ?? throw new \InvalidArgumentException('Field name required');
            unset($body['name']);

            $this->blockService->addField($id, $fieldName, $body);

            return Response::json(['data' => $this->blockService->getOrFail($id)]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('DELETE', '/block-types/{id}/fields/{name}', name: 'api::block-types.fields.delete', summary: 'Remove field from block type', tags: ['Blocks'])]
    public function removeField(ServerRequestInterface $request, string $id, string $name): Response
    {
        try {
            $this->blockService->removeField($id, $name);
            return Response::json(null, 204);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    // ═══ Block Instances ═══════════════════════════════════════════════

    #[Route('GET', '/block-instances', name: 'api::block-instances.index', summary: 'List block instances', tags: ['Blocks'])]
    public function listInstances(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $instances = $this->instanceService->getAll(
            $params['block_type'] ?? null,
            $params['status'] ?? null,
            (int) ($params['limit'] ?? 100),
            (int) ($params['offset'] ?? 0),
        );

        return Response::json([
            'data' => $instances,
            'meta' => ['total' => $this->instanceService->count(
                $params['block_type'] ?? null,
                $params['status'] ?? null,
            )],
        ]);
    }

    #[Route('POST', '/block-instances', name: 'api::block-instances.create', summary: 'Create block instance', tags: ['Blocks'])]
    public function createInstance(ServerRequestInterface $request): Response
    {
        try {
            $body = json_decode((string) $request->getBody(), true) ?? [];
            $instance = $this->instanceService->create($body);

            return Response::json(['data' => $instance], 201);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('GET', '/block-instances/{id:\\d+}', name: 'api::block-instances.show', summary: 'Get block instance', tags: ['Blocks'])]
    public function showInstance(ServerRequestInterface $request, string $id): Response
    {
        $instance = $this->instanceService->get((int) $id);
        if (!$instance) {
            return Response::json(['error' => 'Block instance not found'], 404);
        }

        return Response::json(['data' => $instance]);
    }

    #[Route('PUT', '/block-instances/{id:\\d+}', name: 'api::block-instances.update', summary: 'Update block instance', tags: ['Blocks'])]
    public function updateInstance(ServerRequestInterface $request, string $id): Response
    {
        try {
            $body = json_decode((string) $request->getBody(), true) ?? [];
            $instance = $this->instanceService->update((int) $id, $body);

            return Response::json(['data' => $instance]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('DELETE', '/block-instances/{id:\\d+}', name: 'api::block-instances.delete', summary: 'Delete block instance', tags: ['Blocks'])]
    public function deleteInstance(ServerRequestInterface $request, string $id): Response
    {
        try {
            $this->instanceService->delete((int) $id);
            return Response::json(null, 204);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }
}
