<?php

declare(strict_types=1);

namespace App\Cms\Form;

/**
 * Form — Complete form definition with elements, groups, and security.
 *
 * Produced by FormBuilder, consumed by FormRenderer.
 */
final class Form
{
    /** @var list<FormElement> */
    private array $elements = [];

    /** @var array<string, FormGroup> */
    private array $groups = [];

    /** @var array<string, string> */
    private array $attributes = [];

    private ?string $csrfToken = null;
    private string $layout = 'default';
    private ?string $submitLabel = null;
    private ?string $submitIcon = null;
    private ?string $cancelUrl = null;

    public function __construct(
        public readonly string $action,
        public readonly string $method = 'POST',
        public readonly string $id = '',
    ) {}

    // ── Builder-facing Mutators ─────────────────────────────────────────

    public function addElement(FormElement $element): void
    {
        $this->elements[] = $element;

        // Also add to group if specified
        $groupName = $element->getGroup;
        if ($groupName !== null && isset($this->groups[$groupName])) {
            $this->groups[$groupName]->addElement($element);
        }
    }

    public function addGroup(FormGroup $group): void
    {
        $this->groups[$group->name] = $group;
    }

    public function setCsrfToken(?string $token): void
    {
        $this->csrfToken = $token;
    }

    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function setSubmit(?string $label, ?string $icon = null): void
    {
        $this->submitLabel = $label;
        $this->submitIcon = $icon;
    }

    public function setCancelUrl(?string $url): void
    {
        $this->cancelUrl = $url;
    }

    /** @param array<string, string> $attrs */
    public function setAttributes(array $attrs): void
    {
        $this->attributes = array_merge($this->attributes, $attrs);
    }

    // ── Read Accessors ──────────────────────────────────────────────────

    /** @return list<FormElement> */
    public function getElements(): array
    {
        return $this->elements;
    }

    /** @return array<string, FormGroup> */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Get elements NOT assigned to any group.
     * @return list<FormElement>
     */
    public function getUngroupedElements(): array
    {
        return array_values(array_filter(
            $this->elements,
            fn(FormElement $el) => $el->getGroup === null,
        ));
    }

    public ?string $csrf {
        get => $this->csrfToken;
    }

    public string $formLayout {
        get => $this->layout;
    }

    public ?string $getSubmitLabel {
        get => $this->submitLabel;
    }

    public ?string $getSubmitIcon {
        get => $this->submitIcon;
    }

    public ?string $getCancelUrl {
        get => $this->cancelUrl;
    }

    /** @return array<string, string> */
    public function getFormAttributes(): array
    {
        return $this->attributes;
    }

    public function hasGroups(): bool
    {
        return count($this->groups) > 0;
    }

    /**
     * Get a specific element by name.
     */
    public function getElement(string $name): ?FormElement
    {
        foreach ($this->elements as $el) {
            if ($el->name === $name) {
                return $el;
            }
        }
        return null;
    }

    /**
     * Validate submitted data against element rules.
     *
     * @param array<string, mixed> $data
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        foreach ($this->elements as $el) {
            $value = $data[$el->name] ?? null;

            // Required check
            if ($el->isRequired && ($value === null || $value === '')) {
                $errors[$el->name] = ($el->label ?: $el->name) . ' is required.';
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $rules = $el->getRules();

            // Min length
            if (isset($rules['min']) && is_string($value) && mb_strlen($value) < $rules['min']) {
                $errors[$el->name] = ($el->label ?: $el->name) . ' must be at least ' . $rules['min'] . ' characters.';
            }

            // Max length
            if (isset($rules['max']) && is_string($value) && mb_strlen($value) > $rules['max']) {
                $errors[$el->name] = ($el->label ?: $el->name) . ' must not exceed ' . $rules['max'] . ' characters.';
            }

            // Pattern
            if (isset($rules['pattern']) && is_string($value) && !preg_match($rules['pattern'], $value)) {
                $errors[$el->name] = ($el->label ?: $el->name) . ' has an invalid format.';
            }
        }

        return new ValidationResult($errors);
    }

    /**
     * Export form definition for JSON API / JS consumption.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'method' => $this->method,
            'id' => $this->id,
            'layout' => $this->layout,
            'groups' => array_map(fn(FormGroup $g) => [
                'name' => $g->name,
                'title' => $g->title,
                'icon' => $g->icon,
            ], $this->groups),
            'elements' => array_map(fn(FormElement $el) => [
                'type' => $el->type,
                'name' => $el->name,
                'label' => $el->label,
                'value' => $el->currentValue,
                'required' => $el->isRequired,
                'group' => $el->getGroup,
                'options' => $el->getOptions(),
                'conditions' => $el->getConditions(),
            ], $this->elements),
        ];
    }
}
