<?php

declare(strict_types=1);

namespace App\Cms\Search;

/**
 * SearchQuery — Immutable value object representing a search request.
 *
 * Encapsulates all parameters for a search: query text, filters, facets,
 * pagination, sorting, and highlight options. Used by all search engine
 * adapters regardless of backend (MySQL, Elasticsearch, Solr).
 *
 * Uses PHP 8.4 fluent builder pattern with immutable copies.
 */
final readonly class SearchQuery
{
    /**
     * @param string                    $text        The search query text
     * @param array<string, mixed>      $filters     Key-value filters (e.g. ['content_type' => 'article', 'status' => 'published'])
     * @param list<string>              $searchFields Fields to search in (default: title, body, summary)
     * @param list<string>              $facetFields  Fields to aggregate facets for
     * @param string                    $sortField   Field to sort by
     * @param string                    $sortDirection ASC or DESC
     * @param int                       $limit       Maximum results to return
     * @param int                       $offset      Pagination offset
     * @param bool                      $highlight   Whether to return highlighted snippets
     * @param int                       $highlightLength Excerpt length for highlights
     * @param list<string>              $boostFields Fields to boost in relevance scoring
     * @param array<string, float>      $boostWeights Per-field boost multipliers
     * @param string|null               $language    Language filter/analyzer
     * @param string|null               $index       Target index/table name override
     */
    public function __construct(
        public string $text = '',
        public array $filters = [],
        public array $searchFields = ['title', 'body', 'summary'],
        public array $facetFields = [],
        public string $sortField = '_score',
        public string $sortDirection = 'DESC',
        public int $limit = 25,
        public int $offset = 0,
        public bool $highlight = true,
        public int $highlightLength = 200,
        public array $boostFields = ['title'],
        public array $boostWeights = ['title' => 2.0, 'summary' => 1.5, 'body' => 1.0],
        public ?string $language = null,
        public ?string $index = null,
    ) {}

    // ── Fluent Builder (returns new immutable copies) ────────────────────

    public function withText(string $text): self
    {
        return new self(...[...$this->toArray(), 'text' => $text]);
    }

    public function withFilter(string $key, mixed $value): self
    {
        $filters = $this->filters;
        $filters[$key] = $value;
        return new self(...[...$this->toArray(), 'filters' => $filters]);
    }

    public function withFilters(array $filters): self
    {
        return new self(...[...$this->toArray(), 'filters' => array_merge($this->filters, $filters)]);
    }

    public function withSort(string $field, string $direction = 'DESC'): self
    {
        return new self(...[...$this->toArray(), 'sortField' => $field, 'sortDirection' => strtoupper($direction)]);
    }

    public function withPagination(int $limit, int $offset = 0): self
    {
        return new self(...[...$this->toArray(), 'limit' => $limit, 'offset' => $offset]);
    }

    public function withPage(int $page, int $perPage = 25): self
    {
        return $this->withPagination($perPage, ($page - 1) * $perPage);
    }

    public function withFacets(string ...$fields): self
    {
        return new self(...[...$this->toArray(), 'facetFields' => $fields]);
    }

    public function withSearchFields(string ...$fields): self
    {
        return new self(...[...$this->toArray(), 'searchFields' => $fields]);
    }

    public function withHighlight(bool $enabled = true, int $length = 200): self
    {
        return new self(...[...$this->toArray(), 'highlight' => $enabled, 'highlightLength' => $length]);
    }

    public function withBoost(string $field, float $weight): self
    {
        $weights = $this->boostWeights;
        $weights[$field] = $weight;
        return new self(...[...$this->toArray(), 'boostWeights' => $weights]);
    }

    public function withLanguage(string $language): self
    {
        return new self(...[...$this->toArray(), 'language' => $language]);
    }

    public function withIndex(string $index): self
    {
        return new self(...[...$this->toArray(), 'index' => $index]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }

    public function getPage(): int
    {
        return $this->limit > 0 ? (int) floor($this->offset / $this->limit) + 1 : 1;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'filters' => $this->filters,
            'searchFields' => $this->searchFields,
            'facetFields' => $this->facetFields,
            'sortField' => $this->sortField,
            'sortDirection' => $this->sortDirection,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'highlight' => $this->highlight,
            'highlightLength' => $this->highlightLength,
            'boostFields' => $this->boostFields,
            'boostWeights' => $this->boostWeights,
            'language' => $this->language,
            'index' => $this->index,
        ];
    }
}
