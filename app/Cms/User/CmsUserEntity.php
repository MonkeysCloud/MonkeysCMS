<?php

declare(strict_types=1);

namespace App\Cms\User;

use MonkeysLegion\Entity\Attributes\Column;
use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Id;

/**
 * CmsUserEntity — Admin user for the CMS backend.
 *
 * Separate from the framework User entity to keep CMS auth decoupled.
 */
#[Entity(table: 'cms_users')]
class CmsUserEntity
{
    #[Id]
    public ?int $id = null;

    #[Column(type: 'string', length: 100)]
    public string $name = '';

    #[Column(type: 'string', length: 255, unique: true)]
    public string $email = '';

    #[Column(type: 'string', length: 255)]
    public string $password = '';

    #[Column(type: 'string', length: 255, nullable: true)]
    public ?string $avatar = null;

    #[Column(type: 'integer')]
    public int $role_id = 0;

    #[Column(type: 'json', default: '[]')]
    public array $permissions = [];

    #[Column(type: 'boolean', default: true)]
    public bool $active = true;

    #[Column(type: 'string', length: 10, default: 'en')]
    public string $locale = 'en';

    #[Column(type: 'string', length: 50, nullable: true)]
    public ?string $timezone = null;

    #[Column(type: 'json', default: '{}')]
    public array $preferences = [];

    #[Column(type: 'datetime', nullable: true)]
    public ?\DateTimeImmutable $last_login_at = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    /** @var RoleEntity|null loaded via join */
    public ?RoleEntity $role = null;

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->role?->is_super_admin) {
            return true;
        }

        return in_array($permission, $this->permissions, true)
            || in_array($permission, $this->role?->permissions ?? [], true);
    }

    /**
     * Check if user can perform action on a content type
     */
    public function can(string $action, string $contentType): bool
    {
        return $this->hasPermission("{$contentType}.{$action}")
            || $this->hasPermission("content.{$action}")
            || $this->hasPermission('admin.*');
    }

    public function hydrate(array $data): static
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : $this->id;
        $this->name = $data['name'] ?? $this->name;
        $this->email = $data['email'] ?? $this->email;
        $this->password = $data['password'] ?? $this->password;
        $this->avatar = $data['avatar'] ?? $this->avatar;
        $this->role_id = (int) ($data['role_id'] ?? $this->role_id);
        $this->active = (bool) ($data['active'] ?? $this->active);
        $this->locale = $data['locale'] ?? $this->locale;
        $this->timezone = $data['timezone'] ?? $this->timezone;

        foreach (['permissions', 'preferences'] as $jsonField) {
            if (isset($data[$jsonField])) {
                $this->$jsonField = is_string($data[$jsonField])
                    ? (json_decode($data[$jsonField], true) ?? [])
                    : $data[$jsonField];
            }
        }

        foreach (['last_login_at', 'created_at', 'updated_at'] as $ts) {
            if (isset($data[$ts]) && $data[$ts] !== null) {
                $this->$ts = new \DateTimeImmutable($data[$ts]);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'user',
            'attributes' => [
                'name' => $this->name,
                'email' => $this->email,
                'avatar' => $this->avatar,
                'locale' => $this->locale,
                'active' => $this->active,
                'last_login_at' => $this->last_login_at?->format('c'),
            ],
            'relationships' => [
                'role' => ['type' => 'role', 'id' => (string) $this->role_id],
            ],
        ];
    }
}
