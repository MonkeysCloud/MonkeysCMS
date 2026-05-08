<?php

declare(strict_types=1);

namespace App\Cms\Search\Engine;

use App\Cms\Search\SearchHit;
use App\Cms\Search\SearchQuery;
use App\Cms\Search\SearchResult;

/**
 * ElasticsearchEngine — Elasticsearch/OpenSearch adapter.
 *
 * Provides full integration with Elasticsearch 7.x/8.x and OpenSearch,
 * including multi-field boosted search, facet aggregations, highlighting,
 * and suggest/autocomplete.
 *
 * Configuration:
 *   - host: 'https://localhost:9200'
 *   - index: 'monkeyscms_content'
 *   - api_key: optional API key
 *   - username/password: optional basic auth
 *   - ssl_verify: bool
 *
 * Uses native cURL for zero-dependency HTTP communication.
 */
final class ElasticsearchEngine implements SearchEngineInterface
{
    private string $host;
    private string $index;
    private ?string $apiKey;
    private ?string $username;
    private ?string $password;
    private bool $sslVerify;

    /**
     * @param array{
     *   host?: string,
     *   index?: string,
     *   api_key?: string|null,
     *   username?: string|null,
     *   password?: string|null,
     *   ssl_verify?: bool,
     * } $config
     */
    public function __construct(private readonly array $config = [])
    {
        $this->host = rtrim($config['host'] ?? 'http://localhost:9200', '/');
        $this->index = $config['index'] ?? 'monkeyscms_content';
        $this->apiKey = $config['api_key'] ?? null;
        $this->username = $config['username'] ?? null;
        $this->password = $config['password'] ?? null;
        $this->sslVerify = $config['ssl_verify'] ?? true;
    }

    public function name(): string { return 'elasticsearch'; }
    public function displayName(): string { return 'Elasticsearch'; }

    // ── Search ──────────────────────────────────────────────────────────

    public function search(SearchQuery $query): SearchResult
    {
        if ($query->isEmpty()) {
            return SearchResult::empty($query, $this->name());
        }

        $start = microtime(true);

        $body = $this->buildSearchBody($query);
        $response = $this->request('POST', "/{$this->index}/_search", $body);

        $took = (microtime(true) - $start) * 1000;

        if (!isset($response['hits'])) {
            return SearchResult::empty($query, $this->name());
        }

        $hits = array_map(
            fn(array $hit) => $this->toSearchHit($hit),
            $response['hits']['hits'] ?? [],
        );

        // Extract facets from aggregations
        $facets = [];
        foreach ($response['aggregations'] ?? [] as $name => $agg) {
            $facets[$name] = [];
            foreach ($agg['buckets'] ?? [] as $bucket) {
                $facets[$name][$bucket['key']] = (int) $bucket['doc_count'];
            }
        }

        // Extract suggestions
        $suggestions = [];
        foreach ($response['suggest'] ?? [] as $suggestName => $suggestItems) {
            foreach ($suggestItems as $item) {
                foreach ($item['options'] ?? [] as $option) {
                    $suggestions[$suggestName][] = $option['text'];
                }
            }
        }

        return new SearchResult(
            hits: $hits,
            total: $response['hits']['total']['value'] ?? count($hits),
            query: $query,
            took: $response['took'] ?? round($took, 2),
            facets: $facets,
            suggestions: $suggestions,
            engine: $this->name(),
        );
    }

    public function suggest(string $prefix, int $limit = 10): array
    {
        $body = [
            'suggest' => [
                'title_suggest' => [
                    'prefix' => $prefix,
                    'completion' => [
                        'field' => 'title.suggest',
                        'size' => $limit,
                        'skip_duplicates' => true,
                    ],
                ],
            ],
        ];

        $response = $this->request('POST', "/{$this->index}/_search", $body);

        $results = [];
        foreach ($response['suggest']['title_suggest'] ?? [] as $entry) {
            foreach ($entry['options'] ?? [] as $option) {
                $results[] = $option['text'];
            }
        }

        return array_unique($results);
    }

    // ── Indexing ────────────────────────────────────────────────────────

    public function index(string $id, array $data): void
    {
        $this->request('PUT', "/{$this->index}/_doc/{$id}", $data);
    }

    public function bulkIndex(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $ndjson = '';
        foreach ($documents as $id => $data) {
            $ndjson .= json_encode(['index' => ['_index' => $this->index, '_id' => $id]]) . "\n";
            $ndjson .= json_encode($data) . "\n";
        }

        $this->request('POST', '/_bulk', null, $ndjson);
    }

    public function delete(string $id): void
    {
        try {
            $this->request('DELETE', "/{$this->index}/_doc/{$id}");
        } catch (\Throwable) {
            // Ignore 404 — document might not exist in index
        }
    }

    public function flush(): void
    {
        try {
            $this->request('POST', "/{$this->index}/_delete_by_query", [
                'query' => ['match_all' => (object) []],
            ]);
        } catch (\Throwable) {
            // Index might not exist
        }
    }

    public function count(): int
    {
        try {
            $response = $this->request('GET', "/{$this->index}/_count");
            return (int) ($response['count'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    // ── Health ──────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        try {
            $response = $this->request('GET', '/_cluster/health');
            return isset($response['status']);
        } catch (\Throwable) {
            return false;
        }
    }

    public function status(): array
    {
        try {
            $health = $this->request('GET', '/_cluster/health');
            $info = $this->request('GET', '/');

            $indexStats = [];
            try {
                $indexStats = $this->request('GET', "/{$this->index}/_stats");
            } catch (\Throwable) {}

            return [
                'engine' => $this->displayName(),
                'available' => true,
                'cluster_name' => $health['cluster_name'] ?? 'unknown',
                'cluster_status' => $health['status'] ?? 'unknown',
                'version' => $info['version']['number'] ?? 'unknown',
                'index' => $this->index,
                'total_documents' => $indexStats['_all']['primaries']['docs']['count'] ?? 0,
                'index_size' => $indexStats['_all']['primaries']['store']['size_in_bytes'] ?? 0,
            ];
        } catch (\Throwable $e) {
            return [
                'engine' => $this->displayName(),
                'available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ── Index Management ────────────────────────────────────────────────

    /**
     * Create the search index with optimized mappings.
     */
    public function createIndex(): void
    {
        $mappings = [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
                'analysis' => [
                    'analyzer' => [
                        'content_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'stop', 'snowball'],
                        ],
                    ],
                ],
            ],
            'mappings' => [
                'properties' => [
                    'title' => [
                        'type' => 'text',
                        'analyzer' => 'content_analyzer',
                        'boost' => 2.0,
                        'fields' => [
                            'keyword' => ['type' => 'keyword'],
                            'suggest' => ['type' => 'completion'],
                        ],
                    ],
                    'body' => [
                        'type' => 'text',
                        'analyzer' => 'content_analyzer',
                    ],
                    'summary' => [
                        'type' => 'text',
                        'analyzer' => 'content_analyzer',
                        'boost' => 1.5,
                    ],
                    'slug' => ['type' => 'keyword'],
                    'content_type' => ['type' => 'keyword'],
                    'status' => ['type' => 'keyword'],
                    'language' => ['type' => 'keyword'],
                    'author_id' => ['type' => 'integer'],
                    'author_name' => ['type' => 'keyword'],
                    'published_at' => ['type' => 'date'],
                    'created_at' => ['type' => 'date'],
                    'updated_at' => ['type' => 'date'],
                    'tags' => ['type' => 'keyword'],
                ],
            ],
        ];

        $this->request('PUT', "/{$this->index}", $mappings);
    }

    /**
     * Delete and recreate the index.
     */
    public function resetIndex(): void
    {
        try {
            $this->request('DELETE', "/{$this->index}");
        } catch (\Throwable) {}

        $this->createIndex();
    }

    // ── Internal: Query Builder ─────────────────────────────────────────

    private function buildSearchBody(SearchQuery $query): array
    {
        $body = [
            'from' => $query->offset,
            'size' => $query->limit,
        ];

        // Build multi-match query with field boosting
        $fields = [];
        foreach ($query->searchFields as $field) {
            $boost = $query->boostWeights[$field] ?? 1.0;
            $fields[] = $boost != 1.0 ? "{$field}^{$boost}" : $field;
        }

        $must = [
            'multi_match' => [
                'query' => $query->text,
                'fields' => $fields,
                'type' => 'best_fields',
                'fuzziness' => 'AUTO',
                'prefix_length' => 2,
            ],
        ];

        // Build filters
        $filterClauses = [];
        foreach ($query->filters as $key => $value) {
            if (is_array($value)) {
                $filterClauses[] = ['terms' => [$key => $value]];
            } elseif ($value === null) {
                $filterClauses[] = ['bool' => ['must_not' => ['exists' => ['field' => $key]]]];
            } else {
                $filterClauses[] = ['term' => [$key => $value]];
            }
        }

        $body['query'] = [
            'bool' => [
                'must' => [$must],
                'filter' => $filterClauses,
            ],
        ];

        // Sorting
        if ($query->sortField !== '_score') {
            $body['sort'] = [
                [$query->sortField => ['order' => strtolower($query->sortDirection)]],
                '_score',
            ];
        }

        // Highlighting
        if ($query->highlight) {
            $body['highlight'] = [
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
                'fragment_size' => $query->highlightLength,
                'number_of_fragments' => 3,
                'fields' => [
                    'title' => (object) [],
                    'body' => (object) [],
                    'summary' => (object) [],
                ],
            ];
        }

        // Aggregations (facets)
        if ($query->facetFields !== []) {
            $body['aggs'] = [];
            foreach ($query->facetFields as $field) {
                $body['aggs'][$field] = [
                    'terms' => [
                        'field' => $field,
                        'size' => 50,
                    ],
                ];
            }
        }

        return $body;
    }

    private function toSearchHit(array $hit): SearchHit
    {
        $source = $hit['_source'] ?? [];
        $highlight = $hit['highlight'] ?? [];

        $highlights = [];
        foreach ($highlight as $field => $fragments) {
            $highlights[$field] = implode(' … ', $fragments);
        }

        $publishedAt = null;
        if (!empty($source['published_at'])) {
            try {
                $publishedAt = new \DateTimeImmutable($source['published_at']);
            } catch (\Throwable) {}
        }

        return new SearchHit(
            id: $hit['_id'],
            type: $source['content_type'] ?? '',
            title: $source['title'] ?? '',
            url: '/' . ltrim($source['slug'] ?? '', '/'),
            score: (float) ($hit['_score'] ?? 0),
            highlights: $highlights,
            source: $source,
            publishedAt: $publishedAt,
            summary: $source['summary'] ?? null,
            author: $source['author_name'] ?? null,
        );
    }

    // ── Internal: HTTP Client ───────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, ?string $rawBody = null): array
    {
        $url = $this->host . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $this->buildHeaders($rawBody !== null),
        ]);

        if (!$this->sslVerify) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if ($this->username && $this->password) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($rawBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("Elasticsearch request failed: {$error}");
        }

        $decoded = json_decode((string) $response, true) ?? [];

        if ($httpCode >= 400 && $httpCode !== 404) {
            $msg = $decoded['error']['reason'] ?? $decoded['error'] ?? "HTTP {$httpCode}";
            if (is_array($msg)) {
                $msg = json_encode($msg);
            }
            throw new \RuntimeException("Elasticsearch error: {$msg}");
        }

        return $decoded;
    }

    /** @return list<string> */
    private function buildHeaders(bool $isNdjson = false): array
    {
        $contentType = $isNdjson ? 'application/x-ndjson' : 'application/json';
        $headers = ["Content-Type: {$contentType}"];

        if ($this->apiKey) {
            $headers[] = "Authorization: ApiKey {$this->apiKey}";
        }

        return $headers;
    }
}
