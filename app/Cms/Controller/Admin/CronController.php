<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Cron\CronService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CronController — Admin dashboard for monitoring and managing scheduled tasks.
 *
 * Lists all registered MonkeysLegion Schedule tasks, shows execution history,
 * and provides a "Run Now" button for on-demand execution.
 */
#[RoutePrefix('/admin/cron')]
final class CronController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly CronService $cronService,
    ) {}

    /**
     * GET /admin/cron
     * Cron Dashboard — list all tasks with status and history.
     */
    #[Route('GET', '/', name: 'admin::cron.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $query = $request->getQueryParams();
        $filterTask = $query['task'] ?? null;
        $filterStatus = $query['status'] ?? null;
        $page = max(1, (int) ($query['page'] ?? 1));

        $tasks = $this->cronService->listTasks();
        $stats = $this->cronService->getTaskStats();
        $history = $this->cronService->getHistory($page, 20, $filterTask, $filterStatus);

        // Enrich tasks with stats
        foreach ($tasks as &$task) {
            $task['stats'] = $stats[$task['id']] ?? null;
        }
        unset($task);

        return Response::html($this->renderer->render('admin::cron.index', [
            'title'        => 'Cron Dashboard',
            'tasks'        => $tasks,
            'history'      => $history,
            'filterTask'   => $filterTask,
            'filterStatus' => $filterStatus,
            'page'         => $page,
        ]));
    }

    /**
     * POST /admin/cron/{taskId}/run
     * Execute a task immediately (AJAX endpoint).
     */
    #[Route('POST', '/{taskId}/run', name: 'admin::cron.run')]
    public function run(ServerRequestInterface $request, string $taskId): Response
    {
        $result = $this->cronService->runTask(urldecode($taskId));

        return Response::json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /admin/cron/clear
     * Clear old log entries.
     */
    #[Route('POST', '/clear', name: 'admin::cron.clear')]
    public function clear(ServerRequestInterface $request): Response
    {
        $body = (array) $request->getParsedBody();
        $days = max(1, (int) ($body['days'] ?? 30));

        $deleted = $this->cronService->clearHistory($days);

        return Response::json([
            'success' => true,
            'deleted' => $deleted,
            'message' => "Cleared {$deleted} log entries older than {$days} days.",
        ]);
    }
}
