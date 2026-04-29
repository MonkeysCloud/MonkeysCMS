<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use App\Cms\Block\BlockTypeRegistry;
use App\Cms\Mosaic\MosaicManager;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MosaicApiController — REST API for the Mosaic visual page builder.
 *
 * Handles CRUD operations on layouts and provides block type metadata
 * for the MonkeysJS-powered editor frontend.
 */
#[RoutePrefix('/admin/api/mosaic')]
final class MosaicApiController
{
    public function __construct(
        private readonly MosaicManager $mosaicManager,
        private readonly BlockTypeRegistry $blockRegistry,
    ) {}

    /**
     * GET /admin/api/mosaic/{nodeId}
     * Load the Mosaic layout for a content node.
     */
    #[Route('GET', '/{nodeId:\d+}', name: 'admin.api.mosaic.show')]
    public function show(ServerRequestInterface $request, string $nodeId): Response
    {
        $contentType = $request->getQueryParams()['type'] ?? 'page';
        $mosaic = $this->mosaicManager->getForNode((int) $nodeId, $contentType);

        if (!$mosaic) {
            return Response::json([
                'data' => [
                    'node_id' => (int) $nodeId,
                    'content_type' => $contentType,
                    'sections' => [],
                    'revision' => 0,
                ],
            ]);
        }

        return Response::json(['data' => $mosaic->toArray()]);
    }

    /**
     * PUT /admin/api/mosaic/{nodeId}
     * Save the Mosaic layout for a content node.
     */
    #[Route('PUT', '/{nodeId:\d+}', name: 'admin.api.mosaic.save')]
    public function save(ServerRequestInterface $request, string $nodeId): Response
    {
        $body = json_decode((string) $request->getBody(), true);

        if (!isset($body['sections']) || !is_array($body['sections'])) {
            return Response::json(['error' => 'Invalid payload: sections array required'], 422);
        }

        $contentType = $body['content_type'] ?? $request->getQueryParams()['type'] ?? 'page';

        $mosaic = $this->mosaicManager->save(
            (int) $nodeId,
            $contentType,
            $body['sections'],
        );

        return Response::json([
            'data' => $mosaic->toArray(),
            'meta' => ['saved' => true, 'revision' => $mosaic->revision],
        ]);
    }

    /**
     * POST /admin/api/mosaic/{nodeId}/preview
     * Server-side render a Mosaic layout to HTML for live preview.
     */
    #[Route('POST', '/{nodeId:\d+}/preview', name: 'admin.api.mosaic.preview')]
    public function preview(ServerRequestInterface $request, string $nodeId): Response
    {
        $body = json_decode((string) $request->getBody(), true);
        $sections = $body['sections'] ?? [];

        $mosaic = new \App\Cms\Mosaic\MosaicEntity();
        $mosaic->node_id = (int) $nodeId;
        $mosaic->sections = $sections;

        $html = $this->mosaicManager->render(
            $mosaic,
            fn(string $type, array $data, array $settings) => $this->blockRegistry->render($type, $data, $settings),
        );

        return Response::json(['html' => $html]);
    }

    /**
     * DELETE /admin/api/mosaic/{nodeId}
     * Remove the Mosaic layout for a content node.
     */
    #[Route('DELETE', '/{nodeId:\d+}', name: 'admin.api.mosaic.delete')]
    public function delete(ServerRequestInterface $request, string $nodeId): Response
    {
        $contentType = $request->getQueryParams()['type'] ?? 'page';
        $deleted = $this->mosaicManager->deleteForNode((int) $nodeId, $contentType);

        return $deleted
            ? Response::json(['meta' => ['deleted' => true]])
            : Response::json(['error' => 'Layout not found'], 404);
    }

    /**
     * GET /admin/api/mosaic/blocks/types
     * List all available block types for the block picker.
     */
    #[Route('GET', '/blocks/types', name: 'admin.api.mosaic.blocks')]
    public function blockTypes(): Response
    {
        return Response::json([
            'data' => $this->blockRegistry->all(),
            'grouped' => $this->blockRegistry->grouped(),
        ]);
    }

    /**
     * POST /admin/api/mosaic/blocks/render
     * Render a single block to HTML (for live inline preview).
     */
    #[Route('POST', '/blocks/render', name: 'admin.api.mosaic.blocks.render')]
    public function renderBlock(ServerRequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true);

        $blockType = $body['blockType'] ?? '';
        $data = $body['data'] ?? [];
        $settings = $body['settings'] ?? [];

        if (!$this->blockRegistry->has($blockType)) {
            return Response::json(['error' => "Unknown block type: {$blockType}"], 422);
        }

        $html = $this->blockRegistry->render($blockType, $data, $settings);

        return Response::json(['html' => $html]);
    }

    /**
     * GET /admin/api/mosaic/sections/layouts
     * List available section layouts (full, two_col, etc.)
     */
    #[Route('GET', '/sections/layouts', name: 'admin.api.mosaic.sections.layouts')]
    public function sectionLayouts(): Response
    {
        return Response::json([
            'data' => \App\Cms\Mosaic\Section::getAvailableLayouts(),
        ]);
    }
}
