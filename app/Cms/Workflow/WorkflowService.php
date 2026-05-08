<?php

declare(strict_types=1);

namespace App\Cms\Workflow;

use App\Cms\Content\ContentStatus;
use PDO;

/**
 * WorkflowService — Editorial workflow state machine and transition log.
 *
 * Manages content moderation: submit for review, approve/reject,
 * assign reviewers, custom statuses with order/behavior, and transition logging.
 *
 * Status definitions are stored in `workflow_statuses` table,
 * transition rules per content type in `workflow_config.allowed_transitions`.
 */
final class WorkflowService
{
    /** Fallback transitions when no per-type config exists */
    private const DEFAULT_TRANSITIONS = [
        'draft'        => ['needs_review', 'published', 'archived', 'scheduled'],
        'needs_review' => ['in_review', 'draft', 'published'],
        'in_review'    => ['published', 'draft', 'needs_review'],
        'published'    => ['draft', 'archived'],
        'archived'     => ['draft'],
        'scheduled'    => ['draft', 'published'],
    ];

    /** @var array<string,array>|null Cached statuses */
    private ?array $statusCache = null;

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Status Management ────────────────────────────────────────────

    /**
     * Get all workflow statuses ordered by weight.
     *
     * @return list<array{machine_name: string, label: string, color: string, icon: string, weight: int, is_system: bool, is_published: bool, is_review: bool, is_default: bool}>
     */
    public function getStatuses(): array
    {
        if ($this->statusCache !== null) {
            return array_values($this->statusCache);
        }

        try {
            $rows = $this->pdo->query('SELECT * FROM workflow_statuses ORDER BY weight ASC, id ASC')
                ->fetchAll(PDO::FETCH_ASSOC);

            $this->statusCache = [];
            foreach ($rows as $row) {
                $this->statusCache[$row['machine_name']] = $row;
            }

            return $rows;
        } catch (\Throwable) {
            // Table doesn't exist — return built-in defaults
            return $this->getBuiltinStatuses();
        }
    }

    /**
     * Get a single status definition.
     */
    public function getStatus(string $machineName): ?array
    {
        $statuses = $this->getStatuses();
        foreach ($statuses as $s) {
            if ($s['machine_name'] === $machineName) return $s;
        }
        return null;
    }

    /**
     * Create a new custom status.
     *
     * @return string|true  True on success, error message string on failure.
     */
    public function createStatus(array $data): string|true
    {
        try {
            // Auto-calculate weight: last + 10
            $maxWeight = 0;
            foreach ($this->getStatuses() as $s) {
                $maxWeight = max($maxWeight, (int) ($s['weight'] ?? 0));
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO workflow_statuses (machine_name, label, color, icon, weight, is_system, is_published, is_review, is_default)
                 VALUES (:name, :label, :color, :icon, :weight, 0, :is_published, :is_review, 0)"
            );
            $stmt->execute([
                'name'         => $data['machine_name'],
                'label'        => $data['label'],
                'color'        => $data['color'] ?? '#94a3b8',
                'icon'         => $data['icon'] ?? 'circle',
                'weight'       => (int) ($data['weight'] ?? ($maxWeight + 10)),
                'is_published' => (int) ($data['is_published'] ?? false),
                'is_review'    => (int) ($data['is_review'] ?? false),
            ]);
            $this->statusCache = null;
            return true;
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '1146') || str_contains($msg, 'doesn\'t exist')) {
                return 'The workflow_statuses table does not exist. Please run the editorial_workflow migration first.';
            }
            if (str_contains($msg, 'Duplicate')) {
                return "A status with machine name '{$data['machine_name']}' already exists.";
            }
            return 'Failed to create status: ' . $msg;
        }
    }

    /**
     * Update a status definition.
     */
    public function updateStatus(string $machineName, array $data): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE workflow_statuses SET label = :label, color = :color, icon = :icon,
                 weight = :weight, is_published = :is_published, is_review = :is_review
                 WHERE machine_name = :name"
            );
            $stmt->execute([
                'name'         => $machineName,
                'label'        => $data['label'],
                'color'        => $data['color'] ?? '#94a3b8',
                'icon'         => $data['icon'] ?? 'circle',
                'weight'       => (int) ($data['weight'] ?? 0),
                'is_published' => (int) ($data['is_published'] ?? false),
                'is_review'    => (int) ($data['is_review'] ?? false),
            ]);
            $this->statusCache = null;
            return $stmt->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Delete a custom status (only non-system).
     */
    public function deleteStatus(string $machineName): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM workflow_statuses WHERE machine_name = :name AND is_system = 0"
            );
            $stmt->execute(['name' => $machineName]);
            $this->statusCache = null;
            return $stmt->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Reorder statuses by weight.
     *
     * @param list<string> $orderedNames  Machine names in desired order
     */
    public function reorderStatuses(array $orderedNames): void
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE workflow_statuses SET weight = :w WHERE machine_name = :name');
            foreach ($orderedNames as $i => $name) {
                $stmt->execute(['name' => $name, 'w' => $i * 10]);
            }
            $this->statusCache = null;
        } catch (\Throwable) {
            // ignore
        }
    }

    // ── Transition Logic ─────────────────────────────────────────────

    /**
     * Get allowed target statuses from a given status.
     *
     * @return list<string>
     */
    public function getAllowedTransitions(string $fromStatus, ?string $contentType = null): array
    {
        $config = $contentType ? $this->getConfig($contentType) : null;

        if ($config && !empty($config['allowed_transitions'])) {
            $transitions = json_decode($config['allowed_transitions'], true) ?: [];
            if (!empty($transitions) && isset($transitions[$fromStatus])) {
                return $transitions[$fromStatus];
            }
        }

        return self::DEFAULT_TRANSITIONS[$fromStatus] ?? [];
    }

    /**
     * Check if a transition is allowed for a content type.
     */
    public function canTransition(string $fromStatus, string $toStatus, ?string $contentType = null): bool
    {
        return in_array($toStatus, $this->getAllowedTransitions($fromStatus, $contentType), true);
    }

    /**
     * Execute a status transition.
     */
    public function transition(
        int $nodeId,
        string $fromStatus,
        string $toStatus,
        ?int $userId = null,
        ?int $assigneeId = null,
        ?string $comment = null,
    ): bool {
        $contentType = $this->getNodeContentType($nodeId);

        if (!$this->canTransition($fromStatus, $toStatus, $contentType)) {
            return false;
        }

        // Check if the target status is a "published" type
        $targetDef = $this->getStatus($toStatus);
        $publishedAt = null;
        if ($targetDef && !empty($targetDef['is_published'])) {
            $publishedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE nodes SET status = :status, published_at = :published_at, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'id'           => $nodeId,
            'status'       => $toStatus,
            'published_at' => $publishedAt,
        ]);

        $this->logTransition($nodeId, $fromStatus, $toStatus, $userId, $assigneeId, $comment);

        return true;
    }

    /**
     * Submit content for review (author → editor).
     */
    public function submitForReview(int $nodeId, ?int $userId = null, ?string $comment = null): bool
    {
        $currentStatus = $this->getNodeStatus($nodeId);
        return $this->transition($nodeId, $currentStatus, 'needs_review', $userId, comment: $comment);
    }

    /**
     * Claim a review (editor picks up the item).
     */
    public function claimReview(int $nodeId, int $reviewerId, ?string $comment = null): bool
    {
        $currentStatus = $this->getNodeStatus($nodeId);
        return $this->transition($nodeId, $currentStatus, 'in_review', $reviewerId, $reviewerId, $comment ?? 'Claimed for review');
    }

    /**
     * Approve and publish content.
     */
    public function approve(int $nodeId, int $reviewerId, ?string $comment = null): bool
    {
        $currentStatus = $this->getNodeStatus($nodeId);
        return $this->transition($nodeId, $currentStatus, 'published', $reviewerId, comment: $comment ?? 'Approved');
    }

    /**
     * Reject/send back to draft with feedback.
     */
    public function reject(int $nodeId, int $reviewerId, ?string $comment = null): bool
    {
        $currentStatus = $this->getNodeStatus($nodeId);
        return $this->transition($nodeId, $currentStatus, 'draft', $reviewerId, comment: $comment ?? 'Rejected');
    }

    // ── Transition History ───────────────────────────────────────────

    /**
     * Get transition history for a node.
     */
    public function getHistory(int $nodeId, int $limit = 25): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT t.*, u.name AS user_name, a.name AS assignee_name
                 FROM workflow_transitions t
                 LEFT JOIN cms_users u ON t.user_id = u.id
                 LEFT JOIN cms_users a ON t.assignee_id = a.id
                 WHERE t.node_id = :node_id
                 ORDER BY t.created_at DESC
                 LIMIT :limit"
            );
            $stmt->bindValue('node_id', $nodeId, PDO::PARAM_INT);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Review Queue ─────────────────────────────────────────────────

    /**
     * Get items pending review, optionally filtered by content type.
     */
    public function getReviewQueue(?string $contentType = null, int $page = 1, int $perPage = 20): array
    {
        // Get all review-type statuses
        $reviewStatuses = [];
        foreach ($this->getStatuses() as $s) {
            if (!empty($s['is_review'])) {
                $reviewStatuses[] = $s['machine_name'];
            }
        }

        if (empty($reviewStatuses)) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'pages' => 0];
        }

        try {
            $placeholders = implode(',', array_map(fn($i) => ":s{$i}", array_keys($reviewStatuses)));
            $where = "n.deleted_at IS NULL AND n.status IN ({$placeholders})";
            $params = [];
            foreach ($reviewStatuses as $i => $s) {
                $params["s{$i}"] = $s;
            }

            if ($contentType) {
                $where .= ' AND n.content_type = :type';
                $params['type'] = $contentType;
            }

            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM nodes n WHERE {$where}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $dataStmt = $this->pdo->prepare(
                "SELECT n.*, u.name AS author_name
                 FROM nodes n
                 LEFT JOIN cms_users u ON n.author_id = u.id
                 WHERE {$where}
                 ORDER BY n.updated_at DESC
                 LIMIT :lim OFFSET :off"
            );
            foreach ($params as $k => $v) {
                $dataStmt->bindValue($k, $v);
            }
            $dataStmt->bindValue('lim', $perPage, PDO::PARAM_INT);
            $dataStmt->bindValue('off', $offset, PDO::PARAM_INT);
            $dataStmt->execute();

            return [
                'items'   => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
                'total'   => $total,
                'page'    => $page,
                'pages'   => (int) ceil($total / $perPage),
            ];
        } catch (\Throwable) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'pages' => 0];
        }
    }

    /**
     * Count items pending review (for sidebar badge).
     */
    public function countPending(): int
    {
        $reviewStatuses = [];
        foreach ($this->getStatuses() as $s) {
            if (!empty($s['is_review'])) {
                $reviewStatuses[] = "'" . addslashes($s['machine_name']) . "'";
            }
        }

        if (empty($reviewStatuses)) return 0;

        try {
            $list = implode(',', $reviewStatuses);
            $stmt = $this->pdo->query(
                "SELECT COUNT(*) FROM nodes WHERE deleted_at IS NULL AND status IN ({$list})"
            );
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    // ── Workflow Config ──────────────────────────────────────────────

    /**
     * Get workflow config for a content type.
     */
    public function getConfig(string $contentType): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM workflow_config WHERE content_type = :type LIMIT 1'
            );
            $stmt->execute(['type' => $contentType]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Save workflow config for a content type (including custom transition map).
     */
    public function saveConfig(string $contentType, array $data): void
    {
        try {
            $transitions = $data['allowed_transitions'] ?? null;
            $transitionsJson = is_array($transitions) ? json_encode($transitions) : '{}';

            $stmt = $this->pdo->prepare(
                "INSERT INTO workflow_config (content_type, require_review, allowed_transitions, reviewer_roles, notify_on_submit, updated_at)
                 VALUES (:type, :require, :trans, :roles, :notify, NOW())
                 ON DUPLICATE KEY UPDATE require_review = VALUES(require_review), allowed_transitions = VALUES(allowed_transitions),
                 reviewer_roles = VALUES(reviewer_roles), notify_on_submit = VALUES(notify_on_submit), updated_at = NOW()"
            );
            $stmt->execute([
                'type'    => $contentType,
                'require' => (int) ($data['require_review'] ?? false),
                'trans'   => $transitionsJson,
                'roles'   => json_encode($data['reviewer_roles'] ?? ['editor', 'admin']),
                'notify'  => (int) ($data['notify_on_submit'] ?? true),
            ]);
        } catch (\Throwable) {
            // Table may not exist yet
        }
    }

    /**
     * Check if a content type requires review before publishing.
     */
    public function requiresReview(string $contentType): bool
    {
        $config = $this->getConfig($contentType);
        return (bool) ($config['require_review'] ?? false);
    }

    /**
     * Get all workflow configs.
     */
    public function getAllConfigs(): array
    {
        try {
            return $this->pdo->query('SELECT * FROM workflow_config ORDER BY content_type')
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Private ──────────────────────────────────────────────────────

    private function getNodeStatus(int $nodeId): string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM nodes WHERE id = :id');
        $stmt->execute(['id' => $nodeId]);
        return $stmt->fetchColumn() ?: 'draft';
    }

    private function getNodeContentType(int $nodeId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT content_type FROM nodes WHERE id = :id');
        $stmt->execute(['id' => $nodeId]);
        return $stmt->fetchColumn() ?: null;
    }

    private function logTransition(
        int $nodeId,
        string $from,
        string $to,
        ?int $userId,
        ?int $assigneeId,
        ?string $comment,
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO workflow_transitions (node_id, from_status, to_status, user_id, assignee_id, comment, created_at)
                 VALUES (:node_id, :from, :to, :user_id, :assignee_id, :comment, NOW())"
            );
            $stmt->execute([
                'node_id'     => $nodeId,
                'from'        => $from,
                'to'          => $to,
                'user_id'     => $userId,
                'assignee_id' => $assigneeId,
                'comment'     => $comment,
            ]);
        } catch (\Throwable) {
            // Silently ignore if table doesn't exist
        }
    }

    /**
     * Fallback built-in statuses when table doesn't exist.
     */
    private function getBuiltinStatuses(): array
    {
        return [
            ['machine_name' => 'draft',        'label' => 'Draft',        'color' => '#fbbf24', 'icon' => 'pencil-line',  'weight' => 0,  'is_system' => true, 'is_published' => false, 'is_review' => false, 'is_default' => true],
            ['machine_name' => 'needs_review', 'label' => 'Needs Review', 'color' => '#f97316', 'icon' => 'send',         'weight' => 10, 'is_system' => true, 'is_published' => false, 'is_review' => true,  'is_default' => false],
            ['machine_name' => 'in_review',    'label' => 'In Review',    'color' => '#3b82f6', 'icon' => 'eye',          'weight' => 20, 'is_system' => true, 'is_published' => false, 'is_review' => true,  'is_default' => false],
            ['machine_name' => 'published',    'label' => 'Published',    'color' => '#22c55e', 'icon' => 'check-circle', 'weight' => 30, 'is_system' => true, 'is_published' => true,  'is_review' => false, 'is_default' => false],
            ['machine_name' => 'archived',     'label' => 'Archived',     'color' => '#64748b', 'icon' => 'archive',      'weight' => 40, 'is_system' => true, 'is_published' => false, 'is_review' => false, 'is_default' => false],
            ['machine_name' => 'scheduled',    'label' => 'Scheduled',    'color' => '#8b5cf6', 'icon' => 'clock',        'weight' => 50, 'is_system' => true, 'is_published' => false, 'is_review' => false, 'is_default' => false],
        ];
    }
}
