<?php

declare(strict_types=1);

namespace App\Cms\Fields\Rendering;

/**
 * HtmlBuilder - Fluent HTML element builder
 * 
 * Provides a clean OOP way to build HTML elements with proper escaping.
 */
final class HtmlBuilder
{
    private string $tag;
    private array $attributes = [];
    private array $content = [];
    private bool $selfClosing = false;

    private function __construct(string $tag)
    {
        $this->tag = $tag;
    }

    /**
     * Create a new element
     */
    public static function element(string $tag): self
    {
        return new self($tag);
    }

    /**
     * Create a div
     */
    public static function div(): self
    {
        return new self('div');
    }

    /**
     * Create a span
     */
    public static function span(): self
    {
        return new self('span');
    }

    /**
     * Create a label
     */
    public static function label(): self
    {
        return new self('label');
    }

    /**
     * Create an input
     */
    public static function input(string $type = 'text'): self
    {
        return (new self('input'))
            ->selfClosing(true)
            ->attr('type', $type);
    }

    /**
     * Create a textarea
     */
    public static function textarea(): self
    {
        return new self('textarea');
    }

    /**
     * Create a select
     */
    public static function select(): self
    {
        return new self('select');
    }

    /**
     * Create a button
     */
    public static function button(string $type = 'button'): self
    {
        return (new self('button'))->attr('type', $type);
    }

    /**
     * Create an option
     */
    public static function option(string $value, string $label, bool $selected = false): self
    {
        $option = (new self('option'))
            ->attr('value', $value)
            ->text($label);
        
        if ($selected) {
            $option->attr('selected', true);
        }
        
        return $option;
    }

    /**
     * Mark as self-closing element
     */
    public function selfClosing(bool $selfClosing = true): self
    {
        $this->selfClosing = $selfClosing;
        return $this;
    }

    /**
     * Set an attribute
     */
    public function attr(string $name, mixed $value): self
    {
        if ($value === false || $value === null) {
            unset($this->attributes[$name]);
        } elseif ($value === true) {
            $this->attributes[$name] = true;
        } else {
            $this->attributes[$name] = (string) $value;
        }
        return $this;
    }

    /**
     * Set multiple attributes
     */
    public function attrs(array $attributes): self
    {
        foreach ($attributes as $name => $value) {
            $this->attr($name, $value);
        }
        return $this;
    }

    /**
     * Set ID attribute
     */
    public function id(string $id): self
    {
        return $this->attr('id', $id);
    }

    /**
     * Set name attribute
     */
    public function name(string $name): self
    {
        return $this->attr('name', $name);
    }

    /**
     * Set value attribute
     */
    public function value(mixed $value): self
    {
        return $this->attr('value', $value);
    }

    /**
     * Set class attribute (replace existing)
     */
    public function class(string ...$classes): self
    {
        $classes = array_filter($classes);
        return $this->attr('class', implode(' ', $classes));
    }

    /**
     * Add class(es) to existing
     */
    public function addClass(string ...$classes): self
    {
        $existing = $this->attributes['class'] ?? '';
        $allClasses = array_filter(array_merge(
            explode(' ', $existing),
            $classes
        ));
        return $this->attr('class', implode(' ', array_unique($allClasses)));
    }

    /**
     * Set data attribute
     */
    public function data(string $name, mixed $value): self
    {
        return $this->attr('data-' . $name, $value);
    }

    /**
     * Set aria attribute
     */
    public function aria(string $name, mixed $value): self
    {
        return $this->attr('aria-' . $name, $value);
    }

    /**
     * Set placeholder
     */
    public function placeholder(string $placeholder): self
    {
        return $this->attr('placeholder', $placeholder);
    }

    /**
     * Mark as required
     */
    public function required(bool $required = true): self
    {
        return $this->attr('required', $required);
    }

    /**
     * Mark as disabled
     */
    public function disabled(bool $disabled = true): self
    {
        return $this->attr('disabled', $disabled);
    }

    /**
     * Mark as readonly
     */
    public function readonly(bool $readonly = true): self
    {
        return $this->attr('readonly', $readonly);
    }

    /**
     * Add text content (escaped)
     */
    public function text(string $text): self
    {
        $this->content[] = ['type' => 'text', 'value' => $text];
        return $this;
    }

    /**
     * Add raw HTML content (not escaped)
     */
    public function html(string $html): self
    {
        $this->content[] = ['type' => 'html', 'value' => $html];
        return $this;
    }

    /**
     * Add child element
     */
    public function child(HtmlBuilder $child): self
    {
        $this->content[] = ['type' => 'builder', 'value' => $child];
        return $this;
    }

    /**
     * Add multiple children
     */
    public function children(array $children): self
    {
        foreach ($children as $child) {
            if ($child instanceof HtmlBuilder) {
                $this->child($child);
            } elseif (is_string($child)) {
                $this->html($child);
            }
        }
        return $this;
    }

    /**
     * Conditionally add attributes/content
     */
    public function when(bool $condition, callable $callback): self
    {
        if ($condition) {
            $callback($this);
        }
        return $this;
    }

    /**
     * Render to HTML string
     */
    public function render(): string
    {
        $html = '<' . $this->escape($this->tag);
        
        // Render attributes
        foreach ($this->attributes as $name => $value) {
            if ($value === true) {
                $html .= ' ' . $this->escape($name);
            } else {
                $html .= ' ' . $this->escape($name) . '="' . $this->escape($value) . '"';
            }
        }
        
        if ($this->selfClosing && empty($this->content)) {
            return $html . '>';
        }
        
        $html .= '>';
        
        // Render content
        foreach ($this->content as $item) {
            $html .= match ($item['type']) {
                'text' => $this->escape($item['value']),
                'html' => $item['value'],
                'builder' => $item['value']->render(),
            };
        }
        
        $html .= '</' . $this->escape($this->tag) . '>';
        
        return $html;
    }

    /**
     * Escape HTML special characters
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Magic string conversion
     */
    public function __toString(): string
    {
        return $this->render();
    }
}

/**
 * Html - Static helper for common elements
 */
final class Html
{
    public static function element(string $tag): HtmlBuilder
    {
        return HtmlBuilder::element($tag);
    }

    public static function div(): HtmlBuilder
    {
        return HtmlBuilder::div();
    }

    public static function span(): HtmlBuilder
    {
        return HtmlBuilder::span();
    }

    public static function label(): HtmlBuilder
    {
        return HtmlBuilder::label();
    }

    public static function input(string $type = 'text'): HtmlBuilder
    {
        return HtmlBuilder::input($type);
    }

    public static function textarea(): HtmlBuilder
    {
        return HtmlBuilder::textarea();
    }

    public static function select(): HtmlBuilder
    {
        return HtmlBuilder::select();
    }

    public static function button(string $type = 'button'): HtmlBuilder
    {
        return HtmlBuilder::button($type);
    }

    public static function hidden(string $name, mixed $value): HtmlBuilder
    {
        return HtmlBuilder::input('hidden')->name($name)->value($value);
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
