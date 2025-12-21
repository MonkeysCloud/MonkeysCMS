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
 * DateTimeWidget - Date and time picker
 */
final class DateTimeWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'datetime';
    }

    public function getLabel(): string
    {
        return 'Date & Time';
    }

    public function getCategory(): string
    {
        return 'Date/Time';
    }

    public function getIcon(): string
    {
        return '📆';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['datetime'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $formattedValue = $this->formatValue($field, $value);
        
        $input = Html::input('datetime-local')
            ->attrs($this->buildCommonAttributes($field, $context))
            ->value($formattedValue);

        if ($min = $settings->getString('min_datetime')) {
            $input->attr('min', $min);
        }

        if ($max = $settings->getString('max_datetime')) {
            $input->attr('max', $max);
        }

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

        // Format for datetime-local input (YYYY-MM-DDTHH:MM)
        return $this->formatDate($value, 'Y-m-d\TH:i');
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        // Convert to standard format for storage
        return $this->formatDate($value, 'Y-m-d H:i:s');
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $settings = $this->getSettings($field);
        $format = $settings->getString('display_format', 'F j, Y g:i A');
        $formatted = $this->formatDate($value, $format);

        $html = Html::span()
            ->class('field-display', 'field-display--datetime')
            ->text($formatted)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'min_datetime' => ['type' => 'string', 'label' => 'Minimum Date/Time'],
            'max_datetime' => ['type' => 'string', 'label' => 'Maximum Date/Time'],
            'step' => ['type' => 'integer', 'label' => 'Step (seconds)'],
            'display_format' => ['type' => 'string', 'label' => 'Display Format', 'default' => 'F j, Y g:i A'],
        ];
    }
}
