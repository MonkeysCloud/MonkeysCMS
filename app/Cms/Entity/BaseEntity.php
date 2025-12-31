<?php

declare(strict_types=1);

namespace App\Cms\Entity;

/**
 * BaseEntity - Abstract base class for all CMS entities
 *
 * Provides common functionality for entities including:
 * - Property hydration and extraction
 * - Dirty tracking
 * - Type casting
 * - Array conversion
 */
abstract class BaseEntity implements EntityInterface, TimestampInterface
{
    protected ?int $id = null;
    protected ?\DateTimeImmutable $created_at = null;
    protected ?\DateTimeImmutable $updated_at = null;

    /** @var array<string, mixed> Original values for dirty tracking */
    protected array $original = [];

    /** @var bool Whether entity exists in database */
    protected bool $exists = false;

    /**
     * Create entity with optional data
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    // =========================================================================
    // EntityInterface Implementation
    // =========================================================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public static function getPrimaryKey(): string
    {
        return 'id';
    }

    public static function getFillable(): array
    {
        return [];
    }

    public static function getHidden(): array
    {
        return [];
    }

    public static function getCasts(): array
    {
        return [
            'id' => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function isDirty(): bool
    {
        return !empty($this->getDirty());
    }

    public function getDirty(): array
    {
        $dirty = [];
        $current = $this->toArray();

        foreach ($current as $key => $value) {
            if (!array_key_exists($key, $this->original)) {
                $dirty[$key] = $value;
            } elseif ($this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function getOriginal(): array
    {
        return $this->original;
    }

    // =========================================================================
    // TimestampInterface Implementation
    // =========================================================================

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updated_at = $updatedAt;
    }

    // =========================================================================
    // Data Handling
    // =========================================================================

    /**
     * Fill entity with data (mass assignment)
     *
     * @param array<string, mixed> $data
     */
    public function fill(array $data): static
    {
        $fillable = static::getFillable();
        $casts = static::getCasts();

        foreach ($data as $key => $value) {
            // Check if fillable (empty means all allowed)
            if (!empty($fillable) && !in_array($key, $fillable) && $key !== 'id') {
                continue;
            }

            // Cast value if needed
            if (isset($casts[$key])) {
                $value = $this->castValue($value, $casts[$key]);
            }

            // Set property if it exists
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    /**
     * Convert entity to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $hidden = static::getHidden();
        $data = [];

        // Get all public and protected properties
        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $property) {
            $name = $property->getName();

            // Skip internal properties
            if (in_array($name, ['original', 'exists'])) {
                continue;
            }

            // Skip hidden properties
            if (in_array($name, $hidden)) {
                continue;
            }

            $value = $this->$name;

            // Convert DateTime to string
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $data[$name] = $value;
        }

        return $data;
    }

    /**
     * Convert entity to array for database storage
     *
     * @return array<string, mixed>
     */
    public static function getTransient(): array
    {
        return [];
    }

    /**
     * Convert entity to array for database storage
     *
     * @return array<string, mixed>
     */
    public function toDatabase(): array
    {
        $data = [];
        $casts = static::getCasts();
        $transient = static::getTransient();

        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $property) {
            $name = $property->getName();

            // Skip internal properties
            if (in_array($name, ['original', 'exists'])) {
                continue;
            }

            // Skip transient properties (not in database)
            if (in_array($name, $transient)) {
                continue;
            }

            $value = $this->$name;

            // Convert for database
            $value = $this->convertForDatabase($value, $casts[$name] ?? null);

            $data[$name] = $value;
        }

        // Remove null id for inserts
        if ($data['id'] === null) {
            unset($data['id']);
        }

        return $data;
    }

    /**
     * Create entity from database row
     *
     * @param array<string, mixed> $data
     */
    public static function fromDatabase(array $data): static
    {
        $entity = new static();
        $casts = static::getCasts();

        foreach ($data as $key => $value) {
            if (property_exists($entity, $key)) {
                // Cast from database
                if (isset($casts[$key])) {
                    $value = $entity->castFromDatabase($value, $casts[$key]);
                }
                $entity->$key = $value;
            }
        }

        // Mark as existing and store original
        $entity->exists = true;
        $entity->original = $entity->toArray();

        return $entity;
    }

    /**
     * Sync original values with current state
     */
    public function syncOriginal(): void
    {
        $this->original = $this->toArray();
        $this->exists = true;
    }

    // =========================================================================
    // Type Casting
    // =========================================================================

    /**
     * Cast a value to specified type
     */
    protected function castValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array' => is_array($value) ? $value : json_decode($value, true) ?? [],
            'json', 'object' => is_string($value) ? json_decode($value, true) : $value,
            'datetime' => $this->castToDateTime($value),
            'date' => $this->castToDateTime($value)?->setTime(0, 0, 0),
            default => $value,
        };
    }

    /**
     * Cast value from database
     */
    protected function castFromDatabase(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json', 'object' => is_string($value) ? json_decode($value, true) : $value,
            'datetime', 'date' => $this->castToDateTime($value),
            default => $value,
        };
    }

    /**
     * Convert value for database storage
     */
    protected function convertForDatabase(mixed $value, ?string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }

    /**
     * Cast value to DateTimeImmutable
     */
    protected function castToDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value);
        }

        if (is_string($value)) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        if (is_int($value)) {
            return (new \DateTimeImmutable())->setTimestamp($value);
        }

        return null;
    }

    // =========================================================================
    // Magic Methods
    // =========================================================================

    /**
     * Get property value
     */
    public function __get(string $name): mixed
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        return null;
    }

    /**
     * Set property value
     */
    public function __set(string $name, mixed $value): void
    {
        if (property_exists($this, $name)) {
            $casts = static::getCasts();
            if (isset($casts[$name])) {
                $value = $this->castValue($value, $casts[$name]);
            }
            $this->$name = $value;
        }
    }

    /**
     * Check if property is set
     */
    public function __isset(string $name): bool
    {
        return property_exists($this, $name) && $this->$name !== null;
    }

    /**
     * JSON serialization
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

/**
 * SoftDeleteTrait - Add soft delete functionality to entities
 */
trait SoftDeleteTrait
{
    protected ?\DateTimeImmutable $deleted_at = null;

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): void
    {
        $this->deleted_at = $deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function restore(): void
    {
        $this->deleted_at = null;
    }
}

/**
 * RevisionTrait - Add revision tracking to entities
 */
trait RevisionTrait
{
    protected int $revision_id = 1;

    public function getRevisionId(): int
    {
        return $this->revision_id;
    }

    public function setRevisionId(int $revisionId): void
    {
        $this->revision_id = $revisionId;
    }

    public function incrementRevision(): void
    {
        $this->revision_id++;
    }
}
