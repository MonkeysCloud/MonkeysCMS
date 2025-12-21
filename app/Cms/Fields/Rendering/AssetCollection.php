<?php

declare(strict_types=1);

namespace App\Cms\Fields\Rendering;

/**
 * AssetCollection - Collects and manages CSS/JS assets for field rendering
 *
 * Tracks required assets from widgets, ensures uniqueness,
 * and generates proper HTML includes.
 */
final class AssetCollection
{
    /** @var array<string, bool> */
    private array $cssFiles = [];

    /** @var array<string, bool> */
    private array $jsFiles = [];

    /** @var array<string> */
    private array $initScripts = [];

    /** @var array<string, string> */
    private array $inlineStyles = [];

    /** @var array<string, string> */
    private array $inlineScripts = [];

    /**
     * Add CSS file
     */
    public function addCss(string $path): self
    {
        $this->cssFiles[$path] = true;
        return $this;
    }

    /**
     * Add multiple CSS files
     */
    public function addCssFiles(array $paths): self
    {
        foreach ($paths as $path) {
            $this->addCss($path);
        }
        return $this;
    }

    /**
     * Add JavaScript file
     */
    public function addJs(string $path): self
    {
        $this->jsFiles[$path] = true;
        return $this;
    }

    /**
     * Add multiple JavaScript files
     */
    public function addJsFiles(array $paths): self
    {
        foreach ($paths as $path) {
            $this->addJs($path);
        }
        return $this;
    }

    /**
     * Add initialization script (runs on DOM ready)
     */
    public function addInitScript(string $script): self
    {
        $this->initScripts[] = $script;
        return $this;
    }

    /**
     * Add inline CSS
     */
    public function addInlineStyle(string $id, string $css): self
    {
        $this->inlineStyles[$id] = $css;
        return $this;
    }

    /**
     * Add inline JavaScript
     */
    public function addInlineScript(string $id, string $js): self
    {
        $this->inlineScripts[$id] = $js;
        return $this;
    }

    /**
     * Merge another collection into this one
     */
    public function merge(AssetCollection $other): self
    {
        foreach (array_keys($other->cssFiles) as $path) {
            $this->addCss($path);
        }

        foreach (array_keys($other->jsFiles) as $path) {
            $this->addJs($path);
        }

        foreach ($other->initScripts as $script) {
            $this->initScripts[] = $script;
        }

        foreach ($other->inlineStyles as $id => $css) {
            $this->inlineStyles[$id] = $css;
        }

        foreach ($other->inlineScripts as $id => $js) {
            $this->inlineScripts[$id] = $js;
        }

        return $this;
    }

    /**
     * Get all CSS file paths
     *
     * @return array<string>
     */
    public function getCssFiles(): array
    {
        return array_keys($this->cssFiles);
    }

    /**
     * Get all JavaScript file paths
     *
     * @return array<string>
     */
    public function getJsFiles(): array
    {
        return array_keys($this->jsFiles);
    }

    /**
     * Get all initialization scripts
     *
     * @return array<string>
     */
    public function getInitScripts(): array
    {
        return $this->initScripts;
    }

    /**
     * Get inline styles
     *
     * @return array<string, string>
     */
    public function getInlineStyles(): array
    {
        return $this->inlineStyles;
    }

    /**
     * Get inline scripts
     *
     * @return array<string, string>
     */
    public function getInlineScripts(): array
    {
        return $this->inlineScripts;
    }

    /**
     * Render CSS link tags
     */
    public function renderCssTags(): string
    {
        $html = '';

        foreach ($this->getCssFiles() as $path) {
            $html .= '<link rel="stylesheet" href="' . htmlspecialchars($path, ENT_QUOTES | ENT_HTML5) . '">' . "\n";
        }

        return $html;
    }

    /**
     * Render inline style tags
     */
    public function renderInlineStyles(): string
    {
        if (empty($this->inlineStyles)) {
            return '';
        }

        $html = '<style>' . "\n";
        foreach ($this->inlineStyles as $css) {
            $html .= $css . "\n";
        }
        $html .= '</style>' . "\n";

        return $html;
    }

    /**
     * Render JavaScript script tags
     */
    public function renderJsTags(): string
    {
        $html = '';

        foreach ($this->getJsFiles() as $path) {
            $html .= '<script src="' . htmlspecialchars($path, ENT_QUOTES | ENT_HTML5) . '"></script>' . "\n";
        }

        return $html;
    }

    /**
     * Render initialization scripts
     */
    public function renderInitScripts(): string
    {
        if (empty($this->initScripts) && empty($this->inlineScripts)) {
            return '';
        }

        $html = '<script>' . "\n";
        $html .= 'document.addEventListener("DOMContentLoaded", function() {' . "\n";

        foreach ($this->initScripts as $script) {
            $html .= $script . "\n";
        }

        $html .= '});' . "\n";

        foreach ($this->inlineScripts as $script) {
            $html .= $script . "\n";
        }

        $html .= '</script>' . "\n";

        return $html;
    }

    /**
     * Render all assets (CSS and JS)
     */
    public function render(): string
    {
        return $this->renderCssTags()
            . $this->renderInlineStyles()
            . $this->renderJsTags()
            . $this->renderInitScripts();
    }

    /**
     * Check if collection is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->cssFiles)
            && empty($this->jsFiles)
            && empty($this->initScripts)
            && empty($this->inlineStyles)
            && empty($this->inlineScripts);
    }

    /**
     * Clear all collected assets
     */
    public function clear(): self
    {
        $this->cssFiles = [];
        $this->jsFiles = [];
        $this->initScripts = [];
        $this->inlineStyles = [];
        $this->inlineScripts = [];
        return $this;
    }

    /**
     * Get statistics about collected assets
     */
    public function getStats(): array
    {
        return [
            'css_files' => count($this->cssFiles),
            'js_files' => count($this->jsFiles),
            'init_scripts' => count($this->initScripts),
            'inline_styles' => count($this->inlineStyles),
            'inline_scripts' => count($this->inlineScripts),
        ];
    }
}
