<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * VideoEmbedWidget — Input for video embed URLs (YouTube, Vimeo, etc.).
 */
final class VideoEmbedWidget extends AbstractWidget
{
    public static function type(): string { return 'video_embed'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $val = htmlspecialchars((string) ($value ?? ''));
        $name = $this->fieldName($field, $namePrefix);
        $id = $this->fieldId($field);

        $html = '<div class="video-embed-widget">';
        $html .= '<input type="url" name="' . $name . '" ' . $this->commonAttrs($field)
            . ' class="form-input" value="' . $val . '" '
            . 'placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/...">';

        // Preview area
        if ($val) {
            $embedUrl = $this->toEmbedUrl($val);
            if ($embedUrl) {
                $html .= '<div class="video-embed-preview" style="margin-top:.75rem;border-radius:12px;overflow:hidden;'
                    . 'aspect-ratio:16/9;background:#0f1019">'
                    . '<iframe src="' . htmlspecialchars($embedUrl) . '" style="width:100%;height:100%;border:none" '
                    . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
                    . 'allowfullscreen loading="lazy"></iframe>'
                    . '</div>';
            }
        }

        $html .= '<p class="form-help" style="font-size:.72rem;color:#64748b;margin-top:.35rem">'
            . 'Supports YouTube, Vimeo, and direct video URLs</p>';
        $html .= '</div>';

        return $this->wrapGroup($field, $html);
    }

    private function toEmbedUrl(string $url): ?string
    {
        // YouTube
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        // Vimeo
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return null;
    }
}
