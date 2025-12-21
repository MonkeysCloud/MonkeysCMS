<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Special;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * HiddenWidget - Hidden input
 */
final class HiddenWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'hidden';
    }

    public function getLabel(): string
    {
        return 'Hidden';
    }

    public function getCategory(): string
    {
        return 'Special';
    }

    public function getIcon(): string
    {
        return '👁️‍🗨️';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['hidden', 'string', 'integer'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        return Html::hidden(
            $this->getFieldName($field, $context),
            $value ?? ''
        )->id($this->getFieldId($field, $context));
    }

    protected function buildWrapper(FieldDefinition $field, string $inputHtml, RenderContext $context): string
    {
        // No wrapper for hidden fields
        return $inputHtml;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        // Hidden fields typically don't display
        return RenderResult::empty();
    }
}
