<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

/**
 * WidgetOutput - Result of widget rendering
 */
final class WidgetOutput
{
    private function __construct(
        private readonly string $html,
        private readonly WidgetAssets $assets,
        private readonly ?string $initScript,
    ) {
    }

    public static function create(
        string $html,
        ?WidgetAssets $assets = null,
        ?string $initScript = null,
    ): self {
        return new self(
            $html,
            $assets ?? WidgetAssets::empty(),
            $initScript,
        );
    }

    public static function html(string $html): self
    {
        return new self($html, WidgetAssets::empty(), null);
    }

    public static function empty(): self
    {
        return new self('', WidgetAssets::empty(), null);
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function getAssets(): WidgetAssets
    {
        return $this->assets;
    }

    public function getInitScript(): ?string
    {
        return $this->initScript;
    }

    public function withAssets(WidgetAssets $assets): self
    {
        return new self($this->html, $assets, $this->initScript);
    }

    public function withInitScript(string $script): self
    {
        return new self($this->html, $this->assets, $script);
    }

    public function wrap(string $before, string $after): self
    {
        return new self($before . $this->html . $after, $this->assets, $this->initScript);
    }

    public function combine(self $other): self
    {
        return new self(
            $this->html . $other->html,
            $this->assets->merge($other->assets),
            $this->initScript !== null || $other->initScript !== null
                ? implode("\n", array_filter([$this->initScript, $other->initScript]))
                : null
        );
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
