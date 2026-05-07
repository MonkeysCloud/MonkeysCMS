<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Content\ContentRepository;
use App\Cms\Content\ContentStatus;
use App\Cms\Content\ContentTypeManager;
use App\Cms\Log\ActivityLogger;
use App\Cms\Workflow\WorkflowService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * WorkflowController — Admin dashboard for editorial workflow.
 *
 * Review queue, transition actions, custom status management,
 * per-content-type workflow config, and transition history.
 */
#[RoutePrefix('/admin/workflow')]
final class WorkflowController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly WorkflowService $workflow,
        private readonly ContentRepository $contentRepo,
        private readonly ContentTypeManager $typeManager,
        private readonly PDO $pdo,
        private readonly ActivityLogger $activity,
    ) {}

    // ── Review Queue ─────────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::workflow.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $contentType = $params['type'] ?? null;
        $page = max(1, (int) ($params['page'] ?? 1));

        $queue = $this->workflow->getReviewQueue($contentType, $page);
        $types = $this->typeManager->getEnabled();
        $pendingCount = $this->workflow->countPending();
        $statuses = $this->workflow->getStatuses();

        return Response::html($this->renderer->render('admin::workflow.index', [
            'title'        => 'Editorial Workflow',
            'queue'        => $queue,
            'types'        => $types,
            'activeType'   => $contentType,
            'page'         => $page,
            'pendingCount' => $pendingCount,
            'statuses'     => $statuses,
        ]));
    }

    // ── Transition Actions (AJAX) ────────────────────────────────────

    #[Route('POST', '/{id:\d+}/transition', name: 'admin::workflow.transition')]
    public function transition(ServerRequestInterface $request, string $id): Response
    {
        $body = $this->parseBody($request);
        $toStatus = $body['to_status'] ?? '';
        $comment  = $body['comment'] ?? null;
        $assigneeId = !empty($body['assignee_id']) ? (int) $body['assignee_id'] : null;

        $userId = $this->getSessionUserId($request);
        $node = $this->contentRepo->findOrFail((int) $id);

        $success = $this->workflow->transition(
            (int) $id,
            $node->status,
            $toStatus,
            $userId,
            $assigneeId,
            $comment,
        );

        if (!$success) {
            return Response::json([
                'success' => false,
                'error'   => "Transition from '{$node->status}' to '{$toStatus}' is not allowed.",
            ], 422);
        }

        $this->activity->setContext($request);
        $this->activity->log('updated', 'node', $id, $node->title, [
            'workflow'    => true,
            'from_status' => $node->status,
            'to_status'   => $toStatus,
        ]);

        return Response::json(['success' => true, 'status' => $toStatus]);
    }

    #[Route('POST', '/{id:\d+}/submit-review', name: 'admin::workflow.submit')]
    public function submitForReview(ServerRequestInterface $request, string $id): Response
    {
        $body = $this->parseBody($request);
        $userId = $this->getSessionUserId($request);
        $success = $this->workflow->submitForReview((int) $id, $userId, $body['comment'] ?? null);

        if (!$success) {
            return Response::json(['success' => false, 'error' => 'Cannot submit for review from current status.'], 422);
        }

        return Response::json(['success' => true, 'status' => 'needs_review']);
    }

    #[Route('POST', '/{id:\d+}/claim', name: 'admin::workflow.claim')]
    public function claim(ServerRequestInterface $request, string $id): Response
    {
        $userId = $this->getSessionUserId($request);
        $success = $this->workflow->claimReview((int) $id, $userId);

        return Response::json(['success' => $success, 'status' => $success ? 'in_review' : null]);
    }

    #[Route('POST', '/{id:\d+}/approve', name: 'admin::workflow.approve')]
    public function approve(ServerRequestInterface $request, string $id): Response
    {
        $body = $this->parseBody($request);
        $userId = $this->getSessionUserId($request);
        $success = $this->workflow->approve((int) $id, $userId, $body['comment'] ?? null);

        $this->activity->setContext($request);
        $this->activity->log('published', 'node', $id, null, ['workflow' => true]);

        return Response::json(['success' => $success, 'status' => $success ? 'published' : null]);
    }

    #[Route('POST', '/{id:\d+}/reject', name: 'admin::workflow.reject')]
    public function reject(ServerRequestInterface $request, string $id): Response
    {
        $body = $this->parseBody($request);
        $userId = $this->getSessionUserId($request);
        $success = $this->workflow->reject((int) $id, $userId, $body['comment'] ?? null);

        return Response::json(['success' => $success, 'status' => $success ? 'draft' : null]);
    }

    // ── Transition History (AJAX) ────────────────────────────────────

    #[Route('GET', '/{id:\d+}/history', name: 'admin::workflow.history')]
    public function history(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->findOrFail((int) $id);
        $history = $this->workflow->getHistory((int) $id);

        return Response::json([
            'node_id' => (int) $id,
            'title'   => $node->title,
            'status'  => $node->status,
            'history' => $history,
        ]);
    }

    // ── Workflow Settings ────────────────────────────────────────────

    #[Route('GET', '/settings', name: 'admin::workflow.settings')]
    public function settings(ServerRequestInterface $request): Response
    {
        $types = $this->typeManager->getEnabled();
        $configs = $this->workflow->getAllConfigs();
        $statuses = $this->workflow->getStatuses();

        // Index configs by content type
        $configMap = [];
        foreach ($configs as $c) {
            $configMap[$c['content_type']] = $c;
        }

        return Response::html($this->renderer->render('admin::workflow.settings', [
            'title'     => 'Workflow Settings',
            'types'     => $types,
            'configMap' => $configMap,
            'statuses'  => $statuses,
        ]));
    }

    #[Route('POST', '/settings', name: 'admin::workflow.settings.save')]
    public function saveSettings(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $configs = $body['workflow'] ?? [];

        foreach ($configs as $contentType => $data) {
            $this->workflow->saveConfig($contentType, [
                'require_review'      => filter_var($data['require_review'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'allowed_transitions' => $data['allowed_transitions'] ?? null,
                'reviewer_roles'      => $data['reviewer_roles'] ?? ['editor', 'admin'],
                'notify_on_submit'    => filter_var($data['notify_on_submit'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        return Response::json(['success' => true]);
    }

    // ── Status CRUD (AJAX) ──────────────────────────────────────────

    #[Route('POST', '/statuses', name: 'admin::workflow.status.create')]
    public function createStatus(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);

        if (empty($body['machine_name']) || empty($body['label'])) {
            return Response::json(['success' => false, 'error' => 'Name and label are required.'], 422);
        }

        $result = $this->workflow->createStatus($body);

        if ($result === true) {
            return Response::json(['success' => true]);
        }

        return Response::json(['success' => false, 'error' => $result], 422);
    }

    #[Route('POST', '/statuses/{name}/update', name: 'admin::workflow.status.update')]
    public function updateStatus(ServerRequestInterface $request, string $name): Response
    {
        $body = $this->parseBody($request);
        $success = $this->workflow->updateStatus($name, $body);

        return Response::json(['success' => $success]);
    }

    #[Route('POST', '/statuses/{name}/delete', name: 'admin::workflow.status.delete')]
    public function deleteStatus(ServerRequestInterface $request, string $name): Response
    {
        $success = $this->workflow->deleteStatus($name);

        return Response::json(['success' => $success]);
    }

    #[Route('POST', '/statuses/reorder', name: 'admin::workflow.status.reorder')]
    public function reorderStatuses(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $order = $body['order'] ?? [];

        if (!empty($order)) {
            $this->workflow->reorderStatuses($order);
        }

        return Response::json(['success' => true]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody() ?? [];
        if (empty($body)) {
            $stream = $request->getBody();
            $stream->rewind();
            $decoded = json_decode($stream->getContents(), true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
        return $body;
    }

    private function getSessionUserId(ServerRequestInterface $request): ?int
    {
        $session = $request->getAttribute('session');
        return $session ? ($session->get('cms_user_id') ?? $session->get('user_id') ?? null) : null;
    }
}
