<?php

declare(strict_types=1);

namespace App\Cms\Comment;

use MonkeysLegion\DI\Attributes\Singleton;
use PDO;

/**
 * CommentService — CRUD, moderation, and query service for comments.
 */
#[Singleton]
final class CommentService
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Create ─────────────────────────────────────────────────────────

    public function create(CommentEntity $comment): CommentEntity
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO comments (node_id, parent_id, author_name, author_email, author_url, author_id, body, status, ip_address, user_agent)
             VALUES (:node_id, :parent_id, :author_name, :author_email, :author_url, :author_id, :body, :status, :ip_address, :user_agent)'
        );

        $stmt->execute([
            'node_id'      => $comment->node_id,
            'parent_id'    => $comment->parent_id,
            'author_name'  => $comment->author_name,
            'author_email' => $comment->author_email,
            'author_url'   => $comment->author_url,
            'author_id'    => $comment->author_id,
            'body'         => $comment->body,
            'status'       => $comment->status,
            'ip_address'   => $comment->ip_address,
            'user_agent'   => $comment->user_agent ? mb_substr($comment->user_agent, 0, 500) : null,
        ]);

        $comment->id = (int) $this->pdo->lastInsertId();
        $comment->created_at = new \DateTimeImmutable();

        return $comment;
    }

    // ── Read ──────────────────────────────────────────────────────────

    public function find(int $id): ?CommentEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM comments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new CommentEntity())->hydrate($row) : null;
    }

    /**
     * Get all approved comments for a node, structured as a thread tree.
     *
     * @return CommentEntity[]
     */
    public function getThreaded(int $nodeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM comments WHERE node_id = :node_id AND status = :status ORDER BY created_at ASC'
        );
        $stmt->execute(['node_id' => $nodeId, 'status' => 'approved']);

        $all = [];
        $byId = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $entity = (new CommentEntity())->hydrate($row);
            $byId[$entity->id] = $entity;
            $all[] = $entity;
        }

        // Build tree
        $roots = [];
        foreach ($all as $comment) {
            if ($comment->parent_id && isset($byId[$comment->parent_id])) {
                $byId[$comment->parent_id]->children[] = $comment;
            } else {
                $roots[] = $comment;
            }
        }

        return $roots;
    }

    /**
     * Count comments for a node by status.
     */
    public function countForNode(int $nodeId, ?string $status = 'approved'): int
    {
        $sql = 'SELECT COUNT(*) FROM comments WHERE node_id = :node_id';
        $params = ['node_id' => $nodeId];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get comment counts grouped by node_id.
     *
     * @param int[] $nodeIds
     * @return array<int, int>
     */
    public function countByNodes(array $nodeIds, string $status = 'approved'): array
    {
        if (empty($nodeIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT node_id, COUNT(*) as cnt FROM comments WHERE node_id IN ({$placeholders}) AND status = ? GROUP BY node_id"
        );
        $stmt->execute([...array_values($nodeIds), $status]);

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(int) $row['node_id']] = (int) $row['cnt'];
        }

        return $counts;
    }

    // ── Admin Listing (Moderation Queue) ──────────────────────────────

    /**
     * Paginated comment listing for admin moderation.
     */
    public function paginate(
        int $page = 1,
        int $perPage = 25,
        ?string $status = null,
        ?int $nodeId = null,
        ?string $search = null,
    ): array {
        $where = '1=1';
        $params = [];

        if ($status !== null && $status !== 'all') {
            $where .= ' AND c.status = :status';
            $params['status'] = $status;
        }

        if ($nodeId !== null) {
            $where .= ' AND c.node_id = :node_id';
            $params['node_id'] = $nodeId;
        }

        if ($search !== null && $search !== '') {
            $where .= ' AND (c.author_name LIKE :search1 OR c.body LIKE :search2 OR c.author_email LIKE :search3)';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['search3'] = "%{$search}%";
        }

        // Count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM comments c WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch with node info
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT c.*, n.title AS node_title, n.slug AS node_slug, n.content_type
                FROM comments c
                LEFT JOIN nodes n ON c.node_id = n.id
                WHERE {$where}
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset";

        $dataStmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $items = array_map(
            fn(array $row) => (new CommentEntity())->hydrate($row),
            $dataStmt->fetchAll(PDO::FETCH_ASSOC),
        );

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'pages'    => (int) ceil($total / $perPage),
            'perPage'  => $perPage,
        ];
    }

    /**
     * Get counts by status for the moderation tabs.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $stmt = $this->pdo->query(
            'SELECT status, COUNT(*) as cnt FROM comments GROUP BY status'
        );

        $counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'spam' => 0, 'trashed' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
            $counts['all'] += (int) $row['cnt'];
        }

        return $counts;
    }

    // ── Moderation ────────────────────────────────────────────────────

    public function approve(int $id): bool
    {
        return $this->updateStatus($id, 'approved');
    }

    public function markSpam(int $id): bool
    {
        return $this->updateStatus($id, 'spam');
    }

    public function trash(int $id): bool
    {
        return $this->updateStatus($id, 'trashed');
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE comments SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'status' => $status]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Bulk moderation.
     *
     * @param int[] $ids
     */
    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "UPDATE comments SET status = ?, updated_at = NOW() WHERE id IN ({$placeholders})"
        );
        $stmt->execute([$status, ...array_values($ids)]);

        return $stmt->rowCount();
    }

    /**
     * Permanently delete a comment and its children.
     */
    public function delete(int $id): bool
    {
        // Delete children first (one level)
        $this->pdo->prepare('DELETE FROM comments WHERE parent_id = :id')
            ->execute(['id' => $id]);

        $stmt = $this->pdo->prepare('DELETE FROM comments WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete all spam or trashed comments.
     */
    public function emptyByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM comments WHERE status = :status');
        $stmt->execute(['status' => $status]);

        return $stmt->rowCount();
    }

    // ── Spam Helpers ──────────────────────────────────────────────────

    /**
     * Check if a comment looks like spam (basic heuristics).
     */
    public function isLikelySpam(CommentEntity $comment): bool
    {
        // Too many links
        if (substr_count($comment->body, 'http') > 3) {
            return true;
        }

        // Very short body with URL
        if (mb_strlen($comment->body) < 10 && str_contains($comment->body, 'http')) {
            return true;
        }

        return false;
    }

    /**
     * Rate limit check: max 1 comment per minute from same IP.
     */
    public function isRateLimited(string $ipAddress, int $seconds = 60): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM comments WHERE ip_address = :ip AND created_at > DATE_SUB(NOW(), INTERVAL :seconds SECOND)'
        );
        $stmt->execute(['ip' => $ipAddress, 'seconds' => $seconds]);

        return (int) $stmt->fetchColumn() > 0;
    }

    // ── Settings ─────────────────────────────────────────────────────

    /**
     * Check if comments are enabled for a content type.
     */
    public function isEnabledForType(string $contentType): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT comments_enabled FROM content_types WHERE type_id = :type'
        );
        $stmt->execute(['type' => $contentType]);
        $val = $stmt->fetchColumn();

        return $val !== false && (int) $val === 1;
    }
}
