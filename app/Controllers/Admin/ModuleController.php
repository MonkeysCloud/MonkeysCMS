<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Modules\ModuleException;
use App\Cms\Modules\ModuleManager;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ModuleController - Admin API for managing CMS modules
 *
 * This controller provides REST endpoints for:
 * - Listing available and enabled modules
 * - Enabling/disabling modules (with auto-sync)
 * - Viewing module details and dependencies
 *
 * Security Note: In production, protect these endpoints with admin authentication!
 *
 * @example
 * ```bash
 * # List all modules
 * curl -X GET http://localhost/admin/modules
 *
 * # Enable a module
 * curl -X POST http://localhost/admin/modules/Custom/Ecommerce/enable
 *
 * # Disable a module
 * curl -X POST http://localhost/admin/modules/Custom/Ecommerce/disable
 * ```
 */
#[Route('/admin/modules', name: 'admin.modules')]
final class ModuleController
{
    public function __construct(
        private readonly ModuleManager $moduleManager,
    ) {
    }

    /**
     * List all available modules
     *
     * @return ResponseInterface JSON list of modules with their status
     */
    #[Route('GET', '/', name: 'index')]
    public function index(): ResponseInterface
    {
        $modules = $this->moduleManager->getAvailableModules();

        return json([
            'success' => true,
            'data' => [
                'modules' => $modules,
                'enabled_count' => count(array_filter($modules, fn($m) => $m['enabled'] ?? false)),
                'total_count' => count($modules),
            ],
        ]);
    }

    /**
     * Get enabled modules only
     */
    #[Route('GET', '/enabled', name: 'enabled')]
    public function enabled(): ResponseInterface
    {
        $enabled = $this->moduleManager->getEnabledModules();

        return json([
            'success' => true,
            'data' => $enabled,
        ]);
    }

    /**
     * Get details for a specific module
     */
    #[Route('GET', '/{module:.+}/details', name: 'details')]
    public function details(string $module): ResponseInterface
    {
        if (!$this->moduleManager->moduleExists($module)) {
            return json([
                'success' => false,
                'error' => "Module '{$module}' not found",
            ], 404);
        }

        $modules = $this->moduleManager->getAvailableModules();
        $moduleData = $modules[$module] ?? null;

        if ($moduleData === null) {
            return json([
                'success' => false,
                'error' => 'Module data not available',
            ], 404);
        }

        // Get discovered entities for this module
        try {
            $entities = $this->moduleManager->discoverEntities($module);
        } catch (\Exception $e) {
            $entities = [];
        }

        return json([
            'success' => true,
            'data' => array_merge($moduleData, [
                'discovered_entities' => $entities,
            ]),
        ]);
    }

    /**
     * Enable a module
     *
     * This is the key endpoint that triggers the auto-sync feature:
     * 1. ModuleManager discovers entity classes in the module
     * 2. SchemaGenerator creates SQL from entity attributes
     * 3. SQL is executed immediately against the database
     * 4. Module is marked as enabled in modules.json
     */
    #[Route('POST', '/{module:.+}/enable', name: 'enable')]
    public function enable(ServerRequestInterface $request, string $module): ResponseInterface
    {
        try {
            // Parse request body for options
            $body = json_decode((string) $request->getBody(), true) ?? [];
            $syncSchema = $body['sync_schema'] ?? true;

            // Enable the module
            $result = $this->moduleManager->enable($module, $syncSchema);

            return json([
                'success' => true,
                'message' => "Module '{$module}' enabled successfully",
                'data' => [
                    'module' => $module,
                    'schema_synced' => $syncSchema,
                    'entities_discovered' => $this->moduleManager->discoverEntities($module),
                ],
            ]);
        } catch (ModuleException $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to enable module: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable a module
     *
     * By default, this does NOT remove database tables - data is preserved.
     * Pass {"remove_tables": true} in body to drop tables (dangerous!).
     */
    #[Route('POST', '/{module:.+}/disable', name: 'disable')]
    public function disable(ServerRequestInterface $request, string $module): ResponseInterface
    {
        try {
            $body = json_decode((string) $request->getBody(), true) ?? [];
            $removeTables = $body['remove_tables'] ?? false;

            // Require confirmation for table removal
            if ($removeTables && !($body['confirm_removal'] ?? false)) {
                return json([
                    'success' => false,
                    'error' => 'Table removal requires explicit confirmation',
                    'message' => 'Set "confirm_removal": true to proceed with data deletion',
                ], 400);
            }

            $result = $this->moduleManager->disable($module, $removeTables);

            return json([
                'success' => true,
                'message' => "Module '{$module}' disabled successfully",
                'data' => [
                    'module' => $module,
                    'tables_removed' => $removeTables,
                ],
            ]);
        } catch (ModuleException $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to disable module: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Re-sync schema for a module
     *
     * Useful after making changes to entity attributes.
     * This runs ALTER TABLE statements to sync schema changes.
     */
    #[Route('POST', '/{module:.+}/sync', name: 'sync')]
    public function sync(string $module): ResponseInterface
    {
        try {
            if (!$this->moduleManager->isEnabled($module)) {
                return json([
                    'success' => false,
                    'error' => 'Module must be enabled before syncing schema',
                ], 400);
            }

            $entities = $this->moduleManager->discoverEntities($module);
            $synced = [];

            foreach ($entities as $entityClass) {
                $this->moduleManager->syncEntitySchema($entityClass);
                $synced[] = $entityClass;
            }

            return json([
                'success' => true,
                'message' => 'Schema synchronized successfully',
                'data' => [
                    'module' => $module,
                    'synced_entities' => $synced,
                ],
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Schema sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview schema SQL without executing
     *
     * Useful for reviewing what tables/columns will be created.
     */
    #[Route('GET', '/{module:.+}/schema-preview', name: 'schema.preview')]
    public function schemaPreview(string $module): ResponseInterface
    {
        try {
            if (!$this->moduleManager->moduleExists($module)) {
                return json([
                    'success' => false,
                    'error' => "Module '{$module}' not found",
                ], 404);
            }

            $entities = $this->moduleManager->discoverEntities($module);

            if (empty($entities)) {
                return json([
                    'success' => true,
                    'data' => [
                        'module' => $module,
                        'entities' => [],
                        'sql' => [],
                        'message' => 'No entities found in this module',
                    ],
                ]);
            }

            $schemaGenerator = new \App\Cms\Core\SchemaGenerator();
            $sqlStatements = [];

            foreach ($entities as $entityClass) {
                $sqlStatements[$entityClass] = $schemaGenerator->generateSql($entityClass);
            }

            return json([
                'success' => true,
                'data' => [
                    'module' => $module,
                    'entities' => $entities,
                    'sql' => $sqlStatements,
                ],
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => 'Failed to generate schema preview: ' . $e->getMessage(),
            ], 500);
        }
    }
}
