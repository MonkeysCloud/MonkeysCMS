<?php

declare(strict_types=1);

namespace App\Cms\Webhook;

use App\Cms\Content\PaginatedResult;
use MonkeysLegion\DI\Attributes\Singleton;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * WebhookService — CRUD, delivery, and log management for outbound webhooks.
 *
 * Delivers payloads with HMAC-SHA256 signatures, supports retry with
 * exponential backoff, and auto-disables after 10 consecutive failures.
 */
#[Singleton]
final class WebhookService
{
    private const int MAX_FAILURES = 10;
    private const int MAX_ATTEMPTS = 3;
    private const array BACKOFF_SECONDS = [1, 4, 16];

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // CRUD
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @return list<WebhookEntity>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT w.*,
                    (SELECT COUNT(*) FROM webhook_logs wl WHERE wl.webhook_id = w.id) AS log_count,
                    (SELECT COUNT(*) FROM webhook_logs wl WHERE wl.webhook_id = w.id AND wl.status = \'failed\') AS failed_count
             FROM webhooks w
             ORDER BY w.created_at DESC'
        );

        $entities = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $entity = (new WebhookEntity())->hydrate($row);
            $entity->_logCount = (int) ($row['log_count'] ?? 0);
            $entity->_failedCount = (int) ($row['failed_count'] ?? 0);
            $entities[] = $entity;
        }

        return $entities;
    }

    public function find(int $id): ?WebhookEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webhooks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new WebhookEntity())->hydrate($row) : null;
    }

    public function findOrFail(int $id): WebhookEntity
    {
        return $this->find($id) ?? throw new \RuntimeException("Webhook #{$id} not found.");
    }

    public function persist(WebhookEntity $entity): WebhookEntity
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($entity->id !== null) {
            return $this->update($entity, $now);
        }

        return $this->insert($entity, $now);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM webhooks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE webhooks SET is_active = NOT is_active, failure_count = 0, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Dispatch
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Dispatch an event to all subscribed active webhooks.
     *
     * @param string             $event   e.g. "node.created"
     * @param array<string,mixed> $payload Event payload data
     * @return int Number of webhooks notified
     */
    public function dispatch(string $event, array $payload): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM webhooks WHERE is_active = 1'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dispatched = 0;

        foreach ($rows as $row) {
            $webhook = (new WebhookEntity())->hydrate($row);

            if (!$webhook->subscribedTo($event)) {
                continue;
            }

            $this->deliver($webhook, $event, $payload);
            $dispatched++;
        }

        return $dispatched;
    }

    /**
     * Send a test event to a specific webhook.
     */
    public function test(int $id): array
    {
        $webhook = $this->findOrFail($id);

        $payload = [
            'event'     => 'webhook.test',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'data'      => [
                'message' => 'This is a test webhook delivery from MonkeysCMS.',
                'webhook' => ['id' => $webhook->id, 'name' => $webhook->name],
            ],
        ];

        return $this->deliver($webhook, 'webhook.test', $payload);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Delivery
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Deliver a payload to a webhook endpoint with retry logic.
     *
     * @return array{status: string, response_code: ?int, duration_ms: int, attempts: int}
     */
    private function deliver(WebhookEntity $webhook, string $event, array $payload): array
    {
        $fullPayload = [
            'event'      => $event,
            'timestamp'  => (new \DateTimeImmutable())->format('c'),
            'webhook_id' => $webhook->id,
            'data'       => $payload['data'] ?? $payload,
        ];

        $json = json_encode($fullPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $json, $webhook->secret);

        $result = [
            'status'        => 'pending',
            'response_code' => null,
            'response_body' => null,
            'duration_ms'   => 0,
            'attempts'      => 0,
        ];

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $result['attempts'] = $attempt + 1;

            if ($attempt > 0) {
                $backoff = self::BACKOFF_SECONDS[$attempt - 1] ?? 16;
                sleep($backoff);
            }

            $start = hrtime(true);

            try {
                $ch = curl_init($webhook->url);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $json,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'X-Webhook-Event: ' . $event,
                        'X-Webhook-Signature: sha256=' . $signature,
                        'X-Webhook-Id: ' . $webhook->id,
                        'User-Agent: MonkeysCMS/2.0 Webhook',
                    ],
                ]);

                $body = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                $elapsed = (int) ((hrtime(true) - $start) / 1_000_000); // ms

                $result['response_code'] = $httpCode;
                $result['response_body'] = is_string($body) ? mb_substr($body, 0, 2000) : null;
                $result['duration_ms']   = $elapsed;

                if ($httpCode >= 200 && $httpCode < 300) {
                    $result['status'] = 'delivered';
                    $this->recordSuccess($webhook);
                    break;
                }

                // Non-retryable status codes
                if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 429) {
                    $result['status'] = 'failed';
                    $this->recordFailure($webhook);
                    break;
                }

                // Retryable — continue loop
            } catch (\Throwable $e) {
                $elapsed = (int) ((hrtime(true) - $start) / 1_000_000);
                $result['duration_ms']   = $elapsed;
                $result['response_body'] = $e->getMessage();

                $this->logger?->warning('Webhook delivery error', [
                    'webhook_id' => $webhook->id,
                    'event'      => $event,
                    'attempt'    => $attempt + 1,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // If we exhausted all retries without success
        if ($result['status'] === 'pending') {
            $result['status'] = 'failed';
            $this->recordFailure($webhook);
        }

        // Write delivery log
        $this->logDelivery($webhook->id, $event, $fullPayload, $result);

        return $result;
    }

    /**
     * Record successful delivery — reset failure count.
     */
    private function recordSuccess(WebhookEntity $webhook): void
    {
        $this->pdo->prepare(
            'UPDATE webhooks SET last_triggered_at = NOW(), failure_count = 0, updated_at = NOW() WHERE id = :id'
        )->execute(['id' => $webhook->id]);
    }

    /**
     * Record failed delivery — increment failure count, auto-disable at threshold.
     */
    private function recordFailure(WebhookEntity $webhook): void
    {
        $newCount = $webhook->failure_count + 1;

        $sql = 'UPDATE webhooks SET failure_count = :count, last_triggered_at = NOW(), updated_at = NOW()';
        $params = ['id' => $webhook->id, 'count' => $newCount];

        if ($newCount >= self::MAX_FAILURES) {
            $sql .= ', is_active = 0';
            $this->logger?->warning('Webhook auto-disabled after max failures', [
                'webhook_id' => $webhook->id,
                'name'       => $webhook->name,
                'url'        => $webhook->url,
            ]);
        }

        $sql .= ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Delivery Log
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Write a delivery attempt to the log table.
     */
    private function logDelivery(int $webhookId, string $event, array $payload, array $result): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO webhook_logs
                 (webhook_id, event, payload, response_code, response_body, duration_ms, status, attempts)
                 VALUES (:wid, :event, :payload, :code, :body, :ms, :status, :attempts)'
            );
            $stmt->execute([
                'wid'      => $webhookId,
                'event'    => $event,
                'payload'  => json_encode($payload),
                'code'     => $result['response_code'],
                'body'     => $result['response_body'],
                'ms'       => $result['duration_ms'],
                'status'   => $result['status'],
                'attempts' => $result['attempts'],
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to log webhook delivery', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get paginated delivery log for a webhook.
     */
    public function getLog(int $webhookId, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM webhook_logs WHERE webhook_id = :wid');
        $countStmt->execute(['wid' => $webhookId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT * FROM webhook_logs WHERE webhook_id = :wid
             ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('wid', $webhookId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(function (array $row) {
            $row['payload'] = json_decode($row['payload'] ?? '{}', true) ?: [];
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        return new PaginatedResult($items, $total, $page, $perPage);
    }

    /**
     * Get delivery stats for a webhook.
     *
     * @return array{total: int, delivered: int, failed: int, avg_duration_ms: int}
     */
    public function getStats(int $webhookId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                COALESCE(AVG(duration_ms), 0) AS avg_duration_ms
             FROM webhook_logs WHERE webhook_id = :wid"
        );
        $stmt->execute(['wid' => $webhookId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'           => (int) ($row['total'] ?? 0),
            'delivered'       => (int) ($row['delivered'] ?? 0),
            'failed'          => (int) ($row['failed'] ?? 0),
            'avg_duration_ms' => (int) ($row['avg_duration_ms'] ?? 0),
        ];
    }

    /**
     * Delete old log entries.
     */
    public function cleanupLogs(int $daysOld = 30): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM webhook_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        );
        $stmt->execute(['days' => $daysOld]);
        return $stmt->rowCount();
    }

    /**
     * Get the list of all available events that can be subscribed to.
     *
     * @return array<string, string> event => label
     */
    public static function availableEvents(): array
    {
        return [
            'node.created'      => 'Content created',
            'node.updated'      => 'Content updated',
            'node.published'    => 'Content published',
            'node.unpublished'  => 'Content unpublished',
            'node.deleted'      => 'Content deleted',
            'media.uploaded'    => 'Media uploaded',
            'media.deleted'     => 'Media deleted',
            'user.created'      => 'User created',
            'user.updated'      => 'User updated',
            'comment.created'   => 'Comment posted',
            'comment.approved'  => 'Comment approved',
            'form.submitted'    => 'Webform submitted',
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Internals
    // ═══════════════════════════════════════════════════════════════════════

    private function insert(WebhookEntity $entity, string $now): WebhookEntity
    {
        // Generate a secret if not provided
        if (empty($entity->secret)) {
            $entity->secret = bin2hex(random_bytes(32));
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO webhooks
                (name, url, events, secret, is_active, failure_count, created_by, created_at, updated_at)
             VALUES
                (:name, :url, :events, :secret, :active, 0, :created_by, :created_at, :updated_at)'
        );

        $stmt->execute([
            'name'       => $entity->name,
            'url'        => $entity->url,
            'events'     => json_encode($entity->events),
            'secret'     => $entity->secret,
            'active'     => (int) $entity->is_active,
            'created_by' => $entity->created_by,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $entity->id = (int) $this->pdo->lastInsertId();
        $entity->created_at = new \DateTimeImmutable($now);
        $entity->updated_at = new \DateTimeImmutable($now);

        return $entity;
    }

    private function update(WebhookEntity $entity, string $now): WebhookEntity
    {
        $stmt = $this->pdo->prepare(
            'UPDATE webhooks SET
                name = :name, url = :url, events = :events,
                secret = :secret, is_active = :active, updated_at = :now
             WHERE id = :id'
        );

        $stmt->execute([
            'id'     => $entity->id,
            'name'   => $entity->name,
            'url'    => $entity->url,
            'events' => json_encode($entity->events),
            'secret' => $entity->secret,
            'active' => (int) $entity->is_active,
            'now'    => $now,
        ]);

        $entity->updated_at = new \DateTimeImmutable($now);

        return $entity;
    }
}
