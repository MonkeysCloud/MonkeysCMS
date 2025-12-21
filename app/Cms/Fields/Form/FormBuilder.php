<?php

declare(strict_types=1);

namespace App\Cms\Fields\Form;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\WidgetRegistry;

/**
 * FormBuilder - Builds forms from field definitions
 *
 * Uses the builder pattern to construct forms with customizable
 * options, layouts, and styling.
 */
final class FormBuilder
{
    private string $id = 'form';
    private string $action = '';
    private string $method = 'POST';
    private string $class = 'cms-form';
    private string $enctype = 'multipart/form-data';
    private bool $ajax = false;
    private bool $showRequiredIndicator = true;
    private bool $groupFields = true;
    private string $submitLabel = 'Save';
    private ?string $cancelUrl = null;
    private array $errors = [];
    private bool $includeCsrf = true;

    public function __construct(
        private readonly WidgetRegistry $widgets,
    ) {
    }

    // =========================================================================
    // Builder Methods
    // =========================================================================

    public function id(string $id): self
    {
        $clone = clone $this;
        $clone->id = $id;
        return $clone;
    }

    public function action(string $action): self
    {
        $clone = clone $this;
        $clone->action = $action;
        return $clone;
    }

    public function method(string $method): self
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function class(string $class): self
    {
        $clone = clone $this;
        $clone->class = $class;
        return $clone;
    }

    public function enctype(string $enctype): self
    {
        $clone = clone $this;
        $clone->enctype = $enctype;
        return $clone;
    }

    public function ajax(bool $ajax = true): self
    {
        $clone = clone $this;
        $clone->ajax = $ajax;
        return $clone;
    }

    public function groupFields(bool $group = true): self
    {
        $clone = clone $this;
        $clone->groupFields = $group;
        return $clone;
    }

    public function submitLabel(string $label): self
    {
        $clone = clone $this;
        $clone->submitLabel = $label;
        return $clone;
    }

    public function cancelUrl(?string $url): self
    {
        $clone = clone $this;
        $clone->cancelUrl = $url;
        return $clone;
    }

    public function errors(array $errors): self
    {
        $clone = clone $this;
        $clone->errors = $errors;
        return $clone;
    }

    public function withoutCsrf(): self
    {
        $clone = clone $this;
        $clone->includeCsrf = false;
        return $clone;
    }

    // =========================================================================
    // Build Methods
    // =========================================================================

    /**
     * Build a complete form
     *
     * @param FieldDefinition[] $fields
     * @param array $values Current values indexed by machine_name
     */
    public function build(array $fields, array $values = []): FormResult
    {
        $this->widgets->clearAssets();

        $context = RenderContext::create([
            'form_id' => $this->id,
            'errors' => $this->errors,
        ]);

        $form = Html::element('form')
            ->id($this->id)
            ->attr('action', $this->action)
            ->attr('method', $this->method)
            ->class($this->class)
            ->attr('enctype', $this->enctype)
            ->when($this->ajax, fn($f) => $f->data('ajax', 'true'));

        // CSRF token
        if ($this->includeCsrf && $this->method === 'POST') {
            $form->child($this->buildCsrfToken());
        }

        // Global form errors
        if (!empty($this->errors['_form'])) {
            $form->html($this->buildFormErrors($this->errors['_form']));
        }

        // Fields container
        $fieldsContainer = Html::div()->class('cms-form__fields');

        if ($this->groupFields) {
            $fieldsContainer->html($this->buildGroupedFields($fields, $values, $context));
        } else {
            $result = $this->widgets->renderFields($fields, $values, $context);
            $fieldsContainer->html($result->getHtml());
        }

        $form->child($fieldsContainer);

        // Actions
        $form->child($this->buildActions());

        // Collect assets
        $assets = $this->widgets->getCollectedAssets();

        return new FormResult(
            html: $form->render(),
            assets: $assets,
        );
    }

    /**
     * Build just the fields (without form wrapper)
     *
     * @param FieldDefinition[] $fields
     * @param array $values
     */
    public function buildFields(array $fields, array $values = []): RenderResult
    {
        $this->widgets->clearAssets();

        $context = RenderContext::create([
            'form_id' => $this->id,
            'errors' => $this->errors,
        ]);

        if ($this->groupFields) {
            $html = $this->buildGroupedFields($fields, $values, $context);
            return RenderResult::create($html, $this->widgets->getCollectedAssets());
        }

        return $this->widgets->renderFields($fields, $values, $context);
    }

    /**
     * Build a single field
     */
    public function buildField(FieldDefinition $field, mixed $value = null): RenderResult
    {
        $context = RenderContext::create([
            'form_id' => $this->id,
            'errors' => $this->errors,
        ]);

        return $this->widgets->renderField($field, $value ?? $field->default_value, $context);
    }

    // =========================================================================
    // Validation & Preparation
    // =========================================================================

    /**
     * Validate submitted values
     *
     * @param FieldDefinition[] $fields
     * @param array $values Submitted values
     * @return array<string, array<string>> Errors indexed by field machine_name
     */
    public function validate(array $fields, array $values): array
    {
        return $this->widgets->validateFields($fields, $values);
    }

    /**
     * Prepare submitted values for storage
     *
     * @param FieldDefinition[] $fields
     * @param array $values Submitted values
     * @return array Prepared values indexed by machine_name
     */
    public function prepare(array $fields, array $values): array
    {
        return $this->widgets->prepareValues($fields, $values);
    }

    // =========================================================================
    // Private Build Methods
    // =========================================================================

    private function buildCsrfToken(): HtmlBuilder
    {
        // In production, use a proper CSRF token generator
        $token = bin2hex(random_bytes(32));
        return Html::hidden('_token', $token);
    }

    private function buildFormErrors(array $errors): string
    {
        $container = Html::div()->class('cms-form__errors');

        foreach ($errors as $error) {
            $container->child(
                Html::div()
                    ->class('cms-form__error')
                    ->text($error)
            );
        }

        return $container->render();
    }

    private function buildGroupedFields(array $fields, array $values, RenderContext $context): string
    {
        // Group fields by their group setting
        $groups = [];
        foreach ($fields as $field) {
            $group = $field->getSetting('group', 'General');
            if (!isset($groups[$group])) {
                $groups[$group] = [];
            }
            $groups[$group][] = $field;
        }

        $html = '';
        $index = 0;

        foreach ($groups as $groupName => $groupFields) {
            $collapsed = $index > 0;

            $fieldset = Html::element('fieldset')
                ->class('cms-form__group', $collapsed ? 'cms-form__group--collapsed' : '');

            // Legend with toggle
            $legend = Html::element('legend')
                ->class('cms-form__group-title')
                ->attr('onclick', "this.parentElement.classList.toggle('cms-form__group--collapsed')")
                ->child(Html::span()->class('cms-form__group-toggle')->text('▼'))
                ->text(' ' . $groupName);

            $fieldset->child($legend);

            // Group content
            $content = Html::div()->class('cms-form__group-content');

            foreach ($groupFields as $field) {
                $value = $values[$field->machine_name] ?? $field->default_value;
                $result = $this->widgets->renderField($field, $value, $context);
                $content->html($result->getHtml());
            }

            $fieldset->child($content);
            $html .= $fieldset->render();
            $index++;
        }

        return $html;
    }

    private function buildActions(): HtmlBuilder
    {
        $actions = Html::div()->class('cms-form__actions');

        // Submit button
        $actions->child(
            Html::button('submit')
                ->class('cms-form__submit')
                ->text($this->submitLabel)
        );

        // Cancel link
        if ($this->cancelUrl) {
            $actions->child(
                Html::element('a')
                    ->attr('href', $this->cancelUrl)
                    ->class('cms-form__cancel')
                    ->text('Cancel')
            );
        }

        return $actions;
    }
}

/**
 * FormResult - Result of form building
 */
final class FormResult
{
    public function __construct(
        public readonly string $html,
        public readonly \App\Cms\Fields\Rendering\AssetCollection $assets,
    ) {
    }

    /**
     * Get HTML with assets included
     */
    public function getHtmlWithAssets(): string
    {
        return $this->html . $this->assets->render();
    }

    /**
     * Magic string conversion
     */
    public function __toString(): string
    {
        return $this->getHtmlWithAssets();
    }
}
