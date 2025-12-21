<?php

declare(strict_types=1);

namespace App\Cms\Blocks\Types;

use App\Modules\Core\Entities\Block;

/**
 * ImageBlock - Display a single image with optional caption and link
 */
class ImageBlock extends AbstractBlockType
{
    protected const ID = 'image';
    protected const LABEL = 'Image Block';
    protected const DESCRIPTION = 'Display a single image with optional caption';
    protected const ICON = '🖼️';
    protected const CATEGORY = 'Media';

    public static function getFields(): array
    {
        return [
            'image' => [
                'type' => 'image',
                'label' => 'Image',
                'required' => true,
                'description' => 'Select or upload an image',
            ],
            'alt_text' => [
                'type' => 'string',
                'label' => 'Alt Text',
                'required' => false,
                'description' => 'Alternative text for accessibility',
            ],
            'caption' => [
                'type' => 'string',
                'label' => 'Caption',
                'required' => false,
                'description' => 'Image caption displayed below',
            ],
            'link_url' => [
                'type' => 'url',
                'label' => 'Link URL',
                'required' => false,
                'description' => 'Make the image clickable',
            ],
            'link_target' => [
                'type' => 'select',
                'label' => 'Link Target',
                'required' => false,
                'default' => '_self',
                'settings' => [
                    'options' => [
                        '_self' => 'Same Window',
                        '_blank' => 'New Window',
                    ],
                ],
            ],
            'image_style' => [
                'type' => 'select',
                'label' => 'Image Style',
                'required' => false,
                'default' => 'original',
                'settings' => [
                    'options' => [
                        'original' => 'Original',
                        'thumbnail' => 'Thumbnail (150x150)',
                        'small' => 'Small (320x240)',
                        'medium' => 'Medium (800x600)',
                        'large' => 'Large (1920x1080)',
                    ],
                ],
            ],
            'alignment' => [
                'type' => 'select',
                'label' => 'Alignment',
                'required' => false,
                'default' => 'center',
                'settings' => [
                    'options' => [
                        'left' => 'Left',
                        'center' => 'Center',
                        'right' => 'Right',
                    ],
                ],
            ],
        ];
    }

    public function render(Block $block, array $context = []): string
    {
        $imageId = $this->getFieldValue($block, 'image');
        $altText = $this->getFieldValue($block, 'alt_text', '');
        $caption = $this->getFieldValue($block, 'caption', '');
        $linkUrl = $this->getFieldValue($block, 'link_url', '');
        $linkTarget = $this->getFieldValue($block, 'link_target', '_self');
        $imageStyle = $this->getFieldValue($block, 'image_style', 'original');
        $alignment = $this->getFieldValue($block, 'alignment', 'center');

        // Get image URL from media service (placeholder for now)
        $imageUrl = $this->getImageUrl($imageId, $imageStyle);

        if (!$imageUrl) {
            return '<div class="image-block image-block--placeholder">No image selected</div>';
        }

        $alignClass = "image-block--{$alignment}";

        $imgTag = sprintf(
            '<img src="%s" alt="%s" class="image-block__image" loading="lazy">',
            $this->escape($imageUrl),
            $this->escape($altText)
        );

        // Wrap in link if provided
        if ($linkUrl) {
            $imgTag = sprintf(
                '<a href="%s" target="%s" class="image-block__link">%s</a>',
                $this->escape($linkUrl),
                $this->escape($linkTarget),
                $imgTag
            );
        }

        // Add caption if provided
        $captionHtml = '';
        if ($caption) {
            $captionHtml = sprintf(
                '<figcaption class="image-block__caption">%s</figcaption>',
                $this->escape($caption)
            );
        }

        return sprintf(
            '<figure class="image-block %s">%s%s</figure>',
            $alignClass,
            $imgTag,
            $captionHtml
        );
    }

    public function getCacheTags(Block $block): array
    {
        $tags = parent::getCacheTags($block);

        $imageId = $this->getFieldValue($block, 'image');
        if ($imageId) {
            $tags[] = 'media:' . $imageId;
        }

        return $tags;
    }

    private function getImageUrl(?int $imageId, string $style): ?string
    {
        if (!$imageId) {
            return null;
        }

        // This would integrate with the MediaService
        // For now, return a placeholder URL
        $variant = $style !== 'original' ? "?variant={$style}" : '';
        return "/media/{$imageId}{$variant}";
    }

    public static function getCssAssets(): array
    {
        return [
            '/css/blocks/image-block.css',
        ];
    }
}
