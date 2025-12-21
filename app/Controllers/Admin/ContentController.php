<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Attributes\ContentType;
use App\Cms\Core\BaseEntity;
use App\Cms\Modules\ModuleManager;
use App\Cms\Repository\CmsRepository;
use App\Cms\Security\PermissionService;
use App\Modules\Core\Services\TaxonomyService;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

/**
 * ContentController - Generic CRUD API for all CMS content types
 *
 * This controller provides a unified REST API for any content type entity.
 * It dynamically resolves the entity class based on the content type parameter.
 *
 * Routes:
 * - GET    /admin/content/{type}           - List/paginate content
 * - GET    /admin/content/{type}/{id}      - Get single item
 * - POST   /admin/content/{type}           - Create new item
 * - PUT    /admin/content/{type}/{id}      - Update item
 * - DELETE /admin/content/{type}/{id}      - Delete item
 * - GET    /admin/content/{type}/search    - Search content
 *
 * Features:
 * - Permission-based access control per entity type
 * - Supports "own content" permissions (view_own, edit_own, delete_own)
 * - Automatic validation based on entity attributes
 * - Taxonomy integration for content tagging
 * - Works with any entity that has #[ContentType]
 */
#[Route('/admin/content', name: 'admin.content')]
final class ContentController
{
    /**
     * Cached entity class mappings (content type name => FQCN)
     * @var array<string, string>
     */
    private array $entityMap = [];

    public function __construct(
        private readonly CmsRepository $repository,
        private readonly ModuleManager $moduleManager,
        private readonly PermissionService $permissions,
        private readonly ?TaxonomyService $taxonomy = null,
    ) {
        $this->buildEntityMap();
    }

    /**
     * List content items with pagination
     */
    #[Route('GET', '/{type}', name: 'index')]
    public function index(ServerRequestInterface $request, string $type): ResponseInterface
    {
        $entityClass = $this->resolveEntityClass($type);
        if ($entityClass === null) {
            return json([
                'success' => false,
                'error' => "Content type '{$type}' not found",
            ], 404);
        }

        // Check view permission
        if (!$this->permissions->canOnEntityType('view', $type)) {
            return json([
                'success' => false,
                'error' => "You don't have permission to view {$type}",
            ], 403);
        }

        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));

        // Build criteria from query params
        $criteria = $this->buildCriteria($params, $entityClass);

        // If user only has "view_own" permission, filter by author
        if (!$this->permissions->can("view_{$type}") && $this->permissions->can("view_own_{$type}")) {
            $currentUser = $this->permissions->getCurrentUser();
            if ($currentUser) {
                $criteria['author_id'] = $currentUser->id;
            }
        }

        // Build ordering
        $orderBy = [];
        if (isset($params['sort'])) {
            $direction = strtoupper($params['direction'] ?? 'DESC');
            $orderBy[$params['sort']] = in_array($direction, ['ASC', 'DESC']) ? $direction : 'DESC';
        } else {
            $orderBy['id'] = 'DESC';
        }

        $result = $this->repository->paginate($entityClass, $page, $perPage, $criteria, $orderBy);

        return json([
            'success' => true,
            'data' => array_map(fn($e) => $e->toArray(true), $result['data']),
            'meta' => [
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'total_pages' => $result['total_pages'],
            ],
            'permissions' => $this->getContentPermissions($type),
        ]);
    }

    /**
     * Get a single content item by ID
     */
    #[Route('GET', '/{type}/{id:\d+}', name: 'show')]
    public function show(string $type, int $id): ResponseInterface
    {
        $entityClass = $this->resolveEntityClass($type);
        if ($entityClass === null) {
            return json([
                'success' => false,
                'error' => "Content type '{$type}' not found",
            ], 404);
        }

        $entity = $this->repository->find($entityClass, $id);
        if ($entity === null) {
            return json([
                'success' => false,
                'error' => "Item not found",
            ], 404);
        }

        // Check view permission on this specific entity
        if (!$this->permissions->canOnEntity('view', $entity)) {
            return json([
                'success' => false,
                'error' => "You don't have permission to view this item",
            ], 403);
        }

        // Load taxonomy terms if available
        $terms = [];
        if ($this->taxonomy) {
            $terms = $this->taxonomy->getEntityTerms($type, $id);
        }

        return json([
            'success' => true,
            'data' => $entity->toArray(true),
            'terms' => array_map(fn($t) => $t->toArray(), $terms),
            'permissions' => [
                'can_edit' => $this->permissions->canOnEntity('edit', $entity),
                'can_delete' => $this->permissions->canOnEntity('delete', $entity),
            ],
        ]);
    }

    /**
     * Create a new content item
     */
    #[Route('POST', '/{type}', name: 'store')]
    public function store(ServerRequestInterface $request, string $type): ResponseInterface
    {
        $entityClass = $this->resolveEntityClass($type);
        if ($entityClass === null) {
            return json([
                'success' => false,
                'error' => "Content type '{$type}' not found",
            ], 404);
        }

        // Check create permission
        if (!$this->permissions->canOnEntityType('create', $type)) {
            return json([
                'success' => false,
                'error' => "You don't have permission to create {$type}",
            ], 403);
        }

        $data = json_decode((string) $request->getBody(), true);
        if ($data === null) {
            return json([
                'success' => false,
                'error' => 'Invalid JSON body',
            ], 400);
        }

        try {
            /** @var BaseEntity $entity */
            $entity = new $entityClass();
            $entity->hydrate($data);

            // Set author if entity supports it
            $currentUser = $this->permissions->getCurrentUser();
            if ($currentUser && property_exists($entity, 'author_id')) {
                /** @phpstan-ignore-next-line */
                $entity->author_id = $currentUser->id;
            }

            $saved = $this->repository->save($entity);

            // Handle taxonomy terms
            if (isset($data['terms']) && $this->taxonomy) {
                foreach ($data['terms'] as $vocabId => $termIds) {
                    $this->taxonomy->setEntityTerms($type, $saved->id, (int) $vocabId, $termIds);
                }
            }

            return json([
                'success' => true,
                'data' => $saved->toArray(true),
                'message' => 'Created successfully',
            ], 201);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to create: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update an existing content item
     */
    #[Route('PUT', '/{type}/{id:\d+}', name: 'update')]
    public function update(ServerRequestInterface $request, string $type, int $id): ResponseInterface
    {
        $entityClass = $this->resolveEntityClass($type);
        if ($entityClass === null) {
            return json([
                'success' => false,
                'error' => "Content type '{$type}' not found",
            ], 404);
        }

        $entity = $this->repository->find($entityClass, $id);
        if ($entity === null) {
            return json([
                'success' => false,
                'error' => "Item not found",
            ], 404);
        }

        // Check edit permission on this specific entity
        if (!$this->permissions->canOnEntity('edit', $entity)) {
            return json([
                'success' => false,
                'error' => "You don't have permission to edit this item",
            ], 403);
        }

        $data = json_decode((string) $request->getBody(), true);
        if ($data === null) {
            return json([
                'success' => false,
                'error' => 'Invalid JSON body',
            ], 400);
        }

        try {
            // Don't overwrite ID, created_at, or author
            unset($data['id'], $data['created_at'], $data['author_id']);

            $entity->hydrate($data);
            $saved = $this->repository->save($entity);

            // Handle taxonomy terms
            if (isset($data['terms']) && $this->taxonomy) {
                foreach ($data['terms'] as $vocabId => $termIds) {
                    $this->taxonomy->setEntityTerms($type, $saved->id, (int) $vocabId, $termIds);
                }
            }

            return json([
                'success' => true,
                'data' => $saved->toArray(true),
                'message' => 'Updated successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to update: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete a content item
     */
    #[Route('DELETE', '/{type}/{id:\d+}', name: 'destroy')]
    public function destroy(string $type, int $id): ResponseInterface
    {
        $entityClass = $this->resolveEntityClass($type);
        if ($entityClass === null) {
            return json([
                'success' => false,
                'error' => "Content type '{$type}' not found",
            ], 404);
        }

        $entity = $this->repository->find($entityClass, $id);
        if ($entity === null) {
            return json([
                'success' => false,
                'error' => "Item not found",
            ], 404);
        }

        // Check delete permission on this specific entity
        if (!$this->permissions->canOnEntity('delete', $entity)) {
            return json([
                'success' => false,
                'error' => "You don't have permission to delete this item",
            ], 403);
        }

        try {
            // Clear taxonomy terms
            if ($this->taxonomy) {
                $this->taxonomy->clearEntityTerms($type, $id);
            }

            $this->repository->delete($entity);

            return json([
                'success' => true,
                'message' => 'Deleted successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to delete: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Search content items
     */
    #[Route('GET', '/{type}/search', name: 'search')]
    public function search(ServerRequestInterface $request, string $type): ResponseInterface
    {
        $entityClass = $this->resolveEntityClass($type);
        if ($entityClass === null) {
            return json([
                'success' => false,
                'error' => "Content type '{$type}' not found",
            ], 404);
        }

        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';

        if (empty($query)) {
            return json([
                'success' => false,
                'error' => 'Search query "q" is required',
            ], 400);
        }

        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));

        $results = $this->repository->search($entityClass, $query, [], $limit);

        return json([
            'success' => true,
            'data' => array_map(fn($e) => $e->toArray(true), $results),
            'meta' => [
                'query' => $query,
                'count' => count($results),
            ],
        ]);
    }

    /**
     * Get available content types
     */
    #[Route('GET', '/types', name: 'types')]
    public function types(): ResponseInterface
    {
        $types = [];

        foreach ($this->entityMap as $typeName => $entityClass) {
            $reflection = new ReflectionClass($entityClass);
            $attrs = $reflection->getAttributes(ContentType::class);

            if (!empty($attrs)) {
                $contentType = $attrs[0]->newInstance();
                $types[$typeName] = [
                    'name' => $typeName,
                    'label' => $contentType->label,
                    'label_plural' => $contentType->getPluralLabel(),
                    'description' => $contentType->description,
                    'icon' => $contentType->icon,
                    'revisionable' => $contentType->revisionable,
                    'publishable' => $contentType->publishable,
                    'entity_class' => $entityClass,
                ];
            }
        }

        return json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Build entity class mapping from enabled modules
     */
    private function buildEntityMap(): void
    {
        foreach ($this->moduleManager->getEnabledModules() as $moduleName) {
            try {
                $entities = $this->moduleManager->discoverEntities($moduleName);

                foreach ($entities as $entityClass) {
                    if (!class_exists($entityClass)) {
                        continue;
                    }

                    $reflection = new ReflectionClass($entityClass);
                    $attrs = $reflection->getAttributes(ContentType::class);

                    if (!empty($attrs)) {
                        $contentType = $attrs[0]->newInstance();
                        // Map both table name and class short name
                        $this->entityMap[$contentType->tableName] = $entityClass;
                        $this->entityMap[strtolower($reflection->getShortName())] = $entityClass;
                    }
                }
            } catch (\Exception $e) {
                // Skip modules with issues
            }
        }
    }

    /**
     * Resolve entity class from content type name
     */
    private function resolveEntityClass(string $type): ?string
    {
        $normalized = strtolower($type);
        return $this->entityMap[$normalized] ?? null;
    }

    /**
     * Build query criteria from request parameters
     */
    private function buildCriteria(array $params, string $entityClass): array
    {
        $criteria = [];

        // Remove pagination/sorting params
        $reserved = ['page', 'per_page', 'sort', 'direction', 'q'];

        foreach ($params as $key => $value) {
            if (in_array($key, $reserved, true)) {
                continue;
            }

            // Handle special filter syntax
            if (str_ends_with($key, '__in')) {
                $field = substr($key, 0, -4);
                $criteria[$field] = explode(',', $value);
            } elseif (str_ends_with($key, '__null')) {
                $field = substr($key, 0, -6);
                $criteria[$field] = null;
            } else {
                $criteria[$key] = $value;
            }
        }

        return $criteria;
    }



    /**
     * Get current user's permissions for a content type
     */
    private function getContentPermissions(string $type): array
    {
        return [
            'can_view' => $this->permissions->canOnEntityType('view', $type),
            'can_view_own' => $this->permissions->can("view_own_{$type}"),
            'can_create' => $this->permissions->canOnEntityType('create', $type),
            'can_edit' => $this->permissions->canOnEntityType('edit', $type),
            'can_edit_own' => $this->permissions->can("edit_own_{$type}"),
            'can_delete' => $this->permissions->canOnEntityType('delete', $type),
            'can_delete_own' => $this->permissions->can("delete_own_{$type}"),
            'can_administer' => $this->permissions->can("administer_{$type}"),
        ];
    }
}
