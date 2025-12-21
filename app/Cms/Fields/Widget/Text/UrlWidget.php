<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Text;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * UrlWidget - URL input with validation
 */
final class UrlWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'url';
    }

    public function getLabel(): string
    {
        return 'URL';
    }

    public function getCategory(): string
    {
        return 'Text';
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
        return ['url', 'string'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);

        $input = Html::input('url')
            ->attrs($this->buildCommonAttributes($field, $context))
            ->value($value ?? '');

        if ($settings->getBool('show_preview') && $value) {
            return Html::div()
                ->class('field-url')
                ->child($input)
                ->child(
                    Html::element('a')
                        ->class('field-url__preview')
                        ->attr('href', $value)
                        ->attr('target', '_blank')
                        ->attr('rel', 'noopener')
                        ->text('Open ↗')
                );
        }

        return $input;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $html = Html::element('a')
            ->class('field-display', 'field-display--url')
            ->attr('href', $value)
            ->attr('target', '_blank')
            ->attr('rel', 'noopener')
            ->text($value)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'placeholder' => ['type' => 'string', 'label' => 'Placeholder', 'default' => 'https://'],
            'show_preview' => ['type' => 'boolean', 'label' => 'Show Preview Link', 'default' => false],
        ];
    }
}
