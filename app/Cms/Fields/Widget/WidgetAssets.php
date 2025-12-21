<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

/**
 * WidgetAssets - Collection of CSS/JS assets
 */
final class WidgetAssets
{
    private function __construct(
        private readonly array $css,
        private readonly array $js,
        private readonly array $inlineStyles,
        private readonly array $inlineScripts,
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], [], []);
    }

    public static function create(array $css = [], array $js = []): self
    {
        return new self($css, $js, [], []);
    }

    public function addCss(string $path): self
    {
        $css = $this->css;
        $css[$path] = true;
        return new self(array_keys($css), $this->js, $this->inlineStyles, $this->inlineScripts);
    }

    public function addJs(string $path): self
    {
        $js = $this->js;
        $js[$path] = true;
        return new self($this->css, array_keys($js), $this->inlineStyles, $this->inlineScripts);
    }

    public function addInlineStyle(string $id, string $css): self
    {
        $styles = $this->inlineStyles;
        $styles[$id] = $css;
        return new self($this->css, $this->js, $styles, $this->inlineScripts);
    }

    public function addInlineScript(string $id, string $js): self
    {
        $scripts = $this->inlineScripts;
        $scripts[$id] = $js;
        return new self($this->css, $this->js, $this->inlineStyles, $scripts);
    }

    public function getCss(): array
    {
        return $this->css;
    }

    public function getJs(): array
    {
        return $this->js;
    }

    public function getInlineStyles(): array
    {
        return $this->inlineStyles;
    }

    public function getInlineScripts(): array
    {
        return $this->inlineScripts;
    }

    public function merge(self $other): self
    {
        return new self(
            array_unique(array_merge($this->css, $other->css)),
            array_unique(array_merge($this->js, $other->js)),
            array_merge($this->inlineStyles, $other->inlineStyles),
            array_merge($this->inlineScripts, $other->inlineScripts),
        );
    }

    public function isEmpty(): bool
    {
        return empty($this->css)
            && empty($this->js)
            && empty($this->inlineStyles)
            && empty($this->inlineScripts);
    }

    public function renderCssTags(): string
    {
        $html = '';
        foreach ($this->css as $path) {
            $html .= '<link rel="stylesheet" href="' . htmlspecialchars($path) . '">' . "\n";
        }
        return $html;
    }

    public function renderJsTags(): string
    {
        $html = '';
        foreach ($this->js as $path) {
            $html .= '<script src="' . htmlspecialchars($path) . '"></script>' . "\n";
        }
        return $html;
    }
}
