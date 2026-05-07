<?php

declare(strict_types=1);

namespace App\Cms\Breadcrumb;

/**
 * BreadcrumbTrail — An ordered collection of BreadcrumbItem crumbs.
 *
 * Provides iteration, array conversion, and JSON-LD structured data output
 * for SEO (Schema.org BreadcrumbList).
 */
final class BreadcrumbTrail implements \Countable, \IteratorAggregate
{
    /** @var list<BreadcrumbItem> */
    private array $items = [];

    private string $separator = '›';

    /**
     * @param list<BreadcrumbItem> $items
     */
    public function __construct(array $items = [], string $separator = '›')
    {
        $this->items = $items;
        $this->separator = $separator;
    }

    public function prepend(BreadcrumbItem $item): static
    {
        array_unshift($this->items, $item);
        return $this;
    }

    public function append(BreadcrumbItem $item): static
    {
        $this->items[] = $item;
        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /** @return list<BreadcrumbItem> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getSeparator(): string
    {
        return $this->separator;
    }

    /** @return list<array{label: string, url?: string}> */
    public function toArray(): array
    {
        return array_map(static fn(BreadcrumbItem $i) => $i->toArray(), $this->items);
    }

    /**
     * Generate a JSON-LD BreadcrumbList script tag for SEO.
     *
     * @param string $baseUrl The site base URL (e.g. https://example.com)
     */
    public function toJsonLd(string $baseUrl = ''): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $elements = [];
        $position = 1;

        foreach ($this->items as $item) {
            $element = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => $item->label,
            ];

            if ($item->url !== null) {
                $url = $item->url;
                // Make absolute if relative
                if (str_starts_with($url, '/')) {
                    $url = rtrim($baseUrl, '/') . $url;
                }
                $element['item'] = $url;
            }

            $elements[] = $element;
        }

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }
}
