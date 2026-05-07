<?php

declare(strict_types=1);

namespace App\Cms\Controller;

use App\Cms\Breadcrumb\BreadcrumbBuilder;
use App\Cms\Comment\CommentService;
use App\Cms\Content\ContentRepository;
use App\Cms\Content\ContentRouter;
use App\Cms\Form\FormBuilder;
use App\Cms\Form\FormRenderer;
use App\Cms\I18n\LanguageService;
use App\Cms\I18n\TranslationService;
use App\Cms\Menu\MenuRepository;
use App\Cms\Mosaic\MosaicRenderer;
use App\Cms\Service\SettingsService;
use App\Cms\Taxonomy\TaxonomyRepository;
use App\Cms\Url\UrlManager;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * FrontendController — Serves the public-facing CMS pages.
 *
 * Routes dynamic content through the theme system with Mosaic rendering.
 * Uses ContentRouter for URL resolution and MosaicRenderer for
 * template-based block rendering.
 */
final class FrontendController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly ContentRepository $contentRepo,
        private readonly ContentRouter $contentRouter,
        private readonly MenuRepository $menuRepo,
        private readonly MosaicRenderer $mosaicRenderer,
        private readonly TaxonomyRepository $taxonomyRepo,
        private readonly UrlManager $urlManager,
        private readonly BreadcrumbBuilder $breadcrumbBuilder,
        private readonly CommentService $commentService,
        private readonly SettingsService $settingsService,
        private readonly FormRenderer $formRenderer,
        private readonly PDO $pdo,
        private readonly LanguageService $languageService,
        private readonly TranslationService $translationService,
    ) {
    }

    /**
     * Content Preview — token-based preview for draft/scheduled content.
     *
     * Allows sharing preview URLs with non-authenticated users.
     * Token is HMAC-signed with the app key for security.
     */
    #[Route('GET', '/preview/{id:\d+}', name: 'front.preview')]
    public function preview(ServerRequestInterface $request, string $id): Response
    {
        $params = $request->getQueryParams();
        $token = $params['token'] ?? '';

        $node = $this->contentRepo->find((int) $id);
        if (!$node) {
            return Response::html('<h1>404 — Not Found</h1>', 404);
        }

        // Verify token
        $appKey = $_ENV['APP_KEY'] ?? 'monkeyscms-default-key';
        $expectedToken = hash_hmac('sha256', "preview:{$node->id}:{$node->revision}", $appKey);

        if (!hash_equals($expectedToken, $token)) {
            // Fallback: allow if user is authenticated
            $session = $request->getAttribute('session');
            $isAuthenticated = $session && $session->get('cms_user_id');

            if (!$isAuthenticated) {
                return Response::html('<h1>403 — Invalid Preview Token</h1>', 403);
            }
        }

        // Check token age (24h expiry based on updated_at)
        if ($node->updated_at instanceof \DateTimeImmutable) {
            $age = time() - $node->updated_at->getTimestamp();
            if ($age > 86400 && $token !== '') {
                return Response::html('<h1>410 — Preview Link Expired</h1>', 410);
            }
        }

        $template = $node->content_type ?: 'page';

        return $this->renderNode($node, $template, $request);
    }

    /**
     * Homepage
     */
    #[Route('GET', '/', name: 'front.home')]
    public function home(ServerRequestInterface $request): Response
    {
        // Redirect to installer if CMS hasn't been set up yet
        $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : dirname(__DIR__, 3);
        if (!file_exists($basePath . '/storage/.installed')) {
            return Response::redirect('/install');
        }

        $latestArticles = $this->contentRepo->findByType('article', 'published', 6);

        return Response::html($this->renderer->render('home', array_merge(
            $this->getGlobals($request),
            ['latest_articles' => $latestArticles],
        )));
    }

    /**
     * Frontend search — public full-text search across published content.
     */
    #[Route('GET', '/search', name: 'front.search')]
    public function search(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $queryText = trim($params['q'] ?? '');
        $type = $params['type'] ?? null;
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = 12;

        $manager = \App\Cms\Search\SearchManager::create($this->pdo);
        $result = $manager->searchPublished(
            text: $queryText,
            contentType: ($type !== '' && $type !== null) ? $type : null,
            page: $page,
            perPage: $perPage,
        );

        return Response::html($this->renderer->render('search', array_merge(
            $this->getGlobals($request),
            [
                'query'      => $queryText,
                'type'       => $type,
                'results'    => $result->hits,
                'total'      => $result->total,
                'page'       => $result->currentPage(),
                'totalPages' => $result->totalPages(),
                'perPage'    => $perPage,
                'took'       => $result->took,
            ],
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
     * Dynamic content resolver — handles any content type by slug.
     *
     * Replaces hardcoded /article/{slug} with pattern-based resolution.
     * The ContentRouter checks the URL against all configured slug patterns.
     */
    #[Route('GET', '/{slug:[a-z0-9][a-z0-9-]*}', name: 'front.content')]
    public function content(ServerRequestInterface $request, string $slug): Response
    {
        // Try pattern-based resolution via ContentRouter (checks all content types)
        $resolved = $this->contentRouter->resolve('/' . $slug);

        if ($resolved) {
            $node = $resolved['node'];
            $type = $resolved['type'];
            $template = $this->contentRouter->templateFor($type);

            return $this->renderNode($node, $template, $request);
        }

        // Try direct slug lookup across all content types (published only)
        $node = $this->contentRepo->findBySlugGlobal($slug);

        if ($node) {
            return $this->renderNode($node, $node->content_type ?: 'page', $request);
        }

        // Allow authenticated CMS users to preview unpublished content
        $session = $request->getAttribute('session');
        $isAuthenticated = $session && $session->get('cms_user_id');

        if ($isAuthenticated) {
            $node = $this->contentRepo->findBySlugAny($slug);
            if ($node) {
                return $this->renderNode($node, $node->content_type ?: 'page', $request);
            }
        }

        return Response::html('<h1>404 — Not Found</h1>', 404);
    }

    /**
     * Multi-segment catch-all — handles slugs like article/test-article
     * or date-based patterns like 2026/05/my-article.
     *
     * The slug in the database matches the full path (e.g. "article/test-article").
     * Must be registered LAST to avoid intercepting real routes.
     */
    #[Route('GET', '/{path:.+}', name: 'front.catchall')]
    public function catchAll(ServerRequestInterface $request, string $path): Response
    {
        // Skip admin, api, install paths
        if (str_starts_with($path, 'admin') || str_starts_with($path, 'api') || str_starts_with($path, 'install')) {
            return Response::html('<h1>404 — Not Found</h1>', 404);
        }

        // Try pattern-based resolution via ContentRouter
        $resolved = $this->contentRouter->resolve('/' . $path);

        if ($resolved) {
            $node = $resolved['node'];
            $type = $resolved['type'];
            $template = $this->contentRouter->templateFor($type);

            return $this->renderNode($node, $template, $request);
        }

        // Direct slug lookup — the full path IS the slug (e.g. "article/test-article")
        $node = $this->contentRepo->findBySlugGlobal($path);

        if ($node) {
            return $this->renderNode($node, $node->content_type ?: 'page', $request);
        }

        // Allow authenticated CMS users to preview drafts
        $session = $request->getAttribute('session');
        if ($session && $session->get('cms_user_id')) {
            $node = $this->contentRepo->findBySlugAny($path);
            if ($node) {
                return $this->renderNode($node, $node->content_type ?: 'page', $request);
            }
        }

        // Try the UrlManager for any registered resolver
        $urlResolved = $this->urlManager->resolve('/' . $path);

        if ($urlResolved) {
            return $this->renderNode($urlResolved['entity'], $urlResolved['type'] ?? 'page', $request);
        }

        // Try taxonomy term resolution (e.g. categories/advanced-techniques)
        $termResolved = $this->resolveTermPath($path);
        if ($termResolved) {
            return $termResolved;
        }

        return Response::html('<h1>404 — Not Found</h1>', 404);
    }

    // ── Private Helpers ─────────────────────────────────────────────────

    /**
     * Try to resolve a path as a taxonomy term URL.
     *
     * Supports patterns like:
     *   - categories/advanced-techniques  (vocabulary/term-slug)
     *   - tags/php                        (vocabulary/term-slug)
     *
     * Handles two slug storage modes:
     *   1. Full-path slugs (slug = "categories/advanced-techniques")
     *   2. Simple slugs (slug = "advanced-techniques", vocab parsed from URL)
     */
    private function resolveTermPath(string $path): ?Response
    {
        // Split path into segments — we need at least vocabulary + term
        $segments = explode('/', $path);
        if (count($segments) < 2) {
            return null;
        }

        // First segment = vocabulary machine name
        $vocabName = $segments[0];

        // Look up vocabulary
        $vocab = $this->taxonomyRepo->findVocabulary($vocabName);
        if (!$vocab) {
            return null;
        }

        // Try 1: full path as slug (e.g. slug = "categories/advanced-techniques")
        $term = $this->taxonomyRepo->findTermBySlug($vocab->id, $path);

        // Try 2: last segment as slug (e.g. slug = "advanced-techniques")
        if (!$term) {
            $termSlug = end($segments);
            $term = $this->taxonomyRepo->findTermBySlug($vocab->id, $termSlug);
        }

        if (!$term) {
            return null;
        }

        // Load associated published content
        $nodes = $this->taxonomyRepo->findNodesByTerm($term->id);

        // Build breadcrumbs
        $breadcrumbs = $this->breadcrumbBuilder->buildForTerm($term, $vocab);

        return Response::html($this->renderer->render('taxonomy', array_merge(
            $this->getGlobals($request),
            [
                'vocabulary' => $vocab,
                'term' => $term,
                'nodes' => $nodes,
                'breadcrumbs' => $breadcrumbs,
                'breadcrumb_jsonld' => $breadcrumbs->toJsonLd(),
            ],
        )));
    }

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

        // Build breadcrumbs (cast to object — buildForListing expects ?object)
        $breadcrumbs = $this->breadcrumbBuilder->buildForListing((object) $contentType, $type);

        return Response::html($this->renderer->render('listing', array_merge(
            $this->getGlobals($request),
            [
                'nodes' => $nodes,
                'contentType' => $contentType,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $limit),
                ],
                'breadcrumbs' => $breadcrumbs,
                'breadcrumb_jsonld' => $breadcrumbs->toJsonLd(),
            ],
        )));
    }

    private function single(ServerRequestInterface $request, string $type, string $slug, string $template): Response
    {
        $node = $this->contentRepo->findBySlug($slug, $type);

        if (!$node) {
            return Response::html('<h1>404 — Not Found</h1>', 404);
        }

        // Allow authenticated CMS users to preview unpublished content
        if (!$node->isPublished) {
            $session = $request->getAttribute('session');
            $isAuthenticated = $session && $session->get('cms_user_id');

            if (!$isAuthenticated) {
                return Response::html('<h1>404 — Not Found</h1>', 404);
            }
        }

        return $this->renderNode($node, $template, $request);
    }

    /**
     * Render a single node with its template and mosaic layout.
     */
    private function renderNode(object $node, string $template, ?ServerRequestInterface $request = null): Response
    {
        // Render Mosaic if active
        $mosaicHtml = '';
        if ($node->mosaic_mode) {
            $mosaicHtml = $this->mosaicRenderer->renderForNode($node->id, $node->content_type);
        }

        // Load taxonomy terms
        $terms = $this->taxonomyRepo->findTermsForNode($node->id);

        // Load EAV fields
        $nodeWithFields = $this->contentRepo->findWithFields($node->id);

        // Resolve translation siblings for hreflang + language switcher
        $locale = $request?->getAttribute('locale') ?? $this->languageService->getDefaultCode();
        $translationSiblings = $this->resolveTranslationSiblings($node, $locale);

        // Load content type info for breadcrumbs
        $contentType = null;
        if ($node->content_type) {
            $stmt = $this->pdo->prepare('SELECT * FROM content_types WHERE type_id = :type');
            $stmt->execute(['type' => $node->content_type]);
            $contentType = $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        }

        // Build breadcrumbs
        $breadcrumbs = $this->breadcrumbBuilder->buildForNode($node, $contentType);

        // Resolve logged-in CMS user
        $session = $request?->getAttribute('session');
        $cmsUserId = $session ? $session->get('cms_user_id') : null;
        $cmsUser = null;
        if ($cmsUserId) {
            $stmt = $this->pdo->prepare('SELECT id, name, email FROM cms_users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $cmsUserId]);
            $cmsUser = $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        }

        // Load comments if enabled
        $commentsEnabled = false;
        $comments = [];
        $commentCount = 0;
        $commentsThreaded = false;
        $commentFormHtml = '';
        $commentsRequireLogin = (bool) $this->settingsService->get('comments_require_login', '0');
        $commentCanPost = true;

        $globalCommentsEnabled = (bool) $this->settingsService->get('enable_comments', '0');
        if ($globalCommentsEnabled && $contentType) {
            $ctCommentsEnabled = (bool) ($contentType->comments_enabled ?? false);
            if ($ctCommentsEnabled) {
                $commentsEnabled = true;
                $comments = $this->commentService->getThreaded($node->id);
                $commentCount = $this->commentService->countForNode($node->id);
                $commentsThreaded = (bool) $this->settingsService->get('comments_threaded', '1');

                // Check if user can post
                if ($commentsRequireLogin && !$cmsUser) {
                    $commentCanPost = false;
                } else {
                    $commentFormHtml = $this->buildCommentForm($node->id, $cmsUser, $session);
                }
            }
        }

        return Response::html($this->renderer->render($template, array_merge(
            $this->getGlobals($request),
            [
                'node' => $nodeWithFields ?? $node,
                'mosaic_html' => $mosaicHtml,
                'terms' => $terms,
                'url' => $this->urlManager->url($node),
                'urls' => $this->urlManager,
                'breadcrumbs' => $breadcrumbs,
                'breadcrumb_jsonld' => $breadcrumbs->toJsonLd(),
                'comments_enabled' => $commentsEnabled,
                'comments' => $comments,
                'comment_count' => $commentCount,
                'comments_threaded' => $commentsThreaded,
                'comment_form_html' => $commentFormHtml,
                'comment_can_post' => $commentCanPost,
                'comment_require_login' => $commentsRequireLogin,
                'cms_user' => $cmsUser,
                'translationSiblings' => $translationSiblings,
            ],
        )));
    }

    /**
     * Build the comment submission form via FormBuilder API.
     *
     * When a CMS user is logged in, name/email are pre-filled and readonly.
     */
    private function buildCommentForm(int $nodeId, ?object $cmsUser = null, $session = null): string
    {
        // Reply indicator HTML (shown/hidden via JS)
        $replyIndicatorHtml = '<div class="comment-reply-to" id="comment-reply-to" style="display:none">'
            . '<span>Replying to <strong id="reply-to-name"></strong></span>'
            . '<button type="button" class="comment-reply-cancel" onclick="cancelReply()">'
            . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>'
            . ' Cancel</button>'
            . '</div>';

        // Honeypot (hidden field for spam bots)
        $honeypotHtml = '<div style="position:absolute;left:-9999px;height:0;overflow:hidden">'
            . '<input type="text" name="website_url" tabindex="-1" autocomplete="off">'
            . '</div>';

        $builder = FormBuilder::create('/comments', 'POST')
            ->id('comment-submit-form')
            ->layout('default')
            ->hidden('node_id', $nodeId)
            ->hidden('parent_id', '')
            ->html($replyIndicatorHtml);

        if ($cmsUser) {
            // Logged in: show name as readonly badge, hide email input
            $userBadge = '<div class="comment-user-badge">'
                . '<div class="comment-user-badge__avatar">'
                . '<img src="https://www.gravatar.com/avatar/' . md5(strtolower(trim($cmsUser->email))) . '?s=32&d=mp" width="32" height="32" alt="" style="border-radius:8px">'
                . '</div>'
                . '<div class="comment-user-badge__info">'
                . '<span class="comment-user-badge__name">' . htmlspecialchars($cmsUser->name) . '</span>'
                . '<span class="comment-user-badge__email">' . htmlspecialchars($cmsUser->email) . '</span>'
                . '</div>'
                . '</div>';
            $builder->html($userBadge);
            $builder->hidden('author_name', $cmsUser->name);
            $builder->hidden('author_email', $cmsUser->email);
        } else {
            // Anonymous: show name and email fields
            $builder->text('author_name', 'Name')
                ->placeholder('Your name')
                ->required()
                ->attrs(['maxlength' => '100']);
            $builder->email('author_email', 'Email')
                ->placeholder('your@email.com')
                ->required()
                ->help('Will not be published')
                ->attrs(['maxlength' => '255']);
        }

        $builder->textarea('body', 'Comment')
            ->placeholder('Write your comment…')
            ->required()
            ->attrs(['rows' => '4', 'minlength' => '3', 'maxlength' => '5000']);
        $builder->html($honeypotHtml);
        $builder->submit('Post Comment', 'send');

        $form = $builder->build($session);
        return $this->formRenderer->render($form);
    }

    /**
     * Global template variables available to all frontend views.
     *
     * Menus are loaded via MenuService and passed as arrays.
     * Templates can also use @menu('name') directly for HTML rendering.
     */
    private function getGlobals(?ServerRequestInterface $request = null): array
    {
        // Detect locale from middleware
        $locale = $request?->getAttribute('locale') ?? $this->languageService->getDefaultCode();
        $langEntity = $this->languageService->find($locale);

        // Load settings
        $stmt = $this->pdo->query("SELECT `key`, `value` FROM settings WHERE `group` = 'general' AND autoload = 1");
        $settings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[$row['key']] = $row['value'];
        }

        // Load all menus dynamically via MenuService (locale-aware)
        $menuService = \App\Cms\Menu\MenuService::getInstance();
        $menus = [];
        if ($menuService) {
            foreach ($menuService->getMenuNames() as $name) {
                $menus[$name] = $menuService->getMenuTree($name, $locale);
            }
        }

        // Multilingual context
        $multilingualEnabled = $this->languageService->isEnabled();

        return [
            'site_name'           => $settings['site_name'] ?? 'MonkeysCMS',
            'site_tagline'        => $settings['site_tagline'] ?? '',
            'language'            => $locale,
            'locale'              => $locale,
            'textDirection'       => $langEntity?->direction ?? 'ltr',
            'menus'               => $menus,
            'multilingualEnabled' => $multilingualEnabled,
            'enabledLanguages'    => $multilingualEnabled ? $this->languageService->getEnabled() : [],
            'defaultLang'         => $this->languageService->getDefaultCode(),
            'translationSiblings' => [],  // Overridden per-page by renderNode()
        ];
    }

    /**
     * Resolve translation sibling URLs for a content node.
     *
     * Returns [lang_code => url, ...] for all translations + current node.
     * Used by hreflang tags and the language switcher.
     */
    private function resolveTranslationSiblings(object $node, string $currentLocale): array
    {
        if (!$this->languageService->isEnabled()) {
            return [];
        }

        $siblings = $this->translationService->getSiblings('node', $node->id, $node->language ?? $currentLocale);
        $urls = [];

        foreach ($siblings as $lang => $entityId) {
            if ($entityId === $node->id) {
                // Current node — use its own URL
                $urls[$lang] = $this->urlManager->localizedUrl($node, $lang);
            } else {
                // Translation — load and generate URL
                $translatedNode = $this->contentRepo->find($entityId);
                if ($translatedNode) {
                    $urls[$lang] = $this->urlManager->localizedUrl($translatedNode, $lang);
                }
            }
        }

        // If no siblings exist, still include current node
        if (empty($urls)) {
            $urls[$node->language ?? $currentLocale] = $this->urlManager->url($node);
        }

        return $urls;
    }
}
