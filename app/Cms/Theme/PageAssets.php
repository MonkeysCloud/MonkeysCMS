<?php

declare(strict_types=1);

namespace App\Cms\Theme;

use MonkeysLegion\DI\Attributes\Singleton;

/**
 * PageAssets — Per-request asset collector.
 *
 * Controllers and middleware can attach additional libraries or inline
 * assets to the current page, similar to Drupal's #attached system.
 *
 * Usage in controllers:
 *   $pageAssets->attachLibrary('admin/editor');
 *   $pageAssets->addInlineJs('console.log("hello")');
 */
#[Singleton]
final class PageAssets
{
    /** @var list<string> Library IDs attached for this request */
    private array $libraries = [];

    /** @var list<string> Inline JS blocks */
    private array $inlineJs = [];

    /** @var list<string> Inline CSS blocks */
    private array $inlineCss = [];

    /** @var list<string> Extra CSS URLs */
    private array $extraCss = [];

    /** @var list<string> Extra JS URLs */
    private array $extraJs = [];

    /**
     * Computed: all attached library IDs (read-only).
     *
     * @var list<string>
     */
    public array $attachedLibraries {
        get => $this->libraries;
    }

    /**
     * Computed: all inline JS blocks (read-only).
     *
     * @var list<string>
     */
    public array $inlineJsBlocks {
        get => $this->inlineJs;
    }

    /**
     * Computed: all inline CSS blocks (read-only).
     *
     * @var list<string>
     */
    public array $inlineCssBlocks {
        get => $this->inlineCss;
    }

    /**
     * Computed: extra CSS URLs (read-only).
     *
     * @var list<string>
     */
    public array $extraCssUrls {
        get => $this->extraCss;
    }

    /**
     * Computed: extra JS URLs (read-only).
     *
     * @var list<string>
     */
    public array $extraJsUrls {
        get => $this->extraJs;
    }

    /**
     * Attach a named library (from resources/libraries.mlc or theme.mlc).
     * Duplicates are ignored.
     */
    public function attachLibrary(string $id): self
    {
        if (!in_array($id, $this->libraries, true)) {
            $this->libraries[] = $id;
        }
        return $this;
    }

    /**
     * Add an inline JavaScript block.
     */
    public function addInlineJs(string $code): self
    {
        $this->inlineJs[] = $code;
        return $this;
    }

    /**
     * Add an inline CSS block.
     */
    public function addInlineCss(string $code): self
    {
        $this->inlineCss[] = $code;
        return $this;
    }

    /**
     * Add an extra CSS URL not part of any library.
     */
    public function addCss(string $url): self
    {
        if (!in_array($url, $this->extraCss, true)) {
            $this->extraCss[] = $url;
        }
        return $this;
    }

    /**
     * Add an extra JS URL not part of any library.
     */
    public function addJs(string $url): self
    {
        if (!in_array($url, $this->extraJs, true)) {
            $this->extraJs[] = $url;
        }
        return $this;
    }

    /**
     * Reset all page-level assets (called between requests if needed).
     */
    public function reset(): void
    {
        $this->libraries = [];
        $this->inlineJs = [];
        $this->inlineCss = [];
        $this->extraCss = [];
        $this->extraJs = [];
    }
}
