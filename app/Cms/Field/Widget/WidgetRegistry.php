<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\FieldType;
use App\Cms\Field\RenderContext;

/**
 * WidgetRegistry — Global registry mapping field types to widget renderers.
 *
 * This is the SINGLE source of truth for all field rendering across
 * the entire CMS: content types, taxonomy, Mosaic blocks, and custom modules.
 *
 * Custom modules register widgets via: $registry->register(new MyWidget());
 * The widget then becomes available everywhere automatically.
 */
final class WidgetRegistry
{
    /** @var array<string, AbstractWidget> */
    private array $widgets = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Register a widget instance.
     */
    public function register(AbstractWidget $widget): void
    {
        $this->widgets[$widget::type()] = $widget;
    }

    /**
     * Check if a widget type is registered.
     */
    public function has(string $widgetType): bool
    {
        return isset($this->widgets[$widgetType]);
    }

    /**
     * Get the widget for a field definition.
     */
    public function getWidget(FieldDefinition $field): AbstractWidget
    {
        $widgetType = $field->getWidget();

        if (isset($this->widgets[$widgetType])) {
            return $this->widgets[$widgetType];
        }

        // Fallback to text_input
        return $this->widgets['text_input'] ?? throw new \RuntimeException(
            "No widget registered for type '{$widgetType}'"
        );
    }

    /**
     * Render a field using its configured widget (admin form context).
     */
    public function renderField(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        return $this->getWidget($field)->render($field, $value, $namePrefix);
    }

    /**
     * Render a field for a specific context (admin form, Mosaic inspector, API).
     *
     * This is the primary method used by FieldFormRenderer and any
     * context-aware rendering pipeline.
     */
    public function renderFieldForContext(FieldDefinition $field, mixed $value, RenderContext $ctx): string
    {
        return $this->getWidget($field)->renderForContext($field, $value, $ctx);
    }

    /**
     * Render all fields for a content type.
     *
     * @param list<FieldDefinition> $fields
     * @param array<string, mixed>  $values
     */
    public function renderAll(array $fields, array $values = [], string $namePrefix = 'fields'): string
    {
        $html = '';
        foreach ($fields as $field) {
            $value = $values[$field->machine_name] ?? $field->default_value;
            $html .= $this->renderField($field, $value, $namePrefix);
        }
        return $html;
    }

    /**
     * Render all fields for a specific context.
     *
     * @param array<string, FieldDefinition> $fields  Keyed by machine_name
     * @param array<string, mixed>           $values
     */
    public function renderAllForContext(array $fields, array $values, RenderContext $ctx): string
    {
        $html = '';
        foreach ($fields as $field) {
            $value = $values[$field->machine_name] ?? $field->default_value;
            $html .= $this->renderFieldForContext($field, $value, $ctx);
        }
        return $html;
    }

    /**
     * Get all registered widget types.
     *
     * @return array<string, AbstractWidget>
     */
    public function all(): array
    {
        return $this->widgets;
    }

    /**
     * Register the default built-in widgets.
     */
    private function registerDefaults(): void
    {
        // Core text widgets
        $this->register(new TextInputWidget());
        $this->register(new TextareaWidget());
        $this->register(new WysiwygWidget());
        $this->register(new PasswordInputWidget());

        // Number / Boolean
        $this->register(new NumberInputWidget());
        $this->register(new ToggleWidget());

        // Selection widgets
        $this->register(new SelectWidget());
        $this->register(new MultiselectWidget());
        $this->register(new CheckboxesWidget());
        $this->register(new ColorPickerWidget());

        // Date / Time
        $this->register(new DatePickerWidget());
        $this->register(new DatetimePickerWidget());
        $this->register(new TimePickerWidget());

        // Media widgets
        $this->register(new MediaPickerWidget());
        $this->register(new FileUploadWidget());
        $this->register(new GalleryPickerWidget());
        $this->register(new VideoEmbedWidget());

        // Structured input
        $this->register(new LinkInputWidget());
        $this->register(new AddressInputWidget());
        $this->register(new MapPickerWidget());

        // Code / Data
        $this->register(new CodeEditorWidget());
        $this->register(new MarkdownEditorWidget());
        $this->register(new JsonEditorWidget());

        // Reference widgets
        $this->register(new TaxonomySelectWidget());
        $this->register(new EntityAutocompleteWidget());
        $this->register(new UserAutocompleteWidget());
        $this->register(new BlockSelectWidget());

        // Compound / Complex widgets
        $this->register(new RepeaterWidget($this));
        $this->register(new SlotWidget());
    }
}
