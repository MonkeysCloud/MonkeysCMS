<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\ContentTypes\ContentTypeManager;
use App\Cms\Fields\FieldType;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ContentTypeController - Admin API for managing content types
 *
 * Endpoints:
 * - GET /admin/content-types - List all content types
 * - GET /admin/content-types/{id} - Get content type details
 * - POST /admin/content-types - Create database content type
 * - PUT /admin/content-types/{id} - Update database content type
 * - DELETE /admin/content-types/{id} - Delete database content type
 * - POST /admin/content-types/{id}/fields - Add field to content type
 * - PUT /admin/content-types/{id}/fields/{fieldName} - Update field
 * - DELETE /admin/content-types/{id}/fields/{fieldName} - Remove field
 * - GET /admin/content-types/{id}/schema - Get table schema
 *
 * Content CRUD:
 * - GET /admin/content-types/{id}/content - List content
 * - POST /admin/content-types/{id}/content - Create content
 * - GET /admin/content-types/{id}/content/{contentId} - Get content item
 * - PUT /admin/content-types/{id}/content/{contentId} - Update content
 * - DELETE /admin/content-types/{id}/content/{contentId} - Delete content
 */
class ContentTypeController
{
    public function __construct(
        private readonly ContentTypeManager $contentTypeManager,
    ) {
    }

    // =========================================================================
    // Content Type Management
    // =========================================================================

    /**
     * List all content types
     */
    #[Route('GET', '/admin/content-types')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $types = $this->contentTypeManager->getTypes();

        // Format for API response
        $formatted = [];
        foreach ($types as $type) {
            $formatted[] = [
                'id' => $type['id'],
                'label' => $type['label'],
                'label_plural' => $type['label_plural'] ?? $type['label'] . 's',
                'description' => $type['description'] ?? '',
                'icon' => $type['icon'] ?? '📄',
                'source' => $type['source'],
                'publishable' => $type['publishable'] ?? true,
                'revisionable' => $type['revisionable'] ?? false,
                'translatable' => $type['translatable'] ?? false,
                'field_count' => count($type['fields'] ?? []),
            ];
        }

        return json([
            'success' => true,
            'data' => $formatted,
            'total' => count($formatted),
        ]);
    }

    /**
     * Get content type details
     */
    #[Route('GET', '/admin/content-types/{id}')]
    public function show(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);

        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        // Add editability info
        $type['editable'] = $type['source'] === 'database' &&
            (!isset($type['entity']) || !$type['entity']->is_system);

        return json([
            'success' => true,
            'data' => $type,
        ]);
    }

    /**
     * Create a new database content type
     */
    #[Route('POST', '/admin/content-types')]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        // Validate required fields
        if (empty($data['label'])) {
            return json([
                'success' => false,
                'error' => 'Label is required',
            ], 400);
        }

        // Check for duplicate type_id
        $typeId = $data['type_id'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '_', $data['label']));
        if ($this->contentTypeManager->hasType($typeId)) {
            return json([
                'success' => false,
                'error' => 'A content type with this ID already exists',
            ], 400);
        }

        try {
            $entity = $this->contentTypeManager->createDatabaseType($data);

            return json([
                'success' => true,
                'data' => $entity->toArray(),
                'message' => 'Content type created successfully',
            ], 201);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to create content type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a database content type
     */
    #[Route('PUT', '/admin/content-types/{id}')]
    public function update(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        if ($type['source'] !== 'database') {
            return json([
                'success' => false,
                'error' => 'Code-defined content types cannot be modified',
            ], 400);
        }

        if ($type['entity']->is_system ?? false) {
            return json([
                'success' => false,
                'error' => 'System content types cannot be modified',
            ], 400);
        }

        try {
            $entity = $this->contentTypeManager->updateDatabaseType($type['entity']->id, $data);

            return json([
                'success' => true,
                'data' => $entity->toArray(),
                'message' => 'Content type updated successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to update content type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a database content type
     */
    #[Route('DELETE', '/admin/content-types/{id}')]
    public function destroy(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $params = $request->getQueryParams();
        $dropTable = filter_var($params['drop_table'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        if ($type['source'] !== 'database') {
            return json([
                'success' => false,
                'error' => 'Code-defined content types cannot be deleted',
            ], 400);
        }

        if ($type['entity']->is_system ?? false) {
            return json([
                'success' => false,
                'error' => 'System content types cannot be deleted',
            ], 400);
        }

        try {
            $this->contentTypeManager->deleteDatabaseType($type['entity']->id, $dropTable);

            return json([
                'success' => true,
                'message' => 'Content type deleted successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to delete content type: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // Field Management
    // =========================================================================

    /**
     * Add a field to a content type
     */
    #[Route('POST', '/admin/content-types/{id}/fields')]
    public function addField(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        if ($type['source'] !== 'database') {
            return json([
                'success' => false,
                'error' => 'Cannot add fields to code-defined content types',
            ], 400);
        }

        // Validate
        if (empty($data['name'])) {
            return json([
                'success' => false,
                'error' => 'Field name is required',
            ], 400);
        }

        if (empty($data['type'])) {
            return json([
                'success' => false,
                'error' => 'Field type is required',
            ], 400);
        }

        // Validate field type
        try {
            FieldType::from($data['type']);
        } catch (\ValueError) {
            return json([
                'success' => false,
                'error' => 'Invalid field type: ' . $data['type'],
            ], 400);
        }

        try {
            $field = $this->contentTypeManager->addFieldToType($type['entity']->id, $data);

            return json([
                'success' => true,
                'data' => $field->toArray(),
                'message' => 'Field added successfully',
            ], 201);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to add field: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a field from a content type
     */
    #[Route('DELETE', '/admin/content-types/{id}/fields/{fieldName}')]
    public function removeField(ServerRequestInterface $request, string $id, string $fieldName): ResponseInterface
    {
        $params = $request->getQueryParams();
        $dropColumn = filter_var($params['drop_column'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        if ($type['source'] !== 'database') {
            return json([
                'success' => false,
                'error' => 'Cannot remove fields from code-defined content types',
            ], 400);
        }

        try {
            $removed = $this->contentTypeManager->removeFieldFromType(
                $type['entity']->id,
                $fieldName,
                $dropColumn
            );

            if (!$removed) {
                return json([
                    'success' => false,
                    'error' => 'Field not found',
                ], 404);
            }

            return json([
                'success' => true,
                'message' => 'Field removed successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to remove field: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get table schema for a content type
     */
    #[Route('GET', '/admin/content-types/{id}/schema')]
    public function schema(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        $schema = [];

        if ($type['source'] === 'database' && isset($type['entity'])) {
            $schema = [
                'table_name' => $type['entity']->getTableName(),
                'sql' => $type['entity']->generateTableSql(),
            ];
        } elseif ($type['source'] === 'code') {
            $schema = [
                'table_name' => $type['table_name'] ?? $type['id'],
                'note' => 'Code-defined content type',
            ];
        }

        return json([
            'success' => true,
            'data' => $schema,
        ]);
    }

    // =========================================================================
    // Content CRUD
    // =========================================================================

    /**
     * List content of a specific type
     */
    #[Route('GET', '/admin/content-types/{id}/content')]
    public function listContent(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $params = $request->getQueryParams();

        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        try {
            $result = $this->contentTypeManager->listContent($id, [
                'page' => (int) ($params['page'] ?? 1),
                'per_page' => (int) ($params['per_page'] ?? 20),
                'sort' => $params['sort'] ?? 'created_at',
                'direction' => $params['direction'] ?? 'DESC',
                'status' => $params['status'] ?? null,
                'search' => $params['search'] ?? null,
            ]);

            return json([
                'success' => true,
                'data' => $result['items'],
                'meta' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'total_pages' => $result['total_pages'],
                ],
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to list content: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create content
     */
    #[Route('POST', '/admin/content-types/{id}/content')]
    public function createContent(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        // Add author if authenticated
        // $data['author_id'] = $request->getAttribute('user')?->id;

        try {
            $contentId = $this->contentTypeManager->createContent($id, $data);

            if (!$contentId) {
                return json([
                    'success' => false,
                    'error' => 'Failed to create content',
                ], 500);
            }

            $content = $this->contentTypeManager->getContent($id, $contentId);

            return json([
                'success' => true,
                'data' => $content,
                'message' => 'Content created successfully',
            ], 201);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to create content: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single content item
     */
    #[Route('GET', '/admin/content-types/{id}/content/{contentId}')]
    public function getContent(ServerRequestInterface $request, string $id, int $contentId): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        $content = $this->contentTypeManager->getContent($id, $contentId);
        if (!$content) {
            return json([
                'success' => false,
                'error' => 'Content not found',
            ], 404);
        }

        return json([
            'success' => true,
            'data' => $content,
        ]);
    }

    /**
     * Update content
     */
    #[Route('PUT', '/admin/content-types/{id}/content/{contentId}')]
    public function updateContent(ServerRequestInterface $request, string $id, int $contentId): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        try {
            $this->contentTypeManager->updateContent($id, $contentId, $data);
            $content = $this->contentTypeManager->getContent($id, $contentId);

            return json([
                'success' => true,
                'data' => $content,
                'message' => 'Content updated successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to update content: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete content
     */
    #[Route('DELETE', '/admin/content-types/{id}/content/{contentId}')]
    public function deleteContent(ServerRequestInterface $request, string $id, int $contentId): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        try {
            $deleted = $this->contentTypeManager->deleteContent($id, $contentId);

            if (!$deleted) {
                return json([
                    'success' => false,
                    'error' => 'Content not found',
                ], 404);
            }

            return json([
                'success' => true,
                'message' => 'Content deleted successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to delete content: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk operations on content
     */
    #[Route('POST', '/admin/content-types/{id}/content/bulk')]
    public function bulkAction(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];
        $action = $data['action'] ?? null;
        $ids = $data['ids'] ?? [];

        if (!$action || empty($ids)) {
            return json([
                'success' => false,
                'error' => 'Action and IDs are required',
            ], 400);
        }

        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Content type not found',
            ], 404);
        }

        $processed = 0;
        $errors = [];

        foreach ($ids as $contentId) {
            try {
                switch ($action) {
                    case 'delete':
                        $this->contentTypeManager->deleteContent($id, $contentId);
                        break;
                    case 'publish':
                        $this->contentTypeManager->updateContent($id, $contentId, ['status' => 'published']);
                        break;
                    case 'unpublish':
                        $this->contentTypeManager->updateContent($id, $contentId, ['status' => 'draft']);
                        break;
                    default:
                        $errors[] = "Unknown action: {$action}";
                        continue 2;
                }
                $processed++;
            } catch (\Exception $e) {
                $errors[] = "ID {$contentId}: " . $e->getMessage();
            }
        }

        return json([
            'success' => true,
            'data' => [
                'processed' => $processed,
                'total' => count($ids),
                'errors' => $errors,
            ],
            'message' => "Processed {$processed} of " . count($ids) . " items",
        ]);
    }
}
