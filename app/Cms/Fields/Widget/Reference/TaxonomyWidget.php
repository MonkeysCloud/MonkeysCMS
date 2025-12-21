<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Reference;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * TaxonomyWidget - Reference to taxonomy terms
 */
final class TaxonomyWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'taxonomy';
    }

    public function getLabel(): string
    {
        return 'Taxonomy';
    }

    public function getCategory(): string
    {
        return 'Reference';
    }

    public function getIcon(): string
    {
        return '🏷️';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['taxonomy_reference', 'taxonomy', 'tags'];
    }

    public function supportsMultiple(): bool
    {
        return true;
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/reference.css');
        $this->assets->addJs('/js/fields/taxonomy.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $vocabulary = $settings->getString('vocabulary');
        $displayStyle = $settings->getString('display_style', 'checkboxes');
        $values = is_array($value) ? $value : ($value ? [$value] : []);
        $terms = $settings->getArray('terms', []); // In production, fetch from database

        $wrapper = Html::div()
            ->class('field-taxonomy', "field-taxonomy--{$displayStyle}")
            ->data('field-id', $fieldId)
            ->data('vocabulary', $vocabulary);

        // Hidden input for value
        $wrapper->child(
            Html::hidden($fieldName, json_encode($values))
                ->id($fieldId)
                ->class('field-taxonomy__value')
        );

        if ($displayStyle === 'tree') {
            $wrapper->html($this->buildTree($terms, $values, $fieldId, $fieldName));
        } elseif ($displayStyle === 'tags') {
            $wrapper->html($this->buildTagInput($field, $values, $context));
        } else {
            $wrapper->html($this->buildCheckboxes($terms, $values, $fieldId, $fieldName));
        }

        return $wrapper;
    }

    private function buildCheckboxes(array $terms, array $values, string $fieldId, string $fieldName): string
    {
        $container = Html::div()->class('field-taxonomy__checkboxes');

        foreach ($terms as $term) {
            $termId = $term['id'] ?? $term['value'] ?? '';
            $termLabel = $term['label'] ?? $term['name'] ?? $termId;
            $checked = in_array($termId, $values);

            $container->child(
                Html::element('label')
                    ->class('field-taxonomy__checkbox')
                    ->child(
                        Html::input('checkbox')
                            ->name($fieldName . '[]')
                            ->value($termId)
                            ->when($checked, fn($el) => $el->attr('checked', true))
                    )
                    ->child(Html::span()->text($termLabel))
            );
        }

        return $container->render();
    }

    private function buildTree(array $terms, array $values, string $fieldId, string $fieldName, int $depth = 0): string
    {
        $list = Html::element('ul')
            ->class('field-taxonomy__tree')
            ->when($depth === 0, fn($el) => $el->addClass('field-taxonomy__tree--root'));

        foreach ($terms as $term) {
            $termId = $term['id'] ?? $term['value'] ?? '';
            $termLabel = $term['label'] ?? $term['name'] ?? $termId;
            $children = $term['children'] ?? [];
            $checked = in_array($termId, $values);

            $item = Html::element('li')->class('field-taxonomy__tree-item');

            $item->child(
                Html::element('label')
                    ->child(
                        Html::input('checkbox')
                            ->name($fieldName . '[]')
                            ->value($termId)
                            ->when($checked, fn($el) => $el->attr('checked', true))
                    )
                    ->child(Html::span()->text($termLabel))
            );

            if (!empty($children)) {
                $item->html($this->buildTree($children, $values, $fieldId, $fieldName, $depth + 1));
            }

            $list->child($item);
        }

        return $list->render();
    }

    private function buildTagInput(FieldDefinition $field, array $values, RenderContext $context): string
    {
        $fieldId = $this->getFieldId($field, $context);
        $settings = $this->getSettings($field);
        $allowNew = $settings->getBool('allow_new', false);

        $html = '';

        // Selected tags
        $tags = Html::div()->class('field-taxonomy__tags');

        foreach ($values as $value) {
            $tags->child(
                Html::span()
                    ->class('field-taxonomy__tag')
                    ->data('value', $value)
                    ->text($value)
                    ->child(
                        Html::button()
                            ->class('field-taxonomy__tag-remove')
                            ->attr('type', 'button')
                            ->text('×')
                    )
            );
        }

        $html .= $tags->render();

        // Input for adding tags
        $html .= Html::input('text')
            ->class('field-taxonomy__tag-input')
            ->id($fieldId . '_input')
            ->attr('placeholder', $allowNew ? 'Type and press Enter to add...' : 'Search...')
            ->render();

        // Suggestions dropdown
        $html .= Html::div()
            ->class('field-taxonomy__suggestions')
            ->id($fieldId . '_suggestions')
            ->render();

        return $html;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        $displayStyle = $settings->getString('display_style', 'checkboxes');

        if ($displayStyle === 'tags') {
            $vocabulary = $settings->getString('vocabulary');
            $apiUrl = "/api/taxonomy/{$vocabulary}/terms";
            return "CmsTaxonomy.initTags('{$elementId}', '{$apiUrl}');";
        }

        return "CmsTaxonomy.init('{$elementId}');";
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (!is_array($value)) {
            return $value ? [$value] : [];
        }

        return array_values(array_filter($value));
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        $values = is_array($value) ? $value : [];

        if (empty($values)) {
            return parent::renderDisplay($field, null, $context);
        }

        $tags = Html::span()->class('field-display', 'field-display--taxonomy');

        foreach ($values as $v) {
            $tags->child(
                Html::span()
                    ->class('field-display__tag')
                    ->text($v)
            );
        }

        return RenderResult::fromHtml($tags->render());
    }

    public function getSettingsSchema(): array
    {
        return [
            'vocabulary' => ['type' => 'string', 'label' => 'Vocabulary'],
            'display_style' => [
                'type' => 'select',
                'label' => 'Display Style',
                'options' => [
                    'checkboxes' => 'Checkboxes',
                    'tree' => 'Tree',
                    'tags' => 'Tags Input',
                ],
                'default' => 'checkboxes',
            ],
            'allow_new' => ['type' => 'boolean', 'label' => 'Allow Creating New Terms', 'default' => false],
            'terms' => ['type' => 'json', 'label' => 'Static Terms (optional)'],
        ];
    }
}
