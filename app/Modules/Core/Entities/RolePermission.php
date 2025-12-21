<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * RolePermission Junction Entity - Links roles to permissions
 */
#[ContentType(
    tableName: 'role_permissions',
    label: 'Role Permission',
    description: 'Junction table linking roles to permissions',
    revisionable: false,
    publishable: false
)]
class RolePermission extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(
        type: 'int',
        label: 'Role ID',
        required: true,
        indexed: true
    )]
    public int $role_id = 0;

    #[Field(
        type: 'int',
        label: 'Permission ID',
        required: true,
        indexed: true
    )]
    public int $permission_id = 0;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    /**
     * Create a role-permission assignment
     */
    public static function create(int $roleId, int $permissionId): self
    {
        $rp = new self();
        $rp->role_id = $roleId;
        $rp->permission_id = $permissionId;
        return $rp;
    }
}
