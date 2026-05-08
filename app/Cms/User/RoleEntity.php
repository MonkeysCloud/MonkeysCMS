<?php

declare(strict_types=1);

namespace App\Cms\User;

use MonkeysLegion\Entity\Attributes\Column;
use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Id;

/**
 * RoleEntity — CMS admin role with permissions.
 */
#[Entity(table: 'cms_roles')]
class RoleEntity
{
    #[Id]
    public ?int $id = null;

    #[Column(type: 'string', length: 64, unique: true)]
    public string $machine_name = '';

    #[Column(type: 'string', length: 128)]
    public string $label = '';

    #[Column(type: 'text', nullable: true)]
    public ?string $description = null;

    #[Column(type: 'json', default: '[]')]
    public array $permissions = [];

    #[Column(type: 'boolean', default: false)]
    public bool $is_super_admin = false;

    #[Column(type: 'boolean', default: false)]
    public bool $is_system = false;

    #[Column(type: 'integer', default: 0)]
    public int $weight = 0;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    public function hydrate(array $data): static
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : $this->id;
        $this->machine_name = $data['machine_name'] ?? $this->machine_name;
        $this->label = $data['label'] ?? $this->label;
        $this->description = $data['description'] ?? $this->description;
        $this->is_super_admin = (bool) ($data['is_super_admin'] ?? $this->is_super_admin);
        $this->is_system = (bool) ($data['is_system'] ?? $this->is_system);
        $this->weight = (int) ($data['weight'] ?? $this->weight);

        if (isset($data['permissions'])) {
            $this->permissions = is_string($data['permissions'])
                ? (json_decode($data['permissions'], true) ?? [])
                : $data['permissions'];
        }

        $this->created_at = isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : $this->created_at;
        $this->updated_at = isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : $this->updated_at;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'machine_name' => $this->machine_name,
            'label' => $this->label,
            'description' => $this->description,
            'permissions' => $this->permissions,
            'is_super_admin' => $this->is_super_admin,
        ];
    }
}
