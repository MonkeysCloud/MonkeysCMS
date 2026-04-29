<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Block\BlockTypeRegistry;
use App\Cms\Content\ContentRepository;
use App\Cms\Mosaic\MosaicManager;
use App\Cms\Mosaic\Section;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MosaicController — Admin UI for the Mosaic visual page builder.
 *
 * Serves the editor page which is powered by MonkeysJS on the frontend.
 * The editor communicates with MosaicApiController for data operations.
 */
#[RoutePrefix('/admin/mosaic')]
final class MosaicController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly MosaicManager $mosaicManager,
        private readonly ContentRepository $contentRepo,
        private readonly BlockTypeRegistry $blockRegistry,
    ) {}

    /**
     * GET /admin/mosaic/{nodeId}
     * Display the Mosaic editor for a content node.
     */
    #[Route('GET', '/{nodeId:\d+}', name: 'admin.mosaic.edit')]
    public function edit(ServerRequestInterface $request, string $nodeId): Response
    {
        $node = $this->contentRepo->findOrFail((int) $nodeId);
        $mosaic = $this->mosaicManager->getForNode((int) $nodeId, $node->content_type);

        $html = $this->renderer->render('admin.mosaic.editor', [
            'title' => 'Mosaic Editor — ' . $node->title,
            'node' => $node,
            'mosaic' => $mosaic,
            'sections' => $mosaic ? $mosaic->sections : [],
            'layouts' => Section::getAvailableLayouts(),
            'blockTypes' => $this->blockRegistry->grouped(),
        ]);

        return Response::html($html);
    }
}
