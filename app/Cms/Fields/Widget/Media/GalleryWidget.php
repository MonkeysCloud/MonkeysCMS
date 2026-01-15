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
 * GalleryWidget - Multiple image selection
 */
final class GalleryWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'gallery';
    }

    public function getLabel(): string
    {
        return 'Gallery';
    }

    public function getCategory(): string
    {
        return 'Media';
    }

    public function getIcon(): string
    {
        return '🖼️';
    }

    public function getPriority(): int
    {
        return 90;
    }

    public function getSupportedTypes(): array
    {
        return ['gallery', 'images', 'media'];
    }

    public function supportsMultiple(): bool
    {
        return true;
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
        $maxItems = $settings->getInt('max_items', 0);
        $images = is_array($value) ? $value : ($value ? [$value] : []);

        $wrapper = Html::div()
            ->class('field-gallery')
            ->data('field-id', $fieldId)
            ->data('max-items', $maxItems);

        // Hidden input for JSON value
        $wrapper->child(
            Html::hidden($fieldName, json_encode($images))
                ->id($fieldId)
                ->class('field-gallery__value')
        );

        // Gallery grid
        $grid = Html::div()->class('field-gallery__grid');

        foreach ($images as $index => $imageUrl) {
            $grid->child($this->buildGalleryItem($imageUrl, $index));
        }

        $wrapper->child($grid);

        // Actions container
        $actions = Html::div()->class('field-gallery__actions');

        // File input for uploading
        $actions->child(
            Html::element('label')
                ->class('field-gallery__upload')
                ->child(
                    Html::input('file')
                        ->class('field-gallery__file')
                        ->attr('accept', 'image/jpeg,image/png,image/gif,image/webp')
                        ->attr('multiple', 'multiple')
                )
                ->child(Html::span()->text('Upload'))
        );

        // Browse media library
        $actions->child(
            Html::button()
                ->class('field-gallery__browse')
                ->attr('type', 'button')
                ->data('action', 'browse')
                ->text('Browse Library')
        );

        $wrapper->child($actions);

        return $wrapper;
    }

    private function buildGalleryItem(string $url, int $index): HtmlBuilder
    {
        return Html::div()
            ->class('field-gallery__item')
            ->data('index', $index)
            ->attr('draggable', 'true')
            ->child(
                Html::element('img')
                    ->attr('src', $url)
                    ->attr('alt', '')
            )
            ->child(
                Html::button()
                    ->class('field-gallery__remove')
                    ->attr('type', 'button')
                    ->data('action', 'remove')
                    ->text('×')
            );
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return "CmsMedia.initGallery('{$elementId}');";
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [$value];
        }

        return is_array($value) ? array_values(array_filter($value)) : [$value];
    }

    public function formatValue(FieldDefinition $field, mixed $value): mixed
    {
        return is_array($value) ? $value : [];
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        $images = is_array($value) ? $value : [];

        if (empty($images)) {
            return parent::renderDisplay($field, null, $context);
        }

        $settings = $this->getSettings($field);
        $thumbSize = $settings->getInt('thumb_size', 80);

        $grid = Html::div()->class('field-display', 'field-display--gallery');

        foreach ($images as $url) {
            $grid->child(
                Html::element('img')
                    ->attr('src', $url)
                    ->attr('alt', '')
                    ->attr('style', "width: {$thumbSize}px; height: {$thumbSize}px; object-fit: cover;")
            );
        }

        return RenderResult::fromHtml($grid->render());
    }

    public function getSettingsSchema(): array
    {
        return [
            'max_items' => ['type' => 'integer', 'label' => 'Max Items (0 = unlimited)', 'default' => 0],
            'thumb_size' => ['type' => 'integer', 'label' => 'Thumbnail Size (px)', 'default' => 80],
            'allowed_types' => ['type' => 'array', 'label' => 'Allowed MIME Types'],
        ];
    }
}
