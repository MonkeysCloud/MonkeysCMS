<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Layout;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * LinkWidget - URL with title and target options
 */
final class LinkWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'link';
    }

    public function getLabel(): string
    {
        return 'Link';
    }

    public function getCategory(): string
    {
        return 'Layout';
    }

    public function getIcon(): string
    {
        return '🔗';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['link', 'json', 'array'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $value = is_array($value) ? $value : ['url' => '', 'title' => '', 'target' => '_self'];
        $showTitle = $settings->getBool('show_title', true);
        $showTarget = $settings->getBool('show_target', true);

        $wrapper = Html::div()->class('field-link');

        // URL input
        $wrapper->child(
            Html::div()
                ->class('field-link__field')
                ->child(Html::label()->attr('for', $fieldId . '_url')->text('URL'))
                ->child(
                    Html::input('url')
                        ->id($fieldId . '_url')
                        ->name($fieldName . '[url]')
                        ->value($value['url'] ?? '')
                        ->attr('placeholder', 'https://...')
                        ->class('field-link__input')
                        ->when($context->isDisabled(), fn($el) => $el->disabled())
                )
        );

        // Title input
        if ($showTitle) {
            $wrapper->child(
                Html::div()
                    ->class('field-link__field')
                    ->child(Html::label()->attr('for', $fieldId . '_title')->text('Title'))
                    ->child(
                        Html::input('text')
                            ->id($fieldId . '_title')
                            ->name($fieldName . '[title]')
                            ->value($value['title'] ?? '')
                            ->attr('placeholder', 'Link text')
                            ->class('field-link__input')
                            ->when($context->isDisabled(), fn($el) => $el->disabled())
                    )
            );
        }

        // Target select
        if ($showTarget) {
            $targets = [
                '_self' => 'Same window',
                '_blank' => 'New window',
            ];

            $select = Html::select()
                ->id($fieldId . '_target')
                ->name($fieldName . '[target]')
                ->class('field-link__input')
                ->when($context->isDisabled(), fn($el) => $el->disabled());

            foreach ($targets as $targetValue => $label) {
                $select->child(Html::option($targetValue, $label, ($value['target'] ?? '_self') === $targetValue));
            }

            $wrapper->child(
                Html::div()
                    ->class('field-link__field', 'field-link__field--target')
                    ->child(Html::label()->attr('for', $fieldId . '_target')->text('Open in'))
                    ->child($select)
            );
        }

        return $wrapper;
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        if (!is_array($value) || empty($value['url'])) {
            if ($field->required) {
                return ValidationResult::failure('URL is required');
            }
            return ValidationResult::success();
        }

        if (!filter_var($value['url'], FILTER_VALIDATE_URL)) {
            return ValidationResult::failure('Please enter a valid URL');
        }

        return ValidationResult::success();
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if (!is_array($value)) {
            return null;
        }

        if (empty($value['url'])) {
            return null;
        }

        return [
            'url' => $value['url'],
            'title' => $value['title'] ?? '',
            'target' => $value['target'] ?? '_self',
        ];
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if (!is_array($value) || empty($value['url'])) {
            return parent::renderDisplay($field, null, $context);
        }

        $title = !empty($value['title']) ? $value['title'] : $value['url'];
        $target = $value['target'] ?? '_self';

        $html = Html::element('a')
            ->class('field-display', 'field-display--link')
            ->attr('href', $value['url'])
            ->attr('target', $target)
            ->when($target === '_blank', fn($el) => $el->attr('rel', 'noopener noreferrer'))
            ->text($title)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'show_title' => ['type' => 'boolean', 'label' => 'Show Title Field', 'default' => true],
            'show_target' => ['type' => 'boolean', 'label' => 'Show Target Options', 'default' => true],
        ];
    }
}
