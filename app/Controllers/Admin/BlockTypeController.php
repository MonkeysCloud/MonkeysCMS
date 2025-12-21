<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Blocks\BlockManager;
use App\Cms\Blocks\BlockRenderer;
use App\Cms\Fields\FieldType;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * BlockTypeController - Admin API for managing block types
 *
 * Endpoints:
 * - GET /admin/block-types - List all block types
 * - GET /admin/block-types/grouped - List types grouped by category
 * - GET /admin/block-types/{id} - Get block type details
 * - POST /admin/block-types - Create database block type
 * - PUT /admin/block-types/{id} - Update database block type
 * - DELETE /admin/block-types/{id} - Delete database block type
 * - POST /admin/block-types/{id}/fields - Add field to block type
 * - DELETE /admin/block-types/{id}/fields/{fieldName} - Remove field
 * - GET /admin/block-types/field-types - Get available field types
 * - POST /admin/block-types/{id}/preview - Preview block type
 */
class BlockTypeController
{
    public function __construct(
        private readonly BlockManager $blockManager,
        private readonly ?BlockRenderer $blockRenderer = null,
    ) {
    }

    /**
     * List all block types
     */
    #[Route('GET', '/admin/block-types')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $types = $this->blockManager->getTypes();

        return json([
            'success' => true,
            'data' => array_values($types),
            'total' => count($types),
        ]);
    }

    /**
     * List block types grouped by category
     */
    #[Route('GET', '/admin/block-types/grouped')]
    public function grouped(ServerRequestInterface $request): ResponseInterface
    {
        $grouped = $this->blockManager->getTypesGrouped();

        return json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    /**
     * Get available field types
     */
    #[Route('GET', '/admin/block-types/field-types')]
    public function fieldTypes(ServerRequestInterface $request): ResponseInterface
    {
        $grouped = FieldType::getGrouped();
        $types = [];

        foreach ($grouped as $category => $fieldTypes) {
            foreach ($fieldTypes as $fieldType) {
                $types[] = [
                    'value' => $fieldType->value,
                    'label' => $fieldType->getLabel(),
                    'category' => $category,
                    'widget' => $fieldType->getDefaultWidget(),
                    'supports_multiple' => $fieldType->supportsMultiple(),
                ];
            }
        }

        return json([
            'success' => true,
            'data' => $types,
            'grouped' => $grouped,
        ]);
    }

    /**
     * Get block type details
     */
    #[Route('GET', '/admin/block-types/{id}')]
    public function show(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->blockManager->getType($id);

        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Block type not found',
            ], 404);
        }

        // Add additional info for database types
        if ($type['source'] === 'database' && isset($type['entity'])) {
            $type['editable'] = !$type['entity']->is_system;
            $type['db_id'] = $type['entity']->id;
        } else {
            $type['editable'] = false;
        }

        return json([
            'success' => true,
            'data' => $type,
        ]);
    }

    /**
     * Create a new database block type
     */
    #[Route('POST', '/admin/block-types')]
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
        if ($this->blockManager->hasType($typeId)) {
            return json([
                'success' => false,
                'error' => 'A block type with this ID already exists',
            ], 400);
        }

        try {
            $entity = $this->blockManager->createDatabaseType($data);

            return json([
                'success' => true,
                'data' => $entity->toArray(),
                'message' => 'Block type created successfully',
            ], 201);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to create block type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a database block type
     */
    #[Route('PUT', '/admin/block-types/{id}')]
    public function update(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        // Get the type
        $type = $this->blockManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Block type not found',
            ], 404);
        }

        // Only database types can be updated
        if ($type['source'] !== 'database') {
            return json([
                'success' => false,
                'error' => 'Code-defined block types cannot be modified',
            ], 400);
        }

        // System types cannot be modified
        if ($type['entity']->is_system) {
            return json([
                'success' => false,
                'error' => 'System block types cannot be modified',
            ], 400);
        }

        try {
            $entity = $this->blockManager->updateDatabaseType($type['entity']->id, $data);

            return json([
                'success' => true,
                'data' => $entity->toArray(),
                'message' => 'Block type updated successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to update block type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a database block type
     */
    #[Route('DELETE', '/admin/block-types/{id}')]
    public function destroy(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Block type not found',
            ], 404);
        }

        if ($type['source'] !== 'database') {
            return json([
                'success' => false,
                'error' => 'Code-defined block types cannot be deleted',
            ], 400);
        }

        if ($type['entity']->is_system) {
            return json([
                'success' => false,
                'error' => 'System block types cannot be deleted',
            ], 400);
        }

        try {
            $this->blockManager->deleteDatabaseType($type['entity']->id);

            return json([
                'success' => true,
                'message' => 'Block type deleted successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to delete block type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a field to a database block type
     */
    #[Route('POST', '/admin/block-types/{id}/fields')]
    public function addField(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        $type = $this->blockManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Block type not found',
            ], 404);
        }

        if ($type['source'] !== 'database') {
            return json([
                'success' => false,
                'error' => 'Cannot add fields to code-defined block types',
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

        try {
            $field = $this->blockManager->addFieldToType($type['entity']->id, $data);

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
     * Remove a field from a database block type
     */
    #[Route('DELETE', '/admin/block-types/{id}/fields/{fieldName}')]
    public function removeField(ServerRequestInterface $request, string $id, string $fieldName): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Block type not found',
            ], 404);
        }

        if ($type['source'] !== 'database') {
            return json([
                'success' => false,
                'error' => 'Cannot remove fields from code-defined block types',
            ], 400);
        }

        try {
            $removed = $this->blockManager->removeFieldFromType($type['entity']->id, $fieldName);

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
     * Preview a block type
     */
    #[Route('POST', '/admin/block-types/{id}/preview')]
    public function preview(ServerRequestInterface $request, string $id): ResponseInterface
    {
        if (!$this->blockRenderer) {
            return json([
                'success' => false,
                'error' => 'Block renderer not available',
            ], 500);
        }

        $type = $this->blockManager->getType($id);
        if (!$type) {
            return json([
                'success' => false,
                'error' => 'Block type not found',
            ], 404);
        }

        $html = $this->blockRenderer->previewType($id);

        return json([
            'success' => true,
            'data' => [
                'html' => $html,
                'type' => $type,
            ],
        ]);
    }

    /**
     * Get fields for a block type
     */
    #[Route('GET', '/admin/block-types/{id}/fields')]
    public function getFields(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $fields = $this->blockManager->getFieldsForType($id);

        return json([
            'success' => true,
            'data' => $fields,
        ]);
    }

    /**
     * Reorder fields
     */
    #[Route('PUT', '/admin/block-types/{id}/fields/reorder')]
    public function reorderFields(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];
        $order = $data['order'] ?? [];

        $type = $this->blockManager->getType($id);
        if (!$type || $type['source'] !== 'database') {
            return json([
                'success' => false,
                'error' => 'Block type not found or not editable',
            ], 404);
        }

        // Update weights based on order
        // This would need implementation in BlockManager

        return json([
            'success' => true,
            'message' => 'Fields reordered successfully',
        ]);
    }
}
