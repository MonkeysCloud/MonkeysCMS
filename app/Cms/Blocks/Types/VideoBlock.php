<?php

declare(strict_types=1);

namespace App\Cms\Blocks\Types;

use App\Modules\Core\Entities\Block;

/**
 * VideoBlock - Embed videos from various sources
 */
class VideoBlock extends AbstractBlockType
{
    protected const ID = 'video';
    protected const LABEL = 'Video Block';
    protected const DESCRIPTION = 'Embed videos from YouTube, Vimeo, or upload your own';
    protected const ICON = '🎬';
    protected const CATEGORY = 'Media';

    private const YOUTUBE_REGEX = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i';
    private const VIMEO_REGEX = '/(?:vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|video\/|))(\d+)/i';

    public static function getFields(): array
    {
        return [
            'source' => [
                'type' => 'select',
                'label' => 'Video Source',
                'required' => true,
                'default' => 'youtube',
                'settings' => [
                    'options' => [
                        'youtube' => 'YouTube',
                        'vimeo' => 'Vimeo',
                        'upload' => 'Uploaded Video',
                        'url' => 'Direct URL',
                    ],
                ],
            ],
            'video_url' => [
                'type' => 'url',
                'label' => 'Video URL',
                'required' => true,
                'description' => 'YouTube/Vimeo URL or direct video URL',
            ],
            'video_file' => [
                'type' => 'video',
                'label' => 'Uploaded Video',
                'required' => false,
                'description' => 'Upload a video file (MP4, WebM)',
            ],
            'poster' => [
                'type' => 'image',
                'label' => 'Poster Image',
                'required' => false,
                'description' => 'Thumbnail shown before video plays',
            ],
            'title' => [
                'type' => 'string',
                'label' => 'Video Title',
                'required' => false,
            ],
            'caption' => [
                'type' => 'text',
                'label' => 'Caption',
                'required' => false,
            ],
            'autoplay' => [
                'type' => 'boolean',
                'label' => 'Autoplay',
                'default' => false,
                'description' => 'Start playing automatically (muted)',
            ],
            'loop' => [
                'type' => 'boolean',
                'label' => 'Loop',
                'default' => false,
            ],
            'muted' => [
                'type' => 'boolean',
                'label' => 'Muted',
                'default' => false,
            ],
            'controls' => [
                'type' => 'boolean',
                'label' => 'Show Controls',
                'default' => true,
            ],
            'aspect_ratio' => [
                'type' => 'select',
                'label' => 'Aspect Ratio',
                'required' => false,
                'default' => '16:9',
                'settings' => [
                    'options' => [
                        '16:9' => '16:9 (Widescreen)',
                        '4:3' => '4:3 (Standard)',
                        '1:1' => '1:1 (Square)',
                        '9:16' => '9:16 (Vertical)',
                        '21:9' => '21:9 (Cinematic)',
                    ],
                ],
            ],
            'max_width' => [
                'type' => 'string',
                'label' => 'Maximum Width',
                'required' => false,
                'default' => '100%',
                'description' => 'e.g., 800px, 100%',
            ],
        ];
    }

    public function render(Block $block, array $context = []): string
    {
        $source = $this->getFieldValue($block, 'source', 'youtube');
        $videoUrl = $this->getFieldValue($block, 'video_url', '');
        $videoFile = $this->getFieldValue($block, 'video_file');
        $poster = $this->getFieldValue($block, 'poster');
        $title = $this->getFieldValue($block, 'title', '');
        $caption = $this->getFieldValue($block, 'caption', '');
        $autoplay = $this->getFieldValue($block, 'autoplay', false);
        $loop = $this->getFieldValue($block, 'loop', false);
        $muted = $this->getFieldValue($block, 'muted', false);
        $controls = $this->getFieldValue($block, 'controls', true);
        $aspectRatio = $this->getFieldValue($block, 'aspect_ratio', '16:9');
        $maxWidth = $this->getFieldValue($block, 'max_width', '100%');

        // Calculate padding for aspect ratio
        $aspectPadding = $this->getAspectRatioPadding($aspectRatio);

        $html = '<div class="video-block" style="max-width: ' . $this->escape($maxWidth) . ';">';
        $html .= '<div class="video-block__wrapper" style="padding-bottom: ' . $aspectPadding . '%;">';

        switch ($source) {
            case 'youtube':
                $html .= $this->renderYouTube($videoUrl, $autoplay, $loop, $muted, $controls);
                break;
            case 'vimeo':
                $html .= $this->renderVimeo($videoUrl, $autoplay, $loop, $muted);
                break;
            case 'upload':
            case 'url':
                $html .= $this->renderNative($videoUrl, $videoFile, $poster, $autoplay, $loop, $muted, $controls);
                break;
        }

        $html .= '</div>';

        if ($caption) {
            $html .= '<figcaption class="video-block__caption">' . $this->escape($caption) . '</figcaption>';
        }

        $html .= '</div>';

        return $html;
    }

    private function renderYouTube(string $url, bool $autoplay, bool $loop, bool $muted, bool $controls): string
    {
        $videoId = $this->extractYouTubeId($url);
        if (!$videoId) {
            return '<div class="video-block__error">Invalid YouTube URL</div>';
        }

        $params = [];
        if ($autoplay) {
            $params['autoplay'] = '1';
        }
        if ($loop) {
            $params['loop'] = '1';
            $params['playlist'] = $videoId;
        }
        if ($muted) {
            $params['mute'] = '1';
        }
        if (!$controls) {
            $params['controls'] = '0';
        }
        $params['rel'] = '0'; // Don't show related videos

        $queryString = http_build_query($params);
        $embedUrl = "https://www.youtube.com/embed/{$videoId}" . ($queryString ? "?{$queryString}" : '');

        return sprintf(
            '<iframe class="video-block__iframe" src="%s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>',
            $this->escape($embedUrl)
        );
    }

    private function renderVimeo(string $url, bool $autoplay, bool $loop, bool $muted): string
    {
        $videoId = $this->extractVimeoId($url);
        if (!$videoId) {
            return '<div class="video-block__error">Invalid Vimeo URL</div>';
        }

        $params = [];
        if ($autoplay) {
            $params['autoplay'] = '1';
        }
        if ($loop) {
            $params['loop'] = '1';
        }
        if ($muted) {
            $params['muted'] = '1';
        }

        $queryString = http_build_query($params);
        $embedUrl = "https://player.vimeo.com/video/{$videoId}" . ($queryString ? "?{$queryString}" : '');

        return sprintf(
            '<iframe class="video-block__iframe" src="%s" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>',
            $this->escape($embedUrl)
        );
    }

    private function renderNative(string $url, ?int $videoFile, ?int $poster, bool $autoplay, bool $loop, bool $muted, bool $controls): string
    {
        $videoSrc = $videoFile ? "/media/{$videoFile}" : $url;
        $posterSrc = $poster ? "/media/{$poster}" : '';

        if (!$videoSrc) {
            return '<div class="video-block__error">No video source provided</div>';
        }

        $attrs = ['class="video-block__video"'];
        if ($autoplay) {
            $attrs[] = 'autoplay';
        }
        if ($loop) {
            $attrs[] = 'loop';
        }
        if ($muted) {
            $attrs[] = 'muted';
        }
        if ($controls) {
            $attrs[] = 'controls';
        }
        $attrs[] = 'playsinline';
        if ($posterSrc) {
            $attrs[] = 'poster="' . $this->escape($posterSrc) . '"';
        }

        return sprintf(
            '<video %s><source src="%s" type="video/mp4">Your browser does not support the video tag.</video>',
            implode(' ', $attrs),
            $this->escape($videoSrc)
        );
    }

    private function extractYouTubeId(string $url): ?string
    {
        if (preg_match(self::YOUTUBE_REGEX, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractVimeoId(string $url): ?string
    {
        if (preg_match(self::VIMEO_REGEX, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function getAspectRatioPadding(string $ratio): float
    {
        return match ($ratio) {
            '4:3' => 75,
            '1:1' => 100,
            '9:16' => 177.78,
            '21:9' => 42.86,
            default => 56.25, // 16:9
        };
    }

    public function getCacheTags(Block $block): array
    {
        $tags = parent::getCacheTags($block);

        $videoFile = $this->getFieldValue($block, 'video_file');
        if ($videoFile) {
            $tags[] = 'media:' . $videoFile;
        }

        $poster = $this->getFieldValue($block, 'poster');
        if ($poster) {
            $tags[] = 'media:' . $poster;
        }

        return $tags;
    }

    public static function getCssAssets(): array
    {
        return [
            '/css/blocks/video-block.css',
        ];
    }
}
