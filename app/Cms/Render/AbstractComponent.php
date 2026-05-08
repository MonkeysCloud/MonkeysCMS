<?php

declare(strict_types=1);

namespace App\Cms\Render;

use MonkeysLegion\Template\Renderer;

abstract class AbstractComponent implements RenderableInterface
{
    protected array $attributes = [];

    /**
     * Get the dot-notation path to the template view (e.g., 'admin::components.atoms.input').
     */
    abstract protected function getViewName(): string;

    /**
     * Get the data to pass to the template.
     */
    protected function getViewData(): array
    {
        return [
            'component'  => $this,
            'attributes' => $this->renderAttributes(),
        ];
    }

    public function render(Renderer $renderer): string
    {
        return $renderer->render($this->getViewName(), $this->getViewData());
    }

    public function setAttribute(string $name, string|bool|null $value): static
    {
        if ($value === null) {
            unset($this->attributes[$name]);
        } else {
            $this->attributes[$name] = $value;
        }
        return $this;
    }

    public function addClass(string $class): static
    {
        if (isset($this->attributes['class'])) {
            // Avoid duplicates
            $classes = explode(' ', (string) $this->attributes['class']);
            if (!in_array($class, $classes, true)) {
                $this->attributes['class'] .= ' ' . $class;
            }
        } else {
            $this->attributes['class'] = $class;
        }
        return $this;
    }

    public function removeClass(string $class): static
    {
        if (isset($this->attributes['class'])) {
            $classes = array_filter(explode(' ', (string) $this->attributes['class']), fn($c) => $c !== $class);
            $this->attributes['class'] = implode(' ', $classes);
        }
        return $this;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function renderAttributes(): string
    {
        $html = [];
        foreach ($this->attributes as $key => $value) {
            if ($value === true) {
                $html[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            } elseif ($value !== false && $value !== null) {
                $html[] = sprintf('%s="%s"', htmlspecialchars($key, ENT_QUOTES, 'UTF-8'), htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
            }
        }
        return implode(' ', $html);
    }
}
