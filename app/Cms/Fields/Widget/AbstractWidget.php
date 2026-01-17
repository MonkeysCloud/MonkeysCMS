<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

use App\Cms\Fields\Definition\Field;
use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\AssetCollection;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Settings\FieldSettings;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Value\FieldValue;

/**
 * AbstractWidget - Base implementation for field widgets
 *
 * Provides common functionality for field rendering, value handling,
 * and asset management. Extend this class to create custom widgets.
 */
abstract class AbstractWidget implements WidgetInterface
{
    protected AssetCollection $assets;

    public function __construct()
    {
        $this->assets = new AssetCollection();
        $this->initializeAssets();
    }

    // =========================================================================
    // Abstract Methods - Must be implemented by subclasses
    // =========================================================================

    abstract public function getId(): string;
    abstract public function getLabel(): string;
    abstract public function getSupportedTypes(): array;

    /**
     * Build the input element(s) for this widget
     */
    abstract protected function buildInput(
        FieldDefinition $field,
        mixed $value,
        RenderContext $context
    ): HtmlBuilder|string;

    // =========================================================================
    // Default Implementations
    // =========================================================================

    public function getCategory(): string
    {
        return 'General';
    }

    public function getIcon(): string
    {
        return '📝';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function supportsMultiple(): bool
    {
        return false;
    }

    public function getSettingsSchema(): array
    {
        return [];
    }

    /**
     * Override to add CSS/JS assets
     */
    protected function initializeAssets(): void
    {
        // Subclasses can override to add assets
    }

    public function getAssets(): AssetCollection
    {
        return $this->assets;
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    public function renderField(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        $formattedValue = $this->formatValue($field, $value);

        // Build the input element
        $input = $this->buildInput($field, $formattedValue, $context);
        $inputHtml = $input instanceof HtmlBuilder ? $input->render() : $input;

        // Wrap with field wrapper
        $wrapperHtml = $this->buildWrapper($field, $inputHtml, $context);

        // Clone assets and add any initialization scripts
        $assets = clone $this->assets;
        $initScript = $this->getInitScript($field, $this->getFieldId($field, $context));
        if ($initScript) {
            $assets->addInitScript($initScript);
        }

        return RenderResult::create($wrapperHtml, $assets);
    }

    public function render(Field $field, FieldValue $value, RenderContext $context): WidgetOutput
    {
        // Hydrate a legacy FieldDefinition from the new Field object
        $legacyField = FieldDefinition::fromArray($field->toArray());

        // Use the raw value
        $legacyValue = $value->get();

        // Call the legacy render logic
        $result = $this->renderField($legacyField, $legacyValue, $context);

        // Convert the result back to the new output format
        $output = WidgetOutput::html($result->getHtml());

        // Copy assets if any
        if (!$result->getAssets()->isEmpty()) {
            $assets = WidgetAssets::empty();
            $legacyAssets = $result->getAssets();

            foreach ($legacyAssets->getCssFiles() as $css) {
                $assets = $assets->addCss($css);
            }

            foreach ($legacyAssets->getJsFiles() as $js) {
                $assets = $assets->addJs($js);
            }

            foreach ($legacyAssets->getInlineStyles() as $id => $style) {
                $assets = $assets->addInlineStyle($id, $style);
            }

            foreach ($legacyAssets->getInlineScripts() as $id => $script) {
                $assets = $assets->addInlineScript($id, $script);
            }

            $output = $output->withAssets($assets);

            // Handle init scripts
            $initScripts = $legacyAssets->getInitScripts();
            if (!empty($initScripts)) {
                $output = $output->withInitScript(implode("\n", $initScripts));
            }
        }

        return $output;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            $html = Html::span()
                ->class('field-display', 'field-display--empty')
                ->text('—')
                ->render();
            return RenderResult::fromHtml($html);
        }

        $html = Html::span()
            ->class('field-display')
            ->text((string) $value)
            ->render();

        return RenderResult::fromHtml($html);
    }

    /**
     * Default implementation of the new display interface.
     * Bridges to the legacy renderDisplay method.
     */
    public function display(Field $field, FieldValue $value, RenderContext $context): WidgetOutput
    {
        // Hydrate a legacy FieldDefinition from the new Field object
        // This is a temporary bridge until we fully refactor widgets
        $legacyField = FieldDefinition::fromArray($field->toArray());

        // Use the raw value as that's what renderDisplay expects
        $legacyValue = $value->get();

        // Call the existing render logic (which might be overridden by subclasses)
        $result = $this->renderDisplay($legacyField, $legacyValue, $context);

        // Convert the result back to the new output format
        $output = WidgetOutput::html($result->getHtml());

        // Copy assets if any
        if (!$result->getAssets()->isEmpty()) {
            $assets = WidgetAssets::empty();
            $legacyAssets = $result->getAssets();

            foreach ($legacyAssets->getCssFiles() as $css) {
                $assets = $assets->addCss($css);
            }

            foreach ($legacyAssets->getJsFiles() as $js) {
                $assets = $assets->addJs($js);
            }

            foreach ($legacyAssets->getInlineStyles() as $id => $style) {
                $assets = $assets->addInlineStyle($id, $style);
            }

            foreach ($legacyAssets->getInlineScripts() as $id => $script) {
                $assets = $assets->addInlineScript($id, $script);
            }

            $output = $output->withAssets($assets);

            // Handle init scripts
            $initScripts = $legacyAssets->getInitScripts();
            if (!empty($initScripts)) {
                $output = $output->withInitScript(implode("\n", $initScripts));
            }
        }

        return $output;
    }

    /**
     * Build the complete field wrapper with label, help, and errors
     */
    protected function buildWrapper(FieldDefinition $field, string $inputHtml, RenderContext $context): string
    {
        $hasError = $context->hasErrorsFor($field->machine_name);

        $wrapper = Html::div()
            ->class(
                'field-widget',
                'field-widget--' . $this->getId(),
                'field-type--' . $field->field_type,
                $field->required ? 'field-widget--required' : '',
                $hasError ? 'field-widget--error' : ''
            );

        // Label
        if (!$context->shouldHideLabel()) {
            $wrapper->child($this->buildLabel($field, $context));
        }

        // Input container
        $wrapper->child(
            Html::div()
                ->class('field-widget__input')
                ->html($inputHtml)
        );

        // Help text
        if (!$context->shouldHideHelp() && $field->help_text) {
            $wrapper->child(
                Html::div()
                    ->class('field-widget__help')
                    ->id($this->getHelpId($field, $context))
                    ->text($field->help_text)
            );
        }

        // Errors
        if ($hasError) {
            $wrapper->child($this->buildErrors($field, $context));
        }

        return $wrapper->render();
    }

    /**
     * Build the field label
     */
    protected function buildLabel(FieldDefinition $field, RenderContext $context): HtmlBuilder
    {
        $label = Html::label()
            ->class('field-widget__label')
            ->attr('for', $this->getFieldId($field, $context))
            ->text($field->name);

        if ($field->required) {
            $label->child(
                Html::span()
                    ->class('field-widget__required')
                    ->text('*')
            );
        }

        return $label;
    }

    /**
     * Build the error display
     */
    protected function buildErrors(FieldDefinition $field, RenderContext $context): HtmlBuilder
    {
        $errors = $context->getErrorsFor($field->machine_name);

        $container = Html::div()->class('field-widget__errors');

        foreach ($errors as $error) {
            $container->child(
                Html::div()
                    ->class('field-widget__error')
                    ->text($error)
            );
        }

        return $container;
    }

    /**
     * Get initialization JavaScript (optional)
     */
    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return null;
    }

    // =========================================================================
    // Value Handling
    // =========================================================================

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        return $value;
    }

    public function formatValue(FieldDefinition $field, mixed $value): mixed
    {
        return $value;
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        return ValidationResult::success();
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Get the field's DOM element ID
     */
    protected function getFieldId(FieldDefinition $field, RenderContext $context): string
    {
        $id = $context->getFormId() . '_' . $field->machine_name;

        if ($context->getIndex() !== null) {
            $id .= '_' . $context->getIndex();
        }

        return $id;
    }

    /**
     * Get the field's form name
     */
    protected function getFieldName(FieldDefinition $field, RenderContext $context): string
    {
        $name = $field->machine_name;

        if ($context->getNamePrefix()) {
            $name = $context->getNamePrefix() . '[' . $name . ']';
        }

        if ($field->multiple && $this->supportsMultiple()) {
            $name .= '[]';
        }

        return $name;
    }

    /**
     * Get the help text element ID
     */
    protected function getHelpId(FieldDefinition $field, RenderContext $context): string
    {
        return $this->getFieldId($field, $context) . '_help';
    }

    /**
     * Build common input attributes
     */
    protected function buildCommonAttributes(FieldDefinition $field, RenderContext $context): array
    {
        $attrs = [
            'id' => $this->getFieldId($field, $context),
            'name' => $this->getFieldName($field, $context),
            'class' => 'field-widget__control block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 disabled:bg-gray-50 disabled:text-gray-500 transition-colors duration-200',
        ];

        if ($field->required) {
            $attrs['required'] = true;
        }

        if ($context->isDisabled()) {
            $attrs['disabled'] = true;
        }

        if ($context->isReadonly()) {
            $attrs['readonly'] = true;
        }

        if ($field->help_text) {
            $attrs['aria-describedby'] = $this->getHelpId($field, $context);
        }

        $placeholder = $field->getSetting('placeholder');
        if ($placeholder) {
            $attrs['placeholder'] = $placeholder;
        }

        return $attrs;
    }

    /**
     * Get settings as typed FieldSettings object
     */
    protected function getSettings(FieldDefinition $field): FieldSettings
    {
        return FieldSettings::fromArray(
            $field->settings,
            $this->getSettingsSchema()
        );
    }

    /**
     * Get options from field definition
     */
    protected function getOptions(FieldDefinition $field): array
    {
        $options = $field->getSetting('options', []);

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $options;
    }

    /**
     * Check if a value is empty
     */
    protected function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * Escape HTML
     */
    protected function escape(string $value): string
    {
        return Html::escape($value);
    }

    /**
     * Format a date value
     */
    protected function formatDate(mixed $value, string $format = 'Y-m-d'): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        if (is_string($value) && $value !== '') {
            try {
                return (new \DateTimeImmutable($value))->format($format);
            } catch (\Exception $e) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Format a number value
     */
    protected function formatNumber(mixed $value, int $decimals = 0): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        return number_format((float) $value, $decimals);
    }

    /**
     * Get widget metadata
     */
    public function getMetadata(): WidgetMetadata
    {
        return WidgetMetadata::fromWidget($this);
    }
}
