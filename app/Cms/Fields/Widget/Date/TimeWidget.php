<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Date;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * TimeWidget - Time picker
 */
final class TimeWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'time';
    }

    public function getLabel(): string
    {
        return 'Time';
    }

    public function getCategory(): string
    {
        return 'Date/Time';
    }

    public function getIcon(): string
    {
        return '🕐';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['time'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $formattedValue = $this->formatValue($field, $value);

        $input = Html::input('time')
            ->attrs($this->buildCommonAttributes($field, $context))
            ->value($formattedValue);

        if ($step = $settings->getInt('step')) {
            $input->attr('step', $step);
        }

        return $input;
    }

    public function formatValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return '';
        }

        // Format for time input (HH:MM or HH:MM:SS)
        return $this->formatDate($value, 'H:i');
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        // Ensure consistent format
        return $this->formatDate($value, 'H:i:s');
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $settings = $this->getSettings($field);
        $format = $settings->getString('display_format', 'g:i A');
        $formatted = $this->formatDate($value, $format);

        $html = Html::span()
            ->class('field-display', 'field-display--time')
            ->text($formatted)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'step' => ['type' => 'integer', 'label' => 'Step (seconds)'],
            'display_format' => ['type' => 'string', 'label' => 'Display Format', 'default' => 'g:i A'],
        ];
    }
}
