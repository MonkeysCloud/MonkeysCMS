<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Selection;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * SelectWidget - Dropdown select
 */
final class SelectWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'select';
    }

    public function getLabel(): string
    {
        return 'Select';
    }

    public function getCategory(): string
    {
        return 'Selection';
    }

    public function getIcon(): string
    {
        return '▼';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['select', 'string', 'integer', 'entity_reference', 'taxonomy_reference'];
    }

    public function supportsMultiple(): bool
    {
        return true;
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $options = $this->getOptions($field);
        $values = is_array($value) ? $value : [$value];
        
        $select = Html::select()
            ->attrs($this->buildCommonAttributes($field, $context));

        if ($field->multiple) {
            $select->attr('multiple', true);
        }

        if ($settings->getBool('searchable')) {
            $select->data('searchable', 'true');
        }

        // Empty option
        if ($emptyOption = $settings->getString('empty_option', '- Select -')) {
            $select->child(
                Html::option('', $emptyOption)
            );
        }

        // Options
        foreach ($options as $optValue => $optLabel) {
            // Support option groups
            if (is_array($optLabel) && isset($optLabel['options'])) {
                $optgroup = Html::element('optgroup')
                    ->attr('label', $optLabel['label'] ?? $optValue);
                
                foreach ($optLabel['options'] as $groupOptValue => $groupOptLabel) {
                    $optgroup->child(
                        Html::option(
                            (string) $groupOptValue,
                            (string) $groupOptLabel,
                            in_array($groupOptValue, $values, true)
                        )
                    );
                }
                
                $select->child($optgroup);
            } else {
                $select->child(
                    Html::option(
                        (string) $optValue,
                        (string) $optLabel,
                        in_array($optValue, $values, true)
                    )
                );
            }
        }

        return $select;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $options = $this->getOptions($field);
        $values = is_array($value) ? $value : [$value];
        
        $labels = [];
        foreach ($values as $v) {
            $labels[] = $options[$v] ?? $v;
        }

        $html = Html::span()
            ->class('field-display', 'field-display--select')
            ->text(implode(', ', $labels))
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'empty_option' => ['type' => 'string', 'label' => 'Empty Option Text', 'default' => '- Select -'],
            'searchable' => ['type' => 'boolean', 'label' => 'Searchable', 'default' => false],
            'options' => ['type' => 'json', 'label' => 'Options (JSON)'],
        ];
    }
}
