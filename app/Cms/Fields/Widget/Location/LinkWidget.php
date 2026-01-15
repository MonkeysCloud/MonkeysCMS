<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Location;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * LinkWidget - URL with optional title and target
 */
final class LinkWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'link_field';
    }

    public function getLabel(): string
    {
        return 'Link';
    }

    public function getCategory(): string
    {
        return 'Location';
    }

    public function getIcon(): string
    {
        return '🔗';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['link', 'url', 'json', 'object'];
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/link.css');
        $this->assets->addJs('/js/fields/link.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $showTitle = $settings->getBool('show_title', true);
        $showTarget = $settings->getBool('show_target', true);

        // Parse value
        $link = is_array($value) ? $value : ['url' => $value ?? '', 'title' => '', 'target' => '_self'];

        $wrapper = Html::div()
            ->class('field-link')
            ->data('field-id', $fieldId);

        // Hidden input for JSON value
        $wrapper->child(
            Html::hidden($fieldName, json_encode($link))
                ->id($fieldId)
                ->class('field-link__value')
        );

        // URL input (full width, no label)
        $wrapper->child(
            Html::input('url')
                ->id($fieldId . '_url')
                ->class('field-link__input')
                ->data('field', 'url')
                ->value($link['url'] ?? '')
                ->attr('placeholder', 'https://...')
        );

        // Title input
        if ($showTitle) {
            $wrapper->child(
                Html::input('text')
                    ->id($fieldId . '_title')
                    ->class('field-link__input')
                    ->data('field', 'title')
                    ->value($link['title'] ?? '')
                    ->attr('placeholder', 'Link text')
            );
        }

        // Target select as dropdown
        if ($showTarget) {
            $select = Html::element('select')
                ->id($fieldId . '_target')
                ->class('field-link__select')
                ->data('field', 'target');
            
            $select->child(
                Html::option('_self', 'Open in Same window')
                    ->when(($link['target'] ?? '_self') === '_self', fn($el) => $el->attr('selected', true))
            );
            $select->child(
                Html::option('_blank', 'Open in New window')
                    ->when(($link['target'] ?? '_self') === '_blank', fn($el) => $el->attr('selected', true))
            );
            
            $wrapper->child($select);
        }

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return "CmsLink.init('{$elementId}');";
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if (is_string($value)) {
            // Check if it's JSON
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            // Plain URL string
            return ['url' => $value, 'title' => '', 'target' => '_self'];
        }

        if (is_array($value)) {
            return [
                'url' => $value['url'] ?? '',
                'title' => $value['title'] ?? '',
                'target' => isset($value['external']) && $value['external'] ? '_blank' : ($value['target'] ?? '_self'),
            ];
        }

        return null;
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        if ($this->isEmpty($value)) {
            return ValidationResult::success();
        }

        $link = is_array($value) ? $value : ['url' => $value];
        $url = $link['url'] ?? '';

        if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
            // Allow relative URLs
            if (!str_starts_with($url, '/') && !str_starts_with($url, '#')) {
                return ValidationResult::failure('Please enter a valid URL');
            }
        }

        return ValidationResult::success();
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if (!is_array($value) && !is_string($value)) {
            return parent::renderDisplay($field, null, $context);
        }

        $link = is_array($value) ? $value : ['url' => $value];
        $url = $link['url'] ?? '';
        $title = $link['title'] ?? $url;
        $target = $link['target'] ?? '_self';

        if (empty($url)) {
            return parent::renderDisplay($field, null, $context);
        }

        $anchor = Html::element('a')
            ->class('field-display', 'field-display--link')
            ->attr('href', $url)
            ->text($title);

        if ($target === '_blank') {
            $anchor->attr('target', '_blank')
                ->attr('rel', 'noopener noreferrer');
        }

        return RenderResult::fromHtml($anchor->render());
    }

    public function getSettingsSchema(): array
    {
        return [
            'show_title' => ['type' => 'boolean', 'label' => 'Show Title Field', 'default' => true],
            'show_target' => ['type' => 'boolean', 'label' => 'Show Target Option', 'default' => true],
            'allowed_protocols' => [
                'type' => 'array',
                'label' => 'Allowed Protocols',
                'default' => ['http', 'https', 'mailto', 'tel'],
            ],
        ];
    }
}
