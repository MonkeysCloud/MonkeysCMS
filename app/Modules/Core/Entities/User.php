<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Attributes\Relation;
use App\Cms\Core\BaseEntity;

/**
 * User Entity - Core user account for authentication and authorization
 */
#[ContentType(
    tableName: 'users',
    label: 'User',
    description: 'System users with authentication and role-based permissions',
    icon: '👤',
    revisionable: false,
    publishable: false
)]
class User extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(
        type: 'string',
        label: 'Email',
        required: true,
        length: 255,
        unique: true,
        searchable: true,
        widget: 'email'
    )]
    public string $email = '';

    #[Field(
        type: 'string',
        label: 'Username',
        required: true,
        length: 100,
        unique: true,
        searchable: true
    )]
    public string $username = '';

    #[Field(
        type: 'string',
        label: 'Password Hash',
        required: true,
        length: 255,
        widget: 'password'
    )]
    public string $password_hash = '';

    #[Field(
        type: 'string',
        label: 'Display Name',
        required: false,
        length: 255,
        searchable: true
    )]
    public string $display_name = '';

    #[Field(
        type: 'string',
        label: 'First Name',
        required: false,
        length: 100
    )]
    public string $first_name = '';

    #[Field(
        type: 'string',
        label: 'Last Name',
        required: false,
        length: 100
    )]
    public string $last_name = '';

    #[Field(
        type: 'string',
        label: 'Avatar URL',
        required: false,
        length: 500,
        widget: 'image'
    )]
    public ?string $avatar = null;

    #[Field(
        type: 'string',
        label: 'Status',
        required: true,
        length: 20,
        default: 'active',
        widget: 'select',
        options: ['active' => 'Active', 'inactive' => 'Inactive', 'blocked' => 'Blocked', 'pending' => 'Pending Verification']
    )]
    public string $status = 'active';

    #[Field(
        type: 'string',
        label: 'Locale',
        required: false,
        length: 10,
        default: 'en'
    )]
    public string $locale = 'en';

    #[Field(
        type: 'string',
        label: 'Timezone',
        required: false,
        length: 50,
        default: 'UTC'
    )]
    public string $timezone = 'UTC';

    #[Field(
        type: 'datetime',
        label: 'Email Verified At'
    )]
    public ?\DateTimeImmutable $email_verified_at = null;

    #[Field(
        type: 'datetime',
        label: 'Last Login'
    )]
    public ?\DateTimeImmutable $last_login_at = null;

    #[Field(
        type: 'string',
        label: 'Last Login IP',
        required: false,
        length: 45
    )]
    public ?string $last_login_ip = null;

    #[Field(
        type: 'int',
        label: 'Login Count',
        default: 0
    )]
    public int $login_count = 0;

    #[Field(
        type: 'json',
        label: 'Preferences',
        default: []
    )]
    public array $preferences = [];

    #[Field(
        type: 'json',
        label: 'Metadata',
        default: []
    )]
    public array $metadata = [];

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    /**
     * Roles assigned to this user (loaded separately)
     * @var Role[]
     */
    public array $roles = [];

    /**
     * Direct permissions (in addition to role permissions)
     * @var Permission[]
     */
    public array $directPermissions = [];

    // ─────────────────────────────────────────────────────────────
    // Business Logic
    // ─────────────────────────────────────────────────────────────

    public function prePersist(): void
    {
        parent::prePersist();

        if (empty($this->display_name)) {
            $this->display_name = $this->username;
        }
    }

    /**
     * Set password (hashes it)
     */
    public function setPassword(string $plainPassword): void
    {
        $this->password_hash = password_hash($plainPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ]);
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->password_hash);
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Get full name
     */
    public function getFullName(): string
    {
        $parts = array_filter([$this->first_name, $this->last_name]);
        return !empty($parts) ? implode(' ', $parts) : $this->display_name;
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $roleSlug): bool
    {
        foreach ($this->roles as $role) {
            if ($role->slug === $roleSlug) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permissionSlug): bool
    {
        // Check direct permissions first
        foreach ($this->directPermissions as $permission) {
            if ($permission->slug === $permissionSlug) {
                return true;
            }
        }

        // Check role permissions
        foreach ($this->roles as $role) {
            if ($role->hasPermission($permissionSlug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is a super admin (bypasses all permission checks)
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Get all permissions (from roles + direct)
     *
     * @return Permission[]
     */
    public function getAllPermissions(): array
    {
        $permissions = [];

        // Collect from roles
        foreach ($this->roles as $role) {
            foreach ($role->permissions as $permission) {
                $permissions[$permission->slug] = $permission;
            }
        }

        // Add direct permissions
        foreach ($this->directPermissions as $permission) {
            $permissions[$permission->slug] = $permission;
        }

        return array_values($permissions);
    }

    /**
     * Record a login
     */
    public function recordLogin(?string $ipAddress = null): void
    {
        $this->last_login_at = new \DateTimeImmutable();
        $this->last_login_ip = $ipAddress;
        $this->login_count++;
    }

    /**
     * Get preference value
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return $this->preferences[$key] ?? $default;
    }

    /**
     * Set preference value
     */
    public function setPreference(string $key, mixed $value): void
    {
        $this->preferences[$key] = $value;
    }

    /**
     * Convert to array (excludes sensitive data)
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        // Never expose password hash
        unset($data['password_hash']);

        // Add computed fields
        $data['full_name'] = $this->getFullName();
        $data['is_active'] = $this->isActive();
        $data['is_verified'] = $this->isEmailVerified();

        return $data;
    }

    /**
     * Convert to public array (safe for API responses)
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'avatar' => $this->avatar,
        ];
    }
}
