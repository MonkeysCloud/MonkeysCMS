<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use App\Cms\Block\BlockTypeRegistry;
use App\Cms\Block\FieldFormRenderer;
use App\Cms\Mosaic\MosaicManager;
use App\Cms\Theme\ThemeManager;
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
#[RoutePrefix('/api/cms/mosaic')]
final class MosaicApiController
{
    public function __construct(
        private readonly MosaicManager $mosaicManager,
        private readonly BlockTypeRegistry $blockRegistry,
        private readonly FieldFormRenderer $fieldFormRenderer,
        private readonly ThemeManager $themeManager,
    ) {}

    /**
     * GET /api/cms/mosaic/{nodeId}
     * Load the Mosaic layout for a content node.
     */
    #[Route('GET', '/{nodeId:\d+}', name: 'admin.api.mosaic.show')]
    public function show(ServerRequestInterface $request, string $nodeId): Response
    {
        $contentType = $request->getQueryParams()['type'] ?? null;
        $mosaic = $contentType
            ? $this->mosaicManager->getForNode((int) $nodeId, $contentType)
            : $this->mosaicManager->getForNodeById((int) $nodeId);

        if (!$mosaic) {
            return Response::json([
                'data' => [
                    'node_id' => (int) $nodeId,
                    'content_type' => $contentType ?? 'page',
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
     * GET /api/cms/mosaic/sections/layouts
     * List available section layouts (full, two_col, etc.)
     */
    #[Route('GET', '/sections/layouts', name: 'api.cms.mosaic.sections.layouts')]
    public function sectionLayouts(): Response
    {
        return Response::json([
            'data' => \App\Cms\Mosaic\Section::getAvailableLayouts(),
        ]);
    }

    /**
     * GET /api/cms/mosaic/{nodeId}/revisions
     * Get revision history for a node's mosaic layout.
     */
    #[Route('GET', '/{nodeId:\d+}/revisions', name: 'api.cms.mosaic.revisions')]
    public function revisions(ServerRequestInterface $request, string $nodeId): Response
    {
        $revisions = $this->mosaicManager->getRevisions((int) $nodeId);

        return Response::json(['data' => $revisions]);
    }

    /**
     * POST /api/cms/mosaic/{nodeId}/revert
     * Revert to a specific revision.
     */
    #[Route('POST', '/{nodeId:\d+}/revert', name: 'api.cms.mosaic.revert')]
    public function revert(ServerRequestInterface $request, string $nodeId): Response
    {
        $body = json_decode((string) $request->getBody(), true);
        $revision = (int) ($body['revision'] ?? 0);

        if ($revision <= 0) {
            return Response::json(['error' => 'Invalid revision number'], 422);
        }

        $mosaic = $this->mosaicManager->revertToRevision((int) $nodeId, $revision);

        if (!$mosaic) {
            return Response::json(['error' => 'Revision not found'], 404);
        }

        return Response::json([
            'data' => $mosaic->toArray(),
            'meta' => ['reverted' => true, 'revision' => $mosaic->revision],
        ]);
    }

    /**
     * POST /api/cms/mosaic/render/section
     * Server-side render a complete section for live preview.
     */
    #[Route('POST', '/render/section', name: 'api.cms.mosaic.render.section')]
    public function renderSection(ServerRequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true);
        $sectionData = $body['section'] ?? [];

        if (empty($sectionData)) {
            return Response::json(['error' => 'Missing section data'], 422);
        }

        $section = \App\Cms\Mosaic\Section::fromArray($sectionData);

        // Render with block type renderers
        $html = '<div class="mosaic-section mosaic-section--' . htmlspecialchars($section->layout) . '">';
        $html .= '<div class="mosaic-container"><div class="mosaic-row mosaic-row--' . htmlspecialchars($section->layout) . '">';

        foreach ($section->regions as $regionName => $blocks) {
            $html .= '<div class="mosaic-col mosaic-col--' . htmlspecialchars($regionName) . '">';
            foreach ($blocks as $block) {
                $bt = $block['blockType'] ?? 'text';
                $bd = $block['data'] ?? [];
                $bs = $block['settings'] ?? [];
                $html .= '<div class="mosaic-block mosaic-block--' . htmlspecialchars($bt) . '">';
                $html .= $this->blockRegistry->render($bt, $bd, $bs);
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div></div></div>';

        return Response::json(['html' => $html]);
    }

    /**
     * POST /api/cms/mosaic/blocks/form
     * Return a server-rendered edit form for a block type.
     *
     * Accepts: { blockType, data, blockId, sectionIdx, regionName, blockIdx }
     * Returns: { html } — Form HTML to inject into the right sidebar inspector.
     */
    #[Route('POST', '/blocks/form', name: 'api.cms.mosaic.blocks.form')]
    public function blockForm(ServerRequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true);

        $blockType = $body['blockType'] ?? '';
        $data = $body['data'] ?? [];
        $settings = $body['settings'] ?? [];
        $sIdx = (int) ($body['sectionIdx'] ?? 0);
        $region = $body['regionName'] ?? 'main';
        $bIdx = (int) ($body['blockIdx'] ?? 0);

        $type = $this->blockRegistry->all()[$blockType] ?? null;
        if (!$type) {
            return Response::json(['error' => "Unknown block type: {$blockType}"], 422);
        }

        $html = $this->fieldFormRenderer->render($type, $data, $settings, $sIdx, $region, $bIdx);

        return Response::json(['html' => $html]);
    }

    /**
     * POST /api/cms/mosaic/{nodeId}/autosave
     * Draft save without bumping revision number.
     */
    #[Route('POST', '/{nodeId:\\d+}/autosave', name: 'api.cms.mosaic.autosave')]
    public function autosave(ServerRequestInterface $request, string $nodeId): Response
    {
        $body = json_decode((string) $request->getBody(), true);

        if (!isset($body['sections']) || !is_array($body['sections'])) {
            return Response::json(['error' => 'Invalid payload'], 422);
        }

        $contentType = $body['content_type'] ?? 'page';
        $mosaic = $this->mosaicManager->save((int) $nodeId, $contentType, $body['sections']);

        return Response::json([
            'meta' => [
                'saved' => true,
                'revision' => $mosaic->revision,
                'updated_at' => $mosaic->updated_at?->format('M j, g:ia') ?? 'just now',
            ],
        ]);
    }

    // ── Preview Asset Discovery ──────────────────────────────────────────

    /**
     * GET /api/cms/mosaic/preview-assets
     *
     * Returns the full CSS stack for the front-end theme (or a specific theme
     * via ?theme=name). Used by the Shadow DOM canvas to load the correct
     * stylesheets for WYSIWYG preview.
     */
    #[Route('GET', '/preview-assets', name: 'api.mosaic.preview-assets', summary: 'Get front-end theme CSS for preview', tags: ['Mosaic'])]
    public function previewAssets(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $themeName = $params['theme'] ?? null;

        // Resolve the theme — use requested or fall back to active front-end theme
        if ($themeName) {
            $theme = $this->themeManager->getTheme($themeName);
            if (!$theme || $theme->type !== 'frontend') {
                return Response::json(['error' => 'Theme not found: ' . $themeName], 404);
            }
        } else {
            $theme = $this->themeManager->getActiveTheme();
        }

        // Get aggregated CSS for the front-end theme (not admin)
        $assets = $this->themeManager->getAggregatedAssets(isAdmin: false);
        $css = $assets['css'] ?? [];

        // Auto-discover CSS files from the theme
        $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : '';
        if ($basePath) {
            // Discover blocks.css
            $blocksCss = $this->discoverBlocksCss($basePath, $theme);
            foreach ($blocksCss as $url) {
                if (!in_array($url, $css, true)) {
                    $css[] = $url;
                }
            }

            // Auto-discover additional CSS files in the theme's css/ directory
            // (e.g. front.css that may be loaded via Vite and not in theme.mlc)
            if ($theme) {
                $cssDir = $basePath . '/themes/' . $theme->tier . '/' . $theme->name . '/css';
                if (is_dir($cssDir)) {
                    foreach (glob($cssDir . '/*.css') as $file) {
                        $url = '/themes/' . $theme->tier . '/' . $theme->name . '/css/' . basename($file);
                        if (!in_array($url, $css, true)) {
                            $css[] = $url;
                        }
                    }
                }
            }
        }

        return Response::json([
            'css' => $css,
            'themeName' => $theme?->name ?? 'front',
            'themeLabel' => $theme?->label ?? 'Default',
        ]);
    }

    /**
     * GET /api/cms/mosaic/available-themes
     *
     * Returns all installed front-end themes for the theme switcher dropdown.
     */
    #[Route('GET', '/available-themes', name: 'api.mosaic.available-themes', summary: 'List available front-end themes', tags: ['Mosaic'])]
    public function availableThemes(): Response
    {
        $themes = $this->themeManager->getFrontendThemes();
        $activeTheme = $this->themeManager->getActiveTheme();

        $result = [];
        foreach ($themes as $t) {
            $result[] = [
                'name' => $t->name,
                'label' => $t->label,
                'description' => $t->description,
                'version' => $t->version,
                'tier' => $t->tier,
                'isActive' => $activeTheme && $activeTheme->name === $t->name,
            ];
        }

        return Response::json(['data' => $result]);
    }

    /**
     * Discover blocks.css from a theme + core fallback.
     *
     * @return string[] Public URLs
     */
    private function discoverBlocksCss(string $basePath, $theme): array
    {
        $urls = [];

        // Core front blocks.css (always include as baseline)
        $coreCss = $basePath . '/themes/core/front/css/blocks.css';
        if (file_exists($coreCss)) {
            $urls[] = '/themes/core/front/css/blocks.css';
        }

        // Theme-specific blocks.css
        if ($theme && $theme->name !== 'front') {
            $themeCss = $basePath . '/themes/' . $theme->tier . '/' . $theme->name . '/css/blocks.css';
            if (file_exists($themeCss)) {
                $url = '/themes/' . $theme->tier . '/' . $theme->name . '/css/blocks.css';
                if (!in_array($url, $urls, true)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }
}
