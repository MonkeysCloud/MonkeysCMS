<?php

declare(strict_types=1);

namespace App\Cms\Search;

/**
 * SearchResult — Complete search response from any engine.
 *
 * Contains the matched hits, total count, facet aggregations,
 * pagination info, and timing metadata.
 */
final class SearchResult
{
    /**
     * @param list<SearchHit>                       $hits          Matched documents
     * @param int                                   $total         Total number of matches
     * @param SearchQuery                           $query         The original query
     * @param float                                 $took          Query execution time in ms
     * @param array<string, array<string, int>>     $facets        Facet counts: field => [value => count]
     * @param array<string, list<string>>           $suggestions   Suggested corrections
     * @param string                                $engine        Engine identifier
     */
    public function __construct(
        public readonly array $hits,
        public readonly int $total,
        public readonly SearchQuery $query,
        public readonly float $took = 0.0,
        public readonly array $facets = [],
        public readonly array $suggestions = [],
        public readonly string $engine = 'unknown',
    ) {}

    // ── Pagination Helpers ──────────────────────────────────────────────

    public function totalPages(): int
    {
        return $this->query->limit > 0
            ? (int) ceil($this->total / $this->query->limit)
            : 1;
    }

    public function currentPage(): int
    {
        return $this->query->getPage();
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage() < $this->totalPages();
    }

    public function hasPrevPage(): bool
    {
        return $this->currentPage() > 1;
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    public function hasResults(): bool
    {
        return $this->total > 0;
    }

    // ── Facet Helpers ───────────────────────────────────────────────────

    /**
     * Get facet values for a field.
     *
     * @return array<string, int> [value => count]
     */
    public function facet(string $field): array
    {
        return $this->facets[$field] ?? [];
    }

    /**
     * Check if any facets are available.
     */
    public function hasFacets(): bool
    {
        return $this->facets !== [];
    }

    // ── Serialization ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'hits' => array_map(fn(SearchHit $h) => $h->toArray(), $this->hits),
            'total' => $this->total,
            'page' => $this->currentPage(),
            'total_pages' => $this->totalPages(),
            'took_ms' => $this->took,
            'facets' => $this->facets,
            'suggestions' => $this->suggestions,
            'engine' => $this->engine,
        ];
    }

    /**
     * Create an empty result set.
     */
    public static function empty(SearchQuery $query, string $engine = 'unknown'): self
    {
        return new self(
            hits: [],
            total: 0,
            query: $query,
            engine: $engine,
        );
    }
}
