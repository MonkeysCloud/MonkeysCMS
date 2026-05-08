<?php

declare(strict_types=1);

namespace App\Cms\Menu;

use App\Cms\I18n\LanguageService;
use PDO;

/**
 * MenuService — Global singleton that provides menu data to templates.
 *
 * Usage in templates:
 *   @menu('main')       → renders the 'main' menu as a flat array of items
 *   @menu('footer')     → renders the 'footer' menu with hierarchical children
 *
 * Usage in PHP:
 *   $items = MenuService::getInstance()->getMenu('main');
 *   $tree  = MenuService::getInstance()->getMenuTree('footer');
 */
final class MenuService
{
    private static ?self $instance = null;

    /** @var array<string, ?MenuEntity> Loaded menu cache (per-request) */
    private array $cache = [];

    private MenuRepository $repo;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?LanguageService $languageService = null,
    ) {
        $this->repo = new MenuRepository($pdo);
        self::$instance = $this;
    }

    /**
     * Get the global singleton instance.
     * Available after the DI container creates it.
     */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /**
     * Get flat menu items by machine name.
     *
     * Returns an array of item arrays (only enabled, sorted by weight).
     * Each item has: id, title, url, icon, target, parent_id, weight, enabled, attributes
     *
     * @return list<array<string, mixed>>
     */
    public function getMenu(string $name, ?string $lang = null): array
    {
        $menu = $this->loadMenu($name);
        if (!$menu) {
            return [];
        }

        $items = $this->filterByLanguage($menu->items, $lang);
        return array_map(fn(MenuItemEntity $i) => $i->toArray(), $items);
    }

    /**
     * Get hierarchical menu tree by machine name.
     *
     * Returns top-level items, each with a 'children' key containing nested items.
     * Ideal for footer columns, mega menus, or sidebar trees.
     *
     * @return list<array<string, mixed>>
     */
    public function getMenuTree(string $name, ?string $lang = null): array
    {
        $menu = $this->loadMenu($name);
        if (!$menu) {
            return [];
        }

        $items = $this->filterByLanguage($menu->items, $lang);
        return $this->buildHierarchy($items);
    }

    /**
     * Check if a menu exists by machine name.
     */
    public function hasMenu(string $name): bool
    {
        return $this->loadMenu($name) !== null;
    }

    /**
     * Get all available menu machine names.
     *
     * @return list<string>
     */
    public function getMenuNames(): array
    {
        $stmt = $this->pdo->query('SELECT machine_name FROM menus WHERE enabled = 1 ORDER BY label');
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'machine_name');
    }

    /**
     * Render a menu as HTML navigation markup.
     *
     * Called by the @menu('name') template directive.
     * Outputs a semantic <nav> with nested <ul>/<li> for hierarchical menus.
     *
     * @param string $name   Machine name of the menu
     * @param string $class  Optional CSS class for the <nav> element
     */
    public function renderMenu(string $name, string $class = ''): string
    {
        $tree = $this->getMenuTree($name);
        if (empty($tree)) {
            return '';
        }

        $navClass = $class ?: ('menu menu--' . htmlspecialchars($name, ENT_QUOTES));

        return '<nav class="' . $navClass . '" data-menu="' . htmlspecialchars($name, ENT_QUOTES) . '">'
            . $this->renderItems($tree)
            . '</nav>';
    }

    /**
     * Render a flat list of menu items as <ul>/<li> HTML.
     *
     * @param list<array<string, mixed>> $items
     */
    private function renderItems(array $items, int $depth = 0): string
    {
        $ulClass = $depth === 0 ? 'menu__list' : 'menu__submenu';
        $html = '<ul class="' . $ulClass . '">';

        foreach ($items as $item) {
            $hasChildren = !empty($item['children']);
            $liClass = 'menu__item';
            if ($hasChildren) {
                $liClass .= ' menu__item--parent';
            }
            if (!($item['enabled'] ?? true)) {
                continue;
            }

            $html .= '<li class="' . $liClass . '">';

            $url    = htmlspecialchars($item['url'] ?? '#', ENT_QUOTES);
            $title  = htmlspecialchars($item['title'] ?? '', ENT_QUOTES);
            $target = !empty($item['target']) ? ' target="' . htmlspecialchars($item['target'], ENT_QUOTES) . '"' : '';
            $icon   = '';
            if (!empty($item['icon'])) {
                $icon = '<i data-lucide="' . htmlspecialchars($item['icon'], ENT_QUOTES) . '"></i> ';
            }

            $html .= '<a href="' . $url . '" class="menu__link"' . $target . '>'
                . $icon . $title
                . '</a>';

            if ($hasChildren) {
                $html .= $this->renderItems($item['children'], $depth + 1);
            }

            $html .= '</li>';
        }

        $html .= '</ul>';
        return $html;
    }

    /**
     * Clear the per-request cache (useful after admin saves).
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    // ── Internal ────────────────────────────────────────────────────────

    private function loadMenu(string $name): ?MenuEntity
    {
        if (!array_key_exists($name, $this->cache)) {
            $this->cache[$name] = $this->repo->findByName($name);
        }
        return $this->cache[$name];
    }

    /**
     * Build hierarchical array from flat items list.
     * Top-level items get a 'children' array populated with their nested items.
     *
     * @param list<MenuItemEntity> $items
     * @return list<array<string, mixed>>
     */
    private function buildHierarchy(array $items): array
    {
        $flat = [];
        foreach ($items as $item) {
            $arr = $item->toArray();
            $arr['children'] = [];
            $flat[$item->id] = $arr;
        }

        $tree = [];
        foreach ($flat as $id => &$node) {
            $parentId = $node['parent_id'] ?? null;
            if ($parentId && isset($flat[$parentId])) {
                $flat[$parentId]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return $tree;
    }

    /**
     * Filter menu items by language (only when multilingual is enabled).
     *
     * @param list<MenuItemEntity> $items
     * @return list<MenuItemEntity>
     */
    private function filterByLanguage(array $items, ?string $lang): array
    {
        if ($lang === null && $this->languageService !== null && $this->languageService->isEnabled()) {
            $lang = $this->languageService->getDefaultCode();
        }

        if ($lang === null) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            fn(MenuItemEntity $item) => $item->language === $lang,
        ));
    }
}
