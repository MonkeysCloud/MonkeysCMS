<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Reference;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;
use App\Modules\Core\Services\TaxonomyService;

/**
 * TaxonomyWidget - Reference to taxonomy terms
 *
 * Supports display styles:
 * - checkboxes: Multiple checkboxes for selection
 * - select: Dropdown select (single or multiple)
 * - tree: Hierarchical checkbox tree
 * - tags: Autocomplete tag input
 * - autocomplete: Single autocomplete input
 */
final class TaxonomyWidget extends AbstractWidget
{
    private ?TaxonomyService $taxonomyService = null;

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

    /**
     * Set taxonomy service (called by WidgetRegistry)
     */
    public function setTaxonomyService(TaxonomyService $taxonomyService): void
    {
        $this->taxonomyService = $taxonomyService;
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
        $vocabularyMachineName = $settings->getString('vocabulary');
        $displayStyle = $settings->getString('display_style', 'checkboxes');
        $multiple = $field->multiple || $settings->getBool('multiple', false);

        // Parse current value
        $values = $this->parseValue($value);

        // Get vocabulary and terms from database
        $vocabulary = null;
        $terms = [];

        if ($this->taxonomyService && $vocabularyMachineName) {
            $vocabulary = $this->taxonomyService->getVocabularyByMachineName($vocabularyMachineName);
            if ($vocabulary) {
                if ($displayStyle === 'tree') {
                    $terms = $this->taxonomyService->getTermTree($vocabulary->id, true);
                } else {
                    $terms = $this->taxonomyService->getTerms($vocabulary->id, true);
                }
            }
        }

        // Fallback to static terms from settings (for backward compatibility)
        if (empty($terms)) {
            $staticTerms = $settings->getArray('terms', []);
            $terms = $this->convertStaticTerms($staticTerms);
        }

        $wrapper = Html::div()
            ->class('field-taxonomy', "field-taxonomy--{$displayStyle}")
            ->data('field-id', $fieldId)
            ->data('vocabulary', $vocabularyMachineName)
            ->data('multiple', $multiple ? 'true' : 'false');

        // Hidden input for value
        $wrapper->child(
            Html::hidden($fieldName, json_encode($values))
                ->id($fieldId)
                ->class('field-taxonomy__value')
        );

        // Render based on display style
        switch ($displayStyle) {
            case 'select':
                $wrapper->html($this->buildSelect($terms, $values, $fieldId, $fieldName, $multiple));
                break;
            case 'tree':
                $wrapper->html($this->buildTree($terms, $values, $fieldId, $fieldName));
                break;
            case 'tags':
            case 'autocomplete':
                $wrapper->html($this->buildAutocomplete($field, $values, $context, $multiple));
                break;
            default: // checkboxes
                $wrapper->html($this->buildCheckboxes($terms, $values, $fieldId, $fieldName));
        }

        return $wrapper;
    }

    /**
     * Build checkboxes for term selection
     */
    private function buildCheckboxes(array $terms, array $values, string $fieldId, string $fieldName): string
    {
        $container = Html::div()
            ->class('field-checkbox-group', 'field-checkbox-group--vertical')
            ->id($fieldId . '_checkboxes');

        foreach ($terms as $index => $term) {
            $termId = $this->getTermId($term);
            $termLabel = $this->getTermLabel($term);
            $checked = in_array($termId, $values);
            $checkboxId = $fieldId . '_checkbox_' . $index;

            $container->child(
                Html::element('label')
                    ->class('field-checkbox')
                    ->child(
                        Html::input('checkbox')
                            ->id($checkboxId)
                            ->class('field-checkbox__input')
                            ->data('term-id', $termId)
                            ->when($checked, fn($el) => $el->attr('checked', 'checked'))
                    )
                    ->child(Html::span()->class('field-checkbox__mark'))
                    ->child(
                        Html::span()
                            ->class('field-checkbox__label')
                            ->text($termLabel)
                    )
            );
        }

        if (empty($terms)) {
            $container->child(
                Html::div()->class('field-taxonomy__empty')->text('No terms available')
            );
        }

        return $container->render();
    }

    /**
     * Build select dropdown for term selection
     */
    private function buildSelect(array $terms, array $values, string $fieldId, string $fieldName, bool $multiple): string
    {
        $select = Html::element('select')
            ->class('field-widget__control', 'field-select', 'block', 'w-full', 'rounded-lg', 'border-gray-300', 'shadow-sm', 'focus:border-blue-500', 'focus:ring-blue-500', 'sm:text-sm', 'px-3', 'py-2')
            ->id($fieldId . '_select')
            ->data('field-id', $fieldId)
            ->when($multiple, fn($el) => $el->attr('multiple', true));

        // Add empty option for single select
        if (!$multiple) {
            $select->child(
                Html::option('', '-- Select --')
            );
        }

        foreach ($terms as $term) {
            $termId = $this->getTermId($term);
            $termLabel = $this->getTermLabel($term);
            $depth = $this->getTermDepth($term);
            $prefix = str_repeat('— ', $depth);
            $selected = in_array($termId, $values);

            $select->child(
                Html::option((string)$termId, $prefix . $termLabel)
                    ->when($selected, fn($el) => $el->attr('selected', true))
            );
        }

        return $select->render();
    }

    /**
     * Build hierarchical tree for term selection
     */
    private function buildTree(array $terms, array $values, string $fieldId, string $fieldName, int $depth = 0): string
    {
        $list = Html::element('ul')
            ->class('field-tree')
            ->when($depth === 0, fn($el) => $el->addClass('field-tree--root'));

        foreach ($terms as $index => $term) {
            $termId = $this->getTermId($term);
            $termLabel = $this->getTermLabel($term);
            $children = $this->getTermChildren($term);
            $checked = in_array($termId, $values);
            $checkboxId = $fieldId . '_tree_' . $depth . '_' . $index;

            $item = Html::element('li')->class('field-tree__item');

            $item->child(
                Html::element('label')
                    ->class('field-checkbox', 'field-tree__label')
                    ->child(
                        Html::input('checkbox')
                            ->id($checkboxId)
                            ->class('field-checkbox__input')
                            ->data('term-id', $termId)
                            ->when($checked, fn($el) => $el->attr('checked', 'checked'))
                    )
                    ->child(Html::span()->class('field-checkbox__mark'))
                    ->child(
                        Html::span()
                            ->class('field-checkbox__label')
                            ->text($termLabel)
                    )
            );

            if (!empty($children)) {
                $item->html($this->buildTree($children, $values, $fieldId, $fieldName, $depth + 1));
            }

            $list->child($item);
        }

        return $list->render();
    }

    /**
     * Build autocomplete/tags input
     */
    private function buildAutocomplete(FieldDefinition $field, array $values, RenderContext $context, bool $multiple): string
    {
        $fieldId = $this->getFieldId($field, $context);
        $settings = $this->getSettings($field);
        $allowNew = $settings->getBool('allow_new', false);
        $vocabulary = $settings->getString('vocabulary');

        $html = '';

        // Selected tags container
        $tags = Html::div()
            ->class('field-tags', 'field-tags__selected')
            ->id($fieldId . '_selected');

        // Show existing values as tags (will be populated by JS)
        $html .= $tags->render();

        // Search input wrapper
        $html .= Html::element('div')
            ->class('field-tags__input-wrapper')
            ->id($fieldId . '_wrapper')
            ->child(
                Html::input('text')
                    ->class('field-widget__control', 'field-tags__input', 'block', 'w-full', 'rounded-lg', 'border-gray-300', 'shadow-sm', 'focus:border-blue-500', 'focus:ring-blue-500', 'sm:text-sm', 'px-3', 'py-2')
                    ->id($fieldId . '_search')
                    ->attr('placeholder', $allowNew ? 'Type and press Enter to add...' : 'Search terms...')
                    ->attr('autocomplete', 'off')
            )
            ->child(
                Html::div()
                    ->class('field-tags__results')
                    ->id($fieldId . '_results')
            )
            ->render();

        return $html;
    }

    /**
     * Get initialization script for the widget
     */
    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        $displayStyle = $settings->getString('display_style', 'checkboxes');
        $vocabulary = $settings->getString('vocabulary');
        $multiple = $field->multiple || $settings->getBool('multiple', false);
        $allowNew = $settings->getBool('allow_new', false);

        // Get current values for initialization
        $value = $field->default_value ?? [];
        $values = $this->parseValue($value);
        $valuesJson = json_encode($values);

        if ($displayStyle === 'tree') {
            return "initTaxonomyTree('{$elementId}', '{$vocabulary}', {$valuesJson}, " . ($multiple ? 'true' : 'false') . ");";
        }

        if ($displayStyle === 'tags' || $displayStyle === 'autocomplete') {
            $options = json_encode([
                'multiple' => $multiple,
                'allowCreate' => $allowNew,
            ]);
            return "initTaxonomyAutocomplete('{$elementId}', '{$vocabulary}', {$valuesJson}, {$options});";
        }

        // For select and checkboxes, use standard init
        return "CmsTaxonomy.init('{$elementId}');";
    }

    /**
     * Prepare value for storage
     */
    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        return $this->parseValue($value);
    }

    /**
     * Parse value to array of term IDs
     */
    private function parseValue(mixed $value): array
    {
        if (is_string($value)) {
            // Try JSON decode
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('intval', $decoded)));
            }
            // Try comma-separated
            if (strpos($value, ',') !== false) {
                return array_values(array_filter(array_map('intval', explode(',', $value))));
            }
            // Single value
            return $value ? [(int)$value] : [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }

        if (is_int($value)) {
            return [$value];
        }

        return [];
    }

    /**
     * Render display value
     */
    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        $values = $this->parseValue($value);

        if (empty($values)) {
            return parent::renderDisplay($field, null, $context);
        }

        // Load term names from database
        $termNames = [];
        $settings = $this->getSettings($field);
        $vocabularyMachineName = $settings->getString('vocabulary');

        if ($this->taxonomyService && $vocabularyMachineName) {
            $vocabulary = $this->taxonomyService->getVocabularyByMachineName($vocabularyMachineName);
            if ($vocabulary) {
                $terms = $this->taxonomyService->getTerms($vocabulary->id);
                $termMap = [];
                foreach ($terms as $term) {
                    $termMap[$term->id] = $term->name;
                }
                foreach ($values as $termId) {
                    if (isset($termMap[$termId])) {
                        $termNames[] = $termMap[$termId];
                    }
                }
            }
        }

        // Fallback to IDs if no names found
        if (empty($termNames)) {
            $termNames = array_map('strval', $values);
        }

        $tags = Html::span()->class('field-display', 'field-display--taxonomy');

        foreach ($termNames as $name) {
            $tags->child(
                Html::span()
                    ->class('field-display__tag')
                    ->text($name)
            );
        }

        return RenderResult::fromHtml($tags->render());
    }

    /**
     * Get settings schema for field configuration UI
     */
    public function getSettingsSchema(): array
    {
        // Build vocabulary options dynamically
        $vocabularyOptions = [];
        if ($this->taxonomyService) {
            $vocabularies = $this->taxonomyService->getAllVocabularies();
            foreach ($vocabularies as $vocab) {
                $vocabularyOptions[$vocab->machine_name] = $vocab->name;
            }
        }

        return [
            'vocabulary' => [
                'type' => 'select',
                'label' => 'Vocabulary',
                'required' => true,
                'options' => $vocabularyOptions,
                'description' => 'Select the taxonomy vocabulary to use',
            ],
            'display_style' => [
                'type' => 'select',
                'label' => 'Display Style',
                'options' => [
                    'checkboxes' => 'Checkboxes',
                    'select' => 'Select Dropdown',
                    'tree' => 'Hierarchical Tree',
                    'tags' => 'Tags/Autocomplete',
                ],
                'default' => 'checkboxes',
            ],
            'multiple' => [
                'type' => 'boolean',
                'label' => 'Allow Multiple Selection',
                'default' => true,
            ],
            'allow_new' => [
                'type' => 'boolean',
                'label' => 'Allow Creating New Terms',
                'default' => false,
                'description' => 'Only applies to Tags/Autocomplete display style',
            ],
            'terms' => [
                'type' => 'json',
                'label' => 'Static Terms (optional)',
                'description' => 'Override database terms with static list',
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Helper Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Get term ID (works with both Term objects and arrays)
     */
    private function getTermId(mixed $term): int
    {
        if (is_object($term)) {
            return $term->id;
        }
        return (int)($term['id'] ?? $term['value'] ?? 0);
    }

    /**
     * Get term label (works with both Term objects and arrays)
     */
    private function getTermLabel(mixed $term): string
    {
        if (is_object($term)) {
            return $term->name;
        }
        return $term['label'] ?? $term['name'] ?? (string)($term['id'] ?? '');
    }

    /**
     * Get term depth (works with both Term objects and arrays)
     */
    private function getTermDepth(mixed $term): int
    {
        if (is_object($term)) {
            return $term->depth ?? 0;
        }
        return (int)($term['depth'] ?? 0);
    }

    /**
     * Get term children (works with both Term objects and arrays)
     */
    private function getTermChildren(mixed $term): array
    {
        if (is_object($term)) {
            return $term->children ?? [];
        }
        return $term['children'] ?? [];
    }

    /**
     * Convert static terms array to consistent format
     */
    private function convertStaticTerms(array $terms): array
    {
        $result = [];
        foreach ($terms as $key => $value) {
            if (is_array($value)) {
                $result[] = $value;
            } else {
                $result[] = [
                    'id' => is_int($key) ? $key : $value,
                    'name' => is_string($value) ? $value : (string)$key,
                ];
            }
        }
        return $result;
    }
}
