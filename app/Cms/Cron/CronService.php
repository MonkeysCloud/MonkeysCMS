<?php

declare(strict_types=1);

namespace App\Cms\Cron;

use MonkeysLegion\DI\Attributes\Singleton;
use MonkeysLegion\Schedule\Schedule;
use MonkeysLegion\Schedule\Task;
use MonkeysLegion\Schedule\Support\CronParser;
use PDO;

/**
 * CronService — Bridges the MonkeysLegion Schedule package with the CMS admin dashboard.
 *
 * Provides task listing, on-demand execution, and execution history
 * via the cron_log table.
 */
#[Singleton]
final class CronService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Schedule $schedule,
    ) {}

    // ── Task Listing ─────────────────────────────────────────────────

    /**
     * Get all registered scheduled tasks with their metadata.
     *
     * @return list<array{id: string, expression: string, tags: array, next_run: ?string, last_run: ?array}>
     */
    public function listTasks(): array
    {
        $tasks = $this->schedule->getTasks();
        $parser = new CronParser();
        $result = [];

        foreach ($tasks as $task) {
            $lastRun = $this->getLastRun($task->id);

            $result[] = [
                'id'          => $task->id,
                'expression'  => $task->expression,
                'tags'        => $task->tags,
                'metadata'    => $task->metadata,
                'overlapping' => !$task->withoutOverlapping,
                'next_run'    => $this->getNextRun($parser, $task->expression),
                'last_run'    => $lastRun,
            ];
        }

        return $result;
    }

    // ── Execution ────────────────────────────────────────────────────

    /**
     * Execute a task immediately by its ID and log the result.
     */
    public function runTask(string $taskId): array
    {
        $task = $this->schedule->getTask($taskId);
        if (!$task) {
            return ['success' => false, 'error' => "Task '{$taskId}' not found."];
        }

        $logId = $this->logStart($task);
        $startTime = hrtime(true);

        try {
            $result = $task->execute();
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            $output = $this->formatResult($result);
            $this->logFinish($logId, 'success', $durationMs, $output);

            return [
                'success'     => true,
                'duration_ms' => $durationMs,
                'output'      => $output,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $this->logFinish($logId, 'failed', $durationMs, null, $e->getMessage());

            return [
                'success'     => false,
                'duration_ms' => $durationMs,
                'error'       => $e->getMessage(),
            ];
        }
    }

    // ── History ───────────────────────────────────────────────────────

    /**
     * Get paginated execution history.
     */
    public function getHistory(
        int $page = 1,
        int $perPage = 25,
        ?string $taskId = null,
        ?string $status = null,
    ): array {
        try {
            $where = '1=1';
            $params = [];

            if ($taskId) {
                $where .= ' AND task_id = :task_id';
                $params['task_id'] = $taskId;
            }

            if ($status && $status !== 'all') {
                $where .= ' AND status = :status';
                $params['status'] = $status;
            }

            // Count
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM cron_log WHERE {$where}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            // Fetch
            $offset = ($page - 1) * $perPage;
            $dataStmt = $this->pdo->prepare(
                "SELECT * FROM cron_log WHERE {$where} ORDER BY started_at DESC LIMIT :limit OFFSET :offset"
            );
            foreach ($params as $k => $v) {
                $dataStmt->bindValue($k, $v);
            }
            $dataStmt->bindValue('limit', $perPage, PDO::PARAM_INT);
            $dataStmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $dataStmt->execute();

            return [
                'items'   => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
                'total'   => $total,
                'page'    => $page,
                'pages'   => (int) ceil($total / $perPage),
                'perPage' => $perPage,
            ];
        } catch (\Throwable) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'pages' => 0, 'perPage' => $perPage];
        }
    }

    /**
     * Get aggregated stats per task.
     */
    public function getTaskStats(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT task_id,
                        COUNT(*) as total_runs,
                        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                        AVG(duration_ms) as avg_duration,
                        MAX(started_at) as last_run_at
                 FROM cron_log
                 GROUP BY task_id"
            );

            $stats = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $stats[$row['task_id']] = $row;
            }

            return $stats;
        } catch (\Throwable) {
            return []; // Table may not exist yet
        }
    }

    /**
     * Clear old log entries.
     */
    public function clearHistory(int $olderThanDays = 30): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM cron_log WHERE started_at < DATE_SUB(NOW(), INTERVAL :days DAY)"
        );
        $stmt->execute(['days' => $olderThanDays]);

        return $stmt->rowCount();
    }

    // ── Private ──────────────────────────────────────────────────────

    private function getLastRun(string $taskId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM cron_log WHERE task_id = :task_id ORDER BY started_at DESC LIMIT 1'
            );
            $stmt->execute(['task_id' => $taskId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\Throwable) {
            return null; // Table may not exist yet
        }
    }

    private function getNextRun(CronParser $parser, string $expression): ?string
    {
        try {
            $next = $parser->nextRun($expression);
            return $next->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function logStart(Task $task): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO cron_log (task_id, task_name, status, started_at) VALUES (:task_id, :task_name, 'running', NOW())"
        );
        $stmt->execute([
            'task_id'   => $task->id,
            'task_name' => $task->metadata['description'] ?? $task->id,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function logFinish(int $logId, string $status, int $durationMs, ?string $output = null, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE cron_log SET status = :status, duration_ms = :duration, output = :output, error = :error, finished_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'id'       => $logId,
            'status'   => $status,
            'duration' => $durationMs,
            'output'   => $output ? mb_substr($output, 0, 10000) : null,
            'error'    => $error ? mb_substr($error, 0, 10000) : null,
        ]);
    }

    private function formatResult(mixed $result): ?string
    {
        if ($result === null) {
            return null;
        }

        if (is_array($result)) {
            return $result['output'] ?? json_encode($result);
        }

        if (is_string($result)) {
            return $result;
        }

        return (string) $result;
    }
}
