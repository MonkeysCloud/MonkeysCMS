<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Media;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * VideoWidget - Video URL/upload
 */
final class VideoWidget extends AbstractWidget
{
    private const VIDEO_PATTERNS = [
        'youtube' => '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/',
        'vimeo' => '/vimeo\.com\/(?:video\/)?(\d+)/',
    ];

    public function getId(): string
    {
        return 'video';
    }

    public function getLabel(): string
    {
        return 'Video';
    }

    public function getCategory(): string
    {
        return 'Media';
    }

    public function getIcon(): string
    {
        return '🎬';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['video', 'media'];
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/media.css');
        $this->assets->addJs('/js/fields/media.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);

        $wrapper = Html::div()
            ->class('field-video')
            ->data('field-id', $fieldId);

        // URL input
        $wrapper->child(
            Html::input('url')
                ->attrs($this->buildCommonAttributes($field, $context))
                ->attr('placeholder', 'https://youtube.com/watch?v=... or https://vimeo.com/...')
                ->value($value ?? '')
        );

        // Preview container
        $preview = Html::div()
            ->class('field-video__preview')
            ->id($fieldId . '_preview');

        if ($value) {
            $embedHtml = $this->getEmbedHtml($value);
            if ($embedHtml) {
                $preview->html($embedHtml);
            }
        }

        $wrapper->child($preview);

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return "CmsMedia.initVideo('{$elementId}');";
    }

    private function getEmbedHtml(string $url): ?string
    {
        // YouTube
        if (preg_match(self::VIDEO_PATTERNS['youtube'], $url, $matches)) {
            $videoId = $matches[1];
            return '<iframe src="https://www.youtube.com/embed/' . $videoId . '" frameborder="0" allowfullscreen></iframe>';
        }

        // Vimeo
        if (preg_match(self::VIDEO_PATTERNS['vimeo'], $url, $matches)) {
            $videoId = $matches[1];
            return '<iframe src="https://player.vimeo.com/video/' . $videoId . '" frameborder="0" allowfullscreen></iframe>';
        }

        // Direct video URL
        if (preg_match('/\.(mp4|webm|ogg)$/i', $url)) {
            return '<video src="' . htmlspecialchars($url) . '" controls></video>';
        }

        return null;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $embedHtml = $this->getEmbedHtml($value);

        if ($embedHtml) {
            $html = Html::div()
                ->class('field-display', 'field-display--video')
                ->html($embedHtml)
                ->render();
        } else {
            $html = Html::element('a')
                ->class('field-display', 'field-display--video-link')
                ->attr('href', $value)
                ->attr('target', '_blank')
                ->text($value)
                ->render();
        }

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'allowed_providers' => [
                'type' => 'array',
                'label' => 'Allowed Providers',
                'default' => ['youtube', 'vimeo'],
            ],
        ];
    }
}
