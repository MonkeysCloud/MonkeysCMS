<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Block\BlockTypeRegistry;
use App\Cms\Content\ContentRepository;
use App\Cms\Field\FieldRepository;
use App\Cms\Mosaic\MosaicManager;
use App\Cms\Mosaic\Section;
use App\Cms\Theme\ThemeManager;
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
        private readonly FieldRepository $fieldRepo,
        private readonly ThemeManager $themeManager,
    ) {}

    /**
     * GET /admin/mosaic/{nodeId}
     * Display the Mosaic editor for a content node.
     */
    #[Route('GET', '/{nodeId:\d+}', name: 'admin::mosaic.edit')]
    public function edit(ServerRequestInterface $request, string $nodeId): Response
    {
        $node = $this->contentRepo->findOrFail((int) $nodeId);
        $mosaic = $this->mosaicManager->getForNode((int) $nodeId, $node->content_type);

        // Build the available fields list for the Field block picker
        $nodeFields = $this->buildNodeFields($node->content_type);

        // Gather front-end theme CSS for Shadow DOM WYSIWYG preview
        $frontAssets = $this->themeManager->getAggregatedAssets(isAdmin: false);
        $frontCssUrls = $frontAssets['css'] ?? [];

        // Auto-discover CSS for the front theme
        $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : '';
        if ($basePath) {
            $frontTheme = $this->themeManager->getActiveTheme();
            $coreCss = '/themes/core/front/css/blocks.css';
            if (file_exists($basePath . $coreCss) && !in_array($coreCss, $frontCssUrls, true)) {
                $frontCssUrls[] = $coreCss;
            }
            if ($frontTheme && $frontTheme->name !== 'front') {
                $themeCss = '/themes/' . $frontTheme->tier . '/' . $frontTheme->name . '/css/blocks.css';
                if (file_exists($basePath . $themeCss) && !in_array($themeCss, $frontCssUrls, true)) {
                    $frontCssUrls[] = $themeCss;
                }
            }
            // Auto-discover all CSS in the theme's css/ directory
            if ($frontTheme) {
                $cssDir = $basePath . '/themes/' . $frontTheme->tier . '/' . $frontTheme->name . '/css';
                if (is_dir($cssDir)) {
                    foreach (glob($cssDir . '/*.css') as $file) {
                        $url = '/themes/' . $frontTheme->tier . '/' . $frontTheme->name . '/css/' . basename($file);
                        if (!in_array($url, $frontCssUrls, true)) {
                            $frontCssUrls[] = $url;
                        }
                    }
                }
            }
        }

        // Available front-end themes for the theme switcher
        $availableThemes = [];
        $activeThemeName = $this->themeManager->getActiveTheme()?->name ?? 'front';
        foreach ($this->themeManager->getFrontendThemes() as $t) {
            $availableThemes[] = [
                'name' => $t->name,
                'label' => $t->label,
                'isActive' => $t->name === $activeThemeName,
            ];
        }

        $html = $this->renderer->render('admin::mosaic.editor', [
            'title'          => 'Mosaic Editor — ' . $node->title,
            'node'           => $node,
            'mosaic'         => $mosaic,
            'sections'       => $mosaic ? $mosaic->sections : [],
            'layouts'        => Section::getAvailableLayouts(),
            'blockTypes'     => $this->blockRegistry->grouped(),
            'blockTypesFlat' => $this->blockRegistry->all(),
            'nodeFields'     => $nodeFields,
            'frontCssUrls'   => $frontCssUrls,
            'availableThemes' => $availableThemes,
            'activeThemeName' => $activeThemeName,
        ]);

        return Response::html($html);
    }

    /**
     * Build the list of available fields for the Field block picker.
     * Includes core node properties plus EAV custom fields.
     */
    private function buildNodeFields(string $contentType): array
    {
        // Core fields always available
        $fields = [
            'title' => ['label' => 'Title', 'type' => 'string', 'icon' => 'type'],
            'body'  => ['label' => 'Body', 'type' => 'text_long', 'icon' => 'align-left'],
            'slug'  => ['label' => 'URL Slug', 'type' => 'string', 'icon' => 'link'],
        ];

        // Add custom EAV fields
        try {
            $customFields = $this->fieldRepo->findByTypeId($contentType);
            foreach ($customFields as $f) {
                $fields[$f->machine_name] = [
                    'label' => $f->name,
                    'type'  => $f->field_type,
                    'icon'  => match ($f->field_type) {
                        'text_long', 'text_formatted' => 'file-text',
                        'integer', 'float', 'decimal' => 'hash',
                        'boolean'       => 'toggle-left',
                        'date', 'datetime' => 'calendar',
                        'media', 'image' => 'image',
                        'reference'     => 'link-2',
                        'email'         => 'mail',
                        'url'           => 'globe',
                        default         => 'database',
                    },
                ];
            }
        } catch (\Throwable) {
            // Pre-migration or table doesn't exist yet
        }

        return $fields;
    }
}
