<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Comment\CommentService;
use App\Cms\Log\ActivityLogger;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CommentController — Admin moderation UI for comments.
 */
#[RoutePrefix('/admin/comments')]
final class CommentController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly CommentService $commentService,
        private readonly ActivityLogger $activity,
    ) {}

    // ── Moderation Queue ───────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::comments.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $status = $params['status'] ?? 'all';
        $search = trim($params['search'] ?? '');
        $nodeId = !empty($params['node_id']) ? (int) $params['node_id'] : null;

        $result = $this->commentService->paginate(
            page: $page,
            perPage: 25,
            status: $status !== 'all' ? $status : null,
            nodeId: $nodeId,
            search: $search !== '' ? $search : null,
        );

        $statusCounts = $this->commentService->statusCounts();

        return Response::html($this->renderer->render('admin::comments.index', [
            'title'        => 'Comments',
            'items'        => $result['items'],
            'total'        => $result['total'],
            'page'         => $result['page'],
            'pages'        => $result['pages'],
            'activeStatus' => $status,
            'search'       => $search,
            'nodeId'       => $nodeId,
            'statusCounts' => $statusCounts,
        ]));
    }

    // ── Single Actions ─────────────────────────────────────────────────

    #[Route('POST', '/{id:\d+}/approve', name: 'admin::comments.approve')]
    public function approve(ServerRequestInterface $request, string $id): Response
    {
        $this->commentService->approve((int) $id);

        $this->activity->setContext($request);
        $this->activity->log('updated', 'comment', $id, null, ['status' => 'approved']);

        return $this->redirectBack($request);
    }

    #[Route('POST', '/{id:\d+}/spam', name: 'admin::comments.spam')]
    public function spam(ServerRequestInterface $request, string $id): Response
    {
        $this->commentService->markSpam((int) $id);

        $this->activity->setContext($request);
        $this->activity->log('updated', 'comment', $id, null, ['status' => 'spam']);

        return $this->redirectBack($request);
    }

    #[Route('POST', '/{id:\d+}/trash', name: 'admin::comments.trash')]
    public function trash(ServerRequestInterface $request, string $id): Response
    {
        $this->commentService->trash((int) $id);

        $this->activity->setContext($request);
        $this->activity->log('trashed', 'comment', $id, null);

        return $this->redirectBack($request);
    }

    #[Route('POST', '/{id:\d+}/delete', name: 'admin::comments.delete')]
    public function delete(ServerRequestInterface $request, string $id): Response
    {
        $this->commentService->delete((int) $id);

        $this->activity->setContext($request);
        $this->activity->log('deleted', 'comment', $id, null, ['permanent' => true]);

        return $this->redirectBack($request);
    }

    // ── Bulk Actions ───────────────────────────────────────────────────

    #[Route('POST', '/bulk', name: 'admin::comments.bulk')]
    public function bulk(ServerRequestInterface $request): Response
    {
        $body = $request->getParsedBody() ?? [];
        $action = $body['action'] ?? '';
        $ids = array_map('intval', $body['ids'] ?? []);

        if (empty($ids)) {
            return Response::redirect('/admin/comments');
        }

        match ($action) {
            'approve' => $this->commentService->bulkUpdateStatus($ids, 'approved'),
            'spam'    => $this->commentService->bulkUpdateStatus($ids, 'spam'),
            'trash'   => $this->commentService->bulkUpdateStatus($ids, 'trashed'),
            default   => null,
        };

        $this->activity->setContext($request);
        $this->activity->log('bulk_' . $action, 'comment', null, count($ids) . ' comments', [
            'ids' => $ids,
        ]);

        return $this->redirectBack($request);
    }

    #[Route('POST', '/empty-spam', name: 'admin::comments.empty_spam')]
    public function emptySpam(ServerRequestInterface $request): Response
    {
        $count = $this->commentService->emptyByStatus('spam');

        $this->activity->setContext($request);
        $this->activity->log('deleted', 'comment', null, "{$count} spam comments", ['bulk' => true]);

        return Response::redirect('/admin/comments?status=spam');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function redirectBack(ServerRequestInterface $request): Response
    {
        $referer = $request->getHeaderLine('Referer');
        return Response::redirect($referer ?: '/admin/comments');
    }
}
