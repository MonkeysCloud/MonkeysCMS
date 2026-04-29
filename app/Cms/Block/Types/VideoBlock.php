<?php

declare(strict_types=1);

namespace App\Cms\Block\Types;

use App\Cms\Block\BlockTypeInterface;

/**
 * VideoBlock — Embeddable video block (YouTube, Vimeo, or direct URL).
 */
final class VideoBlock implements BlockTypeInterface
{
    public static function getId(): string { return 'video'; }
    public static function getLabel(): string { return 'Video'; }
    public static function getDescription(): string { return 'Embed a video from YouTube, Vimeo, or a URL'; }
    public static function getIcon(): string { return '🎬'; }
    public static function getCategory(): string { return 'Media'; }

    public static function getFields(): array
    {
        return [
            'url' => ['type' => 'url', 'label' => 'Video URL', 'required' => true],
            'caption' => ['type' => 'string', 'label' => 'Caption'],
            'autoplay' => ['type' => 'select', 'label' => 'Autoplay', 'default' => 'no', 'options' => ['no' => 'No', 'yes' => 'Yes']],
        ];
    }

    public function render(array $data, array $settings = []): string
    {
        $url = htmlspecialchars($data['url'] ?? '');
        $caption = htmlspecialchars($data['caption'] ?? '');

        // Convert YouTube/Vimeo to embed
        $embed = $this->toEmbed($url);

        $html = '<figure class="block-video">';
        $html .= '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">';
        $html .= '<iframe src="' . $embed . '" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" allowfullscreen loading="lazy"></iframe>';
        $html .= '</div>';
        if ($caption) {
            $html .= '<figcaption>' . $caption . '</figcaption>';
        }
        $html .= '</figure>';

        return $html;
    }

    private function toEmbed(string $url): string
    {
        // YouTube
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        // Vimeo
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return $url;
    }
}
