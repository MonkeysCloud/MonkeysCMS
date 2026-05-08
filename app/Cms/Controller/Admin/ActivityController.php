<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Log\ActivityLogger;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * ActivityController — Admin UI for viewing the activity audit log.
 */
#[RoutePrefix('/admin/activity')]
final class ActivityController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly ActivityLogger $activityLogger,
        private readonly PDO $pdo,
    ) {}

    #[Route('GET', '/', name: 'admin::activity.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));

        $filters = array_filter([
            'action'      => $params['action'] ?? null,
            'entity_type' => $params['entity_type'] ?? null,
            'user_id'     => !empty($params['user_id']) ? (int) $params['user_id'] : null,
            'date_from'   => $params['date_from'] ?? null,
            'date_to'     => $params['date_to'] ?? null,
            'search'      => trim($params['search'] ?? ''),
        ], fn($v) => $v !== null && $v !== '' && $v !== 0);

        $result = $this->activityLogger->query($page, 50, $filters);
        $filterOptions = $this->activityLogger->getFilterOptions();

        // Load users for filter dropdown
        $users = $this->pdo->query('SELECT id, name FROM cms_users ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC);

        return Response::html($this->renderer->render('admin::activity.index', [
            'title'         => 'Activity Log',
            'items'         => $result['items'],
            'total'         => $result['total'],
            'page'          => $result['page'],
            'pages'         => $result['pages'],
            'filters'       => $filters,
            'filterOptions' => $filterOptions,
            'users'         => $users,
        ]));
    }

    #[Route('POST', '/cleanup', name: 'admin::activity.cleanup')]
    public function cleanup(ServerRequestInterface $request): Response
    {
        $body = $request->getParsedBody() ?? [];
        $days = max(1, (int) ($body['days'] ?? 90));

        $deleted = $this->activityLogger->cleanup($days);

        $session = $request->getAttribute('session');
        $session?->flash('flash_success', "Cleaned up {$deleted} log entries older than {$days} days.");

        return Response::redirect('/admin/activity');
    }
}
