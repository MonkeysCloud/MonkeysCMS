<?php

declare(strict_types=1);

namespace App\Cms\Form;

use MonkeysLegion\Session\SessionManager;

/**
 * FormBuilder — Fluent API to construct Form definitions.
 *
 * Usage:
 *   $form = FormBuilder::create('/admin/media/settings', 'POST')
 *       ->id('media-settings-form')
 *       ->layout('settings-grid')
 *       ->group('general', 'General', 'settings')
 *       ->group('storage', 'Storage Driver', 'hard-drive')
 *       ->toggle('enabled', 'Enable Module')->group('general')
 *       ->text('upload_path', 'Upload Path')->placeholder('uploads')->group('general')
 *       ->select('driver', 'Storage Driver', $drivers)->group('storage')
 *       ->submit('Save Settings', 'save')
 *       ->build($session);
 */
final class FormBuilder
{
    /** @var list<FormElement> */
    private array $elements = [];

    /** @var array<string, FormGroup> */
    private array $groups = [];

    /** @var array<string, string> */
    private array $formAttrs = [];

    private string $layout = 'default';
    private ?string $submitLabel = 'Save';
    private ?string $submitIcon = 'save';
    private ?string $cancelUrl = null;
    private ?FormElement $lastElement = null;

    private function __construct(
        private readonly string $action,
        private readonly string $method = 'POST',
        private readonly string $formId = '',
    ) {}

    /**
     * Start building a form.
     */
    public static function create(string $action, string $method = 'POST'): static
    {
        return new static($action, $method);
    }

    // ── Layout ──────────────────────────────────────────────────────────

    /** Set form HTML id. */
    public function id(string $id): static
    {
        // This is handled in constructor but allows chaining
        return new static($this->action, $this->method, $id);
    }

    /**
     * Set layout mode.
     * - 'default'        — single-column stacked
     * - 'settings-grid'  — multi-column admin-settings-grid
     * - 'two-column'     — 2-column grid
     * - 'inline'         — horizontal inline form
     */
    public function layout(string $layout): static
    {
        $this->layout = $layout;
        return $this;
    }

    /** Set additional form attributes (e.g. enctype). */
    public function attr(string $key, string $value): static
    {
        $this->formAttrs[$key] = $value;
        return $this;
    }

    /** Set form enctype for file uploads. */
    public function multipart(): static
    {
        $this->formAttrs['enctype'] = 'multipart/form-data';
        return $this;
    }

    /** Set a cancel URL for the cancel button. */
    public function cancel(string $url): static
    {
        $this->cancelUrl = $url;
        return $this;
    }

    // ── Groups ──────────────────────────────────────────────────────────

    /**
     * Define a form group (renders as admin-card).
     */
    public function group(string $name, string $title, string $icon = '', string $description = '', int $weight = 0): static
    {
        $this->groups[$name] = new FormGroup($name, $title, $icon, $description, $weight);
        return $this;
    }

    // ── Field Types ─────────────────────────────────────────────────────

    /** Text input. */
    public function text(string $name, string $label = ''): static
    {
        return $this->addField('text', $name, $label);
    }

    /** Email input. */
    public function email(string $name, string $label = ''): static
    {
        return $this->addField('email', $name, $label);
    }

    /** Password input. */
    public function password(string $name, string $label = ''): static
    {
        return $this->addField('password', $name, $label);
    }

    /** Number input. */
    public function number(string $name, string $label = ''): static
    {
        return $this->addField('number', $name, $label);
    }

    /** Textarea. */
    public function textarea(string $name, string $label = ''): static
    {
        return $this->addField('textarea', $name, $label);
    }

    /** Select dropdown. */
    public function select(string $name, string $label, array $options = []): static
    {
        $el = new FormElement('select', $name, $label);
        if ($options) {
            $el = $el->options($options);
        }
        return $this->pushElement($el);
    }

    /** Checkbox. */
    public function checkbox(string $name, string $label = ''): static
    {
        return $this->addField('checkbox', $name, $label);
    }

    /** Toggle switch. */
    public function toggle(string $name, string $label = ''): static
    {
        return $this->addField('toggle', $name, $label);
    }

    /** Radio group. */
    public function radio(string $name, string $label, array $options = []): static
    {
        $el = new FormElement('radio', $name, $label);
        if ($options) {
            $el = $el->options($options);
        }
        return $this->pushElement($el);
    }

    /** File input. */
    public function file(string $name, string $label = ''): static
    {
        $this->formAttrs['enctype'] = 'multipart/form-data';
        return $this->addField('file', $name, $label);
    }

    /** Hidden input. */
    public function hidden(string $name, mixed $value = null): static
    {
        $el = new FormElement('hidden', $name);
        if ($value !== null) {
            $el = $el->value($value);
        }
        return $this->pushElement($el);
    }

    /** Range slider. */
    public function range(string $name, string $label = ''): static
    {
        return $this->addField('range', $name, $label);
    }

    /** Date input. */
    public function date(string $name, string $label = ''): static
    {
        return $this->addField('date', $name, $label);
    }

    /** DateTime input. */
    public function datetime(string $name, string $label = ''): static
    {
        return $this->addField('datetime-local', $name, $label);
    }

    /** URL input. */
    public function url(string $name, string $label = ''): static
    {
        return $this->addField('url', $name, $label);
    }

    /** Color picker. */
    public function color(string $name, string $label = ''): static
    {
        return $this->addField('color', $name, $label);
    }

    /** Code/monospace textarea. */
    public function code(string $name, string $label = ''): static
    {
        $el = (new FormElement('textarea', $name, $label))
            ->attr('class', 'form-input form-input--mono');
        return $this->pushElement($el);
    }

    /** Separator / horizontal rule. */
    public function separator(): static
    {
        return $this->addField('separator', '_sep_' . count($this->elements));
    }

    /** Static HTML content. */
    public function html(string $content): static
    {
        $el = (new FormElement('html', '_html_' . count($this->elements)))
            ->value($content);
        return $this->pushElement($el);
    }

    /** Heading inside a form. */
    public function heading(string $text, string $level = 'h4'): static
    {
        $el = (new FormElement('heading', '_heading_' . count($this->elements), $text))
            ->attr('level', $level);
        return $this->pushElement($el);
    }

    // ── Modifiers (applied to last-added element) ───────────────────────

    /** Set value on last element. */
    public function value(mixed $val): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->value($val));
        }
        return $this;
    }

    /** Set placeholder on last element. */
    public function placeholder(string $text): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->placeholder($text));
        }
        return $this;
    }

    /** Set help text on last element. */
    public function help(string $text): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->help($text));
        }
        return $this;
    }

    /** Mark last element as required. */
    public function required(bool $flag = true): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->required($flag));
        }
        return $this;
    }

    /** Mark last element as disabled. */
    public function disabled(bool $flag = true): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->disabled($flag));
        }
        return $this;
    }

    /** Set element options (for select/radio). */
    public function options(array $options): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->options($options));
        }
        return $this;
    }

    /** Assign last element to a group. */
    public function inGroup(string $groupName): static
    {
        if ($this->lastElement) {
            $el = $this->lastElement->group($groupName);
            $this->replaceLastWith($el);
        }
        return $this;
    }

    /** Conditional visibility on last element. */
    public function showWhen(string $field, mixed $value): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->showWhen($field, $value));
        }
        return $this;
    }

    /** Min validation on last element. */
    public function min(int $val): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->min($val));
        }
        return $this;
    }

    /** Max validation on last element. */
    public function max(int $val): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->max($val));
        }
        return $this;
    }

    /** Set extra attributes on last element. */
    public function attrs(array $attrs): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->attrs($attrs));
        }
        return $this;
    }

    /** Set wrapper class on last element. */
    public function wrapper(string $class): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->wrapper($class));
        }
        return $this;
    }

    /** Set prefix HTML on last element. */
    public function prefix(string $html): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->prefix($html));
        }
        return $this;
    }

    /** Set suffix HTML on last element. */
    public function suffix(string $html): static
    {
        if ($this->lastElement) {
            $this->replaceLastWith($this->lastElement->suffix($html));
        }
        return $this;
    }

    // ── Submit & Actions ────────────────────────────────────────────────

    /** Configure the submit button. */
    public function submit(string $label = 'Save', string $icon = 'save'): static
    {
        $this->submitLabel = $label;
        $this->submitIcon = $icon;
        return $this;
    }

    /** No submit button. */
    public function noSubmit(): static
    {
        $this->submitLabel = null;
        $this->submitIcon = null;
        return $this;
    }

    // ── Build ───────────────────────────────────────────────────────────

    /**
     * Build the final Form object.
     *
     * @param SessionManager|null $session  If provided, CSRF token is auto-injected
     */
    public function build(?SessionManager $session = null): Form
    {
        $formId = $this->formId ?: 'form-' . md5($this->action . $this->method);

        $form = new Form($this->action, $this->method, $formId);
        $form->setLayout($this->layout);
        $form->setAttributes($this->formAttrs);

        if ($this->submitLabel !== null) {
            $form->setSubmit($this->submitLabel, $this->submitIcon);
        }

        if ($this->cancelUrl) {
            $form->setCancelUrl($this->cancelUrl);
        }

        // Auto-inject CSRF
        if ($session) {
            $form->setCsrfToken($session->token());
        }

        // Add groups
        foreach ($this->groups as $group) {
            $form->addGroup($group);
        }

        // Add elements
        foreach ($this->elements as $el) {
            $form->addElement($el);
        }

        return $form;
    }

    // ── Internals ───────────────────────────────────────────────────────

    private function addField(string $type, string $name, string $label = ''): static
    {
        return $this->pushElement(new FormElement($type, $name, $label));
    }

    private function pushElement(FormElement $el): static
    {
        $this->elements[] = $el;
        $this->lastElement = $el;
        return $this;
    }

    private function replaceLastWith(FormElement $el): void
    {
        $idx = count($this->elements) - 1;
        if ($idx >= 0) {
            $this->elements[$idx] = $el;
        }
        $this->lastElement = $el;
    }
}
