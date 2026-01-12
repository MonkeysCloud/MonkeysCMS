<?php

declare(strict_types=1);

namespace App\Modules\Example\Widgets;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Widget\AbstractWidget;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\Html;

/**
 * IconPickerWidget - Icon selector with search
 *
 * Allows selecting icons from various icon sets (Heroicons, FontAwesome, etc.)
 */
class IconPickerWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'icon_picker';
    }

    public function getLabel(): string
    {
        return 'Icon Picker';
    }

    public function getCategory(): string
    {
        return 'Custom';
    }

    public function getIcon(): string
    {
        return '🎨';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['string', 'icon'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);

        $iconSet = $field->getSetting('icon_set', 'heroicons');
        $showSearch = $field->getSetting('show_search', true);
        $columns = (int) $field->getSetting('columns', 8);

        $icons = $this->getIcons($iconSet);
        $currentIcon = $value ?? '';
        $previewHtml = $currentIcon ? $this->renderIcon($currentIcon, $iconSet) : '<span class="icon-picker__empty">No icon</span>';

        // Build wrapper
        $wrapper = Html::div()
            ->class('field-icon-picker')
            ->id($fieldId . '_wrapper')
            ->data('icon-set', $iconSet);

        // Hidden input
        $wrapper->child(
            Html::input('hidden')
                ->name($fieldName)
                ->id($fieldId)
                ->value($currentIcon)
        );

        // Preview area
        $preview = Html::div()
            ->class('icon-picker__preview')
            ->attr('onclick', "window.toggleIconPicker('{$fieldId}')")
            ->html(
                '<span class="icon-picker__current" id="' . $fieldId . '_preview">' . $previewHtml . '</span>' .
                '<span class="icon-picker__label">' . ($currentIcon ?: 'Select icon') . '</span>' .
                '<button type="button" class="icon-picker__clear" onclick="event.stopPropagation(); window.clearIcon(\'' . $fieldId . '\')" style="' . ($currentIcon ? '' : 'display:none;') . '">×</button>'
            );

        $wrapper->child($preview);

        // Dropdown
        $dropdown = Html::div()
            ->class('icon-picker__dropdown')
            ->id($fieldId . '_dropdown')
            ->attr('style', 'display: none;');

        // Search
        if ($showSearch) {
            $dropdown->child(
                Html::div()
                    ->class('icon-picker__search')
                    ->html('<input type="text" placeholder="Search icons..." oninput="window.filterIcons(\'' . $fieldId . '\', this.value)">')
            );
        }

        // Grid
        $grid = Html::div()
            ->class('icon-picker__grid')
            ->id($fieldId . '_grid')
            ->attr('style', 'grid-template-columns: repeat(' . $columns . ', 1fr);');

        foreach ($icons as $icon) {
            $name = $icon['name'];
            $iconHtml = $this->renderIcon($name, $iconSet);
            $category = $icon['category'] ?? '';
            $selected = $name === $currentIcon ? ' icon-picker__icon--selected' : '';

            $grid->child(
                Html::div()
                    ->class('icon-picker__icon' . $selected)
                    ->data('name', $name)
                    ->data('category', $category)
                    ->attr('onclick', "window.selectIcon('{$fieldId}', '{$name}')")
                    ->attr('title', $name)
                    ->html($iconHtml)
            );
        }

        $dropdown->child($grid);
        $wrapper->child($dropdown);

        return $wrapper;
    }

    private function renderIcon(string $name, string $iconSet): string
    {
        switch ($iconSet) {
            case 'heroicons':
                return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' .
                       $this->getHeroIconPath($name) . '</svg>';
            case 'fontawesome':
                return '<i class="fa fa-' . htmlspecialchars($name) . '"></i>';
            case 'emoji':
                return '<span class="emoji-icon">' . $this->getEmoji($name) . '</span>';
            default:
                return '<span class="icon-placeholder">' . substr($name, 0, 2) . '</span>';
        }
    }

    private function getHeroIconPath(string $name): string
    {
        $paths = [
            'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
            'user' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
            'star' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
            'search' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>',
            'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>',
            'plus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>',
        ];

        return $paths[$name] ?? '<circle cx="12" cy="12" r="8"/>';
    }

    private function getEmoji(string $name): string
    {
        $emojis = [
            'home' => '🏠', 'user' => '👤', 'heart' => '❤️', 'star' => '⭐',
            'mail' => '📧', 'phone' => '📞', 'search' => '🔍', 'camera' => '📷',
        ];

        return $emojis[$name] ?? '❓';
    }

    private function getIcons(string $iconSet): array
    {
        return [
            ['name' => 'home', 'category' => 'Navigation'],
            ['name' => 'user', 'category' => 'User'],
            ['name' => 'star', 'category' => 'Social'],
            ['name' => 'search', 'category' => 'Navigation'],
            ['name' => 'check', 'category' => 'Actions'],
            ['name' => 'plus', 'category' => 'Actions'],
        ];
    }

    public function getSettingsSchema(): array
    {
        return [
            'icon_set' => [
                'type' => 'select',
                'label' => 'Icon set',
                'options' => [
                    'heroicons' => 'Heroicons',
                    'fontawesome' => 'Font Awesome',
                    'emoji' => 'Emoji',
                ],
                'default' => 'heroicons',
            ],
            'show_search' => [
                'type' => 'boolean',
                'label' => 'Show search',
                'default' => true,
            ],
            'columns' => [
                'type' => 'integer',
                'label' => 'Grid columns',
                'default' => 8,
                'min' => 4,
                'max' => 12,
            ],
        ];
    }
}
