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
 * FileWidget - File upload
 */
final class FileWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'file';
    }

    public function getLabel(): string
    {
        return 'File';
    }

    public function getCategory(): string
    {
        return 'Media';
    }

    public function getIcon(): string
    {
        return '📎';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['file', 'document'];
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
        $allowedExtensions = $settings->getArray('allowed_extensions', []);
        $maxSize = $settings->getInt('max_size', 10 * 1024 * 1024); // 10MB default

        $wrapper = Html::div()
            ->class('field-file')
            ->data('field-id', $fieldId);

        // Hidden input for value
        $wrapper->child(
            Html::hidden($fieldName, $value ?? '')
                ->id($fieldId)
                ->class('field-file__value')
        );

        // Current file info
        $info = Html::div()
            ->class('field-file__info')
            ->attr('style', $value ? '' : 'display: none;');

        if ($value) {
            $filename = basename($value);
            $info->child(
                Html::span()
                    ->class('field-file__icon')
                    ->text($this->getFileIcon($filename))
            );
            $info->child(
                Html::span()
                    ->class('field-file__name')
                    ->text($filename)
            );
            $info->child(
                Html::element('a')
                    ->class('field-file__download')
                    ->attr('href', $value)
                    ->attr('target', '_blank')
                    ->text('Download')
            );
        }

        $wrapper->child($info);

        // Dropzone
        $dropzone = Html::div()
            ->class('field-file__dropzone')
            ->text('Drag & drop file here or click to browse');

        $dropzone->child(
            Html::input('file')
                ->class('field-file__input')
                ->attr('accept', $this->buildAcceptAttribute($allowedExtensions))
                ->data('max-size', $maxSize)
        );

        $wrapper->child($dropzone);

        // Actions
        $wrapper->child(
            Html::button()
                ->class('field-file__remove')
                ->attr('type', 'button')
                ->data('action', 'remove')
                ->attr('style', $value ? '' : 'display: none;')
                ->text('Remove')
        );

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return "CmsMedia.initFile('{$elementId}');";
    }

    private function getFileIcon(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => '📕',
            'doc', 'docx' => '📘',
            'xls', 'xlsx' => '📗',
            'ppt', 'pptx' => '📙',
            'zip', 'rar', '7z' => '📦',
            'txt' => '📄',
            'csv' => '📊',
            default => '📎',
        };
    }

    private function buildAcceptAttribute(array $extensions): string
    {
        if (empty($extensions)) {
            return '';
        }

        return implode(',', array_map(fn($ext) => '.' . ltrim($ext, '.'), $extensions));
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $filename = basename($value);
        $icon = $this->getFileIcon($filename);

        $html = Html::element('a')
            ->class('field-display', 'field-display--file')
            ->attr('href', $value)
            ->attr('target', '_blank')
            ->child(Html::span()->text($icon . ' '))
            ->text($filename)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'allowed_extensions' => ['type' => 'array', 'label' => 'Allowed Extensions'],
            'max_size' => ['type' => 'integer', 'label' => 'Max File Size (bytes)', 'default' => 10485760],
        ];
    }
}
