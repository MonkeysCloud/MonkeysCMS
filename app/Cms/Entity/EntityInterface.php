<?php

declare(strict_types=1);

namespace App\Cms\Entity;

/**
 * EntityInterface - Contract for all CMS entities
 *
 * All entities in the CMS must implement this interface to work
 * with the EntityManager and related infrastructure.
 */
interface EntityInterface
{
    /**
     * Get the entity's unique identifier
     */
    public function getId(): ?int;

    /**
     * Set the entity's unique identifier
     */
    public function setId(?int $id): void;

    /**
     * Get the database table name for this entity
     */
    public static function getTableName(): string;

    /**
     * Get the primary key column name
     */
    public static function getPrimaryKey(): string;

    /**
     * Get fillable fields (mass assignable)
     *
     * @return string[]
     */
    public static function getFillable(): array;

    /**
     * Get hidden fields (excluded from toArray)
     *
     * @return string[]
     */
    public static function getHidden(): array;

    /**
     * Get field casts (type conversions)
     *
     * @return array<string, string>
     */
    public static function getCasts(): array;

    /**
     * Convert entity to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Convert entity to array for database storage
     *
     * @return array<string, mixed>
     */
    public function toDatabase(): array;

    /**
     * Create entity from database row
     *
     * @param array<string, mixed> $data
     */
    public static function fromDatabase(array $data): static;

    /**
     * Check if entity exists in database
     */
    public function exists(): bool;

    /**
     * Check if entity has been modified
     */
    public function isDirty(): bool;

    /**
     * Get modified attributes
     *
     * @return array<string, mixed>
     */
    public function getDirty(): array;

    /**
     * Get original attribute values
     *
     * @return array<string, mixed>
     */
    public function getOriginal(): array;
}

/**
 * SoftDeleteInterface - For entities that support soft deletes
 */
interface SoftDeleteInterface
{
    /**
     * Get the deleted at timestamp
     */
    public function getDeletedAt(): ?\DateTimeImmutable;

    /**
     * Set the deleted at timestamp
     */
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): void;

    /**
     * Check if entity is soft deleted
     */
    public function isDeleted(): bool;

    /**
     * Restore a soft deleted entity
     */
    public function restore(): void;
}

/**
 * TimestampInterface - For entities with created/updated timestamps
 */
interface TimestampInterface
{
    public function getCreatedAt(): ?\DateTimeImmutable;
    public function setCreatedAt(\DateTimeImmutable $createdAt): void;
    public function getUpdatedAt(): ?\DateTimeImmutable;
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void;
}

/**
 * RevisionInterface - For entities that support revisions
 */
interface RevisionInterface
{
    public function getRevisionId(): int;
    public function setRevisionId(int $revisionId): void;
    public function incrementRevision(): void;
}
