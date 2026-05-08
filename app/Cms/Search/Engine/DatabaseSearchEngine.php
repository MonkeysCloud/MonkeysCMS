<?php

declare(strict_types=1);

namespace App\Cms\Search\Engine;

use App\Cms\Search\SearchHit;
use App\Cms\Search\SearchQuery;
use App\Cms\Search\SearchResult;
use App\Cms\Search\SearchSource;
use App\Cms\Search\SearchSourceRegistry;
use PDO;

/**
 * DatabaseSearchEngine — Universal SQL database search adapter.
 *
 * Searches across all enabled SearchSource entities (nodes, terms, users,
 * media, menus, etc.) using UNION queries. Auto-detects the PDO driver
 * (MySQL, PostgreSQL, SQLite) and uses the optimal full-text strategy.
 *
 *   MySQL      → MATCH … AGAINST (FULLTEXT index)
 *   PostgreSQL → to_tsvector / to_tsquery (GIN index)
 *   SQLite     → FTS5 virtual table or LIKE fallback
 *
 * Falls back to LIKE-based search when full-text is unavailable.
 */
final class DatabaseSearchEngine implements SearchEngineInterface
{
    private readonly string $driver;

    public function __construct(
        private readonly PDO $pdo,
        private readonly SearchSourceRegistry $sourceRegistry,
    ) {
        $this->driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?: 'mysql');
    }

    public function name(): string { return 'database'; }
    public function displayName(): string
    {
        return match ($this->driver) {
            'pgsql'  => 'PostgreSQL Full-Text',
            'sqlite' => 'SQLite FTS',
            default  => 'MySQL FULLTEXT',
        };
    }

    // ── Search ──────────────────────────────────────────────────────────

    public function search(SearchQuery $query): SearchResult
    {
        if ($query->isEmpty()) {
            return SearchResult::empty($query, $this->name());
        }

        $start = microtime(true);

        $sources = $this->sourceRegistry->enabled();

        // Filter to specific source if SearchQuery has an index override
        if ($query->index !== null) {
            $sources = array_filter($sources, fn(SearchSource $s) => $s->key === $query->index);
        }

        if (empty($sources)) {
            return SearchResult::empty($query, $this->name());
        }

        $allHits = [];
        $totalCount = 0;

        foreach ($sources as $source) {
            try {
                $result = $this->searchSource($source, $query);
                $totalCount += $result['total'];
                $allHits = array_merge($allHits, $result['hits']);
            } catch (\Throwable) {
                // Skip failed sources
            }
        }

        // Sort merged results by score descending
        if ($query->sortField === '_score') {
            usort($allHits, fn(SearchHit $a, SearchHit $b) => $b->score <=> $a->score);
        }

        // Apply pagination across merged results
        $offset = $query->offset;
        $limit = $query->limit;
        $pagedHits = array_slice($allHits, $offset, $limit);

        $took = (microtime(true) - $start) * 1000;

        // Build facets across all sources
        $facets = [];
        if ($query->facetFields !== []) {
            $facets = $this->buildFacets($query, $sources);
        }

        // Add entity_type facet automatically
        $typeFacet = [];
        foreach ($sources as $source) {
            try {
                $cnt = $this->countSource($source);
                if ($cnt > 0) {
                    $typeFacet[$source->label] = $cnt;
                }
            } catch (\Throwable) {}
        }
        if (count($typeFacet) > 1) {
            $facets['entity_type'] = $typeFacet;
        }

        return new SearchResult(
            hits: $pagedHits,
            total: $totalCount,
            query: $query,
            took: round($took, 2),
            facets: $facets,
            engine: $this->name(),
        );
    }

    public function suggest(string $prefix, int $limit = 10): array
    {
        if (trim($prefix) === '') {
            return [];
        }

        $suggestions = [];
        foreach ($this->sourceRegistry->enabled() as $source) {
            try {
                $titleCol = preg_replace('/[^a-z_]/', '', $source->titleField);
                $stmt = $this->pdo->prepare(
                    "SELECT DISTINCT {$titleCol} FROM {$source->table}"
                    . " WHERE {$titleCol} LIKE :prefix"
                    . ($source->deletedField ? " AND {$source->deletedField} IS NULL" : '')
                    . ($source->statusField ? " AND {$source->statusField} = :status" : '')
                    . " ORDER BY {$titleCol} LIMIT :lim"
                );
                $stmt->bindValue('prefix', $prefix . '%');
                if ($source->statusField) {
                    $stmt->bindValue('status', $source->statusValue ?? '');
                }
                $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
                $stmt->execute();

                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $title) {
                    $suggestions[] = $title;
                }
            } catch (\Throwable) {}
        }

        // Deduplicate, sort, limit
        $suggestions = array_unique($suggestions);
        sort($suggestions);
        return array_slice($suggestions, 0, $limit);
    }

    // ── Indexing (no-op for SQL databases) ──────────────────────────────

    public function index(string $id, array $data): void {}
    public function bulkIndex(array $documents): void {}
    public function delete(string $id): void {}
    public function flush(): void {}

    public function count(): int
    {
        $total = 0;
        foreach ($this->sourceRegistry->enabled() as $source) {
            $total += $this->countSource($source);
        }
        return $total;
    }

    // ── Health ──────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function status(): array
    {
        $sources = $this->sourceRegistry->enabled();
        $sourceSummary = [];
        $totalDocs = 0;

        foreach ($sources as $source) {
            $cnt = $this->countSource($source);
            $totalDocs += $cnt;
            $sourceSummary[$source->key] = [
                'label' => $source->label,
                'table' => $source->table,
                'fields' => $source->searchFields,
                'count' => $cnt,
            ];
        }

        $version = '';
        try {
            $version = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?: '';
        } catch (\Throwable) {}

        return [
            'engine' => $this->displayName(),
            'driver' => $this->driver,
            'available' => $this->isAvailable(),
            'total_documents' => $totalDocs,
            'enabled_sources' => count($sources),
            'total_sources' => count($this->sourceRegistry->all()),
            'sources' => $sourceSummary,
            'server_version' => $version,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════
    //  Per-source search
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{hits: list<SearchHit>, total: int} */
    private function searchSource(SearchSource $source, SearchQuery $query): array
    {
        try {
            return match ($this->driver) {
                'pgsql'  => $this->pgsqlSearchSource($source, $query),
                'sqlite' => $this->likeSearchSource($source, $query), // SQLite FTS TODO
                default  => $this->mysqlSearchSource($source, $query),
            };
        } catch (\Throwable) {
            return $this->likeSearchSource($source, $query);
        }
    }

    // ── MySQL MATCH … AGAINST ───────────────────────────────────────────

    /** @return array{hits: list<SearchHit>, total: int} */
    private function mysqlSearchSource(SearchSource $source, SearchQuery $query): array
    {
        [$where, $params] = $this->buildSourceWhere($source, $query);

        // Build MATCH clause from source's search fields
        $matchFields = implode(', ', array_map(
            fn(string $f) => 'n.' . preg_replace('/[^a-z_]/', '', $f),
            $source->searchFields,
        ));

        $hasOperators = (bool) preg_match('/[+\-~*"()]/', $query->text);
        $mode = $hasOperators ? 'IN BOOLEAN MODE' : 'IN NATURAL LANGUAGE MODE';
        $match = "MATCH({$matchFields}) AGAINST(:query {$mode})";

        // For 'nodes' source: also match in EAV node_fields (searchable fields)
        $eavClause = '';
        if ($source->key === 'nodes') {
            $eavClause = " OR n.id IN (
                SELECT nf.node_id FROM node_fields nf
                INNER JOIN field_definitions fd ON fd.machine_name = nf.field_name
                WHERE fd.searchable = 1 AND nf.field_value LIKE :q_eav_ft
            )";
            $params['q_eav_ft'] = "%{$query->text}%";
        }

        $fullWhere = "{$where} AND ({$match}{$eavClause})";

        $authorSelect = $source->authorField ? ", {$source->authorField} AS _author" : ", '' AS _author";
        $joinClause = $source->authorJoin ?? '';

        return $this->executeSourceSearch(
            source: $source,
            countSql: "SELECT COUNT(*) FROM {$source->table} n {$joinClause} WHERE {$fullWhere}",
            dataSql: "SELECT n.*, {$match} AS _relevance{$authorSelect}
                      FROM {$source->table} n {$joinClause}
                      WHERE {$fullWhere}",
            params: array_merge($params, ['query' => $query->text]),
            query: $query,
        );
    }

    // ── PostgreSQL tsvector ─────────────────────────────────────────────

    /** @return array{hits: list<SearchHit>, total: int} */
    private function pgsqlSearchSource(SearchSource $source, SearchQuery $query): array
    {
        [$where, $params] = $this->buildSourceWhere($source, $query);

        $lang = $query->language ?? 'english';
        $tsCols = implode(" || ' ' || ", array_map(
            fn(string $f) => "COALESCE(n." . preg_replace('/[^a-z_]/', '', $f) . ", '')",
            $source->searchFields,
        ));

        $tsQuery = "plainto_tsquery('{$lang}', :query)";
        $tsVector = "to_tsvector('{$lang}', {$tsCols})";
        $rank = "ts_rank_cd({$tsVector}, {$tsQuery})";

        $fullWhere = "{$where} AND {$tsVector} @@ {$tsQuery}";

        $authorSelect = $source->authorField ? ", {$source->authorField} AS _author" : ", '' AS _author";
        $joinClause = $source->authorJoin ?? '';

        $summaryField = $source->searchFields[0] ?? 'title';
        $safeSummary = preg_replace('/[^a-z_]/', '', $summaryField);

        return $this->executeSourceSearch(
            source: $source,
            countSql: "SELECT COUNT(*) FROM {$source->table} n {$joinClause} WHERE {$fullWhere}",
            dataSql: "SELECT n.*, {$rank} AS _relevance{$authorSelect},
                             ts_headline('{$lang}', COALESCE(n.{$safeSummary}, ''), {$tsQuery},
                                'MaxFragments=3, MaxWords=50, MinWords=20, StartSel=<mark>, StopSel=</mark>')
                             AS _pg_headline
                      FROM {$source->table} n {$joinClause}
                      WHERE {$fullWhere}",
            params: array_merge($params, ['query' => $query->text]),
            query: $query,
        );
    }

    // ── LIKE fallback ───────────────────────────────────────────────────

    /** @return array{hits: list<SearchHit>, total: int} */
    private function likeSearchSource(SearchSource $source, SearchQuery $query): array
    {
        [$where, $params] = $this->buildSourceWhere($source, $query);

        $likeClauses = [];
        $i = 0;
        foreach ($source->searchFields as $field) {
            $safeField = preg_replace('/[^a-z_]/', '', $field);
            $pName = "q{$i}";
            $operator = $this->driver === 'pgsql' ? 'ILIKE' : 'LIKE';
            $likeClauses[] = "n.{$safeField} {$operator} :{$pName}";
            $params[$pName] = "%{$query->text}%";
            $i++;
        }

        // For 'nodes' source: also search in EAV node_fields where field is marked searchable
        if ($source->key === 'nodes') {
            $operator = $this->driver === 'pgsql' ? 'ILIKE' : 'LIKE';
            $likeClauses[] = "n.id IN (
                SELECT nf.node_id FROM node_fields nf
                INNER JOIN field_definitions fd ON fd.machine_name = nf.field_name
                WHERE fd.searchable = 1 AND nf.field_value {$operator} :q_eav
            )";
            $params['q_eav'] = "%{$query->text}%";
        }

        $fullWhere = $where . ' AND (' . implode(' OR ', $likeClauses) . ')';

        $authorSelect = $source->authorField ? ", {$source->authorField} AS _author" : ", '' AS _author";
        $joinClause = $source->authorJoin ?? '';

        return $this->executeSourceSearch(
            source: $source,
            countSql: "SELECT COUNT(*) FROM {$source->table} n {$joinClause} WHERE {$fullWhere}",
            dataSql: "SELECT n.*, 0 AS _relevance{$authorSelect}
                      FROM {$source->table} n {$joinClause}
                      WHERE {$fullWhere}",
            params: $params,
            query: $query,
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    //  Shared internals
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{hits: list<SearchHit>, total: int} */
    private function executeSourceSearch(
        SearchSource $source,
        string $countSql,
        string $dataSql,
        array $params,
        SearchQuery $query,
    ): array {
        // Count
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // ORDER BY
        $orderBy = $query->sortField === '_score'
            ? '_relevance DESC'
            : "{$query->sortField} {$query->sortDirection}";

        // We fetch more than needed — pagination is applied after merging
        $fetchLimit = $query->offset + $query->limit + 10;
        $fullSql = "{$dataSql} ORDER BY {$orderBy} LIMIT :lim";

        $dataStmt = $this->pdo->prepare($fullSql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue('lim', $fetchLimit, PDO::PARAM_INT);
        $dataStmt->execute();

        $hits = [];
        foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $hits[] = $this->sourceRowToHit($source, $row, $query);
        }

        return ['hits' => $hits, 'total' => $total];
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildSourceWhere(SearchSource $source, SearchQuery $query): array
    {
        $where = '1=1';
        $params = [];

        // Soft-delete filter
        if ($source->deletedField) {
            $safeDel = preg_replace('/[^a-z_]/', '', $source->deletedField);
            $where .= " AND n.{$safeDel} IS NULL";
        }

        // Status filter (from source config)
        if ($source->statusField && $source->statusValue !== null) {
            $safeStatus = preg_replace('/[^a-z_]/', '', $source->statusField);
            $where .= " AND n.{$safeStatus} = :_src_status";
            $params['_src_status'] = $source->statusValue;
        }

        // Query filters (from SearchQuery)
        foreach ($query->filters as $key => $value) {
            $safeKey = preg_replace('/[^a-z_]/', '', $key);

            // Skip filters that don't apply to this source
            if ($value === null) {
                continue;
            } elseif (is_array($value)) {
                $placeholders = [];
                foreach ($value as $i => $v) {
                    $pName = "f_{$safeKey}_{$i}";
                    $placeholders[] = ":{$pName}";
                    $params[$pName] = $v;
                }
                $where .= " AND n.{$safeKey} IN (" . implode(',', $placeholders) . ')';
            } else {
                $where .= " AND n.{$safeKey} = :f_{$safeKey}";
                $params["f_{$safeKey}"] = $value;
            }
        }

        return [$where, $params];
    }

    private function sourceRowToHit(SearchSource $source, array $row, SearchQuery $query): SearchHit
    {
        $titleField = $source->titleField;
        $title = $row[$titleField] ?? '';

        $highlights = [];
        if ($query->highlight) {
            if (isset($row['_pg_headline']) && $row['_pg_headline'] !== '') {
                $highlights['body'] = $row['_pg_headline'];
            } elseif ($source->summaryField && isset($row[$source->summaryField])) {
                $highlights['body'] = self::highlightExcerpt(
                    $row[$source->summaryField], $query->text, $query->highlightLength,
                );
            } else {
                // Use first text search field
                foreach ($source->searchFields as $f) {
                    if (isset($row[$f]) && $f !== $titleField && is_string($row[$f])) {
                        $highlights['body'] = self::highlightExcerpt(
                            $row[$f], $query->text, $query->highlightLength,
                        );
                        break;
                    }
                }
            }
            $highlights['title'] = self::highlightExcerpt($title, $query->text, 100);
        }

        $publishedAt = null;
        if ($source->dateField && !empty($row[$source->dateField])) {
            try {
                $publishedAt = new \DateTimeImmutable($row[$source->dateField]);
            } catch (\Throwable) {}
        }

        return new SearchHit(
            id: (string) ($row['id'] ?? ''),
            type: $source->getType($row),
            title: $title,
            url: $source->buildUrl($row),
            score: (float) ($row['_relevance'] ?? 0.0),
            highlights: $highlights,
            source: $row,
            publishedAt: $publishedAt,
            summary: ($source->summaryField && isset($row[$source->summaryField]))
                ? $row[$source->summaryField]
                : null,
            author: ($row['_author'] ?? '') !== '' ? $row['_author'] : null,
        );
    }

    /** @return array<string, array<string, int>> */
    private function buildFacets(SearchQuery $query, array $sources): array
    {
        $facets = [];

        foreach ($sources as $source) {
            foreach ($query->facetFields as $facetField) {
                if (!in_array($facetField, $source->facetFields, true)) {
                    continue;
                }

                $safeField = preg_replace('/[^a-z_]/', '', $facetField);
                try {
                    $whereClause = '1=1';
                    if ($source->deletedField) {
                        $whereClause .= " AND {$source->deletedField} IS NULL";
                    }

                    $stmt = $this->pdo->query(
                        "SELECT {$safeField}, COUNT(*) AS cnt FROM {$source->table}"
                        . " WHERE {$whereClause}"
                        . " GROUP BY {$safeField} ORDER BY cnt DESC LIMIT 50"
                    );

                    $key = "{$source->key}.{$facetField}";
                    $facets[$key] = $facets[$key] ?? [];
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $val = $row[$safeField] ?? '(none)';
                        $facets[$key][$val] = ($facets[$key][$val] ?? 0) + (int) $row['cnt'];
                    }
                } catch (\Throwable) {}
            }
        }

        return $facets;
    }

    private function countSource(SearchSource $source): int
    {
        try {
            $where = '1=1';
            if ($source->deletedField) {
                $where .= " AND {$source->deletedField} IS NULL";
            }
            return (int) $this->pdo->query(
                "SELECT COUNT(*) FROM {$source->table} WHERE {$where}"
            )->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Generate a highlighted excerpt around the query match.
     */
    public static function highlightExcerpt(string $text, string $query, int $contextChars = 200): string
    {
        $text = strip_tags($text);
        if ($text === '' || $query === '') {
            return mb_substr($text, 0, $contextChars);
        }

        $pos = mb_stripos($text, $query);
        if ($pos === false) {
            return mb_substr($text, 0, $contextChars * 2) . (mb_strlen($text) > $contextChars * 2 ? '…' : '');
        }

        $start = max(0, $pos - $contextChars);
        $length = mb_strlen($query) + ($contextChars * 2);
        $excerpt = mb_substr($text, $start, $length);

        if ($start > 0) {
            $excerpt = '…' . $excerpt;
        }
        if ($start + $length < mb_strlen($text)) {
            $excerpt .= '…';
        }

        return preg_replace(
            '/(' . preg_quote($query, '/') . ')/iu',
            '<mark>$1</mark>',
            $excerpt,
        );
    }
}
