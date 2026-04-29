<?php

declare(strict_types=1);

namespace App\Cms\Block\Types;

use App\Cms\Block\BlockTypeInterface;

/**
 * ImageBlock — Image block with alt text and optional caption.
 */
final class ImageBlock implements BlockTypeInterface
{
    public static function getId(): string { return 'image'; }
    public static function getLabel(): string { return 'Image'; }
    public static function getDescription(): string { return 'Image with optional caption'; }
    public static function getIcon(): string { return '🖼️'; }
    public static function getCategory(): string { return 'Media'; }

    public static function getFields(): array
    {
        return [
            'media_id' => ['type' => 'media', 'label' => 'Image', 'required' => true],
            'alt' => ['type' => 'string', 'label' => 'Alt Text', 'required' => true],
            'caption' => ['type' => 'string', 'label' => 'Caption', 'required' => false],
            'link' => ['type' => 'url', 'label' => 'Link', 'required' => false],
        ];
    }

    public function render(array $data, array $settings = []): string
    {
        $mediaId = $data['media_id'] ?? 0;
        $alt = htmlspecialchars($data['alt'] ?? '');
        $caption = $data['caption'] ?? '';

        $html = '<figure class="block-image">';
        $html .= '<img src="/uploads/' . (int) $mediaId . '" alt="' . $alt . '" loading="lazy">';

        if ($caption) {
            $html .= '<figcaption>' . htmlspecialchars($caption) . '</figcaption>';
        }

        $html .= '</figure>';

        return $html;
    }
}
