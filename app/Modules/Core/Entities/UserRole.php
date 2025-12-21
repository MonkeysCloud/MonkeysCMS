<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * UserRole Junction Entity - Links users to roles
 */
#[ContentType(
    tableName: 'user_roles',
    label: 'User Role',
    description: 'Junction table linking users to roles',
    revisionable: false,
    publishable: false
)]
class UserRole extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(
        type: 'int',
        label: 'User ID',
        required: true,
        indexed: true
    )]
    public int $user_id = 0;

    #[Field(
        type: 'int',
        label: 'Role ID',
        required: true,
        indexed: true
    )]
    public int $role_id = 0;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    /**
     * Create a user-role assignment
     */
    public static function create(int $userId, int $roleId): self
    {
        $ur = new self();
        $ur->user_id = $userId;
        $ur->role_id = $roleId;
        return $ur;
    }
}
