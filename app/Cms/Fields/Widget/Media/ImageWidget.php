<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Media;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * ImageWidget - Image upload with preview
 */
final class ImageWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'image';
    }

    public function getLabel(): string
    {
        return 'Image';
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
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['image', 'media', 'file'];
    }

    public function usesLabelableInput(): bool
    {
        return false;
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
        $previewSize = $settings->getString('preview_size', 'medium');
        $allowedTypes = $settings->getArray('allowed_types', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        $maxSize = $settings->getInt('max_size', 5 * 1024 * 1024); // 5MB default

        $wrapper = Html::div()
            ->class('field-image')
            ->data('field-id', $fieldId);

        // Hidden input for storing the value
        $wrapper->child(
            Html::hidden($fieldName, $value ?? '')
                ->id($fieldId)
                ->class('field-image__value')
        );

        // Preview container
        $preview = Html::div()
            ->class('field-image__preview', "field-image__preview--{$previewSize}");

        if ($value) {
            $preview->child(
                Html::element('img')
                    ->class('field-image__img')
                    ->attr('src', $value)
                    ->attr('alt', 'Preview')
            );
        } else {
            $preview->child(
                Html::div()
                    ->class('field-image__placeholder')
                    ->text('No image selected')
            );
        }

        $wrapper->child($preview);

        // Actions
        $actions = Html::div()->class('field-image__actions');

        // File input
        $actions->child(
            Html::element('label')
                ->class('field-image__upload')
                ->child(
                    Html::input('file')
                        ->class('field-image__file')
                        ->attr('accept', implode(',', $allowedTypes))
                        ->data('max-size', $maxSize)
                )
                ->child(Html::span()->text('Upload'))
        );

        // Browse media library
        $actions->child(
            Html::button()
                ->class('field-image__browse')
                ->attr('type', 'button')
                ->data('action', 'browse')
                ->text('Browse Library')
        );

        // Remove button
        $actions->child(
            Html::button()
                ->class('field-image__remove')
                ->attr('type', 'button')
                ->data('action', 'remove')
                ->attr('style', $value ? '' : 'display: none;')
                ->text('Remove')
        );

        $wrapper->child($actions);

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return "CmsMedia.initImage('{$elementId}');";
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        if ($this->isEmpty($value)) {
            return ValidationResult::success();
        }

        $settings = $this->getSettings($field);
        $allowedTypes = $settings->getArray('allowed_types', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        // Basic URL validation
        if (is_string($value) && !filter_var($value, FILTER_VALIDATE_URL) && !str_starts_with($value, '/')) {
            return ValidationResult::failure('Invalid image URL');
        }

        return ValidationResult::success();
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $settings = $this->getSettings($field);
        $displaySize = $settings->getString('display_size', '100');

        $html = Html::element('img')
            ->class('field-display', 'field-display--image')
            ->attr('src', $value)
            ->attr('alt', '')
            ->attr('style', "max-width: {$displaySize}px; max-height: {$displaySize}px;")
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'preview_size' => [
                'type' => 'select',
                'label' => 'Preview Size',
                'options' => [
                    'small' => 'Small (100px)',
                    'medium' => 'Medium (200px)',
                    'large' => 'Large (300px)',
                ],
                'default' => 'medium',
            ],
            'display_size' => ['type' => 'integer', 'label' => 'Display Size (px)', 'default' => 100],
            'allowed_types' => ['type' => 'array', 'label' => 'Allowed MIME Types'],
            'max_size' => ['type' => 'integer', 'label' => 'Max File Size (bytes)', 'default' => 5242880],
        ];
    }
}
