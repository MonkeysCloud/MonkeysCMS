<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * EntityTerm Junction Entity - Links any entity to taxonomy terms
 * 
 * This is a polymorphic relationship table that can link any
 * content type to taxonomy terms.
 */
#[ContentType(
    tableName: 'entity_terms',
    label: 'Entity Term',
    description: 'Junction table linking entities to taxonomy terms',
    revisionable: false,
    publishable: false
)]
class EntityTerm extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(
        type: 'string',
        label: 'Entity Type',
        required: true,
        length: 100,
        indexed: true
    )]
    public string $entity_type = '';

    #[Field(
        type: 'int',
        label: 'Entity ID',
        required: true,
        indexed: true
    )]
    public int $entity_id = 0;

    #[Field(
        type: 'int',
        label: 'Term ID',
        required: true,
        indexed: true
    )]
    public int $term_id = 0;

    #[Field(
        type: 'int',
        label: 'Vocabulary ID',
        required: true,
        indexed: true
    )]
    public int $vocabulary_id = 0;

    #[Field(
        type: 'int',
        label: 'Weight',
        default: 0
    )]
    public int $weight = 0;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    /**
     * Term (loaded separately)
     */
    public ?Term $term = null;

    /**
     * Create an entity-term link
     */
    public static function create(
        string $entityType,
        int $entityId,
        int $termId,
        int $vocabularyId,
        int $weight = 0
    ): self {
        $et = new self();
        $et->entity_type = $entityType;
        $et->entity_id = $entityId;
        $et->term_id = $termId;
        $et->vocabulary_id = $vocabularyId;
        $et->weight = $weight;
        return $et;
    }
}
