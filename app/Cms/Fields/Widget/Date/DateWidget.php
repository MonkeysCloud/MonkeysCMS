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
 * DateWidget - Date picker
 */
final class DateWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'date';
    }

    public function getLabel(): string
    {
        return 'Date';
    }

    public function getCategory(): string
    {
        return 'Date/Time';
    }

    public function getIcon(): string
    {
        return '📅';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['date'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $formattedValue = $this->formatValue($field, $value);
        
        $input = Html::input('date')
            ->attrs($this->buildCommonAttributes($field, $context))
            ->value($formattedValue);

        if ($min = $settings->getString('min_date')) {
            $input->attr('min', $min);
        }

        if ($max = $settings->getString('max_date')) {
            $input->attr('max', $max);
        }

        return $input;
    }

    public function formatValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return '';
        }

        return $this->formatDate($value, 'Y-m-d');
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        // Convert to standard format for storage
        return $this->formatDate($value, 'Y-m-d');
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $settings = $this->getSettings($field);
        $format = $settings->getString('display_format', 'F j, Y');
        $formatted = $this->formatDate($value, $format);

        $html = Html::span()
            ->class('field-display', 'field-display--date')
            ->text($formatted)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'min_date' => ['type' => 'string', 'label' => 'Minimum Date (YYYY-MM-DD)'],
            'max_date' => ['type' => 'string', 'label' => 'Maximum Date (YYYY-MM-DD)'],
            'display_format' => ['type' => 'string', 'label' => 'Display Format', 'default' => 'F j, Y'],
        ];
    }
}
