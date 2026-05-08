<?php

declare(strict_types=1);

namespace App\Cms\Middleware;

use App\Cms\Admin\AdminMenuRegistry;
use App\Cms\Content\ContentTypeManager;
use App\Cms\Menu\MenuManager;
use App\Cms\Taxonomy\TaxonomyRepository;
use App\Cms\Plugin\HookManager;
use App\Cms\Theme\AssetRenderer;
use App\Cms\Theme\PageAssets;
use App\Cms\Theme\ThemeLibrary;
use App\Cms\Theme\ThemeManager;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Loader;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ThemeResolverMiddleware — Drupal-inspired template + asset orchestrator.
 *
 * Responsibilities:
 *   1. Configures template view paths (theme hierarchy)
 *   2. Aggregates asset libraries (required + theme-declared + page-attached)
 *   3. Injects global $cms variables into every template via Renderer::onRendering()
 *   4. Generates $cms_head (CSS) and $cms_scripts (JS) HTML blocks
 *
 * Templates receive:
 *   $cms          — Global context (user, site, theme, csrf_token, assets)
 *   $cms_head     — Pre-rendered CSS <link> tags + inline CSS
 *   $cms_scripts  — Pre-rendered JS <script> tags + modules + inline JS
 */
final class ThemeResolverMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Loader $loader,
        private readonly ThemeManager $themeManager,
        private readonly AssetRenderer $assetRenderer,
        private readonly PageAssets $pageAssets,
        private readonly SessionManager $session,
        private readonly Renderer $renderer,
        private readonly ?ContentTypeManager $contentTypeManager = null,
        private readonly ?TaxonomyRepository $taxonomyRepo = null,
        private readonly ?MenuManager $menuManager = null,
        private readonly ?HookManager $hookManager = null,
        private readonly ?AdminMenuRegistry $adminMenuRegistry = null,
        private readonly ?\PDO $pdo = null,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : '';

        if (!$basePath) {
            return $handler->handle($request);
        }

        $isAdmin = str_starts_with($path, '/admin');

        // ── 1. Register view paths (theme hierarchy) ───────────────────
        $this->registerViewPaths($basePath, $isAdmin);

        // ── 2. Register onRendering listener for global variables ──────
        // This runs BEFORE every template render, injecting $cms globals
        // and pre-rendered asset HTML. We register it once per request.
        $this->renderer->onRendering(function ($event) use ($isAdmin, $path) {
            $globals = $this->buildGlobalVariables($isAdmin, $path);
            $event->data = array_merge($globals, $event->data);
        });

        return $handler->handle($request);
    }

    // ── View Path Registration ─────────────────────────────────────────

    private function registerViewPaths(string $basePath, bool $isAdmin): void
    {
        $adminViews = $basePath . '/themes/core/admin/views';
        $frontViews = $basePath . '/themes/core/front/views';

        // Check contrib/custom themes via ThemeManager
        $theme = $isAdmin
            ? $this->themeManager->getAdminTheme()
            : $this->themeManager->getActiveTheme();

        if ($theme) {
            $chain = $this->themeManager->getInheritanceChain($theme);
            // Child first, parent last — prepend so child overrides parent
            foreach ($chain as $t) {
                $viewsPath = $t->getViewsPath();
                if (is_dir($viewsPath)) {
                    $this->loader->prependPath($viewsPath);
                    // Register namespace for the theme type
                    if ($t->type === 'admin') {
                        $this->loader->addNamespace('admin', $viewsPath);
                    }
                }
            }
        }

        // Fallback: ensure core views are always available
        if ($isAdmin) {
            if (is_dir($adminViews)) {
                $this->loader->addNamespace('admin', $adminViews);
                $this->loader->addPath($adminViews);
            }
            if (is_dir($frontViews)) {
                $this->loader->addPath($frontViews);
            }
        } else {
            if (is_dir($frontViews)) {
                $this->loader->addPath($frontViews);
            }
            if (is_dir($adminViews)) {
                $this->loader->addNamespace('admin', $adminViews);
            }
        }
    }

    // ── Global Template Variables ───────────────────────────────────────

    /**
     * Build the complete set of global template variables.
     *
     * @param bool   $isAdmin Whether the current request is for the admin area
     * @param string $path    The current request URI path
     *
     * @return array<string, mixed>
     */
    private function buildGlobalVariables(bool $isAdmin, string $path = '/'): array
    {
        $theme = $isAdmin
            ? $this->themeManager->getAdminTheme()
            : $this->themeManager->getActiveTheme();

        // Aggregate assets: required libs + theme libs + page-attached libs
        $assets = $this->aggregateAssets($isAdmin);

        // Build $cms context
        $cms = [
            'user' => [
                'id' => $this->session->get('cms_user_id'),
                'name' => $this->session->get('cms_user_name'),
                'email' => $this->session->get('cms_user_email'),
                'role' => $this->session->get('cms_user_role'),
                'authenticated' => (bool) $this->session->get('cms_user_id'),
            ],
            'site' => [
                'name' => $_ENV['APP_NAME'] ?? 'MonkeysCMS',
                'url' => $_ENV['APP_URL'] ?? '',
                'env' => $_ENV['APP_ENV'] ?? 'production',
                'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
            ],
            'theme' => $theme ? [
                'name' => $theme->name,
                'label' => $theme->label,
                'path' => '/themes/' . $theme->tier . '/' . $theme->name,
                'tier' => $theme->tier,
            ] : [],
            'assets' => $assets,
            'csrf_token' => $this->session->get('_token', ''),
            'is_admin' => $isAdmin,
            'content_types' => $this->loadContentTypes(),
            'vocabularies' => $this->loadVocabularies(),
            'menus' => $this->loadMenus(),
            'plugin_menu_items' => $this->loadPluginMenuItems(),
            'admin_menu' => $isAdmin ? $this->buildAdminMenu() : null,
        ];

        // Pre-render asset HTML blocks
        $cmsHead = $this->assetRenderer->renderHead(
            $assets,
            $this->pageAssets->inlineCssBlocks,
        );

        $cmsScripts = $this->assetRenderer->renderScripts(
            $assets,
            $this->pageAssets->inlineJsBlocks,
        );

        // Add Lucide initialization if core/icons is loaded
        if (
            in_array('/https://unpkg.com/lucide@latest', $assets['js'], true)
            || in_array('https://unpkg.com/lucide@latest', $assets['js'], true)
        ) {
            $cmsScripts .= "  <script>document.addEventListener('DOMContentLoaded',()=>{if(window.lucide)lucide.createIcons();});</script>\n";
        }

        // Build global admin bar (available on both frontend and admin)
        $cmsAdminBar = $this->renderAdminBar($cms, $isAdmin, $path);

        return [
            'cms' => $cms,
            'cms_head' => $cmsHead,
            'cms_scripts' => $cmsScripts,
            'cms_admin_bar' => $cmsAdminBar,
            'csrf_token' => $cms['csrf_token'],
        ];
    }

    // ── Asset Aggregation ──────────────────────────────────────────────

    /**
     * Aggregate all assets for the current request:
     *   1. ThemeManager aggregated assets (required + theme chain)
     *   2. Page-level attached libraries (from controllers)
     *   3. Page-level extra CSS/JS URLs
     *
     * @return array{css: string[], js: string[], modules: string[]}
     */
    private function aggregateAssets(bool $isAdmin): array
    {
        // Base: theme-aggregated assets
        $assets = $this->themeManager->getAggregatedAssets($isAdmin);

        // Merge page-level attached libraries
        foreach ($this->pageAssets->attachedLibraries as $libId) {
            $lib = $this->themeManager->getLibrary($libId);
            if (!$lib)
                continue;

            foreach ($this->themeManager->resolveLibraryCss($lib) as $url) {
                if (!in_array($url, $assets['css'], true)) {
                    $assets['css'][] = $url;
                }
            }

            $jsTarget = $lib->module ? 'modules' : 'js';
            foreach ($this->themeManager->resolveLibraryJs($lib) as $url) {
                if (!in_array($url, $assets[$jsTarget], true)) {
                    $assets[$jsTarget][] = $url;
                }
            }

            foreach ($lib->preconnect as $url) {
                if (!in_array($url, $assets['preconnect'], true)) {
                    $assets['preconnect'][] = $url;
                }
            }
        }

        // Merge page-level extra assets
        foreach ($this->pageAssets->extraCssUrls as $url) {
            if (!in_array($url, $assets['css'], true)) {
                $assets['css'][] = $url;
            }
        }
        foreach ($this->pageAssets->extraJsUrls as $url) {
            if (!in_array($url, $assets['js'], true)) {
                $assets['js'][] = $url;
            }
        }

        // Auto-discover blocks.css from active theme → core fallback
        $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : '';
        if ($basePath) {
            $blocksUrl = $this->discoverBlocksCss($basePath, $isAdmin);
            if ($blocksUrl && !in_array($blocksUrl, $assets['css'], true)) {
                $assets['css'][] = $blocksUrl;
            }
        }

        return $assets;
    }

    /**
     * Discover blocks.css from the active theme's CSS directory.
     *
     * Resolution order:
     *   1. Active theme's css/blocks.css
     *   2. Core front theme's css/blocks.css
     *
     * Returns the public URL or null if not found.
     */
    private function discoverBlocksCss(string $basePath, bool $isAdmin): ?string
    {
        $theme = $isAdmin
            ? $this->themeManager->getAdminTheme()
            : $this->themeManager->getActiveTheme();

        // Check active theme first
        if ($theme) {
            $themeCss = $basePath . '/themes/' . $theme->tier . '/' . $theme->name . '/css/blocks.css';
            if (file_exists($themeCss)) {
                return '/themes/' . $theme->tier . '/' . $theme->name . '/css/blocks.css';
            }
        }

        // Fall back to core/front
        $coreCss = $basePath . '/themes/core/front/css/blocks.css';
        if (file_exists($coreCss)) {
            return '/themes/core/front/css/blocks.css';
        }

        return null;
    }

    /**
     * Load enabled content types for template globals.
     *
     * @return array<string, \App\Cms\Content\ContentTypeEntity>
     */
    private function loadContentTypes(): array
    {
        if ($this->contentTypeManager === null) {
            return [];
        }

        try {
            return $this->contentTypeManager->getEnabled();
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadVocabularies(): array
    {
        if ($this->taxonomyRepo === null) {
            return [];
        }

        try {
            return $this->taxonomyRepo->findAllVocabularies();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Load enabled menus for template globals.
     *
     * @return array<string, \App\Cms\Menu\MenuEntity>
     */
    private function loadMenus(): array
    {
        if ($this->menuManager === null) {
            return [];
        }

        try {
            return $this->menuManager->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Load plugin-contributed menu items via the admin.menu hook.
     *
     * Plugins register menu items during their register() phase:
     *   $hooks->filter('admin.menu', fn(array $items) => [...$items, $myItem]);
     *
     * @return list<array{label: string, url: string, icon?: string, weight?: int}>
     */
    private function loadPluginMenuItems(): array
    {
        if ($this->hookManager === null) {
            return [];
        }

        try {
            $items = $this->hookManager->applyFilters('admin.menu', []);

            // Sort by weight
            usort($items, static fn(array $a, array $b): int =>
                ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0)
            );

            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Build the admin menu tree for the current user.
     *
     * If the AdminMenuRegistry was not injected by the DI container
     * (nullable constructor param), we create and populate it lazily.
     *
     * @return array{dashboard: ?\App\Cms\Admin\AdminMenuItem, groups: \App\Cms\Admin\AdminMenuGroup[]}|null
     */
    private function buildAdminMenu(): ?array
    {
        try {
            $registry = $this->adminMenuRegistry;

            // Lazy-create if DI didn't inject it
            if ($registry === null && $this->pdo !== null) {
                $registry = new AdminMenuRegistry($this->pdo);
                \App\Cms\Provider\AdminMenuProvider::populateRegistry($registry, $this->pdo);
            }

            if ($registry === null) {
                return null;
            }

            // Let plugins modify the menu
            if ($this->hookManager !== null) {
                $this->hookManager->dispatch('admin.menu.build', $registry);
            }

            // Build for current user's roles
            $roleId = $this->session->get('cms_user_role');
            $userRoleIds = $roleId ? [(int) $roleId] : [];

            return $registry->buildForRoles($userRoleIds);
        } catch (\Throwable $e) {
            // Fallback: return null, sidebar will use legacy template
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                error_log('AdminMenu build error: ' . $e->getMessage());
            }
            return null;
        }
    }

    // ── Admin Bar Rendering ─────────────────────────────────────────────

    /**
     * Render the global admin bar (Drupal-style) if the user is authenticated.
     *
     * Returns empty string for anonymous users. The bar includes:
     *  - Dashboard link, Content link, + New quick action
     *  - User name, Cache clear link, Logout
     *  - Context-aware "Edit" link on content detail pages
     */
    private function renderAdminBar(array $cms, bool $isAdmin, string $path): string
    {
        if (!($cms['user']['authenticated'] ?? false)) {
            return '';
        }

        $userName = htmlspecialchars($cms['user']['name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
        $env = $cms['site']['env'] ?? 'production';

        // Build navigation links
        $links = '';
        $links .= '<a href="/admin" class="cms-bar__link">Dashboard</a>';
        $links .= '<a href="/admin/content" class="cms-bar__link">Content</a>';

        // Content types dropdown
        $contentTypes = $this->loadContentTypes();
        if (!empty($contentTypes)) {
            $links .= '<span class="cms-bar__dropdown">';
            $links .= '<a href="#" class="cms-bar__link" onclick="this.parentElement.classList.toggle(\'open\');return false;">+ New ▾</a>';
            $links .= '<div class="cms-bar__dropdown-menu">';
            foreach ($contentTypes as $ct) {
                $typeId = is_array($ct) ? ($ct['type_id'] ?? '') : ($ct->type_id ?? '');
                $label = is_array($ct) ? ($ct['label'] ?? ucfirst($typeId)) : ($ct->label ?? ucfirst($typeId));
                if ($typeId) {
                    $links .= '<a href="/admin/content/create/' . htmlspecialchars($typeId) . '" class="cms-bar__dropdown-item">' . htmlspecialchars($label) . '</a>';
                }
            }
            $links .= '</div></span>';
        } else {
            $links .= '<a href="/admin/content/create/article" class="cms-bar__link">+ New</a>';
        }

        // Environment badge
        $envBadge = '';
        if ($env !== 'production') {
            $envBadge = '<span class="cms-bar__badge cms-bar__badge--' . htmlspecialchars($env) . '">' . strtoupper($env) . '</span>';
        }

        // Location indicator
        $location = $isAdmin ? 'Admin' : 'Frontend';
        $locationBadge = '<span class="cms-bar__location">' . $location . '</span>';

        $html = <<<HTML
<div class="cms-bar" id="cms-admin-bar">
  <div class="cms-bar__inner">
    <div class="cms-bar__left">
      <a href="/admin" class="cms-bar__logo" title="Dashboard">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3Z"/></svg>
        MonkeysCMS
      </a>
      <span class="cms-bar__sep"></span>
      {$locationBadge}
      {$links}
      {$envBadge}
    </div>
    <div class="cms-bar__right">
      <a href="/admin/cache" class="cms-bar__link" title="Clear cache">Cache</a>
      <span class="cms-bar__sep"></span>
      <span class="cms-bar__user">{$userName}</span>
      <a href="/admin/logout" class="cms-bar__link cms-bar__link--logout">Logout</a>
    </div>
  </div>
</div>
<style>
.cms-bar{position:fixed;top:0;left:0;right:0;z-index:99999;height:34px;background:#181825;border-bottom:1px solid rgba(255,255,255,.07);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:.76rem;color:#cdd6f4;box-shadow:0 1px 3px rgba(0,0,0,.25)}
.cms-bar__inner{display:flex;align-items:center;justify-content:space-between;height:100%;padding:0 .75rem}
.cms-bar__left,.cms-bar__right{display:flex;align-items:center;gap:.1rem}
.cms-bar__logo{display:flex;align-items:center;gap:.3rem;font-weight:600;font-size:.76rem;color:#cba6f7;text-decoration:none;padding:.15rem .4rem;border-radius:4px;transition:background .15s}
.cms-bar__logo:hover{background:rgba(255,255,255,.06);color:#cba6f7}
.cms-bar__logo svg{opacity:.7}
.cms-bar__sep{width:1px;height:14px;background:rgba(255,255,255,.08);margin:0 .3rem}
.cms-bar__link{display:inline-flex;align-items:center;gap:.2rem;padding:.15rem .4rem;color:#a6adc8;text-decoration:none;border-radius:4px;transition:all .15s;white-space:nowrap;font-size:.74rem}
.cms-bar__link:hover{background:rgba(255,255,255,.06);color:#cdd6f4}
.cms-bar__link--edit{color:#89b4fa}
.cms-bar__link--edit:hover{background:rgba(137,180,250,.1);color:#89b4fa}
.cms-bar__link--logout{color:#f38ba8}
.cms-bar__link--logout:hover{background:rgba(243,139,168,.1);color:#f38ba8}
.cms-bar__user{padding:.15rem .35rem;color:#a6adc8;font-size:.7rem}
.cms-bar__location{padding:.1rem .35rem;font-size:.62rem;font-weight:600;border-radius:3px;background:rgba(99,102,241,.15);color:#a5b4fc;text-transform:uppercase;letter-spacing:.03em;margin-right:.2rem}
.cms-bar__badge{padding:.1rem .3rem;font-size:.58rem;font-weight:700;border-radius:3px;text-transform:uppercase;letter-spacing:.04em;margin-left:.25rem}
.cms-bar__badge--dev,.cms-bar__badge--development,.cms-bar__badge--local{background:rgba(250,204,21,.15);color:#fbbf24}
.cms-bar__badge--staging,.cms-bar__badge--test{background:rgba(251,146,60,.15);color:#fb923c}
.cms-bar__dropdown{position:relative}
.cms-bar__dropdown-menu{display:none;position:absolute;top:100%;left:0;min-width:140px;background:#1e1e2e;border:1px solid rgba(255,255,255,.08);border-radius:6px;padding:.25rem 0;box-shadow:0 8px 24px rgba(0,0,0,.4);z-index:100000}
.cms-bar__dropdown.open .cms-bar__dropdown-menu{display:block}
.cms-bar__dropdown-item{display:block;padding:.3rem .6rem;color:#a6adc8;text-decoration:none;font-size:.72rem;transition:all .15s}
.cms-bar__dropdown-item:hover{background:rgba(255,255,255,.06);color:#cdd6f4}
body{padding-top:34px!important}
.site-header{top:34px!important}
.admin-sidebar{top:34px!important;height:calc(100vh - 34px)!important}
.admin-header{top:34px!important}
.admin-wrapper{min-height:calc(100vh - 34px)!important;margin-top:0!important}
.apex-sidebar{top:34px!important;height:calc(100vh - 34px)!important}
</style>
<script>document.addEventListener('click',function(e){document.querySelectorAll('.cms-bar__dropdown.open').forEach(function(d){if(!d.contains(e.target))d.classList.remove('open')})});</script>
HTML;

        return $html;
    }
}
