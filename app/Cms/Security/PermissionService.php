<?php

declare(strict_types=1);

namespace App\Cms\Security;

use App\Modules\Core\Entities\User;
use App\Modules\Core\Entities\Role;
use App\Modules\Core\Entities\Permission;
use App\Cms\Core\BaseEntity;
use MonkeysLegion\Database\Connection;

/**
 * PermissionService - Handles all authorization logic
 * 
 * Provides methods for checking user permissions against entities
 * and managing permission assignments.
 */
final class PermissionService
{
    private ?User $currentUser = null;
    private array $permissionCache = [];
    private array $roleCache = [];

    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Set the current user context
     */
    public function setCurrentUser(?User $user): void
    {
        $this->currentUser = $user;
        $this->permissionCache = [];
    }

    /**
     * Get the current user
     */
    public function getCurrentUser(): ?User
    {
        return $this->currentUser;
    }

    /**
     * Check if current user has a permission
     */
    public function can(string $permission): bool
    {
        if ($this->currentUser === null) {
            return false;
        }

        return $this->userCan($this->currentUser, $permission);
    }

    /**
     * Check if a specific user has a permission
     */
    public function userCan(User $user, string $permission): bool
    {
        // Super admins bypass all checks
        if ($this->userHasRole($user, 'super_admin')) {
            return true;
        }

        // Check cache first
        $cacheKey = $user->id . ':' . $permission;
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }

        // Load user permissions if not loaded
        if (empty($user->roles)) {
            $this->loadUserRoles($user);
        }

        $result = $user->hasPermission($permission);
        $this->permissionCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Check if current user can perform action on entity
     */
    public function canOnEntity(string $action, BaseEntity $entity): bool
    {
        if ($this->currentUser === null) {
            return false;
        }

        return $this->userCanOnEntity($this->currentUser, $action, $entity);
    }

    /**
     * Check if user can perform action on specific entity
     */
    public function userCanOnEntity(User $user, string $action, BaseEntity $entity): bool
    {
        // Super admins bypass all checks
        if ($this->userHasRole($user, 'super_admin')) {
            return true;
        }

        $entityType = $this->getEntityType($entity);

        // Check full permission first (e.g., edit_products)
        $fullPermission = $action . '_' . $entityType;
        if ($this->userCan($user, $fullPermission)) {
            return true;
        }

        // Check "own" permission (e.g., edit_own_products)
        $ownPermission = $action . '_own_' . $entityType;
        if ($this->userCan($user, $ownPermission)) {
            // Check if user owns this entity
            return $this->userOwnsEntity($user, $entity);
        }

        // Check administer permission
        $adminPermission = 'administer_' . $entityType;
        if ($this->userCan($user, $adminPermission)) {
            return true;
        }

        return false;
    }

    /**
     * Check if current user can perform action on entity type
     */
    public function canOnEntityType(string $action, string $entityType): bool
    {
        if ($this->currentUser === null) {
            return false;
        }

        return $this->userCanOnEntityType($this->currentUser, $action, $entityType);
    }

    /**
     * Check if user can perform action on entity type (not specific entity)
     */
    public function userCanOnEntityType(User $user, string $action, string $entityType): bool
    {
        // Super admins bypass all checks
        if ($this->userHasRole($user, 'super_admin')) {
            return true;
        }

        // Check full permission
        $fullPermission = $action . '_' . $entityType;
        if ($this->userCan($user, $fullPermission)) {
            return true;
        }

        // For "view" and "create", also check "own" versions
        if (in_array($action, ['view', 'create'], true)) {
            $ownPermission = $action . '_own_' . $entityType;
            if ($this->userCan($user, $ownPermission)) {
                return true;
            }
        }

        // Check administer permission
        $adminPermission = 'administer_' . $entityType;
        if ($this->userCan($user, $adminPermission)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user has a specific role
     */
    public function userHasRole(User $user, string $roleSlug): bool
    {
        if (empty($user->roles)) {
            $this->loadUserRoles($user);
        }

        return $user->hasRole($roleSlug);
    }

    /**
     * Check if current user has a role
     */
    public function hasRole(string $roleSlug): bool
    {
        if ($this->currentUser === null) {
            return false;
        }

        return $this->userHasRole($this->currentUser, $roleSlug);
    }

    /**
     * Check if user owns an entity
     */
    public function userOwnsEntity(User $user, BaseEntity $entity): bool
    {
        // Check for author_id or user_id property
        if (property_exists($entity, 'author_id') && $entity->author_id === $user->id) {
            return true;
        }

        if (property_exists($entity, 'user_id') && $entity->user_id === $user->id) {
            return true;
        }

        if (property_exists($entity, 'created_by') && $entity->created_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Get all permissions for an entity type
     * 
     * @return Permission[]
     */
    public function getEntityPermissions(string $entityType): array
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM permissions WHERE entity_type = :entity_type ORDER BY weight, name"
        );
        $stmt->execute(['entity_type' => $entityType]);
        
        $permissions = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $permission = new Permission();
            $permission->hydrate($row);
            $permissions[] = $permission;
        }

        return $permissions;
    }

    /**
     * Get all permissions grouped by group
     * 
     * @return array<string, Permission[]>
     */
    public function getAllPermissionsGrouped(): array
    {
        $stmt = $this->connection->query(
            "SELECT * FROM permissions ORDER BY `group`, weight, name"
        );

        $grouped = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $permission = new Permission();
            $permission->hydrate($row);
            $grouped[$permission->group][] = $permission;
        }

        return $grouped;
    }

    /**
     * Get role's permissions
     * 
     * @return Permission[]
     */
    public function getRolePermissions(Role $role): array
    {
        $stmt = $this->connection->prepare("
            SELECT p.* FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id
            ORDER BY p.`group`, p.weight, p.name
        ");
        $stmt->execute(['role_id' => $role->id]);

        $permissions = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $permission = new Permission();
            $permission->hydrate($row);
            $permissions[] = $permission;
        }

        return $permissions;
    }

    /**
     * Assign permission to role
     */
    public function assignPermissionToRole(Role $role, Permission $permission): void
    {
        // Check if already assigned
        $stmt = $this->connection->prepare(
            "SELECT id FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id"
        );
        $stmt->execute([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        if ($stmt->fetch()) {
            return; // Already assigned
        }

        $stmt = $this->connection->prepare(
            "INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:role_id, :permission_id, :created_at)"
        );
        $stmt->execute([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // Clear cache
        $this->roleCache = [];
        $this->permissionCache = [];
    }

    /**
     * Remove permission from role
     */
    public function removePermissionFromRole(Role $role, Permission $permission): void
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id"
        );
        $stmt->execute([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        // Clear cache
        $this->roleCache = [];
        $this->permissionCache = [];
    }

    /**
     * Set role permissions (replaces all existing)
     * 
     * @param int[] $permissionIds
     */
    public function setRolePermissions(Role $role, array $permissionIds): void
    {
        // Remove all existing permissions
        $stmt = $this->connection->prepare(
            "DELETE FROM role_permissions WHERE role_id = :role_id"
        );
        $stmt->execute(['role_id' => $role->id]);

        // Add new permissions
        if (!empty($permissionIds)) {
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $stmt = $this->connection->prepare(
                "INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:role_id, :permission_id, :created_at)"
            );

            foreach ($permissionIds as $permissionId) {
                $stmt->execute([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                ]);
            }
        }

        // Clear cache
        $this->roleCache = [];
        $this->permissionCache = [];
    }

    /**
     * Assign role to user
     */
    public function assignRoleToUser(User $user, Role $role): void
    {
        // Check if already assigned
        $stmt = $this->connection->prepare(
            "SELECT id FROM user_roles WHERE user_id = :user_id AND role_id = :role_id"
        );
        $stmt->execute([
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        if ($stmt->fetch()) {
            return; // Already assigned
        }

        $stmt = $this->connection->prepare(
            "INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:user_id, :role_id, :created_at)"
        );
        $stmt->execute([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // Clear user's role cache
        $user->roles = [];
        $this->permissionCache = [];
    }

    /**
     * Remove role from user
     */
    public function removeRoleFromUser(User $user, Role $role): void
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id"
        );
        $stmt->execute([
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        // Clear user's role cache
        $user->roles = [];
        $this->permissionCache = [];
    }

    /**
     * Set user roles (replaces all existing)
     * 
     * @param int[] $roleIds
     */
    public function setUserRoles(User $user, array $roleIds): void
    {
        // Remove all existing roles
        $stmt = $this->connection->prepare(
            "DELETE FROM user_roles WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $user->id]);

        // Add new roles
        if (!empty($roleIds)) {
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $stmt = $this->connection->prepare(
                "INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:user_id, :role_id, :created_at)"
            );

            foreach ($roleIds as $roleId) {
                $stmt->execute([
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'created_at' => $now,
                ]);
            }
        }

        // Clear user's role cache
        $user->roles = [];
        $this->permissionCache = [];
    }

    /**
     * Load user's roles and permissions
     */
    public function loadUserRoles(User $user): void
    {
        // Load roles
        $stmt = $this->connection->prepare("
            SELECT r.* FROM roles r
            INNER JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = :user_id
            ORDER BY r.weight DESC
        ");
        $stmt->execute(['user_id' => $user->id]);

        $user->roles = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $role = new Role();
            $role->hydrate($row);
            
            // Load role's permissions
            $role->permissions = $this->getRolePermissions($role);
            
            $user->roles[] = $role;
        }
    }

    /**
     * Register permissions for an entity type
     * 
     * @param string[] $actions
     */
    public function registerEntityPermissions(
        string $entityType,
        string $entityLabel,
        string $module,
        array $actions = ['view', 'view_own', 'create', 'edit', 'edit_own', 'delete', 'delete_own']
    ): void {
        $permissions = Permission::generateForEntity($entityType, $entityLabel, $module, $actions);

        foreach ($permissions as $data) {
            // Check if permission exists
            $stmt = $this->connection->prepare(
                "SELECT id FROM permissions WHERE slug = :slug"
            );
            $stmt->execute(['slug' => $data['slug']]);

            if ($stmt->fetch()) {
                continue; // Already exists
            }

            // Insert new permission
            $stmt = $this->connection->prepare("
                INSERT INTO permissions (name, slug, description, `group`, entity_type, action, module, is_system, weight, created_at)
                VALUES (:name, :slug, :description, :group, :entity_type, :action, :module, :is_system, :weight, :created_at)
            ");
            $stmt->execute([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'],
                'group' => $data['group'],
                'entity_type' => $data['entity_type'],
                'action' => $data['action'],
                'module' => $data['module'],
                'is_system' => $data['is_system'] ? 1 : 0,
                'weight' => $data['weight'] ?? 0,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Get entity type name from entity class
     */
    private function getEntityType(BaseEntity $entity): string
    {
        $class = get_class($entity);
        $reflection = new \ReflectionClass($class);
        
        // Try to get from ContentType attribute
        $attributes = $reflection->getAttributes(\App\Cms\Attributes\ContentType::class);
        if (!empty($attributes)) {
            $contentType = $attributes[0]->newInstance();
            return $contentType->tableName;
        }

        // Fallback to class name
        $shortName = $reflection->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
    }

    /**
     * Assert current user has permission (throws if not)
     * 
     * @throws \RuntimeException
     */
    public function authorize(string $permission): void
    {
        if (!$this->can($permission)) {
            throw new \RuntimeException("Access denied: requires '{$permission}' permission");
        }
    }

    /**
     * Assert current user can perform action on entity
     * 
     * @throws \RuntimeException
     */
    public function authorizeEntity(string $action, BaseEntity $entity): void
    {
        if (!$this->canOnEntity($action, $entity)) {
            $entityType = $this->getEntityType($entity);
            throw new \RuntimeException("Access denied: cannot '{$action}' on '{$entityType}'");
        }
    }
}
