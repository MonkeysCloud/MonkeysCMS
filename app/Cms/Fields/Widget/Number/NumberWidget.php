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
 * NumberWidget - Number input with min/max/step
 */
final class NumberWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'number';
    }

    public function getLabel(): string
    {
        return 'Number';
    }

    public function getCategory(): string
    {
        return 'Number';
    }

    public function getIcon(): string
    {
        return '🔢';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['integer', 'float', 'decimal'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $prefix = $settings->getString('prefix');
        $suffix = $settings->getString('suffix');

        $input = Html::input('number')
            ->attrs($this->buildCommonAttributes($field, $context))
            ->value($value ?? '');

        if (($min = $settings->get('min')) !== null) {
            $input->attr('min', $min);
        }

        if (($max = $settings->get('max')) !== null) {
            $input->attr('max', $max);
        }

        if ($step = $settings->get('step')) {
            $input->attr('step', $step);
        } elseif ($field->field_type === 'integer') {
            $input->attr('step', 1);
        } else {
            $input->attr('step', 'any');
        }

        if (!$prefix && !$suffix) {
            return $input;
        }

        // Wrap with prefix/suffix
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

        return match ($field->field_type) {
            'integer' => (int) $value,
            'float', 'decimal' => (float) $value,
            default => $value,
        };
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        $settings = $this->getSettings($field);
        $errors = [];

        if (($min = $settings->get('min')) !== null && $value < $min) {
            $errors[] = "Value must be at least {$min}";
        }

        if (($max = $settings->get('max')) !== null && $value > $max) {
            $errors[] = "Value must be at most {$max}";
        }

        return empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $settings = $this->getSettings($field);
        $decimals = $settings->getInt('decimals', $field->field_type === 'integer' ? 0 : 2);
        $formatted = $this->formatNumber($value, $decimals);

        $html = Html::span()
            ->class('field-display', 'field-display--number')
            ->text($formatted)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'min' => ['type' => 'number', 'label' => 'Minimum Value'],
            'max' => ['type' => 'number', 'label' => 'Maximum Value'],
            'step' => ['type' => 'number', 'label' => 'Step'],
            'prefix' => ['type' => 'string', 'label' => 'Prefix'],
            'suffix' => ['type' => 'string', 'label' => 'Suffix'],
            'decimals' => ['type' => 'integer', 'label' => 'Decimal Places', 'default' => 2],
        ];
    }
}
