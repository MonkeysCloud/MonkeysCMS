<?php

declare(strict_types=1);

namespace App\Cms\Theme;

/**
 * AssetRenderer — Generates HTML tags from aggregated asset lists.
 *
 * Supports CSS link tags, JS script tags, and ES module script tags.
 * Automatically handles external URLs (https://) vs local paths.
 */
final class AssetRenderer
{
    /**
     * Render all CSS <link> tags.
     *
     * @param list<string> $urls
     */
    public function renderCss(array $urls): string
    {
        $html = '';
        foreach ($urls as $url) {
            $html .= '  <link rel="stylesheet" href="' . $this->e($url) . '">' . "\n";
        }
        return $html;
    }

    /**
     * Render all JS <script> tags.
     *
     * @param list<string> $urls
     */
    public function renderJs(array $urls): string
    {
        $html = '';
        foreach ($urls as $url) {
            $html .= '  <script src="' . $this->e($url) . '"></script>' . "\n";
        }
        return $html;
    }

    /**
     * Render all ES module <script type="module"> tags.
     *
     * @param list<string> $urls
     */
    public function renderModules(array $urls): string
    {
        $html = '';
        foreach ($urls as $url) {
            $html .= '  <script type="module" src="' . $this->e($url) . '"></script>' . "\n";
        }
        return $html;
    }

    /**
     * Render inline JS blocks.
     *
     * @param list<string> $blocks
     */
    public function renderInlineJs(array $blocks): string
    {
        if (empty($blocks)) return '';

        $html = '';
        foreach ($blocks as $code) {
            $html .= "  <script>\n" . $code . "\n  </script>\n";
        }
        return $html;
    }

    /**
     * Render inline CSS blocks.
     *
     * @param list<string> $blocks
     */
    public function renderInlineCss(array $blocks): string
    {
        if (empty($blocks)) return '';

        $html = '';
        foreach ($blocks as $code) {
            $html .= "  <style>\n" . $code . "\n  </style>\n";
        }
        return $html;
    }

    /**
     * Render preconnect resource hints.
     *
     * @param list<string> $urls
     */
    public function renderPreconnect(array $urls): string
    {
        $html = '';
        $seen = [];
        foreach ($urls as $url) {
            if (isset($seen[$url])) continue;
            $seen[$url] = true;
            $html .= '  <link rel="preconnect" href="' . $this->e($url) . '"';
            // Google Fonts gstatic needs crossorigin
            if (str_contains($url, 'gstatic') || str_contains($url, 'cdn')) {
                $html .= ' crossorigin';
            }
            $html .= ">\n";
        }
        return $html;
    }

    /**
     * Render all head assets: preconnect + CSS links + inline CSS.
     *
     * @param array{css: string[], modules?: string[], preconnect?: string[]} $assets
     * @param list<string> $inlineCss
     */
    public function renderHead(array $assets, array $inlineCss = []): string
    {
        $html = $this->renderPreconnect($assets['preconnect'] ?? []);
        $html .= $this->renderCss($assets['css'] ?? []);
        $html .= $this->renderInlineCss($inlineCss);
        return $html;
    }

    /**
     * Render all bottom-of-body assets: JS scripts + modules + inline JS.
     *
     * @param array{js: string[], modules?: string[]} $assets
     * @param list<string> $inlineJs
     */
    public function renderScripts(array $assets, array $inlineJs = []): string
    {
        $html = $this->renderJs($assets['js'] ?? []);
        $html .= $this->renderModules($assets['modules'] ?? []);
        $html .= $this->renderInlineJs($inlineJs);
        return $html;
    }

    /**
     * Escape an attribute value.
     */
    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
