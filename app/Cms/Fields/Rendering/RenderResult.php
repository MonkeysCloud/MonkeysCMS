<?php

declare(strict_types=1);

namespace App\Cms\Fields\Rendering;

/**
 * RenderResult - Encapsulates the result of field rendering
 * 
 * Contains the rendered HTML and any assets required by the widget.
 */
final class RenderResult
{
    private function __construct(
        private readonly string $html,
        private readonly AssetCollection $assets,
    ) {}

    /**
     * Create a render result
     */
    public static function create(string $html, ?AssetCollection $assets = null): self
    {
        return new self($html, $assets ?? new AssetCollection());
    }

    /**
     * Create an empty result
     */
    public static function empty(): self
    {
        return new self('', new AssetCollection());
    }

    /**
     * Create from HTML only
     */
    public static function fromHtml(string $html): self
    {
        return new self($html, new AssetCollection());
    }

    /**
     * Get the rendered HTML
     */
    public function getHtml(): string
    {
        return $this->html;
    }

    /**
     * Get the required assets
     */
    public function getAssets(): AssetCollection
    {
        return $this->assets;
    }

    /**
     * Get HTML with assets included
     */
    public function getHtmlWithAssets(): string
    {
        return $this->html . $this->assets->render();
    }

    /**
     * Combine with another render result
     */
    public function combine(RenderResult $other): self
    {
        $assets = clone $this->assets;
        $assets->merge($other->assets);
        
        return new self(
            $this->html . $other->html,
            $assets
        );
    }

    /**
     * Wrap HTML in a container
     */
    public function wrap(string $before, string $after): self
    {
        return new self(
            $before . $this->html . $after,
            $this->assets
        );
    }

    /**
     * Check if result is empty
     */
    public function isEmpty(): bool
    {
        return $this->html === '';
    }

    /**
     * Magic string conversion
     */
    public function __toString(): string
    {
        return $this->html;
    }
}
