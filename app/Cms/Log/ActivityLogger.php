<?php

declare(strict_types=1);

namespace App\Cms\Log;

use MonkeysLegion\DI\Attributes\Singleton;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ActivityLogger — Audit log service for tracking administrative actions.
 *
 * Records all CMS operations (content CRUD, user management, settings changes,
 * login/logout, media uploads, etc.) into the activity_log table for
 * operational visibility and security auditing.
 *
 * Usage:
 *   $logger->log('created', 'node', $nodeId, $title, ['content_type' => 'article']);
 *   $logger->log('login', 'user', $userId, $username);
 *   $logger->log('updated', 'setting', 'site_name', 'Site Name', ['old' => 'Old', 'new' => 'New']);
 */
#[Singleton]
final class ActivityLogger
{
    private ?int $userId = null;
    private ?string $userName = null;
    private ?string $ipAddress = null;
    private ?string $userAgent = null;

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Context ────────────────────────────────────────────────────────

    /**
     * Set the current request context (user, IP, user-agent).
     * Called by middleware or controller before logging.
     */
    public function setContext(ServerRequestInterface $request): void
    {
        $session = $request->getAttribute('session');

        if ($session) {
            $this->userId = $session->get('cms_user_id') ?? $session->get('user_id') ?? null;
            $this->userName = $session->get('cms_user_name') ?? $session->get('user_name') ?? null;
        }

        $serverParams = $request->getServerParams();
        $this->ipAddress = $serverParams['REMOTE_ADDR']
            ?? ($request->getHeaderLine('X-Forwarded-For') ?: null);
        $this->userAgent = $request->getHeaderLine('User-Agent') ?: null;
    }

    /**
     * Override context manually (e.g. from CLI commands).
     */
    public function setUser(?int $userId, ?string $userName = null): void
    {
        $this->userId = $userId;
        $this->userName = $userName;
    }

    // ── Logging ────────────────────────────────────────────────────────

    /**
     * Log an activity entry.
     *
     * @param string             $action      created|updated|deleted|published|login|logout|etc.
     * @param string             $entityType  node|user|media|menu|block_type|setting|vocabulary|term|etc.
     * @param string|int|null    $entityId    The entity's primary key
     * @param string|null        $entityLabel Human-readable label (e.g. "My Blog Post")
     * @param array<string,mixed> $details    Additional context (old/new values, changed fields, etc.)
     */
    public function log(
        string $action,
        string $entityType,
        string|int|null $entityId = null,
        ?string $entityLabel = null,
        array $details = [],
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO activity_log
                 (user_id, user_name, action, entity_type, entity_id, entity_label, details, ip_address, user_agent)
                 VALUES (:user_id, :user_name, :action, :entity_type, :entity_id, :entity_label, :details, :ip_address, :user_agent)'
            );

            $stmt->execute([
                'user_id'      => $this->userId,
                'user_name'    => $this->userName ?? $this->resolveUserName(),
                'action'       => $action,
                'entity_type'  => $entityType,
                'entity_id'    => $entityId !== null ? (string) $entityId : null,
                'entity_label' => $entityLabel,
                'details'      => !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'   => $this->ipAddress,
                'user_agent'   => $this->userAgent ? mb_substr($this->userAgent, 0, 500) : null,
            ]);
        } catch (\Throwable) {
            // Never break the main operation for logging failures
        }
    }

    // ── Query API ──────────────────────────────────────────────────────

    /**
     * Fetch recent log entries with optional filters.
     *
     * @param array{
     *     action?: string,
     *     entity_type?: string,
     *     user_id?: int,
     *     date_from?: string,
     *     date_to?: string,
     *     search?: string,
     * } $filters
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public function query(
        int $page = 1,
        int $perPage = 50,
        array $filters = [],
    ): array {
        $where = '1=1';
        $params = [];

        if (!empty($filters['action'])) {
            $where .= ' AND action = :action';
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['entity_type'])) {
            $where .= ' AND entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }

        if (!empty($filters['user_id'])) {
            $where .= ' AND user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where .= ' AND created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where .= ' AND created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $where .= ' AND (entity_label LIKE :search OR details LIKE :search2)';
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search2'] = '%' . $filters['search'] . '%';
        }

        // Count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch
        $offset = ($page - 1) * $perPage;
        $dataStmt = $this->pdo->prepare(
            "SELECT * FROM activity_log WHERE {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $items = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON details
        foreach ($items as &$item) {
            if (!empty($item['details'])) {
                $item['details'] = json_decode($item['details'], true) ?? [];
            } else {
                $item['details'] = [];
            }
        }

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get distinct values for filter dropdowns.
     *
     * @return array{actions: string[], entity_types: string[]}
     */
    public function getFilterOptions(): array
    {
        $actions = $this->pdo->query(
            'SELECT DISTINCT action FROM activity_log ORDER BY action'
        )->fetchAll(PDO::FETCH_COLUMN);

        $entityTypes = $this->pdo->query(
            'SELECT DISTINCT entity_type FROM activity_log ORDER BY entity_type'
        )->fetchAll(PDO::FETCH_COLUMN);

        return [
            'actions'      => $actions ?: [],
            'entity_types' => $entityTypes ?: [],
        ];
    }

    /**
     * Delete entries older than N days.
     */
    public function cleanup(int $daysOld = 90): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        );
        $stmt->execute(['days' => $daysOld]);

        return $stmt->rowCount();
    }

    /**
     * Get total log count (for dashboard stats).
     */
    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM activity_log')->fetchColumn();
    }

    // ── Internals ──────────────────────────────────────────────────────

    private function resolveUserName(): ?string
    {
        if (!$this->userId) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT name FROM cms_users WHERE id = :id');
            $stmt->execute(['id' => $this->userId]);
            $name = $stmt->fetchColumn();
            if ($name) {
                $this->userName = $name;
            }
            return $name ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
