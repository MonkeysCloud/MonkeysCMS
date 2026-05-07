<?php

declare(strict_types=1);

namespace App\Cms\Search\Engine;

use App\Cms\Search\SearchHit;
use App\Cms\Search\SearchQuery;
use App\Cms\Search\SearchResult;

/**
 * SolrSearchEngine — Apache Solr adapter.
 *
 * Provides integration with Apache Solr 8.x/9.x for enterprise-grade
 * search with faceting, highlighting, and spell-check.
 *
 * Configuration:
 *   - host: 'http://localhost:8983'
 *   - core: 'monkeyscms'
 *   - username/password: optional basic auth
 *   - timeout: request timeout in seconds
 *
 * Uses native cURL for zero-dependency HTTP communication.
 */
final class SolrSearchEngine implements SearchEngineInterface
{
    private string $host;
    private string $core;
    private ?string $username;
    private ?string $password;
    private int $timeout;

    /**
     * @param array{
     *   host?: string,
     *   core?: string,
     *   username?: string|null,
     *   password?: string|null,
     *   timeout?: int,
     * } $config
     */
    public function __construct(private readonly array $config = [])
    {
        $this->host = rtrim($config['host'] ?? 'http://localhost:8983', '/');
        $this->core = $config['core'] ?? 'monkeyscms';
        $this->username = $config['username'] ?? null;
        $this->password = $config['password'] ?? null;
        $this->timeout = $config['timeout'] ?? 30;
    }

    public function name(): string { return 'solr'; }
    public function displayName(): string { return 'Apache Solr'; }

    // ── Search ──────────────────────────────────────────────────────────

    public function search(SearchQuery $query): SearchResult
    {
        if ($query->isEmpty()) {
            return SearchResult::empty($query, $this->name());
        }

        $start = microtime(true);

        $params = $this->buildSearchParams($query);
        $response = $this->request('GET', "/solr/{$this->core}/select", $params);

        $took = (microtime(true) - $start) * 1000;

        $docs = $response['response']['docs'] ?? [];
        $total = $response['response']['numFound'] ?? 0;

        $highlighting = $response['highlighting'] ?? [];

        $hits = array_map(
            fn(array $doc) => $this->toSearchHit($doc, $highlighting[$doc['id'] ?? ''] ?? []),
            $docs,
        );

        // Extract facets
        $facets = [];
        $facetFields = $response['facet_counts']['facet_fields'] ?? [];
        foreach ($facetFields as $field => $values) {
            $facets[$field] = [];
            for ($i = 0; $i < count($values) - 1; $i += 2) {
                if ($values[$i + 1] > 0) {
                    $facets[$field][$values[$i]] = (int) $values[$i + 1];
                }
            }
        }

        // Extract suggestions
        $suggestions = [];
        foreach ($response['spellcheck']['suggestions'] ?? [] as $key => $value) {
            if (is_array($value) && isset($value['suggestion'])) {
                $suggestions[$key] = $value['suggestion'];
            }
        }

        return new SearchResult(
            hits: $hits,
            total: (int) $total,
            query: $query,
            took: $response['responseHeader']['QTime'] ?? round($took, 2),
            facets: $facets,
            suggestions: $suggestions,
            engine: $this->name(),
        );
    }

    public function suggest(string $prefix, int $limit = 10): array
    {
        $params = [
            'suggest' => 'true',
            'suggest.dictionary' => 'titleSuggester',
            'suggest.q' => $prefix,
            'suggest.count' => $limit,
        ];

        $response = $this->request('GET', "/solr/{$this->core}/suggest", $params);

        $results = [];
        foreach ($response['suggest']['titleSuggester'] ?? [] as $entry) {
            foreach ($entry['suggestions'] ?? [] as $suggestion) {
                $results[] = $suggestion['term'];
            }
        }

        return $results;
    }

    // ── Indexing ────────────────────────────────────────────────────────

    public function index(string $id, array $data): void
    {
        $data['id'] = $id;
        $this->request('POST', "/solr/{$this->core}/update/json/docs", null, json_encode($data));
        $this->commit();
    }

    public function bulkIndex(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $docs = [];
        foreach ($documents as $id => $data) {
            $data['id'] = $id;
            $docs[] = $data;
        }

        $this->request('POST', "/solr/{$this->core}/update", null, json_encode($docs));
        $this->commit();
    }

    public function delete(string $id): void
    {
        $this->request('POST', "/solr/{$this->core}/update", null,
            json_encode(['delete' => ['id' => $id]]));
        $this->commit();
    }

    public function flush(): void
    {
        $this->request('POST', "/solr/{$this->core}/update", null,
            json_encode(['delete' => ['query' => '*:*']]));
        $this->commit();
    }

    public function count(): int
    {
        try {
            $response = $this->request('GET', "/solr/{$this->core}/select", [
                'q' => '*:*',
                'rows' => 0,
                'wt' => 'json',
            ]);
            return (int) ($response['response']['numFound'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    // ── Health ──────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        try {
            $response = $this->request('GET', "/solr/{$this->core}/admin/ping");
            return ($response['status'] ?? '') === 'OK';
        } catch (\Throwable) {
            return false;
        }
    }

    public function status(): array
    {
        try {
            $coreStatus = $this->request('GET', '/solr/admin/cores', [
                'action' => 'STATUS',
                'core' => $this->core,
                'wt' => 'json',
            ]);

            $info = $this->request('GET', '/solr/admin/info/system', ['wt' => 'json']);
            $coreData = $coreStatus['status'][$this->core] ?? [];

            return [
                'engine' => $this->displayName(),
                'available' => true,
                'core' => $this->core,
                'solr_version' => $info['lucene']['solr-spec-version'] ?? 'unknown',
                'total_documents' => $coreData['index']['numDocs'] ?? 0,
                'index_size' => $coreData['index']['sizeInBytes'] ?? 0,
                'last_modified' => $coreData['index']['lastModified'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'engine' => $this->displayName(),
                'available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ── Solr-Specific ───────────────────────────────────────────────────

    /**
     * Commit pending index changes.
     */
    public function commit(): void
    {
        $this->request('POST', "/solr/{$this->core}/update", null,
            json_encode(['commit' => (object) []]));
    }

    /**
     * Optimize the index (force merge segments).
     */
    public function optimize(): void
    {
        $this->request('POST', "/solr/{$this->core}/update", null,
            json_encode(['optimize' => ['maxSegments' => 1]]));
    }

    // ── Internal: Query Builder ─────────────────────────────────────────

    /** @return array<string, string> */
    private function buildSearchParams(SearchQuery $query): array
    {
        $params = [
            'wt' => 'json',
            'rows' => (string) $query->limit,
            'start' => (string) $query->offset,
        ];

        // Build query with field boosting via edismax
        $params['defType'] = 'edismax';
        $params['q'] = $query->text;

        $qf = [];
        foreach ($query->searchFields as $field) {
            $boost = $query->boostWeights[$field] ?? 1.0;
            $qf[] = $boost != 1.0 ? "{$field}^{$boost}" : $field;
        }
        $params['qf'] = implode(' ', $qf);

        // Filters
        $fq = [];
        foreach ($query->filters as $key => $value) {
            if (is_array($value)) {
                $fq[] = "{$key}:(" . implode(' OR ', array_map(fn($v) => '"' . addslashes((string) $v) . '"', $value)) . ')';
            } elseif ($value === null) {
                $fq[] = "-{$key}:[* TO *]";
            } else {
                $fq[] = "{$key}:\"" . addslashes((string) $value) . '"';
            }
        }
        if ($fq !== []) {
            $params['fq'] = implode(' AND ', $fq);
        }

        // Sorting
        if ($query->sortField !== '_score') {
            $params['sort'] = "{$query->sortField} " . strtolower($query->sortDirection);
        }

        // Highlighting
        if ($query->highlight) {
            $params['hl'] = 'on';
            $params['hl.fl'] = implode(',', $query->searchFields);
            $params['hl.fragsize'] = (string) $query->highlightLength;
            $params['hl.simple.pre'] = '<mark>';
            $params['hl.simple.post'] = '</mark>';
            $params['hl.snippets'] = '3';
        }

        // Faceting
        if ($query->facetFields !== []) {
            $params['facet'] = 'on';
            $params['facet.field'] = $query->facetFields;
            $params['facet.mincount'] = '1';
            $params['facet.limit'] = '50';
        }

        // Spellcheck
        $params['spellcheck'] = 'on';
        $params['spellcheck.q'] = $query->text;
        $params['spellcheck.count'] = '5';

        return $params;
    }

    private function toSearchHit(array $doc, array $highlighting): SearchHit
    {
        $highlights = [];
        foreach ($highlighting as $field => $fragments) {
            if (is_array($fragments)) {
                $highlights[$field] = implode(' … ', $fragments);
            }
        }

        $publishedAt = null;
        if (!empty($doc['published_at'])) {
            try {
                $publishedAt = new \DateTimeImmutable($doc['published_at']);
            } catch (\Throwable) {}
        }

        return new SearchHit(
            id: (string) ($doc['id'] ?? ''),
            type: $doc['content_type'] ?? '',
            title: $doc['title'] ?? '',
            url: '/' . ltrim($doc['slug'] ?? '', '/'),
            score: (float) ($doc['score'] ?? 0),
            highlights: $highlights,
            source: $doc,
            publishedAt: $publishedAt,
            summary: $doc['summary'] ?? null,
            author: $doc['author_name'] ?? null,
        );
    }

    // ── Internal: HTTP Client ───────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $params = null, ?string $body = null): array
    {
        $url = $this->host . $path;

        if ($params !== null) {
            // Handle array params (facet.field can have multiple values)
            $queryParts = [];
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $queryParts[] = urlencode($key) . '=' . urlencode((string) $v);
                    }
                } else {
                    $queryParts[] = urlencode($key) . '=' . urlencode((string) $value);
                }
            }
            $url .= '?' . implode('&', $queryParts);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        ]);

        if ($this->username && $this->password) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("Solr request failed: {$error}");
        }

        $decoded = json_decode((string) $response, true) ?? [];

        if ($httpCode >= 400) {
            $msg = $decoded['error']['msg'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("Solr error: {$msg}");
        }

        return $decoded;
    }
}
