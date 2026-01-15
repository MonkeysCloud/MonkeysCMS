<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Number;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * DecimalWidget - Decimal number with currency support
 */
final class DecimalWidget extends AbstractWidget
{
    private const CURRENCIES = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CNY' => '¥',
        'MXN' => '$',
    ];

    public function getId(): string
    {
        return 'decimal';
    }

    public function getLabel(): string
    {
        return 'Decimal / Currency';
    }

    public function getCategory(): string
    {
        return 'Number';
    }

    public function getIcon(): string
    {
        return '💰';
    }

    public function getPriority(): int
    {
        return 110;
    }

    public function getSupportedTypes(): array
    {
        return ['decimal', 'float'];
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/decimal.css');
        $this->assets->addJs('/js/fields/decimal.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);

        $currency = $settings->getString('currency');
        $symbol = $currency ? (self::CURRENCIES[$currency] ?? $currency) : null;
        $prefix = $symbol ?? $settings->getString('prefix');
        $suffix = $settings->getString('suffix');
        $decimals = $settings->getInt('decimals', 2);
        $min = $settings->get('min');
        $max = $settings->get('max');

        // Main wrapper
        $wrapper = Html::div()
            ->class('field-decimal')
            ->data('field-id', $fieldId);

        // Input wrapper for icons positioning
        $inputWrapper = Html::div()->class('field-decimal__input-wrapper');

        // Build input group if has prefix/suffix
        if ($prefix || $suffix) {
            $inputGroup = Html::div()->class('field-decimal__input-group');

            if ($prefix) {
                $inputGroup->child(
                    Html::span()
                        ->class('field-decimal__prefix')
                        ->text($prefix)
                );
            }

            $input = Html::input('number')
                ->id($fieldId)
                ->name($fieldName)
                ->class('field-decimal__input')
                ->attr('step', '0.' . str_repeat('0', $decimals - 1) . '1')
                ->attr('placeholder', '0.' . str_repeat('0', $decimals))
                ->value($value ?? '');

            if ($min !== null) {
                $input->attr('min', $min);
            }
            if ($max !== null) {
                $input->attr('max', $max);
            }

            $inputGroup->child($input);

            if ($suffix) {
                $inputGroup->child(
                    Html::span()
                        ->class('field-decimal__suffix')
                        ->text($suffix)
                );
            }

            $inputWrapper->child($inputGroup);
        } else {
            // Simple input without prefix/suffix
            $input = Html::input('number')
                ->id($fieldId)
                ->name($fieldName)
                ->class('field-decimal__input')
                ->attr('step', '0.' . str_repeat('0', $decimals - 1) . '1')
                ->attr('placeholder', '0.' . str_repeat('0', $decimals))
                ->value($value ?? '');

            if ($min !== null) {
                $input->attr('min', $min);
            }
            if ($max !== null) {
                $input->attr('max', $max);
            }

            $inputWrapper->child($input);
        }

        $wrapper->child($inputWrapper);

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        $decimals = $settings->getInt('decimals', 2);
        $min = $settings->get('min');
        $max = $settings->get('max');

        $options = ['decimals' => $decimals];
        if ($min !== null) {
            $options['min'] = $min;
        }
        if ($max !== null) {
            $options['max'] = $max;
        }

        $optionsJson = json_encode($options);
        return "CmsDecimal.init('{$elementId}', {$optionsJson});";
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        if ($this->isEmpty($value)) {
            return ValidationResult::success();
        }

        if (!is_numeric($value)) {
            return ValidationResult::failure('Please enter a valid number');
        }

        $settings = $this->getSettings($field);
        $min = $settings->get('min');
        $max = $settings->get('max');

        if ($min !== null && (float) $value < (float) $min) {
            return ValidationResult::failure("Value must be at least {$min}");
        }

        if ($max !== null && (float) $value > (float) $max) {
            return ValidationResult::failure("Value must be at most {$max}");
        }

        return ValidationResult::success();
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $settings = $this->getSettings($field);
        $decimals = $settings->getInt('decimals', 2);

        return round((float) $value, $decimals);
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $settings = $this->getSettings($field);
        $currency = $settings->getString('currency');
        $symbol = $currency ? (self::CURRENCIES[$currency] ?? $currency) : null;
        $prefix = $symbol ?? $settings->getString('prefix');
        $suffix = $settings->getString('suffix');
        $decimals = $settings->getInt('decimals', 2);

        $formatted = number_format((float) $value, $decimals);

        if ($prefix) {
            $formatted = $prefix . $formatted;
        }
        if ($suffix) {
            $formatted .= $suffix;
        }

        $html = Html::span()
            ->class('field-display', 'field-display--decimal')
            ->text($formatted)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'decimals' => ['type' => 'integer', 'label' => 'Decimal Places', 'default' => 2],
            'min' => ['type' => 'number', 'label' => 'Minimum Value'],
            'max' => ['type' => 'number', 'label' => 'Maximum Value'],
            'currency' => [
                'type' => 'select',
                'label' => 'Currency',
                'options' => array_combine(
                    array_keys(self::CURRENCIES),
                    array_map(fn($c, $s) => "{$c} ({$s})", array_keys(self::CURRENCIES), self::CURRENCIES)
                ),
            ],
            'prefix' => ['type' => 'string', 'label' => 'Prefix (if no currency)'],
            'suffix' => ['type' => 'string', 'label' => 'Suffix'],
        ];
    }
}
