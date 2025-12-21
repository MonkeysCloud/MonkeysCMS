<?php

declare(strict_types=1);

namespace App\Modules\Example\Widgets;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Widgets\AbstractFieldWidget;

/**
 * IconPickerWidget - Icon selector with search
 * 
 * Allows selecting icons from various icon sets (Heroicons, FontAwesome, etc.)
 * 
 * @example
 * ```php
 * // Field definition with icon picker
 * $field = new FieldDefinition();
 * $field->name = 'Icon';
 * $field->machine_name = 'icon';
 * $field->field_type = 'string';
 * $field->widget = 'icon_picker';
 * $field->settings = [
 *     'icon_set' => 'heroicons',
 *     'show_search' => true,
 * ];
 * ```
 */
class IconPickerWidget extends AbstractFieldWidget
{
    protected const ID = 'icon_picker';
    protected const LABEL = 'Icon Picker';
    protected const CATEGORY = 'Custom';
    protected const ICON = '🎨';
    protected const PRIORITY = 100;

    public static function getSupportedTypes(): array
    {
        return ['string', 'icon'];
    }

    public function render(FieldDefinition $field, mixed $value, array $context = []): string
    {
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        
        $iconSet = $field->getSetting('icon_set', 'heroicons');
        $showSearch = $field->getSetting('show_search', true);
        $showCategories = $field->getSetting('show_categories', true);
        $columns = $field->getSetting('columns', 8);
        
        $icons = $this->getIcons($iconSet);
        $categories = $this->getCategories($iconSet);
        $iconsJson = json_encode($icons);
        
        $currentIcon = $value ?? '';
        $previewHtml = $currentIcon ? $this->renderIcon($currentIcon, $iconSet) : '<span class="icon-picker__empty">No icon</span>';
        
        $inputHtml = <<<HTML
<div class="field-icon-picker" id="{$fieldId}_wrapper" data-icon-set="{$this->escape($iconSet)}">
    <input type="hidden" name="{$this->escape($fieldName)}" id="{$fieldId}" value="{$this->escape($currentIcon)}">
    
    <div class="icon-picker__preview" onclick="window.toggleIconPicker('{$fieldId}')">
        <span class="icon-picker__current" id="{$fieldId}_preview">{$previewHtml}</span>
        <span class="icon-picker__label">{$this->escape($currentIcon ?: 'Select icon')}</span>
        <button type="button" class="icon-picker__clear" onclick="event.stopPropagation(); window.clearIcon('{$fieldId}')" style="{($currentIcon ? '' : 'display:none;')}">×</button>
    </div>
    
    <div class="icon-picker__dropdown" id="{$fieldId}_dropdown" style="display: none;">
HTML;

        if ($showSearch) {
            $inputHtml .= <<<HTML
        <div class="icon-picker__search">
            <input type="text" placeholder="Search icons..." oninput="window.filterIcons('{$fieldId}', this.value)">
        </div>
HTML;
        }

        if ($showCategories && !empty($categories)) {
            $inputHtml .= '<div class="icon-picker__categories">';
            $inputHtml .= '<button type="button" class="icon-picker__category icon-picker__category--active" onclick="window.filterIconCategory(\'' . $fieldId . '\', \'\', this)">All</button>';
            foreach ($categories as $cat) {
                $inputHtml .= '<button type="button" class="icon-picker__category" onclick="window.filterIconCategory(\'' . $fieldId . '\', \'' . $this->escape($cat) . '\', this)">' . $this->escape($cat) . '</button>';
            }
            $inputHtml .= '</div>';
        }

        $inputHtml .= '<div class="icon-picker__grid" id="' . $fieldId . '_grid" style="grid-template-columns: repeat(' . $columns . ', 1fr);">';
        
        foreach ($icons as $icon) {
            $name = $icon['name'];
            $iconHtml = $this->renderIcon($name, $iconSet);
            $category = $icon['category'] ?? '';
            $selected = $name === $currentIcon ? ' icon-picker__icon--selected' : '';
            
            $inputHtml .= '<div class="icon-picker__icon' . $selected . '" data-name="' . $this->escape($name) . '" data-category="' . $this->escape($category) . '" onclick="window.selectIcon(\'' . $fieldId . '\', \'' . $this->escape($name) . '\')" title="' . $this->escape($name) . '">';
            $inputHtml .= $iconHtml;
            $inputHtml .= '</div>';
        }
        
        $inputHtml .= '</div>';
        $inputHtml .= '</div>';
        $inputHtml .= '</div>';
        
        return $this->renderWrapper($field, $inputHtml, $context);
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, array $context = []): string
    {
        if ($this->isEmpty($value)) {
            return '<span class="field-display field-display--empty">—</span>';
        }
        
        $iconSet = $field->getSetting('icon_set', 'heroicons');
        
        return '<span class="field-display field-display--icon">' . 
               $this->renderIcon($value, $iconSet) . 
               ' <span class="icon-name">' . $this->escape($value) . '</span></span>';
    }

    /**
     * Render an icon by name
     */
    private function renderIcon(string $name, string $iconSet): string
    {
        // In production, this would load actual SVG icons
        // For demo, we use simple representations
        
        switch ($iconSet) {
            case 'heroicons':
                return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' .
                       $this->getHeroIconPath($name) . '</svg>';
            
            case 'fontawesome':
                return '<i class="fa fa-' . $this->escape($name) . '"></i>';
            
            case 'emoji':
                return '<span class="emoji-icon">' . $this->getEmoji($name) . '</span>';
            
            default:
                return '<span class="icon-placeholder">' . substr($name, 0, 2) . '</span>';
        }
    }

    /**
     * Get icon path for Heroicons
     */
    private function getHeroIconPath(string $name): string
    {
        $paths = [
            'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
            'user' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
            'cog' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
            'heart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>',
            'star' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
            'mail' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
            'phone' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>',
            'search' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>',
            'menu' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>',
            'x' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>',
            'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>',
            'plus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>',
            'minus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/>',
            'arrow-left' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>',
            'arrow-right' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>',
            'document' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
            'folder' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>',
            'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
            'clock' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            'camera' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>',
        ];
        
        return $paths[$name] ?? '<circle cx="12" cy="12" r="8"/>';
    }

    /**
     * Get emoji by name
     */
    private function getEmoji(string $name): string
    {
        $emojis = [
            'home' => '🏠', 'user' => '👤', 'heart' => '❤️', 'star' => '⭐',
            'mail' => '📧', 'phone' => '📞', 'search' => '🔍', 'camera' => '📷',
            'calendar' => '📅', 'clock' => '🕐', 'folder' => '📁', 'document' => '📄',
            'check' => '✓', 'x' => '✗', 'plus' => '➕', 'minus' => '➖',
        ];
        
        return $emojis[$name] ?? '❓';
    }

    /**
     * Get available icons for icon set
     */
    private function getIcons(string $iconSet): array
    {
        // In production, this would load from icon set files
        return [
            ['name' => 'home', 'category' => 'Navigation'],
            ['name' => 'user', 'category' => 'User'],
            ['name' => 'cog', 'category' => 'Settings'],
            ['name' => 'heart', 'category' => 'Social'],
            ['name' => 'star', 'category' => 'Social'],
            ['name' => 'mail', 'category' => 'Communication'],
            ['name' => 'phone', 'category' => 'Communication'],
            ['name' => 'search', 'category' => 'Navigation'],
            ['name' => 'menu', 'category' => 'Navigation'],
            ['name' => 'x', 'category' => 'Actions'],
            ['name' => 'check', 'category' => 'Actions'],
            ['name' => 'plus', 'category' => 'Actions'],
            ['name' => 'minus', 'category' => 'Actions'],
            ['name' => 'arrow-left', 'category' => 'Arrows'],
            ['name' => 'arrow-right', 'category' => 'Arrows'],
            ['name' => 'document', 'category' => 'Files'],
            ['name' => 'folder', 'category' => 'Files'],
            ['name' => 'calendar', 'category' => 'Date'],
            ['name' => 'clock', 'category' => 'Date'],
            ['name' => 'camera', 'category' => 'Media'],
        ];
    }

    /**
     * Get categories for icon set
     */
    private function getCategories(string $iconSet): array
    {
        return ['Navigation', 'User', 'Settings', 'Social', 'Communication', 'Actions', 'Arrows', 'Files', 'Date', 'Media'];
    }

    public static function getCssAssets(): array
    {
        return ['/css/fields/icon-picker.css'];
    }

    public static function getJsAssets(): array
    {
        return ['/js/fields/icon-picker.js'];
    }

    public static function getSettingsSchema(): array
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
            'show_categories' => [
                'type' => 'boolean',
                'label' => 'Show category filter',
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
