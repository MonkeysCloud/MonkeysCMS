<?php

declare(strict_types=1);

namespace App\Cms\Search\Engine;

use App\Cms\Content\ContentEntity;
use App\Cms\Search\SearchHit;
use App\Cms\Search\SearchQuery;
use App\Cms\Search\SearchResult;
use PDO;

/**
 * MySqlSearchEngine — MySQL FULLTEXT search implementation.
 *
 * Default search engine that uses MySQL's built-in FULLTEXT indexing
 * on the `nodes` table (idx_ft_search on title, body). Falls back
 * to LIKE queries if FULLTEXT fails.
 *
 * Supports:
 * - Full-text search with natural language mode
 * - Boolean mode for advanced queries (AND/OR/NOT)
 * - Relevance scoring
 * - Content type and status filtering
 * - Facet aggregation via GROUP BY
 * - Autocomplete via prefix matching
 */
final class MySqlSearchEngine implements SearchEngineInterface
{
    private const TABLE = 'nodes';

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function name(): string { return 'mysql'; }
    public function displayName(): string { return 'MySQL FULLTEXT'; }

    // ── Search ──────────────────────────────────────────────────────────

    public function search(SearchQuery $query): SearchResult
    {
        if ($query->isEmpty()) {
            return SearchResult::empty($query, $this->name());
        }

        $start = microtime(true);

        try {
            $result = $this->fulltextSearch($query);
        } catch (\Throwable) {
            $result = $this->likeSearch($query);
        }

        $took = (microtime(true) - $start) * 1000;

        // Build facets if requested
        $facets = [];
        if ($query->facetFields !== []) {
            $facets = $this->buildFacets($query);
        }

        return new SearchResult(
            hits: $result['hits'],
            total: $result['total'],
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

        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT title FROM " . self::TABLE
            . " WHERE title LIKE :prefix AND status = 'published' AND deleted_at IS NULL"
            . " ORDER BY title LIMIT :limit"
        );
        $stmt->bindValue('prefix', $prefix . '%');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'title');
    }

    // ── Indexing (no-op for MySQL — data is stored in-table) ────────────

    public function index(string $id, array $data): void
    {
        // MySQL FULLTEXT indexes are auto-maintained; no explicit indexing needed.
        // This hook exists for engines that maintain a separate index.
    }

    public function bulkIndex(array $documents): void
    {
        // No-op: MySQL indexes in-place.
    }

    public function delete(string $id): void
    {
        // Handled by content repository soft-delete.
    }

    public function flush(): void
    {
        // Cannot flush FULLTEXT index independently; it's part of the table.
    }

    public function count(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM " . self::TABLE . " WHERE deleted_at IS NULL"
        )->fetchColumn();
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
        $totalDocs = $this->count();
        $publishedDocs = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM " . self::TABLE . " WHERE status = 'published' AND deleted_at IS NULL"
        )->fetchColumn();

        // Check FULLTEXT index
        $hasFulltext = false;
        try {
            $indexes = $this->pdo->query("SHOW INDEX FROM " . self::TABLE . " WHERE Index_type = 'FULLTEXT'")
                ->fetchAll(PDO::FETCH_ASSOC);
            $hasFulltext = count($indexes) > 0;
        } catch (\Throwable) {}

        return [
            'engine' => $this->displayName(),
            'available' => $this->isAvailable(),
            'total_documents' => $totalDocs,
            'published_documents' => $publishedDocs,
            'fulltext_index' => $hasFulltext,
            'server_version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        ];
    }

    // ── Internal: FULLTEXT Search ───────────────────────────────────────

    /** @return array{hits: list<SearchHit>, total: int} */
    private function fulltextSearch(SearchQuery $query): array
    {
        [$where, $params] = $this->buildWhereClause($query);

        // Determine FULLTEXT mode
        $hasOperators = (bool) preg_match('/[+\-~*"()]/', $query->text);
        $mode = $hasOperators ? 'IN BOOLEAN MODE' : 'IN NATURAL LANGUAGE MODE';

        $matchClause = "MATCH(title, body) AGAINST(:query {$mode})";
        $fullWhere = "{$where} AND {$matchClause}";

        // Count
        $countSql = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE {$fullWhere}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute(array_merge($params, ['query' => $query->text]));
        $total = (int) $countStmt->fetchColumn();

        // Fetch with relevance
        $orderBy = $query->sortField === '_score' ? 'relevance DESC' : "{$query->sortField} {$query->sortDirection}";

        $dataSql = "SELECT *, {$matchClause} AS relevance, cu.name AS author_name
                    FROM " . self::TABLE . " n
                    LEFT JOIN cms_users cu ON n.author_id = cu.id
                    WHERE {$fullWhere}
                    ORDER BY {$orderBy}
                    LIMIT :limit OFFSET :offset";

        $dataStmt = $this->pdo->prepare($dataSql);
        foreach (array_merge($params, ['query' => $query->text]) as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue('limit', $query->limit, PDO::PARAM_INT);
        $dataStmt->bindValue('offset', $query->offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $hits = [];
        foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $hits[] = $this->rowToHit($row, $query);
        }

        return ['hits' => $hits, 'total' => $total];
    }

    /** @return array{hits: list<SearchHit>, total: int} */
    private function likeSearch(SearchQuery $query): array
    {
        [$where, $params] = $this->buildWhereClause($query);

        $likeClauses = [];
        $likeIndex = 0;
        foreach ($query->searchFields as $field) {
            $safeField = preg_replace('/[^a-z_]/', '', $field);
            $paramName = "q{$likeIndex}";
            $likeClauses[] = "{$safeField} LIKE :{$paramName}";
            $params[$paramName] = "%{$query->text}%";
            $likeIndex++;
        }

        $likeWhere = $where . ' AND (' . implode(' OR ', $likeClauses) . ')';

        // Count
        $countSql = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE {$likeWhere}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch
        $orderBy = $query->sortField === '_score' ? 'created_at DESC' : "{$query->sortField} {$query->sortDirection}";

        $dataSql = "SELECT n.*, cu.name AS author_name
                    FROM " . self::TABLE . " n
                    LEFT JOIN cms_users cu ON n.author_id = cu.id
                    WHERE {$likeWhere}
                    ORDER BY {$orderBy}
                    LIMIT :limit OFFSET :offset";

        $dataStmt = $this->pdo->prepare($dataSql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue('limit', $query->limit, PDO::PARAM_INT);
        $dataStmt->bindValue('offset', $query->offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $hits = [];
        foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $hits[] = $this->rowToHit($row, $query);
        }

        return ['hits' => $hits, 'total' => $total];
    }

    // ── Internal: Facets ────────────────────────────────────────────────

    /** @return array<string, array<string, int>> */
    private function buildFacets(SearchQuery $query): array
    {
        $facets = [];
        $allowedFacets = ['content_type', 'status', 'language', 'author_id'];

        foreach ($query->facetFields as $field) {
            if (!in_array($field, $allowedFacets, true)) {
                continue;
            }

            $safeFld = preg_replace('/[^a-z_]/', '', $field);
            $stmt = $this->pdo->query(
                "SELECT {$safeFld}, COUNT(*) AS cnt FROM " . self::TABLE
                . " WHERE status = 'published' AND deleted_at IS NULL"
                . " GROUP BY {$safeFld} ORDER BY cnt DESC LIMIT 50"
            );

            $facets[$field] = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $facets[$field][$row[$safeFld] ?? '(none)'] = (int) $row['cnt'];
            }
        }

        return $facets;
    }

    // ── Internal: Helpers ───────────────────────────────────────────────

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildWhereClause(SearchQuery $query): array
    {
        $where = 'n.deleted_at IS NULL';
        $params = [];

        foreach ($query->filters as $key => $value) {
            $safeKey = preg_replace('/[^a-z_]/', '', $key);

            if ($value === null) {
                $where .= " AND n.{$safeKey} IS NULL";
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

    private function rowToHit(array $row, SearchQuery $query): SearchHit
    {
        $highlights = [];
        if ($query->highlight) {
            $highlights['body'] = self::highlightExcerpt(
                $row['body'] ?? '',
                $query->text,
                $query->highlightLength,
            );
            $highlights['title'] = self::highlightExcerpt(
                $row['title'] ?? '',
                $query->text,
                100,
            );
        }

        $publishedAt = null;
        if (!empty($row['published_at'])) {
            try {
                $publishedAt = new \DateTimeImmutable($row['published_at']);
            } catch (\Throwable) {}
        }

        return new SearchHit(
            id: (string) ($row['id'] ?? ''),
            type: $row['content_type'] ?? '',
            title: $row['title'] ?? '',
            url: '/' . ltrim($row['slug'] ?? '', '/'),
            score: (float) ($row['relevance'] ?? 0.0),
            highlights: $highlights,
            source: $row,
            publishedAt: $publishedAt,
            summary: $row['summary'] ?? null,
            author: $row['author_name'] ?? null,
        );
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
