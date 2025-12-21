<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Number;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
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

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $currency = $settings->getString('currency');
        $symbol = $currency ? (self::CURRENCIES[$currency] ?? $currency) : null;
        $prefix = $symbol ?? $settings->getString('prefix');
        $suffix = $settings->getString('suffix');
        $decimals = $settings->getInt('decimals', 2);
        
        $input = Html::input('number')
            ->attrs($this->buildCommonAttributes($field, $context))
            ->attr('step', '0.' . str_repeat('0', $decimals - 1) . '1')
            ->value($value ?? '');

        if (($min = $settings->get('min')) !== null) {
            $input->attr('min', $min);
        }

        if (($max = $settings->get('max')) !== null) {
            $input->attr('max', $max);
        }

        if (!$prefix && !$suffix) {
            return $input;
        }

        $wrapper = Html::div()->class('field-input-group');

        if ($prefix) {
            $wrapper->child(
                Html::span()
                    ->class('field-input-group__prefix')
                    ->text($prefix)
            );
        }

        $wrapper->child($input);

        if ($suffix) {
            $wrapper->child(
                Html::span()
                    ->class('field-input-group__suffix')
                    ->text($suffix)
            );
        }

        return $wrapper;
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

        $formatted = $this->formatNumber($value, $decimals);
        
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
