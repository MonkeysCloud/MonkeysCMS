<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * EntityMedia Junction - Links media to any entity
 */
#[ContentType(
    tableName: 'entity_media',
    label: 'Entity Media',
    description: 'Junction table linking media to entities',
    revisionable: false,
    publishable: false
)]
class EntityMedia extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(type: 'string', label: 'Entity Type', required: true, length: 100, indexed: true)]
    public string $entity_type = '';

    #[Field(type: 'int', label: 'Entity ID', required: true, indexed: true)]
    public int $entity_id = 0;

    #[Field(type: 'int', label: 'Media ID', required: true, indexed: true)]
    public int $media_id = 0;

    #[Field(type: 'string', label: 'Field Name', required: true, length: 100, indexed: true)]
    public string $field_name = 'default';

    #[Field(type: 'int', label: 'Weight', default: 0)]
    public int $weight = 0;

    #[Field(type: 'string', label: 'Caption', required: false, length: 500)]
    public ?string $caption = null;

    #[Field(type: 'json', label: 'Metadata', default: [])]
    public array $metadata = [];

    #[Field(type: 'datetime', label: 'Created At')]
    public ?\DateTimeImmutable $created_at = null;

    public ?Media $media = null;

    public static function create(
        string $entityType,
        int $entityId,
        int $mediaId,
        string $fieldName = 'default',
        int $weight = 0
    ): self {
        $em = new self();
        $em->entity_type = $entityType;
        $em->entity_id = $entityId;
        $em->media_id = $mediaId;
        $em->field_name = $fieldName;
        $em->weight = $weight;
        return $em;
    }
}
