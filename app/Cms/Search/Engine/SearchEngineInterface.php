<?php

declare(strict_types=1);

namespace App\Cms\Search\Engine;

use App\Cms\Search\SearchQuery;
use App\Cms\Search\SearchResult;

/**
 * SearchEngineInterface — Contract for all search backend adapters.
 *
 * Any search engine (MySQL FULLTEXT, Elasticsearch, Solr, Meilisearch,
 * Typesense, Algolia, etc.) implements this interface to provide
 * pluggable search capabilities.
 *
 * The contract covers:
 * - Searching (query → results)
 * - Indexing  (CRUD operations on the search index)
 * - Health    (connection verification)
 *
 * @template TConfig of array<string, mixed>
 */
interface SearchEngineInterface
{
    /**
     * Unique identifier for this engine (e.g. 'mysql', 'elasticsearch', 'solr').
     */
    public function name(): string;

    /**
     * Human-readable display name.
     */
    public function displayName(): string;

    // ── Search ──────────────────────────────────────────────────────────

    /**
     * Execute a search query and return results.
     */
    public function search(SearchQuery $query): SearchResult;

    /**
     * Get autocomplete / suggest completions.
     *
     * @return list<string>
     */
    public function suggest(string $prefix, int $limit = 10): array;

    // ── Indexing ────────────────────────────────────────────────────────

    /**
     * Index a single document.
     *
     * @param string               $id     Document ID
     * @param array<string, mixed> $data   Document data
     */
    public function index(string $id, array $data): void;

    /**
     * Index multiple documents in bulk.
     *
     * @param array<string, array<string, mixed>> $documents ID => data
     */
    public function bulkIndex(array $documents): void;

    /**
     * Remove a document from the index.
     */
    public function delete(string $id): void;

    /**
     * Remove all documents from the index.
     */
    public function flush(): void;

    /**
     * Get the number of indexed documents.
     */
    public function count(): int;

    // ── Health & Diagnostics ────────────────────────────────────────────

    /**
     * Check if the engine is available and healthy.
     */
    public function isAvailable(): bool;

    /**
     * Get engine status info for admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function status(): array;
}
