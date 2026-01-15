<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Date;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
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

    protected function initializeAssets(): void
    {
        // Flatpickr library for custom date picker
        $this->assets->addCss('https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
        $this->assets->addJs('https://cdn.jsdelivr.net/npm/flatpickr');
        
        // Custom styling overrides
        $this->assets->addCss('/css/fields/date.css');
        $this->assets->addJs('/js/fields/date.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $formattedValue = $this->formatValue($field, $value);

        // Main wrapper
        $wrapper = Html::div()
            ->class('field-date')
            ->data('field-id', $fieldId);

        // Input wrapper
        $inputWrapper = Html::div()->class('field-date__input-wrapper');

        $input = Html::input('date')
            ->id($fieldId)
            ->name($fieldName)
            ->class('field-date__input')
            ->value($formattedValue);

        if ($min = $settings->getString('min_date')) {
            $input->attr('min', $min);
        }

        if ($max = $settings->getString('max_date')) {
            $input->attr('max', $max);
        }

        $inputWrapper->child($input);
        $wrapper->child($inputWrapper);

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        $minDate = $settings->getString('min_date');
        $maxDate = $settings->getString('max_date');

        $options = [];
        if ($minDate) {
            $options['minDate'] = $minDate;
        }
        if ($maxDate) {
            $options['maxDate'] = $maxDate;
        }

        $optionsJson = json_encode($options);
        return "CmsDate.init('{$elementId}', {$optionsJson});";
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        if ($this->isEmpty($value)) {
            return ValidationResult::success();
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return ValidationResult::failure('Please enter a valid date');
        }

        $settings = $this->getSettings($field);
        
        if ($minDate = $settings->getString('min_date')) {
            $min = \DateTime::createFromFormat('Y-m-d', $minDate);
            if ($min && $date < $min) {
                return ValidationResult::failure("Date must be on or after {$minDate}");
            }
        }

        if ($maxDate = $settings->getString('max_date')) {
            $max = \DateTime::createFromFormat('Y-m-d', $maxDate);
            if ($max && $date > $max) {
                return ValidationResult::failure("Date must be on or before {$maxDate}");
            }
        }

        return ValidationResult::success();
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
