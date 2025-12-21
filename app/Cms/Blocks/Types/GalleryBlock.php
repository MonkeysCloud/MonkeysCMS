<?php

declare(strict_types=1);

namespace App\Cms\Blocks\Types;

use App\Modules\Core\Entities\Block;

/**
 * GalleryBlock - Display a gallery of images with various layouts
 */
class GalleryBlock extends AbstractBlockType
{
    protected const ID = 'gallery';
    protected const LABEL = 'Gallery Block';
    protected const DESCRIPTION = 'Display a gallery of images with grid, slider, or masonry layouts';
    protected const ICON = '🖼️';
    protected const CATEGORY = 'Media';

    public static function getFields(): array
    {
        return [
            'images' => [
                'type' => 'gallery',
                'label' => 'Images',
                'required' => true,
                'description' => 'Select or upload multiple images',
                'settings' => [
                    'max_items' => 50,
                ],
            ],
            'layout' => [
                'type' => 'select',
                'label' => 'Layout',
                'required' => false,
                'default' => 'grid',
                'settings' => [
                    'options' => [
                        'grid' => 'Grid',
                        'masonry' => 'Masonry',
                        'slider' => 'Slider/Carousel',
                        'justified' => 'Justified',
                    ],
                ],
            ],
            'columns' => [
                'type' => 'select',
                'label' => 'Columns',
                'required' => false,
                'default' => '3',
                'settings' => [
                    'options' => [
                        '2' => '2 Columns',
                        '3' => '3 Columns',
                        '4' => '4 Columns',
                        '5' => '5 Columns',
                        '6' => '6 Columns',
                    ],
                ],
            ],
            'gap' => [
                'type' => 'select',
                'label' => 'Gap Between Images',
                'required' => false,
                'default' => 'medium',
                'settings' => [
                    'options' => [
                        'none' => 'None',
                        'small' => 'Small (8px)',
                        'medium' => 'Medium (16px)',
                        'large' => 'Large (24px)',
                    ],
                ],
            ],
            'thumbnail_size' => [
                'type' => 'select',
                'label' => 'Thumbnail Size',
                'required' => false,
                'default' => 'medium',
                'settings' => [
                    'options' => [
                        'thumbnail' => 'Thumbnail (150x150)',
                        'small' => 'Small (320x240)',
                        'medium' => 'Medium (800x600)',
                    ],
                ],
            ],
            'lightbox' => [
                'type' => 'boolean',
                'label' => 'Enable Lightbox',
                'default' => true,
                'description' => 'Open full-size images in a lightbox overlay',
            ],
            'show_captions' => [
                'type' => 'boolean',
                'label' => 'Show Captions',
                'default' => false,
            ],
            'autoplay' => [
                'type' => 'boolean',
                'label' => 'Autoplay (Slider)',
                'default' => false,
                'description' => 'Auto-advance slides (slider layout only)',
            ],
            'autoplay_speed' => [
                'type' => 'integer',
                'label' => 'Autoplay Speed (ms)',
                'default' => 5000,
                'description' => 'Time between slides in milliseconds',
            ],
        ];
    }

    public function render(Block $block, array $context = []): string
    {
        $images = $this->getFieldValue($block, 'images', []);
        $layout = $this->getFieldValue($block, 'layout', 'grid');
        $columns = $this->getFieldValue($block, 'columns', '3');
        $gap = $this->getFieldValue($block, 'gap', 'medium');
        $thumbnailSize = $this->getFieldValue($block, 'thumbnail_size', 'medium');
        $lightbox = $this->getFieldValue($block, 'lightbox', true);
        $showCaptions = $this->getFieldValue($block, 'show_captions', false);
        $autoplay = $this->getFieldValue($block, 'autoplay', false);
        $autoplaySpeed = $this->getFieldValue($block, 'autoplay_speed', 5000);

        if (empty($images)) {
            return '<div class="gallery-block gallery-block--empty">No images selected</div>';
        }

        $gapClass = "gallery-block--gap-{$gap}";
        $layoutClass = "gallery-block--{$layout}";
        $columnsStyle = $layout === 'grid' ? " style=\"--columns: {$columns};\"" : '';
        
        $dataAttrs = '';
        if ($lightbox) {
            $dataAttrs .= ' data-lightbox="true"';
        }
        if ($layout === 'slider') {
            $dataAttrs .= " data-autoplay=\"" . ($autoplay ? 'true' : 'false') . "\"";
            $dataAttrs .= " data-autoplay-speed=\"{$autoplaySpeed}\"";
        }

        $html = "<div class=\"gallery-block {$layoutClass} {$gapClass}\"{$columnsStyle}{$dataAttrs}>";
        
        if ($layout === 'slider') {
            $html .= '<div class="gallery-block__slider">';
        }

        foreach ($images as $imageId) {
            $imageData = $this->getImageData($imageId);
            $thumbUrl = $this->getImageUrl($imageId, $thumbnailSize);
            $fullUrl = $this->getImageUrl($imageId, 'large');
            $alt = $imageData['alt'] ?? '';
            $caption = $imageData['caption'] ?? '';

            $html .= '<div class="gallery-block__item">';
            
            if ($lightbox) {
                $html .= "<a href=\"{$fullUrl}\" class=\"gallery-block__link\" data-lightbox-group=\"block-{$block->id}\">";
            }
            
            $html .= "<img src=\"{$thumbUrl}\" alt=\"{$this->escape($alt)}\" class=\"gallery-block__image\" loading=\"lazy\">";
            
            if ($lightbox) {
                $html .= '</a>';
            }
            
            if ($showCaptions && $caption) {
                $html .= "<div class=\"gallery-block__caption\">{$this->escape($caption)}</div>";
            }
            
            $html .= '</div>';
        }

        if ($layout === 'slider') {
            $html .= '</div>';
            $html .= '<button class="gallery-block__prev" aria-label="Previous">‹</button>';
            $html .= '<button class="gallery-block__next" aria-label="Next">›</button>';
            $html .= '<div class="gallery-block__dots"></div>';
        }

        $html .= '</div>';

        return $html;
    }

    public function getCacheTags(Block $block): array
    {
        $tags = parent::getCacheTags($block);
        
        $images = $this->getFieldValue($block, 'images', []);
        foreach ($images as $imageId) {
            $tags[] = 'media:' . $imageId;
        }
        
        return $tags;
    }

    private function getImageUrl(?int $imageId, string $style): string
    {
        if (!$imageId) {
            return '/images/placeholder.jpg';
        }
        return "/media/{$imageId}?variant={$style}";
    }

    private function getImageData(int $imageId): array
    {
        // This would integrate with MediaService
        return [
            'id' => $imageId,
            'alt' => '',
            'caption' => '',
        ];
    }

    public static function getJsAssets(): array
    {
        return [
            '/js/blocks/gallery-block.js',
            '/js/vendor/lightbox.js',
        ];
    }

    public static function getCssAssets(): array
    {
        return [
            '/css/blocks/gallery-block.css',
            '/css/vendor/lightbox.css',
        ];
    }
}
