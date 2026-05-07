<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Log\ActivityLogger;
use App\Cms\Webhook\WebhookEntity;
use App\Cms\Webhook\WebhookService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * WebhookController — Admin CRUD + delivery log + testing for outbound webhooks.
 *
 * All mutating endpoints return JSON (MonkeysJS http pattern).
 */
#[RoutePrefix('/admin/webhooks')]
final class WebhookController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly WebhookService $webhooks,
        private readonly ActivityLogger $activity,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // Pages (HTML)
    // ═══════════════════════════════════════════════════════════════════════

    #[Route('GET', '/', name: 'admin::webhooks.index')]
    public function index(): Response
    {
        $items = $this->webhooks->findAll();
        $events = WebhookService::availableEvents();

        return Response::html($this->renderer->render('webhooks.index', [
            'title'    => 'Webhooks',
            'webhooks' => $items,
            'events'   => $events,
        ]));
    }

    #[Route('GET', '/create', name: 'admin::webhooks.create')]
    public function create(): Response
    {
        return Response::html($this->renderer->render('webhooks.form', [
            'title'   => 'Create Webhook',
            'webhook' => new WebhookEntity(),
            'events'  => WebhookService::availableEvents(),
            'isNew'   => true,
        ]));
    }

    #[Route('GET', '/{id:\d+}', name: 'admin::webhooks.edit')]
    public function edit(ServerRequestInterface $request, string $id): Response
    {
        $entity = $this->webhooks->findOrFail((int) $id);
        $stats  = $this->webhooks->getStats((int) $id);
        $page   = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
        $logs   = $this->webhooks->getLog((int) $id, $page, 15);

        return Response::html($this->renderer->render('webhooks.form', [
            'title'   => 'Edit: ' . $entity->name,
            'webhook' => $entity,
            'events'  => WebhookService::availableEvents(),
            'stats'   => $stats,
            'logs'    => $logs,
            'isNew'   => false,
        ]));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // JSON API — CRUD
    // ═══════════════════════════════════════════════════════════════════════

    #[Route('POST', '/', name: 'admin::webhooks.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $data = $this->parseBody($request);

        try {
            $entity = $this->hydrateFromRequest(new WebhookEntity(), $data);

            if (empty($entity->name)) {
                return Response::json(['success' => false, 'error' => 'Webhook name is required.'], 422);
            }

            if (empty($entity->url)) {
                return Response::json(['success' => false, 'error' => 'Webhook URL is required.'], 422);
            }

            if (empty($entity->events)) {
                return Response::json(['success' => false, 'error' => 'At least one event must be selected.'], 422);
            }

            $entity->created_by = $this->getUserId($request);
            $entity = $this->webhooks->persist($entity);

            $this->logActivity($request, 'created', 'webhook', $entity->id, $entity->name);

            return Response::json(['success' => true, 'id' => $entity->id], 201);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('PUT', '/{id:\d+}', name: 'admin::webhooks.update')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        $data = $this->parseBody($request);

        try {
            $entity = $this->webhooks->findOrFail((int) $id);
            $entity = $this->hydrateFromRequest($entity, $data);

            if (empty($entity->name)) {
                return Response::json(['success' => false, 'error' => 'Webhook name is required.'], 422);
            }

            $entity = $this->webhooks->persist($entity);

            $this->logActivity($request, 'updated', 'webhook', $entity->id, $entity->name);

            return Response::json(['success' => true, 'id' => $entity->id]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('DELETE', '/{id:\d+}', name: 'admin::webhooks.delete')]
    public function destroy(ServerRequestInterface $request, string $id): Response
    {
        try {
            $entity = $this->webhooks->find((int) $id);
            $name = $entity?->name ?? "#{$id}";

            $this->webhooks->delete((int) $id);

            $this->logActivity($request, 'deleted', 'webhook', $id, $name);

            return Response::json(['success' => true]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('POST', '/{id:\d+}/toggle', name: 'admin::webhooks.toggle')]
    public function toggle(ServerRequestInterface $request, string $id): Response
    {
        try {
            $this->webhooks->toggleActive((int) $id);
            $entity = $this->webhooks->find((int) $id);
            $status = $entity?->is_active ? 'enabled' : 'disabled';

            $this->logActivity($request, $status, 'webhook', $id, $entity?->name ?? "#{$id}");

            return Response::json(['success' => true, 'is_active' => $entity?->is_active]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('POST', '/{id:\d+}/test', name: 'admin::webhooks.test')]
    public function test(ServerRequestInterface $request, string $id): Response
    {
        try {
            $result = $this->webhooks->test((int) $id);
            return Response::json([
                'success'       => $result['status'] === 'delivered',
                'status'        => $result['status'],
                'response_code' => $result['response_code'],
                'duration_ms'   => $result['duration_ms'],
                'attempts'      => $result['attempts'],
            ]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Internals
    // ═══════════════════════════════════════════════════════════════════════

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

    private function hydrateFromRequest(WebhookEntity $entity, array $data): WebhookEntity
    {
        if (isset($data['name']))      $entity->name      = trim($data['name']);
        if (isset($data['url']))       $entity->url       = trim($data['url']);
        if (isset($data['events']))    $entity->events    = (array) $data['events'];
        if (isset($data['secret']))    $entity->secret    = trim($data['secret']);
        if (isset($data['is_active'])) $entity->is_active = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);

        return $entity;
    }

    private function getUserId(ServerRequestInterface $request): ?int
    {
        $session = $request->getAttribute('session');
        return $session?->get('cms_user_id');
    }

    private function logActivity(ServerRequestInterface $request, string $action, string $type, string|int|null $id, ?string $label): void
    {
        $this->activity->setContext($request);
        $this->activity->log($action, $type, $id, $label);
    }
}
