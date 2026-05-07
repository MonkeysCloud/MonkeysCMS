<?php

declare(strict_types=1);

namespace App\Cms\Search;

use App\Cms\Content\ContentEntity;
use PDO;

/**
 * SearchService — Full-text search across published content.
 *
 * Uses MySQL FULLTEXT indexing (idx_ft_search on title, body) for
 * relevance-ranked search. Falls back to LIKE queries if FULLTEXT
 * is unavailable.
 */
final class SearchService
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * Search published content with relevance ranking.
     *
     * @return array{items: list<ContentEntity>, total: int}
     */
    public function search(
        string $query,
        ?string $contentType = null,
        string $status = 'published',
        int $limit = 25,
        int $offset = 0,
    ): array {
        if (trim($query) === '') {
            return ['items' => [], 'total' => 0];
        }

        $where = 'deleted_at IS NULL';
        $params = [];

        if ($status !== 'all') {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }

        if ($contentType !== null) {
            $where .= ' AND content_type = :type';
            $params['type'] = $contentType;
        }

        // Try FULLTEXT search first
        try {
            return $this->fulltextSearch($query, $where, $params, $limit, $offset);
        } catch (\Throwable) {
            // Fallback to LIKE search
            return $this->likeSearch($query, $where, $params, $limit, $offset);
        }
    }

    /**
     * FULLTEXT search with relevance ranking.
     *
     * @return array{items: list<ContentEntity>, total: int}
     */
    private function fulltextSearch(string $query, string $where, array $params, int $limit, int $offset): array
    {
        $matchClause = 'MATCH(title, body) AGAINST(:query IN NATURAL LANGUAGE MODE)';

        // Count total
        $countSql = "SELECT COUNT(*) FROM nodes WHERE {$where} AND {$matchClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute(array_merge($params, ['query' => $query]));
        $total = (int) $countStmt->fetchColumn();

        // Fetch results with relevance score
        $dataSql = "SELECT *, {$matchClause} AS relevance
                    FROM nodes
                    WHERE {$where} AND {$matchClause}
                    ORDER BY relevance DESC
                    LIMIT :limit OFFSET :offset";

        $dataStmt = $this->pdo->prepare($dataSql);
        foreach (array_merge($params, ['query' => $query]) as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $items = array_map(
            fn(array $row) => (new ContentEntity())->hydrate($row),
            $dataStmt->fetchAll(PDO::FETCH_ASSOC),
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * LIKE-based fallback search.
     *
     * @return array{items: list<ContentEntity>, total: int}
     */
    private function likeSearch(string $query, string $where, array $params, int $limit, int $offset): array
    {
        $likeClause = '(title LIKE :q1 OR body LIKE :q2)';
        $searchParams = array_merge($params, ['q1' => "%{$query}%", 'q2' => "%{$query}%"]);

        // Count total
        $countSql = "SELECT COUNT(*) FROM nodes WHERE {$where} AND {$likeClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($searchParams);
        $total = (int) $countStmt->fetchColumn();

        // Fetch results
        $dataSql = "SELECT * FROM nodes WHERE {$where} AND {$likeClause} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $dataStmt = $this->pdo->prepare($dataSql);
        foreach ($searchParams as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $items = array_map(
            fn(array $row) => (new ContentEntity())->hydrate($row),
            $dataStmt->fetchAll(PDO::FETCH_ASSOC),
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Generate a highlighted excerpt from body text around the query match.
     */
    public static function highlightExcerpt(string $text, string $query, int $contextChars = 150): string
    {
        $text = strip_tags($text);
        $pos = mb_stripos($text, $query);

        if ($pos === false) {
            return mb_substr($text, 0, $contextChars * 2) . '…';
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

        // Highlight matching text
        $excerpt = preg_replace(
            '/(' . preg_quote($query, '/') . ')/i',
            '<mark>$1</mark>',
            $excerpt
        );

        return $excerpt;
    }
}
