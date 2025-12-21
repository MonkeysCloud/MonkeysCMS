<?php

declare(strict_types=1);

namespace App\Cms\Attributes;

use Attribute;

/**
 * Relation Attribute - Defines relationships between content types
 * 
 * This attribute establishes foreign key relationships between entities,
 * enabling features like:
 * - Product -> Category (ManyToOne)
 * - Post -> Tags (ManyToMany)
 * - Order -> OrderItems (OneToMany)
 * 
 * Key improvements over Drupal Entity References:
 * - Proper SQL foreign keys with cascade options
 * - No separate field tables - direct column references
 * - Type-safe relationship definitions
 * 
 * Key improvements over WordPress relationships:
 * - No taxonomy/post_meta confusion
 * - Native JOIN support without plugins
 * - Enforced referential integrity
 * 
 * @example
 * ```php
 * #[Relation(
 *     type: RelationType::MANY_TO_ONE,
 *     target: Category::class,
 *     inversedBy: 'products',
 *     onDelete: 'SET NULL'
 * )]
 * public ?Category $category = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Relation
{
    public const MANY_TO_ONE = 'ManyToOne';
    public const ONE_TO_MANY = 'OneToMany';
    public const MANY_TO_MANY = 'ManyToMany';
    public const ONE_TO_ONE = 'OneToOne';

    /**
     * @param string      $type        Relationship type (ManyToOne, OneToMany, ManyToMany, OneToOne)
     * @param string      $target      Target entity class name (FQCN)
     * @param string      $label       Human-readable label for admin forms
     * @param string|null $inversedBy  Property name on the inverse side of the relation
     * @param string|null $mappedBy    Property name on the owning side (for inverse relations)
     * @param string      $onDelete    Foreign key ON DELETE action (CASCADE, SET NULL, RESTRICT)
     * @param string      $onUpdate    Foreign key ON UPDATE action (CASCADE, SET NULL, RESTRICT)
     * @param string|null $joinTable   Custom junction table name for ManyToMany (auto-generated if null)
     * @param string|null $joinColumn  Custom foreign key column name (auto-generated if null)
     * @param bool        $eager       Whether to eagerly load this relation by default
     * @param bool        $required    Whether the relation is required (NOT NULL for FK)
     * @param string|null $orderBy     Default ordering for OneToMany/ManyToMany collections
     */
    public function __construct(
        public string $type,
        public string $target,
        public string $label = '',
        public ?string $inversedBy = null,
        public ?string $mappedBy = null,
        public string $onDelete = 'SET NULL',
        public string $onUpdate = 'CASCADE',
        public ?string $joinTable = null,
        public ?string $joinColumn = null,
        public bool $eager = false,
        public bool $required = false,
        public ?string $orderBy = null,
    ) {}

    /**
     * Check if this is the owning side of the relationship
     */
    public function isOwningSide(): bool
    {
        return $this->mappedBy === null;
    }

    /**
     * Get the foreign key column name
     */
    public function getForeignKeyColumn(string $propertyName): string
    {
        return $this->joinColumn ?? $propertyName . '_id';
    }
}
