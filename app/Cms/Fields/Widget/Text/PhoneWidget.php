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
 * PhoneWidget - Phone number input
 */
final class PhoneWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'phone';
    }

    public function getLabel(): string
    {
        return 'Phone';
    }

    public function getCategory(): string
    {
        return 'Text';
    }

    public function getIcon(): string
    {
        return '📞';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['phone', 'string'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        
        $input = Html::input('tel')
            ->attrs($this->buildCommonAttributes($field, $context))
            ->value($value ?? '');

        if ($pattern = $settings->getString('pattern')) {
            $input->attr('pattern', $pattern);
        }

        return $input;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $html = Html::element('a')
            ->class('field-display', 'field-display--phone')
            ->attr('href', 'tel:' . preg_replace('/[^+0-9]/', '', $value))
            ->text($value)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'placeholder' => ['type' => 'string', 'label' => 'Placeholder'],
            'pattern' => ['type' => 'string', 'label' => 'Pattern'],
        ];
    }
}
