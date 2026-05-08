<?php

declare(strict_types=1);

namespace App\Cms\Content;

use PDO;

/**
 * DiffService — Compare content node revisions.
 *
 * Computes field-by-field diffs between two revision snapshots
 * and provides the revision history for a node.
 */
final class DiffService
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Revision History ─────────────────────────────────────────────

    /**
     * Get all revisions for a node, newest first.
     *
     * @return list<array{id: int, revision: int, author_id: ?int, author_name: ?string, message: ?string, created_at: string}>
     */
    public function getRevisions(int $nodeId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.name AS author_name
             FROM node_revisions r
             LEFT JOIN cms_users u ON r.author_id = u.id
             WHERE r.node_id = :node_id
             ORDER BY r.revision DESC
             LIMIT :limit"
        );
        $stmt->bindValue('node_id', $nodeId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific revision's data.
     */
    public function getRevision(int $nodeId, int $revision): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.name AS author_name
             FROM node_revisions r
             LEFT JOIN cms_users u ON r.author_id = u.id
             WHERE r.node_id = :node_id AND r.revision = :revision
             LIMIT 1"
        );
        $stmt->execute(['node_id' => $nodeId, 'revision' => $revision]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['data'] = json_decode($row['data'] ?? '{}', true) ?: [];
        return $row;
    }

    /**
     * Get the current state of a node as a "revision" data array
     * (for comparing against saved revisions).
     */
    public function getCurrentState(int $nodeId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM nodes WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $nodeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'title'            => $row['title'] ?? '',
            'body'             => $row['body'] ?? '',
            'slug'             => $row['slug'] ?? '',
            'status'           => $row['status'] ?? '',
            'summary'          => $row['summary'] ?? '',
            'meta_title'       => $row['meta_title'] ?? '',
            'meta_description' => $row['meta_description'] ?? '',
            'fields'           => json_decode($row['fields'] ?? '{}', true) ?: [],
        ];
    }

    // ── Snapshotting ─────────────────────────────────────────────────

    /**
     * Snapshot the current state of a node into node_revisions.
     * Called before an update overwrites the content.
     */
    public function snapshot(int $nodeId, ?int $authorId = null, ?string $message = null): void
    {
        try {
            $state = $this->getCurrentState($nodeId);
            if (!$state) {
                return;
            }

            // Get current revision number from node
            $revStmt = $this->pdo->prepare('SELECT revision FROM nodes WHERE id = :id');
            $revStmt->execute(['id' => $nodeId]);
            $revision = (int) $revStmt->fetchColumn();

            $stmt = $this->pdo->prepare(
                "INSERT INTO node_revisions (node_id, revision, data, author_id, message, created_at)
                 VALUES (:node_id, :revision, :data, :author_id, :message, NOW())"
            );
            $stmt->execute([
                'node_id'   => $nodeId,
                'revision'  => $revision,
                'data'      => json_encode($state),
                'author_id' => $authorId,
                'message'   => $message,
            ]);
        } catch (\Throwable) {
            // Silently ignore if table doesn't exist yet
        }
    }

    // ── Diff Computation ─────────────────────────────────────────────

    /**
     * Compare two revisions of a node, returning field-by-field diffs.
     *
     * @return list<array{field: string, label: string, type: string, from: string, to: string, diff: array}>
     */
    public function compare(int $nodeId, int $revA, int $revB): array
    {
        $dataA = $this->resolveRevisionData($nodeId, $revA);
        $dataB = $this->resolveRevisionData($nodeId, $revB);

        if ($dataA === null || $dataB === null) {
            return [];
        }

        $fieldLabels = [
            'title'            => 'Title',
            'body'             => 'Body',
            'slug'             => 'URL Slug',
            'status'           => 'Status',
            'summary'          => 'Summary',
            'meta_title'       => 'Meta Title',
            'meta_description' => 'Meta Description',
        ];

        $diffs = [];

        // Compare core fields
        foreach ($fieldLabels as $field => $label) {
            $from = (string) ($dataA[$field] ?? '');
            $to   = (string) ($dataB[$field] ?? '');

            if ($from === $to) {
                continue;
            }

            $type = ($field === 'body') ? 'html' : 'text';
            $diffs[] = [
                'field' => $field,
                'label' => $label,
                'type'  => $type,
                'from'  => $from,
                'to'    => $to,
                'diff'  => $this->computeLineDiff($from, $to),
            ];
        }

        // Compare custom fields (JSON)
        $fieldsA = $dataA['fields'] ?? [];
        $fieldsB = $dataB['fields'] ?? [];
        $allFieldKeys = array_unique(array_merge(array_keys($fieldsA), array_keys($fieldsB)));

        foreach ($allFieldKeys as $key) {
            $from = is_scalar($fieldsA[$key] ?? null)
                ? (string) ($fieldsA[$key] ?? '')
                : json_encode($fieldsA[$key] ?? '', JSON_PRETTY_PRINT);
            $to = is_scalar($fieldsB[$key] ?? null)
                ? (string) ($fieldsB[$key] ?? '')
                : json_encode($fieldsB[$key] ?? '', JSON_PRETTY_PRINT);

            if ($from === $to) {
                continue;
            }

            $diffs[] = [
                'field' => "fields.{$key}",
                'label' => ucfirst(str_replace('_', ' ', $key)),
                'type'  => 'text',
                'from'  => $from,
                'to'    => $to,
                'diff'  => $this->computeLineDiff($from, $to),
            ];
        }

        return $diffs;
    }

    /**
     * Revert a node to a specific revision.
     */
    public function revert(int $nodeId, int $revision, ?int $authorId = null): bool
    {
        $revData = $this->getRevision($nodeId, $revision);
        if (!$revData || empty($revData['data'])) {
            return false;
        }

        // Snapshot current state before revert
        $this->snapshot($nodeId, $authorId, "Before revert to revision {$revision}");

        $data = $revData['data'];

        // Restore core fields
        $stmt = $this->pdo->prepare(
            "UPDATE nodes SET
                title = :title, body = :body, slug = :slug, status = :status,
                summary = :summary, meta_title = :meta_title, meta_description = :meta_description,
                fields = :fields, revision = revision + 1, updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'id'               => $nodeId,
            'title'            => $data['title'] ?? '',
            'body'             => $data['body'] ?? '',
            'slug'             => $data['slug'] ?? '',
            'status'           => $data['status'] ?? 'draft',
            'summary'          => $data['summary'] ?? '',
            'meta_title'       => $data['meta_title'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'fields'           => json_encode($data['fields'] ?? []),
        ]);

        return $stmt->rowCount() > 0;
    }

    // ── Private ──────────────────────────────────────────────────────

    /**
     * Resolve revision data — revision 0 means "current" state.
     */
    private function resolveRevisionData(int $nodeId, int $revision): ?array
    {
        if ($revision === 0) {
            return $this->getCurrentState($nodeId);
        }

        $rev = $this->getRevision($nodeId, $revision);
        return $rev ? $rev['data'] : null;
    }

    /**
     * Compute a simple line-by-line diff.
     *
     * @return list<array{type: string, content: string}>
     *   type: 'same', 'add', 'remove'
     */
    private function computeLineDiff(string $from, string $to): array
    {
        $fromLines = explode("\n", $from);
        $toLines   = explode("\n", $to);

        // Use simple LCS-based diff
        $diff = [];
        $matrix = [];
        $fromCount = count($fromLines);
        $toCount   = count($toLines);

        // Build LCS matrix
        for ($i = 0; $i <= $fromCount; $i++) {
            for ($j = 0; $j <= $toCount; $j++) {
                if ($i === 0 || $j === 0) {
                    $matrix[$i][$j] = 0;
                } elseif ($fromLines[$i - 1] === $toLines[$j - 1]) {
                    $matrix[$i][$j] = $matrix[$i - 1][$j - 1] + 1;
                } else {
                    $matrix[$i][$j] = max($matrix[$i - 1][$j], $matrix[$i][$j - 1]);
                }
            }
        }

        // Backtrack to build diff
        $i = $fromCount;
        $j = $toCount;
        $result = [];

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $fromLines[$i - 1] === $toLines[$j - 1]) {
                array_unshift($result, ['type' => 'same', 'content' => $fromLines[$i - 1]]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $matrix[$i][$j - 1] >= $matrix[$i - 1][$j])) {
                array_unshift($result, ['type' => 'add', 'content' => $toLines[$j - 1]]);
                $j--;
            } else {
                array_unshift($result, ['type' => 'remove', 'content' => $fromLines[$i - 1]]);
                $i--;
            }
        }

        return $result;
    }
}
