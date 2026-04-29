<?php

declare(strict_types=1);

namespace App\Cms\Controller;

use App\Cms\Content\ContentRepository;
use App\Cms\Menu\MenuRepository;
use App\Cms\Mosaic\MosaicManager;
use App\Cms\Block\BlockTypeRegistry;
use App\Cms\Taxonomy\TaxonomyRepository;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * FrontendController — Serves the public-facing CMS pages.
 *
 * Routes dynamic content through the theme system with Mosaic rendering.
 */
final class FrontendController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly ContentRepository $contentRepo,
        private readonly MenuRepository $menuRepo,
        private readonly MosaicManager $mosaicManager,
        private readonly BlockTypeRegistry $blockRegistry,
        private readonly TaxonomyRepository $taxonomyRepo,
        private readonly PDO $pdo,
    ) {}

    /**
     * Homepage
     */
    #[Route('GET', '/', name: 'front.home')]
    public function home(ServerRequestInterface $request): Response
    {
        $latestArticles = $this->contentRepo->findByType('article', 'published', 6);

        return Response::html($this->renderer->render('home', array_merge(
            $this->getGlobals(),
            ['latest_articles' => $latestArticles],
        )));
    }

    /**
     * Content listing by type
     */
    #[Route('GET', '/blog', name: 'front.blog')]
    public function blog(ServerRequestInterface $request): Response
    {
        return $this->listing($request, 'article');
    }

    /**
     * Generic content listing
     */
    #[Route('GET', '/{type:article|page|news|event}s', name: 'front.listing')]
    public function listingRoute(ServerRequestInterface $request, string $type): Response
    {
        return $this->listing($request, $type);
    }

    /**
     * Single article view
     */
    #[Route('GET', '/article/{slug}', name: 'front.article')]
    public function article(ServerRequestInterface $request, string $slug): Response
    {
        return $this->single($request, 'article', $slug, 'article');
    }

    /**
     * Single page view (catch-all)
     */
    #[Route('GET', '/{slug:[a-z0-9-]+}', name: 'front.page')]
    public function page(ServerRequestInterface $request, string $slug): Response
    {
        return $this->single($request, 'page', $slug, 'page');
    }

    // ── Private Helpers ─────────────────────────────────────────────────

    private function listing(ServerRequestInterface $request, string $type): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;

        $nodes = $this->contentRepo->findByType($type, 'published', $limit, $offset);
        $total = $this->contentRepo->countByType($type, 'published');

        // Load content type info
        $stmt = $this->pdo->prepare('SELECT * FROM content_types WHERE type_id = :type');
        $stmt->execute(['type' => $type]);
        $contentType = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['label' => ucfirst($type), 'label_plural' => ucfirst($type) . 's'];

        return Response::html($this->renderer->render('listing', array_merge(
            $this->getGlobals(),
            [
                'nodes' => $nodes,
                'contentType' => $contentType,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ],
        )));
    }

    private function single(ServerRequestInterface $request, string $type, string $slug, string $template): Response
    {
        $node = $this->contentRepo->findBySlug($slug, $type);

        if (!$node || !$node->isPublished) {
            return Response::html('<h1>404 — Not Found</h1>', 404);
        }

        // Render Mosaic if active
        $mosaicHtml = '';
        if ($node->mosaic_mode) {
            $mosaic = $this->mosaicManager->getForNode($node->id, $type);
            if ($mosaic) {
                $mosaicHtml = $this->mosaicManager->render(
                    $mosaic,
                    fn(string $bt, array $data, array $settings) => $this->blockRegistry->render($bt, $data, $settings),
                );
            }
        }

        // Load taxonomy terms
        $terms = $this->taxonomyRepo->findTermsForNode($node->id);

        return Response::html($this->renderer->render($template, array_merge(
            $this->getGlobals(),
            [
                'node' => $node,
                'mosaic_html' => $mosaicHtml,
                'terms' => $terms,
            ],
        )));
    }

    /**
     * Global template variables available to all frontend views
     */
    private function getGlobals(): array
    {
        // Load settings
        $stmt = $this->pdo->query("SELECT `key`, `value` FROM settings WHERE `group` = 'general' AND autoload = 1");
        $settings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[$row['key']] = $row['value'];
        }

        // Load main menu
        $mainMenu = $this->menuRepo->findByName('main');

        return [
            'site_name' => $settings['site_name'] ?? 'MonkeysCMS',
            'site_tagline' => $settings['site_tagline'] ?? '',
            'language' => 'en',
            'main_menu' => $mainMenu ? array_map(fn($i) => $i->toArray(), $mainMenu->items) : null,
        ];
    }
}
