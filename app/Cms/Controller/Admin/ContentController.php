<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Content\ContentEntity;
use App\Cms\Content\ContentLockService;
use App\Cms\Plugin\HookManager;
use App\Cms\Content\ContentRepository;
use App\Cms\Content\ContentStatus;
use App\Cms\Content\ContentTypeManager;
use App\Cms\Content\DiffService;
use App\Cms\Content\LockResult;
use App\Cms\Field\Widget\WidgetRegistry;
use App\Cms\I18n\LanguageService;
use App\Cms\I18n\TranslationService;
use App\Cms\Log\ActivityLogger;
use App\Cms\Media\MediaModule;
use App\Cms\Slug\SlugManager;
use App\Cms\Theme\PageAssets;
use App\Cms\Apex\ApexService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * ContentController — Admin UI for content CRUD.
 */
#[RoutePrefix('/admin/content')]
final class ContentController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly ContentRepository $contentRepo,
        private readonly ContentTypeManager $typeManager,
        private readonly WidgetRegistry $widgetRegistry,
        private readonly PDO $pdo,
        private readonly SlugManager $slugManager,
        private readonly PageAssets $pageAssets,
        private readonly ApexService $apex,
        private readonly ActivityLogger $activity,
        private readonly DiffService $diffService,
        private readonly LanguageService $languageService,
        private readonly TranslationService $translationService,
        private readonly ContentLockService $lockService,
        private readonly HookManager $hooks,
        private readonly MediaModule $mediaModule,
    ) {}

    // ── List ────────────────────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::content.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $enabledTypes = $this->typeManager->getEnabled();

        $activeType = $params['type'] ?? null;
        $activeStatus = $params['status'] ?? 'all';
        $page = max(1, (int) ($params['page'] ?? 1));
        $search = trim($params['search'] ?? '');
        $authorId = !empty($params['author']) ? (int) $params['author'] : null;
        $sortBy = $params['sort'] ?? 'updated_at';
        $sortDir = strtoupper($params['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $result = $this->contentRepo->paginate(
            contentType: $activeType,
            status: $activeStatus,
            page: $page,
            perPage: 25,
            orderBy: $sortBy,
            direction: $sortDir,
            search: $search !== '' ? $search : null,
            authorId: $authorId,
        );

        // Load authors for the filter dropdown
        $authors = $this->pdo->query('SELECT id, name FROM cms_users ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC);

        return Response::html($this->renderer->render('admin::content.index', [
            'title'        => 'Content',
            'contentTypes' => $enabledTypes,
            'activeType'   => $activeType,
            'activeStatus' => $activeStatus,
            'nodes'        => $result->items,
            'pagination'   => $result->meta(),
            'statuses'     => ContentStatus::formOptions(),
            'search'       => $search,
            'authorId'     => $authorId,
            'authors'      => $authors,
            'sortBy'       => $sortBy,
            'sortDir'      => $sortDir,
            'enabledLanguages' => $this->languageService->isEnabled() ? $this->languageService->getEnabled() : [],
            'activeLang'       => $params['lang'] ?? null,
        ]));
    }

    // ── Create ──────────────────────────────────────────────────────────

    #[Route('GET', '/create/{type}', name: 'admin::content.create')]
    public function create(ServerRequestInterface $request, string $type): Response
    {
        $ct = $this->typeManager->get($type);
        if (!$ct) {
            return Response::redirect('/admin/content');
        }

        $fields = $this->typeManager->getFieldsFor($type);

        // Attach AI assistant library if enabled for this content type
        $apexConfig = $this->apex->config();
        $ctOverrides = $apexConfig->contentTypeOverrides[$type] ?? [];
        if ($apexConfig->enabled && ($ctOverrides['enabled'] ?? true)) {
            $this->pageAssets->attachLibrary('admin/apex-assistant');
        }

        return Response::html($this->renderer->render('admin::content.form', [
            'title'         => 'Create ' . $ct->label,
            'contentType'   => $ct,
            'fields'        => $fields,
            'fieldValues'   => [],
            'node'          => $this->prepareNewNode($request, $type),
            'isNew'         => true,
            'widgetRegistry' => $this->widgetRegistry,
            'statuses'      => ContentStatus::formOptions(),
            'allTypes'      => $this->typeManager->getEnabled(),
            'authorName'    => $this->getSessionUserName($request),
            'authorId'      => $this->getSessionUserId($request),
            ...$this->getMultilingualViewData(null),
        ]));
    }

    #[Route('POST', '/', name: 'admin::content.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);

        $entity = new ContentEntity();
        $this->hydrateFromRequest($entity, $body);

        // Extract custom field values
        $fieldValues = $body['fields'] ?? [];

        $this->hooks->dispatch('content.before_save', $entity, $fieldValues);
        $this->contentRepo->save($entity, $fieldValues);
        $this->hooks->dispatch('content.after_save', $entity, $fieldValues);

        // Link translation if translate_from was specified
        $translateFrom = (int) ($body['translate_from'] ?? 0);
        if ($translateFrom > 0 && !empty($entity->language) && $entity->id) {
            $this->translationService->link('node', $translateFrom, $entity->language, $entity->id);
        }

        $this->activity->setContext($request);
        $this->activity->log('created', 'node', $entity->id, $entity->title, [
            'content_type' => $entity->content_type,
            'status'       => $entity->status,
        ]);

        return Response::redirect('/admin/content/' . $entity->id . '/edit');
    }

    // ── Edit ────────────────────────────────────────────────────────────

    #[Route('GET', '/{id:\d+}/edit', name: 'admin::content.edit')]
    public function edit(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->findWithFields((int) $id);
        if (!$node) {
            return Response::redirect('/admin/content');
        }

        $ct = $this->typeManager->get($node->content_type);
        $fields = $ct ? $this->typeManager->getFieldsFor($node->content_type) : [];

        // Resolve author name for the form
        $authorName = '';
        $authorId = $node->author_id ?? null;
        if ($authorId) {
            $stmt = $this->pdo->prepare('SELECT name FROM cms_users WHERE id = :id');
            $stmt->execute(['id' => $authorId]);
            $authorName = $stmt->fetchColumn() ?: '';
        }

        // ── Content Locking ─────────────────────────────────────────────
        $lockAcquired = false;
        $lockInfo     = null;
        $currentUserId = $this->getSessionUserId($request);
        $queryParams = $request->getQueryParams();
        $isExplicitlyReleased = ($queryParams['lock'] ?? '') === 'released';

        if ($currentUserId) {
            if (!$isExplicitlyReleased) {
                $lockResult = $this->lockService->acquire((int) $id, $currentUserId);
                $lockAcquired = $lockResult['result'] !== LockResult::LOCKED_BY_OTHER;
                $lockInfo     = $lockResult['lockInfo'];
            } else {
                // If explicitly released, we just check if someone else grabbed it in the meantime
                $lockInfo = $this->lockService->isLocked((int) $id);
                $lockAcquired = false;
            }
        }

        // Attach AI assistant library if enabled for this content type
        $apexConfig = $this->apex->config();
        $ctOverrides = $apexConfig->contentTypeOverrides[$node->content_type] ?? [];
        if ($apexConfig->enabled && ($ctOverrides['enabled'] ?? true)) {
            $this->pageAssets->attachLibrary('admin/apex-assistant');
        }

        // Resolve featured image
        $featuredImageUrl = '';
        $featuredImageAlt = '';
        $featuredImageName = '';
        if ($node->featured_image_id) {
            $fiMedia = $this->mediaModule->find((int) $node->featured_image_id);
            if ($fiMedia) {
                $featuredImageUrl = $fiMedia->url ?: '/uploads/' . $fiMedia->path;
                $featuredImageAlt = $fiMedia->alt ?? '';
                $featuredImageName = $fiMedia->original_name;
            }
        }

        return Response::html($this->renderer->render('admin::content.form', [
            'title'         => 'Edit: ' . $node->title,
            'contentType'   => $ct,
            'fields'        => $fields,
            'fieldValues'   => $node->fields,
            'node'          => $node,
            'isNew'         => false,
            'widgetRegistry' => $this->widgetRegistry,
            'statuses'      => ContentStatus::formOptions(),
            'allTypes'      => $this->typeManager->getEnabled(),
            'authorName'    => $authorName,
            'authorId'      => $authorId,
            'lockAcquired'  => $lockAcquired,
            'lockInfo'      => $lockInfo,
            'featuredImageUrl'  => $featuredImageUrl,
            'featuredImageAlt'  => $featuredImageAlt,
            'featuredImageName' => $featuredImageName,
            ...$this->getMultilingualViewData($node),
        ]));
    }

    #[Route('POST', '/{id:\d+}', name: 'admin::content.update')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->findOrFail((int) $id);
        $body = $this->parseBody($request);

        // Snapshot current state before overwriting
        $userId = $this->getSessionUserId($request);
        $this->diffService->snapshot((int) $id, $userId);

        $this->hydrateFromRequest($node, $body);
        $fieldValues = $body['fields'] ?? [];

        $this->hooks->dispatch('content.before_save', $node, $fieldValues);
        $this->contentRepo->save($node, $fieldValues);
        $this->hooks->dispatch('content.after_save', $node, $fieldValues);

        // Check if the user explicitly wants to keep the lock released
        $lockStatus = $body['_lock_status'] ?? 'held';

        // Renew or release the lock based on intent
        if ($userId) {
            if ($lockStatus === 'released') {
                $this->lockService->release((int) $id, $userId);
            } else {
                $this->lockService->renew((int) $id, $userId);
            }
        }

        $this->activity->setContext($request);
        $this->activity->log('updated', 'node', $node->id, $node->title, [
            'content_type' => $node->content_type,
        ]);

        $url = '/admin/content/' . $node->id . '/edit';
        if ($lockStatus === 'released') {
            $url .= '?lock=released';
        }

        return Response::redirect($url);
    }

    // ── Delete ──────────────────────────────────────────────────────────

    #[Route('POST', '/{id:\d+}/delete', name: 'admin::content.delete')]
    public function delete(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->find((int) $id);
        $this->hooks->dispatch('content.before_delete', $node);
        $this->contentRepo->delete((int) $id);
        $this->hooks->dispatch('content.after_delete', $node);

        $this->activity->setContext($request);
        $this->activity->log('trashed', 'node', $id, $node?->title ?? "#{$id}", [
            'content_type' => $node?->content_type,
        ]);

        return Response::redirect('/admin/content');
    }

    // ── Bulk Operations ─────────────────────────────────────────────────

    #[Route('POST', '/bulk', name: 'admin::content.bulk')]
    public function bulk(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $action = $body['action'] ?? '';
        $ids = array_map('intval', $body['ids'] ?? []);

        $isJson = str_contains($request->getHeaderLine('Content-Type'), 'application/json');

        if (empty($ids)) {
            return $isJson
                ? Response::json(['error' => 'No items selected'], 422)
                : Response::redirect('/admin/content');
        }

        $affected = match ($action) {
            'delete'  => $this->contentRepo->bulkDelete($ids),
            'publish' => $this->contentRepo->bulkUpdateStatus($ids, ContentStatus::PUBLISHED),
            'draft'   => $this->contentRepo->bulkUpdateStatus($ids, ContentStatus::DRAFT),
            'archive' => $this->contentRepo->bulkUpdateStatus($ids, ContentStatus::ARCHIVED),
            default   => 0,
        };

        $this->activity->setContext($request);
        $this->activity->log('bulk_' . $action, 'node', null, count($ids) . ' items', [
            'ids' => $ids,
        ]);

        if ($isJson) {
            return Response::json([
                'message'  => "{$affected} item(s) {$action}ed successfully.",
                'affected' => $affected,
            ]);
        }

        return Response::redirect('/admin/content');
    }

    // ── Trash / Restore ────────────────────────────────────────────────

    #[Route('GET', '/trash', name: 'admin::content.trash')]
    public function trash(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        // Query trashed content
        $stmt = $this->pdo->prepare(
            "SELECT n.*, cu.name AS author_name
             FROM nodes n
             LEFT JOIN cms_users cu ON n.author_id = cu.id
             WHERE n.deleted_at IS NOT NULL
             ORDER BY n.deleted_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $this->pdo->query("SELECT COUNT(*) FROM nodes WHERE deleted_at IS NOT NULL");
        $total = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($total / $perPage);

        return Response::html($this->renderer->render('admin::content.trash', [
            'title'      => 'Trash',
            'nodes'      => $nodes,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]));
    }

    #[Route('POST', '/{id:\d+}/restore', name: 'admin::content.restore')]
    public function restore(ServerRequestInterface $request, string $id): Response
    {
        $this->pdo->prepare('UPDATE nodes SET deleted_at = NULL, status = :status WHERE id = :id')
            ->execute(['id' => (int) $id, 'status' => 'draft']);

        $this->activity->setContext($request);
        $this->activity->log('restored', 'node', $id, null);

        return Response::redirect('/admin/content/trash');
    }

    #[Route('POST', '/{id:\d+}/destroy', name: 'admin::content.destroy')]
    public function destroy(ServerRequestInterface $request, string $id): Response
    {
        $this->contentRepo->forceDelete((int) $id);

        $this->activity->setContext($request);
        $this->activity->log('deleted', 'node', $id, null, ['permanent' => true]);

        return Response::redirect('/admin/content/trash');
    }

    #[Route('POST', '/empty-trash', name: 'admin::content.empty_trash')]
    public function emptyTrash(ServerRequestInterface $request): Response
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM nodes WHERE deleted_at IS NOT NULL')->fetchColumn();
        $this->pdo->exec("DELETE FROM nodes WHERE deleted_at IS NOT NULL");

        $this->activity->setContext($request);
        $this->activity->log('deleted', 'node', null, "{$count} trashed items", ['permanent' => true, 'bulk' => true]);

        return Response::redirect('/admin/content/trash');
    }

    // ── Status Change (AJAX) ────────────────────────────────────────────

    #[Route('POST', '/{id:\d+}/status', name: 'admin::content.status')]
    public function changeStatus(ServerRequestInterface $request, string $id): Response
    {
        $body = $this->parseBody($request);
        $status = ContentStatus::tryFrom($body['status'] ?? '');

        if ($status === null) {
            return Response::json(['error' => 'Invalid status'], 422);
        }

        $this->contentRepo->updateStatus((int) $id, $status);

        $this->activity->setContext($request);
        $this->activity->log($status->value === 'published' ? 'published' : 'updated', 'node', $id, null, [
            'status' => $status->value,
        ]);

        return Response::json(['success' => true, 'status' => $status->value]);
    }

    // ── Quick Publish / Unpublish (from frontend toolbar) ─────────────

    #[Route('POST', '/{id:\d+}/quick-publish', name: 'admin::content.quick_publish')]
    public function quickPublish(ServerRequestInterface $request, string $id): Response
    {
        $entity = $this->contentRepo->find((int) $id);
        if (!$entity) {
            return Response::redirect('/admin/content');
        }

        $this->contentRepo->updateStatus((int) $id, ContentStatus::PUBLISHED);

        // Redirect back to the frontend URL
        $slug = $entity->slug ?? '';
        $type = $entity->content_type ?? 'article';
        $url = "/{$type}/{$slug}";

        return Response::redirect($url);
    }

    #[Route('POST', '/{id:\d+}/quick-unpublish', name: 'admin::content.quick_unpublish')]
    public function quickUnpublish(ServerRequestInterface $request, string $id): Response
    {
        $entity = $this->contentRepo->find((int) $id);
        if (!$entity) {
            return Response::redirect('/admin/content');
        }

        $this->contentRepo->updateStatus((int) $id, ContentStatus::DRAFT);

        // Redirect back to the frontend URL (will show as draft preview)
        $slug = $entity->slug ?? '';
        $type = $entity->content_type ?? 'article';
        $url = "/{$type}/{$slug}";

        return Response::redirect($url);
    }

    // ── Revisions & Diff ────────────────────────────────────────────────

    #[Route('GET', '/{id:\d+}/revisions', name: 'admin::content.revisions')]
    public function revisions(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->findOrFail((int) $id);
        $revisions = $this->diffService->getRevisions((int) $id);

        return Response::html($this->renderer->render('admin::content.revisions', [
            'title'     => 'Revisions: ' . $node->title,
            'node'      => $node,
            'revisions' => $revisions,
        ]));
    }

    #[Route('GET', '/{id:\d+}/diff', name: 'admin::content.diff')]
    public function diff(ServerRequestInterface $request, string $id): Response
    {
        $params = $request->getQueryParams();
        $from = (int) ($params['from'] ?? 0);
        $to   = (int) ($params['to'] ?? 0);

        $node = $this->contentRepo->findOrFail((int) $id);
        $diffs = $this->diffService->compare((int) $id, $from, $to);

        $revFrom = $from > 0 ? $this->diffService->getRevision((int) $id, $from) : null;
        $revTo   = $to > 0   ? $this->diffService->getRevision((int) $id, $to) : null;

        return Response::html($this->renderer->render('admin::content.diff', [
            'title'   => 'Compare: ' . $node->title,
            'node'    => $node,
            'diffs'   => $diffs,
            'from'    => $from,
            'to'      => $to,
            'revFrom' => $revFrom,
            'revTo'   => $revTo,
        ]));
    }

    #[Route('POST', '/{id:\d+}/revert/{revision:\d+}', name: 'admin::content.revert')]
    public function revert(ServerRequestInterface $request, string $id, string $revision): Response
    {
        $userId = $this->getSessionUserId($request);
        $this->diffService->revert((int) $id, (int) $revision, $userId);

        $this->activity->setContext($request);
        $this->activity->log('updated', 'node', $id, null, [
            'action'   => 'revert',
            'revision' => (int) $revision,
        ]);

        return Response::redirect('/admin/content/' . $id . '/revisions');
    }

    // ── Content Lock Endpoints ─────────────────────────────────────────

    #[Route('POST', '/{id:\d+}/lock/renew', name: 'admin::content.lock.renew')]
    public function lockRenew(ServerRequestInterface $request, string $id): Response
    {
        $userId = $this->getSessionUserId($request);
        if (!$userId) {
            return Response::json(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        $renewed = $this->lockService->renew((int) $id, $userId);

        return Response::json([
            'success'    => $renewed,
            'expires_at' => $renewed
                ? (new \DateTimeImmutable('+15 minutes'))->format('c')
                : null,
        ]);
    }

    #[Route('POST', '/{id:\d+}/lock/release', name: 'admin::content.lock.release')]
    public function lockRelease(ServerRequestInterface $request, string $id): Response
    {
        $userId = $this->getSessionUserId($request);
        if ($userId) {
            $this->lockService->release((int) $id, $userId);
        }

        return Response::json(['success' => true]);
    }

    #[Route('POST', '/{id:\d+}/lock/break', name: 'admin::content.lock.break')]
    public function lockBreak(ServerRequestInterface $request, string $id): Response
    {
        $this->lockService->breakLock((int) $id);

        $this->activity->setContext($request);
        $this->activity->log('broke_lock', 'node', $id, null);

        return Response::json(['success' => true]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody() ?? [];

        if (empty($body)) {
            $stream = $request->getBody();
            $stream->rewind();
            $raw = $stream->getContents();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return $body;
    }

    private function hydrateFromRequest(ContentEntity $entity, array $body): void
    {
        $entity->title        = $body['title'] ?? $entity->title;
        $entity->slug         = $body['slug'] ?? $entity->slug;
        $entity->content_type = $body['content_type'] ?? $entity->content_type;
        $entity->status       = $body['status'] ?? $entity->status;
        $entity->body         = $body['body'] ?? $entity->body;
        $entity->body_format  = $body['body_format'] ?? $entity->body_format;
        $entity->summary      = $body['summary'] ?? $entity->summary;
        $entity->meta_title   = $body['meta_title'] ?? $entity->meta_title;
        $entity->meta_description = $body['meta_description'] ?? $entity->meta_description;

        // Language
        if (isset($body['language']) && $body['language'] !== '') {
            $entity->language = $body['language'];
        }

        // Author
        if (isset($body['author_id']) && $body['author_id'] !== '') {
            $entity->author_id = (int) $body['author_id'];
        }

        // Featured image
        if (array_key_exists('featured_image_id', $body)) {
            $entity->featured_image_id = $body['featured_image_id'] !== ''
                ? (int) $body['featured_image_id']
                : null;
        }

        // Auto-generate slug from title if empty, using SlugManager patterns
        if (empty($entity->slug) && !empty($entity->title)) {
            $entity->slug = $this->slugManager->generateSlug($entity);
        }

        // Ensure uniqueness
        if (!empty($entity->slug)) {
            $entity->slug = $this->slugManager->ensureUnique(
                $entity->slug,
                $entity->content_type,
                $entity->id,
            );
        }

        // Handle publish scheduling
        if ($entity->status === 'published' && !empty($body['published_at'])) {
            $entity->published_at = new \DateTimeImmutable($body['published_at']);
        } elseif ($entity->status === 'published' && $entity->published_at === null) {
            $entity->published_at = new \DateTimeImmutable();
        }
    }

    /**
     * Get current session user ID.
     */
    private function getSessionUserId(ServerRequestInterface $request): ?int
    {
        $session = $request->getAttribute('session');
        return $session ? ($session->get('cms_user_id') ?? $session->get('user_id') ?? null) : null;
    }

    /**
     * Get current session user name.
     */
    private function getSessionUserName(ServerRequestInterface $request): string
    {
        $userId = $this->getSessionUserId($request);
        if (!$userId) return '';

        $stmt = $this->pdo->prepare('SELECT name FROM cms_users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchColumn() ?: '';
    }

    /**
     * Get multilingual view data for the content form.
     */
    private function getMultilingualViewData(?ContentEntity $node): array
    {
        $enabled = $this->languageService->isEnabled();
        return [
            'multilingualEnabled' => $enabled,
            'enabledLanguages'    => $enabled ? $this->languageService->getEnabled() : [],
            'defaultLang'         => $this->languageService->getDefaultCode(),
            'translations'        => ($enabled && $node?->id)
                ? $this->translationService->getAllTranslations('node', $node->id)
                : [],
        ];
    }

    /**
     * Prepare a new node entity, pre-filling from translate_from if set.
     */
    private function prepareNewNode(ServerRequestInterface $request, string $type): ContentEntity
    {
        $params = $request->getQueryParams();
        $entity = new ContentEntity();
        $entity->content_type = $type;
        $entity->language = $params['lang'] ?? $this->languageService->getDefaultCode();

        // Pre-fill from source node if translating
        $translateFrom = (int) ($params['translate_from'] ?? 0);
        if ($translateFrom > 0) {
            $source = $this->contentRepo->findWithFields($translateFrom);
            if ($source) {
                $entity->title = $source->title;
                $entity->body = $source->body;
                $entity->body_format = $source->body_format;
                $entity->summary = $source->summary;
                $entity->meta_title = $source->meta_title;
                $entity->meta_description = $source->meta_description;
                $entity->featured_image_id = $source->featured_image_id;
                $entity->fields = $source->fields;
            }
        }

        return $entity;
    }
}
