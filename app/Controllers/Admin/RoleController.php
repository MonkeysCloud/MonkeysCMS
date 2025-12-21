<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Entities\Role;
use App\Modules\Core\Entities\Permission;
use App\Cms\Repository\CmsRepository;
use App\Cms\Security\PermissionService;
use MonkeysLegion\Http\Attribute\Route;
use MonkeysLegion\Http\Request;
use MonkeysLegion\Http\JsonResponse;

/**
 * RoleController - Admin API for role and permission management
 */
final class RoleController
{
    public function __construct(
        private readonly CmsRepository $repository,
        private readonly PermissionService $permissions,
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Role Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * List all roles
     */
    #[Route('GET', '/admin/roles')]
    public function index(): JsonResponse
    {
        $roles = $this->repository->findAll(Role::class, ['weight' => 'DESC', 'name' => 'ASC']);

        // Load permissions for each role
        foreach ($roles as $role) {
            $role->permissions = $this->permissions->getRolePermissions($role);
        }

        return new JsonResponse([
            'roles' => array_map(fn($r) => array_merge($r->toArray(), [
                'permission_count' => count($r->permissions),
                'permission_slugs' => array_map(fn($p) => $p->slug, $r->permissions),
            ]), $roles),
        ]);
    }

    /**
     * Get single role with permissions
     */
    #[Route('GET', '/admin/roles/{id}')]
    public function show(int $id): JsonResponse
    {
        $role = $this->repository->find(Role::class, $id);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        $role->permissions = $this->permissions->getRolePermissions($role);

        return new JsonResponse(array_merge($role->toArray(), [
            'permissions' => array_map(fn($p) => $p->toArray(), $role->permissions),
        ]));
    }

    /**
     * Create role
     */
    #[Route('POST', '/admin/roles')]
    public function create(Request $request): JsonResponse
    {
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return new JsonResponse(['errors' => ['name' => 'Name is required']], 422);
        }

        // Generate slug if not provided
        $slug = $data['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/', '_', $data['name']));

        // Check uniqueness
        $existing = $this->repository->findOneBy(Role::class, ['slug' => $slug]);
        if ($existing) {
            return new JsonResponse(['errors' => ['slug' => 'Slug already exists']], 422);
        }

        $role = new Role();
        $role->name = $data['name'];
        $role->slug = $slug;
        $role->description = $data['description'] ?? '';
        $role->color = $data['color'] ?? '#6b7280';
        $role->weight = $data['weight'] ?? 0;
        $role->is_system = false;
        $role->is_default = $data['is_default'] ?? false;

        $this->repository->save($role);

        // Assign permissions
        if (!empty($data['permission_ids'])) {
            $this->permissions->setRolePermissions($role, $data['permission_ids']);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Role created successfully',
            'role' => $role->toArray(),
        ], 201);
    }

    /**
     * Update role
     */
    #[Route('PUT', '/admin/roles/{id}')]
    public function update(int $id, Request $request): JsonResponse
    {
        $role = $this->repository->find(Role::class, $id);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        // Prevent editing system roles (except permissions)
        $data = $request->getParsedBody();

        if (!$role->is_system) {
            if (isset($data['name'])) {
                $role->name = $data['name'];
            }
            if (isset($data['slug'])) {
                // Check uniqueness
                $existing = $this->repository->findOneBy(Role::class, ['slug' => $data['slug']]);
                if ($existing && $existing->id !== $role->id) {
                    return new JsonResponse(['errors' => ['slug' => 'Slug already exists']], 422);
                }
                $role->slug = $data['slug'];
            }
            if (isset($data['description'])) {
                $role->description = $data['description'];
            }
            if (isset($data['color'])) {
                $role->color = $data['color'];
            }
            if (isset($data['weight'])) {
                $role->weight = $data['weight'];
            }
            if (isset($data['is_default'])) {
                $role->is_default = $data['is_default'];
            }

            $this->repository->save($role);
        }

        // Update permissions (allowed for all roles)
        if (isset($data['permission_ids'])) {
            $this->permissions->setRolePermissions($role, $data['permission_ids']);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Role updated successfully',
            'role' => $role->toArray(),
        ]);
    }

    /**
     * Delete role
     */
    #[Route('DELETE', '/admin/roles/{id}')]
    public function delete(int $id): JsonResponse
    {
        $role = $this->repository->find(Role::class, $id);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        if ($role->is_system) {
            return new JsonResponse(['error' => 'Cannot delete system roles'], 400);
        }

        // Remove all permission assignments
        $this->permissions->setRolePermissions($role, []);

        // Delete role (will cascade to user_roles via foreign key)
        $this->repository->delete($role);

        return new JsonResponse([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Get role's permissions
     */
    #[Route('GET', '/admin/roles/{id}/permissions')]
    public function getRolePermissions(int $id): JsonResponse
    {
        $role = $this->repository->find(Role::class, $id);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        $permissions = $this->permissions->getRolePermissions($role);

        return new JsonResponse([
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permissions' => array_map(fn($p) => $p->toArray(), $permissions),
        ]);
    }

    /**
     * Set role's permissions
     */
    #[Route('PUT', '/admin/roles/{id}/permissions')]
    public function setRolePermissions(int $id, Request $request): JsonResponse
    {
        $role = $this->repository->find(Role::class, $id);

        if (!$role) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        $data = $request->getParsedBody();
        $permissionIds = $data['permission_ids'] ?? [];

        $this->permissions->setRolePermissions($role, $permissionIds);

        return new JsonResponse([
            'success' => true,
            'message' => 'Role permissions updated',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Permission Endpoints
    // ─────────────────────────────────────────────────────────────

    /**
     * List all permissions
     */
    #[Route('GET', '/admin/permissions')]
    public function listPermissions(Request $request): JsonResponse
    {
        $group = $request->getQueryParams()['group'] ?? null;
        $entityType = $request->getQueryParams()['entity_type'] ?? null;

        $criteria = [];
        if ($group) {
            $criteria['group'] = $group;
        }
        if ($entityType) {
            $criteria['entity_type'] = $entityType;
        }

        $permissions = $this->repository->findBy(
            Permission::class,
            $criteria,
            ['group' => 'ASC', 'weight' => 'ASC', 'name' => 'ASC']
        );

        return new JsonResponse([
            'permissions' => array_map(fn($p) => $p->toArray(), $permissions),
        ]);
    }

    /**
     * Get permissions grouped
     */
    #[Route('GET', '/admin/permissions/grouped')]
    public function listPermissionsGrouped(): JsonResponse
    {
        $grouped = $this->permissions->getAllPermissionsGrouped();

        $result = [];
        foreach ($grouped as $group => $permissions) {
            $result[$group] = array_map(fn($p) => $p->toArray(), $permissions);
        }

        return new JsonResponse([
            'groups' => $result,
        ]);
    }

    /**
     * Get permissions for an entity type
     */
    #[Route('GET', '/admin/permissions/entity/{entityType}')]
    public function getEntityPermissions(string $entityType): JsonResponse
    {
        $permissions = $this->permissions->getEntityPermissions($entityType);

        return new JsonResponse([
            'entity_type' => $entityType,
            'permissions' => array_map(fn($p) => $p->toArray(), $permissions),
        ]);
    }

    /**
     * Create permission
     */
    #[Route('POST', '/admin/permissions')]
    public function createPermission(Request $request): JsonResponse
    {
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return new JsonResponse(['errors' => ['name' => 'Name is required']], 422);
        }
        if (empty($data['slug'])) {
            return new JsonResponse(['errors' => ['slug' => 'Slug is required']], 422);
        }

        // Check uniqueness
        $existing = $this->repository->findOneBy(Permission::class, ['slug' => $data['slug']]);
        if ($existing) {
            return new JsonResponse(['errors' => ['slug' => 'Permission slug already exists']], 422);
        }

        $permission = new Permission();
        $permission->name = $data['name'];
        $permission->slug = $data['slug'];
        $permission->description = $data['description'] ?? '';
        $permission->group = $data['group'] ?? 'Custom';
        $permission->entity_type = $data['entity_type'] ?? null;
        $permission->action = $data['action'] ?? 'custom';
        $permission->module = $data['module'] ?? null;
        $permission->weight = $data['weight'] ?? 0;
        $permission->is_system = false;

        $this->repository->save($permission);

        return new JsonResponse([
            'success' => true,
            'message' => 'Permission created successfully',
            'permission' => $permission->toArray(),
        ], 201);
    }

    /**
     * Delete permission
     */
    #[Route('DELETE', '/admin/permissions/{id}')]
    public function deletePermission(int $id): JsonResponse
    {
        $permission = $this->repository->find(Permission::class, $id);

        if (!$permission) {
            return new JsonResponse(['error' => 'Permission not found'], 404);
        }

        if ($permission->is_system) {
            return new JsonResponse(['error' => 'Cannot delete system permissions'], 400);
        }

        $this->repository->delete($permission);

        return new JsonResponse([
            'success' => true,
            'message' => 'Permission deleted successfully',
        ]);
    }

    /**
     * Register permissions for an entity type
     */
    #[Route('POST', '/admin/permissions/register-entity')]
    public function registerEntityPermissions(Request $request): JsonResponse
    {
        $data = $request->getParsedBody();

        if (empty($data['entity_type'])) {
            return new JsonResponse(['errors' => ['entity_type' => 'Entity type is required']], 422);
        }
        if (empty($data['entity_label'])) {
            return new JsonResponse(['errors' => ['entity_label' => 'Entity label is required']], 422);
        }

        $module = $data['module'] ?? 'custom';
        $actions = $data['actions'] ?? ['view', 'view_own', 'create', 'edit', 'edit_own', 'delete', 'delete_own'];

        $this->permissions->registerEntityPermissions(
            $data['entity_type'],
            $data['entity_label'],
            $module,
            $actions
        );

        return new JsonResponse([
            'success' => true,
            'message' => "Permissions registered for {$data['entity_type']}",
        ]);
    }

    /**
     * Get permission matrix for UI
     * Returns all roles and permissions in a matrix format
     */
    #[Route('GET', '/admin/permissions/matrix')]
    public function getPermissionMatrix(): JsonResponse
    {
        $roles = $this->repository->findAll(Role::class, ['weight' => 'DESC', 'name' => 'ASC']);
        $grouped = $this->permissions->getAllPermissionsGrouped();

        // Build matrix
        $matrix = [];
        foreach ($roles as $role) {
            $role->permissions = $this->permissions->getRolePermissions($role);
            $permissionSlugs = array_map(fn($p) => $p->slug, $role->permissions);

            $matrix[$role->id] = [
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'color' => $role->color,
                    'is_system' => $role->is_system,
                ],
                'permissions' => $permissionSlugs,
            ];
        }

        // Flatten permissions for easier UI consumption
        $allPermissions = [];
        foreach ($grouped as $group => $permissions) {
            foreach ($permissions as $permission) {
                $allPermissions[] = [
                    'id' => $permission->id,
                    'slug' => $permission->slug,
                    'name' => $permission->name,
                    'group' => $group,
                    'entity_type' => $permission->entity_type,
                    'action' => $permission->action,
                ];
            }
        }

        return new JsonResponse([
            'roles' => array_values($matrix),
            'permissions' => $allPermissions,
            'groups' => array_keys($grouped),
        ]);
    }

    /**
     * Update permission matrix (batch update)
     */
    #[Route('PUT', '/admin/permissions/matrix')]
    public function updatePermissionMatrix(Request $request): JsonResponse
    {
        $data = $request->getParsedBody();

        if (!isset($data['assignments']) || !is_array($data['assignments'])) {
            return new JsonResponse(['error' => 'Invalid assignments data'], 400);
        }

        // Process each role's permissions
        foreach ($data['assignments'] as $roleId => $permissionIds) {
            $role = $this->repository->find(Role::class, (int) $roleId);
            if ($role) {
                $this->permissions->setRolePermissions($role, $permissionIds);
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Permission matrix updated',
        ]);
    }
}
