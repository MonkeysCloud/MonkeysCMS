<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * Permission Entity - Granular permissions for RBAC
 *
 * Permissions follow the pattern: {action}_{entity}
 * Examples: view_products, create_products, edit_products, delete_products
 *
 * Special permissions:
 * - view_own_{entity}: Can only view own content
 * - edit_own_{entity}: Can only edit own content
 * - delete_own_{entity}: Can only delete own content
 * - administer_{entity}: Full control over entity type
 */
#[ContentType(
    tableName: 'permissions',
    label: 'Permission',
    description: 'System permissions for role-based access control',
    icon: '🔐',
    revisionable: false,
    publishable: false
)]
class Permission extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(
        type: 'string',
        label: 'Name',
        required: true,
        length: 100,
        searchable: true
    )]
    public string $name = '';

    #[Field(
        type: 'string',
        label: 'Slug',
        required: true,
        length: 100,
        unique: true,
        indexed: true
    )]
    public string $slug = '';

    #[Field(
        type: 'text',
        label: 'Description',
        required: false,
        widget: 'textarea'
    )]
    public string $description = '';

    #[Field(
        type: 'string',
        label: 'Group',
        required: true,
        length: 100,
        indexed: true,
        widget: 'select'
    )]
    public string $group = 'general';

    #[Field(
        type: 'string',
        label: 'Entity Type',
        required: false,
        length: 100,
        indexed: true
    )]
    public ?string $entity_type = null;

    #[Field(
        type: 'string',
        label: 'Action',
        required: true,
        length: 50,
        widget: 'select',
        options: [
            'view' => 'View',
            'view_own' => 'View Own',
            'create' => 'Create',
            'edit' => 'Edit',
            'edit_own' => 'Edit Own',
            'delete' => 'Delete',
            'delete_own' => 'Delete Own',
            'publish' => 'Publish',
            'unpublish' => 'Unpublish',
            'administer' => 'Administer',
            'export' => 'Export',
            'import' => 'Import',
            'custom' => 'Custom'
        ]
    )]
    public string $action = 'view';

    #[Field(
        type: 'boolean',
        label: 'Is System Permission',
        default: false
    )]
    public bool $is_system = false;

    #[Field(
        type: 'string',
        label: 'Module',
        required: false,
        length: 100,
        indexed: true
    )]
    public ?string $module = null;

    #[Field(
        type: 'int',
        label: 'Weight',
        default: 0
    )]
    public int $weight = 0;

    #[Field(type: 'datetime', label: 'Created At')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime', label: 'Updated At')]
    public ?\DateTimeImmutable $updated_at = null;

    // ─────────────────────────────────────────────────────────────
    // Business Logic
    // ─────────────────────────────────────────────────────────────

    public function prePersist(): void
    {
        parent::prePersist();

        if (empty($this->slug) && !empty($this->action) && !empty($this->entity_type)) {
            $this->slug = $this->action . '_' . $this->entity_type;
        }
    }

    /**
     * Check if this is an "own content" permission
     */
    public function isOwnContentPermission(): bool
    {
        return str_contains($this->action, '_own');
    }

    /**
     * Get base action (without _own suffix)
     */
    public function getBaseAction(): string
    {
        return str_replace('_own', '', $this->action);
    }

    /**
     * Check if permission is protected
     */
    public function isProtected(): bool
    {
        return $this->is_system;
    }

    /**
     * Generate permissions for an entity type
     *
     * @return array<array<string, mixed>>
     */
    public static function generateForEntity(
        string $entityType,
        string $entityLabel,
        string $module,
        array $actions = ['view', 'create', 'edit', 'delete']
    ): array {
        $permissions = [];

        $actionLabels = [
            'view' => 'View',
            'view_own' => 'View own',
            'create' => 'Create',
            'edit' => 'Edit',
            'edit_own' => 'Edit own',
            'delete' => 'Delete',
            'delete_own' => 'Delete own',
            'publish' => 'Publish',
            'unpublish' => 'Unpublish',
            'administer' => 'Administer',
            'export' => 'Export',
            'import' => 'Import',
        ];

        foreach ($actions as $action) {
            $label = $actionLabels[$action] ?? ucfirst($action);

            $permissions[] = [
                'name' => "{$label} {$entityLabel}",
                'slug' => "{$action}_{$entityType}",
                'description' => "{$label} {$entityLabel} content",
                'group' => $entityLabel,
                'entity_type' => $entityType,
                'action' => $action,
                'module' => $module,
                'is_system' => false,
            ];
        }

        return $permissions;
    }

    /**
     * Get system permissions
     */
    public static function getSystemPermissions(): array
    {
        return [
            // Admin access
            [
                'name' => 'Access Administration',
                'slug' => 'access_admin',
                'description' => 'Access the administration area',
                'group' => 'System',
                'action' => 'custom',
                'is_system' => true,
                'weight' => 100,
            ],
            // User management
            [
                'name' => 'Administer Users',
                'slug' => 'administer_users',
                'description' => 'Full control over user accounts',
                'group' => 'Users',
                'entity_type' => 'users',
                'action' => 'administer',
                'is_system' => true,
            ],
            [
                'name' => 'View Users',
                'slug' => 'view_users',
                'description' => 'View user accounts',
                'group' => 'Users',
                'entity_type' => 'users',
                'action' => 'view',
                'is_system' => true,
            ],
            [
                'name' => 'Create Users',
                'slug' => 'create_users',
                'description' => 'Create new user accounts',
                'group' => 'Users',
                'entity_type' => 'users',
                'action' => 'create',
                'is_system' => true,
            ],
            [
                'name' => 'Edit Users',
                'slug' => 'edit_users',
                'description' => 'Edit user accounts',
                'group' => 'Users',
                'entity_type' => 'users',
                'action' => 'edit',
                'is_system' => true,
            ],
            [
                'name' => 'Delete Users',
                'slug' => 'delete_users',
                'description' => 'Delete user accounts',
                'group' => 'Users',
                'entity_type' => 'users',
                'action' => 'delete',
                'is_system' => true,
            ],
            // Role management
            [
                'name' => 'Administer Roles',
                'slug' => 'administer_roles',
                'description' => 'Full control over roles and permissions',
                'group' => 'Roles',
                'entity_type' => 'roles',
                'action' => 'administer',
                'is_system' => true,
            ],
            // Module management
            [
                'name' => 'Administer Modules',
                'slug' => 'administer_modules',
                'description' => 'Enable/disable modules',
                'group' => 'System',
                'action' => 'administer',
                'is_system' => true,
            ],
            // Theme management
            [
                'name' => 'Administer Themes',
                'slug' => 'administer_themes',
                'description' => 'Manage site themes',
                'group' => 'System',
                'action' => 'administer',
                'is_system' => true,
            ],
            // Settings
            [
                'name' => 'Administer Settings',
                'slug' => 'administer_settings',
                'description' => 'Manage site settings',
                'group' => 'System',
                'action' => 'administer',
                'is_system' => true,
            ],
            // Taxonomy
            [
                'name' => 'Administer Taxonomies',
                'slug' => 'administer_taxonomies',
                'description' => 'Manage vocabularies and terms',
                'group' => 'Taxonomy',
                'action' => 'administer',
                'is_system' => true,
            ],
        ];
    }
}
