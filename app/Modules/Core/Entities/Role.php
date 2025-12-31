<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Attributes\Ignore;
use App\Cms\Core\BaseEntity;

/**
 * Role Entity - Defines user roles for RBAC
 */
#[ContentType(
    tableName: 'roles',
    label: 'Role',
    description: 'User roles for role-based access control',
    icon: '🎭',
    revisionable: false,
    publishable: false
)]
class Role extends BaseEntity
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
        unique: true
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
        label: 'Color',
        required: false,
        length: 20,
        default: '#6b7280',
        widget: 'color'
    )]
    public string $color = '#6b7280';

    #[Field(
        type: 'int',
        label: 'Weight',
        default: 0,
        indexed: true
    )]
    public int $weight = 0;

    #[Field(
        type: 'boolean',
        label: 'Is System Role',
        default: false
    )]
    public bool $is_system = false;

    #[Field(
        type: 'boolean',
        label: 'Is Default',
        default: false
    )]
    public bool $is_default = false;

    #[Field(type: 'datetime', label: 'Created At')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime', label: 'Updated At')]
    public ?\DateTimeImmutable $updated_at = null;

    /**
     * Permissions assigned to this role (loaded separately)
     * @var Permission[]
     */
    #[Ignore]
    public array $permissions = [];

    // ─────────────────────────────────────────────────────────────
    // Business Logic
    // ─────────────────────────────────────────────────────────────

    public function prePersist(): void
    {
        parent::prePersist();

        if (empty($this->slug)) {
            $this->slug = $this->generateSlug($this->name);
        }
    }

    /**
     * Generate slug from name
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        return $slug;
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permissionSlug): bool
    {
        foreach ($this->permissions as $permission) {
            if ($permission->slug === $permissionSlug) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if this is a protected system role
     */
    public function isProtected(): bool
    {
        return $this->is_system;
    }

    /**
     * Get all permission slugs
     *
     * @return string[]
     */
    public function getPermissionSlugs(): array
    {
        return array_map(fn($p) => $p->slug, $this->permissions);
    }

    /**
     * Predefined system roles
     */
    public static function getSystemRoles(): array
    {
        return [
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Full system access - bypasses all permission checks',
                'color' => '#dc2626',
                'weight' => 100,
                'is_system' => true,
            ],
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Site administrator with most permissions',
                'color' => '#ea580c',
                'weight' => 90,
                'is_system' => true,
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Can create and edit all content',
                'color' => '#2563eb',
                'weight' => 50,
                'is_system' => true,
            ],
            [
                'name' => 'Author',
                'slug' => 'author',
                'description' => 'Can create and edit own content',
                'color' => '#16a34a',
                'weight' => 30,
                'is_system' => true,
            ],
            [
                'name' => 'Authenticated User',
                'slug' => 'authenticated',
                'description' => 'Basic authenticated user role',
                'color' => '#6b7280',
                'weight' => 10,
                'is_system' => true,
                'is_default' => true,
            ],
        ];
    }
}
