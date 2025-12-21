<?php

declare(strict_types=1);

namespace App\Cms\Blocks\Types;

use App\Modules\Core\Entities\Block;
use App\Modules\Core\Services\MenuService;

/**
 * MenuBlock - Display a navigation menu
 */
class MenuBlock extends AbstractBlockType
{
    protected const ID = 'menu';
    protected const LABEL = 'Menu Block';
    protected const DESCRIPTION = 'Display a navigation menu';
    protected const ICON = '📋';
    protected const CATEGORY = 'Navigation';

    private ?MenuService $menuService = null;

    public function setMenuService(MenuService $menuService): void
    {
        $this->menuService = $menuService;
    }

    public static function getFields(): array
    {
        return [
            'menu_id' => [
                'type' => 'select',
                'label' => 'Menu',
                'required' => true,
                'description' => 'Select the menu to display',
                'settings' => [
                    'options_callback' => 'getMenuOptions',
                ],
            ],
            'depth' => [
                'type' => 'select',
                'label' => 'Maximum Depth',
                'required' => false,
                'default' => '0',
                'description' => '0 = unlimited',
                'settings' => [
                    'options' => [
                        '0' => 'Unlimited',
                        '1' => '1 Level',
                        '2' => '2 Levels',
                        '3' => '3 Levels',
                    ],
                ],
            ],
            'display_style' => [
                'type' => 'select',
                'label' => 'Display Style',
                'required' => false,
                'default' => 'vertical',
                'settings' => [
                    'options' => [
                        'vertical' => 'Vertical',
                        'horizontal' => 'Horizontal',
                        'dropdown' => 'Dropdown',
                        'mega' => 'Mega Menu',
                    ],
                ],
            ],
            'show_icons' => [
                'type' => 'boolean',
                'label' => 'Show Icons',
                'default' => false,
            ],
            'expand_all' => [
                'type' => 'boolean',
                'label' => 'Expand All Items',
                'default' => false,
            ],
            'active_trail' => [
                'type' => 'boolean',
                'label' => 'Show Active Trail',
                'default' => true,
            ],
        ];
    }

    public function render(Block $block, array $context = []): string
    {
        $menuId = $this->getFieldValue($block, 'menu_id');
        $depth = (int) $this->getFieldValue($block, 'depth', 0);
        $displayStyle = $this->getFieldValue($block, 'display_style', 'vertical');
        $showIcons = $this->getFieldValue($block, 'show_icons', false);
        $expandAll = $this->getFieldValue($block, 'expand_all', false);
        $activeTrail = $this->getFieldValue($block, 'active_trail', true);

        if (!$menuId) {
            return '<div class="menu-block menu-block--empty">No menu selected</div>';
        }

        // Get menu tree
        $menuTree = $this->getMenuTree($menuId, $context['user'] ?? null);
        
        if (empty($menuTree)) {
            return '<div class="menu-block menu-block--empty">Menu is empty</div>';
        }

        $currentPath = $context['current_path'] ?? '/';
        
        $styleClass = "menu-block--{$displayStyle}";
        
        $html = "<nav class=\"menu-block {$styleClass}\" role=\"navigation\">";
        $html .= $this->renderMenuItems($menuTree, [
            'depth' => $depth,
            'current_depth' => 1,
            'show_icons' => $showIcons,
            'expand_all' => $expandAll,
            'active_trail' => $activeTrail,
            'current_path' => $currentPath,
        ]);
        $html .= '</nav>';

        return $html;
    }

    private function renderMenuItems(array $items, array $options): string
    {
        $maxDepth = $options['depth'];
        $currentDepth = $options['current_depth'];
        $showIcons = $options['show_icons'];
        $expandAll = $options['expand_all'];
        $activeTrail = $options['active_trail'];
        $currentPath = $options['current_path'];

        if ($maxDepth > 0 && $currentDepth > $maxDepth) {
            return '';
        }

        $html = '<ul class="menu-block__list menu-block__list--level-' . $currentDepth . '">';

        foreach ($items as $item) {
            $isActive = $activeTrail && $this->isActivePath($item['url'] ?? '', $currentPath);
            $hasChildren = !empty($item['children']);
            
            $itemClasses = ['menu-block__item'];
            if ($isActive) {
                $itemClasses[] = 'menu-block__item--active';
            }
            if ($hasChildren) {
                $itemClasses[] = 'menu-block__item--has-children';
            }
            if ($expandAll || $item['expanded'] ?? false) {
                $itemClasses[] = 'menu-block__item--expanded';
            }

            $html .= '<li class="' . implode(' ', $itemClasses) . '">';
            
            // Link
            $linkClasses = ['menu-block__link'];
            if ($isActive) {
                $linkClasses[] = 'menu-block__link--active';
            }

            $icon = '';
            if ($showIcons && !empty($item['icon'])) {
                $icon = "<span class=\"menu-block__icon\">{$item['icon']}</span>";
            }

            $target = !empty($item['target']) ? " target=\"{$item['target']}\"" : '';
            
            $html .= "<a href=\"{$this->escape($item['url'] ?? '#')}\" class=\"" . implode(' ', $linkClasses) . "\"{$target}>";
            $html .= $icon;
            $html .= "<span class=\"menu-block__title\">{$this->escape($item['title'])}</span>";
            $html .= '</a>';

            // Children
            if ($hasChildren && ($expandAll || $isActive || ($maxDepth === 0 || $currentDepth < $maxDepth))) {
                $html .= $this->renderMenuItems($item['children'], array_merge($options, [
                    'current_depth' => $currentDepth + 1,
                ]));
            }

            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    private function isActivePath(string $menuUrl, string $currentPath): bool
    {
        $menuUrl = rtrim($menuUrl, '/');
        $currentPath = rtrim($currentPath, '/');
        
        if ($menuUrl === $currentPath) {
            return true;
        }
        
        // Check if current path starts with menu URL (for parent highlighting)
        if ($menuUrl !== '' && str_starts_with($currentPath, $menuUrl . '/')) {
            return true;
        }
        
        return false;
    }

    private function getMenuTree(int $menuId, $user): array
    {
        if ($this->menuService) {
            $menu = $this->menuService->getMenuWithItems($menuId, $user);
            if ($menu && $menu->items) {
                return $this->convertToTree($menu->items);
            }
        }
        
        // Fallback: return empty array
        return [];
    }

    private function convertToTree(array $items): array
    {
        $tree = [];
        
        foreach ($items as $item) {
            $node = [
                'title' => $item->title,
                'url' => $item->url,
                'icon' => $item->icon,
                'target' => $item->target,
                'expanded' => $item->expanded,
                'children' => [],
            ];
            
            if (!empty($item->children)) {
                $node['children'] = $this->convertToTree($item->children);
            }
            
            $tree[] = $node;
        }
        
        return $tree;
    }

    public function getCacheTags(Block $block): array
    {
        $tags = parent::getCacheTags($block);
        
        $menuId = $this->getFieldValue($block, 'menu_id');
        if ($menuId) {
            $tags[] = 'menu:' . $menuId;
        }
        
        return $tags;
    }

    public static function getCssAssets(): array
    {
        return [
            '/css/blocks/menu-block.css',
        ];
    }

    public static function getJsAssets(): array
    {
        return [
            '/js/blocks/menu-block.js',
        ];
    }
}
