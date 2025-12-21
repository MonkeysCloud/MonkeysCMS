<?php

declare(strict_types=1);

namespace App\Cms\Core;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use DateTimeImmutable;
use ReflectionClass;
use ReflectionProperty;

/**
 * BaseEntity - Abstract base class for all CMS content types
 * 
 * Provides common functionality for all CMS entities:
 * - Automatic ID management
 * - Created/Updated timestamps
 * - Hydration from database arrays
 * - Serialization to arrays
 * - Dirty tracking for optimized updates
 * 
 * Every content type entity should extend this class.
 * 
 * @example
 * ```php
 * #[ContentType(tableName: 'products', label: 'Product')]
 * class Product extends BaseEntity
 * {
 *     #[Field(type: 'string', label: 'Name', required: true)]
 *     public string $name;
 * }
 * ```
 */
abstract class BaseEntity
{
    /**
     * Primary key - auto-incremented by default
     * Can be overridden in child classes with different strategy
     */
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    /**
     * Creation timestamp - automatically set on first persist
     */
    #[Field(type: 'datetime', label: 'Created At', required: true)]
    public ?DateTimeImmutable $created_at = null;

    /**
     * Last update timestamp - automatically updated on each persist
     */
    #[Field(type: 'datetime', label: 'Updated At', required: true)]
    public ?DateTimeImmutable $updated_at = null;

    /**
     * Track original values for dirty checking
     * @var array<string, mixed>
     */
    private array $originalValues = [];

    /**
     * Flag to track if this is a new entity
     */
    private bool $isNew = true;

    /**
     * Hydrate the entity from a database row array
     * 
     * This method maps database column values to entity properties,
     * handling type coercion for dates, booleans, and JSON fields.
     * 
     * @param array<string, mixed> $data Associative array from database
     * @return static
     */
    public function hydrate(array $data): static
    {
        $reflection = new ReflectionClass($this);

        foreach ($data as $column => $value) {
            // Convert snake_case column to camelCase property
            $propertyName = $this->columnToProperty($column);

            if (!$reflection->hasProperty($propertyName) && !$reflection->hasProperty($column)) {
                continue;
            }

            $actualPropertyName = $reflection->hasProperty($propertyName) ? $propertyName : $column;
            $property = $reflection->getProperty($actualPropertyName);
            
            // Get the property type for proper casting
            $type = $property->getType();
            $typeName = $type?->getName();
            
            // Handle null values
            if ($value === null) {
                if ($type?->allowsNull()) {
                    $property->setValue($this, null);
                }
                continue;
            }

            // Type coercion based on declared type
            $castedValue = match ($typeName) {
                'int' => (int) $value,
                'float' => (float) $value,
                'bool' => (bool) $value || $value === '1',
                'string' => (string) $value,
                'array' => is_string($value) ? json_decode($value, true) : (array) $value,
                DateTimeImmutable::class => $value instanceof DateTimeImmutable 
                    ? $value 
                    : new DateTimeImmutable($value),
                \DateTime::class => $value instanceof \DateTime 
                    ? $value 
                    : new \DateTime($value),
                default => $value,
            };

            $property->setValue($this, $castedValue);
            $this->originalValues[$actualPropertyName] = $castedValue;
        }

        $this->isNew = false;

        return $this;
    }

    /**
     * Convert entity to an associative array for database storage
     * 
     * @param bool $includeNulls Whether to include null values
     * @return array<string, mixed>
     */
    public function toArray(bool $includeNulls = false): array
    {
        $reflection = new ReflectionClass($this);
        $data = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $value = $property->getValue($this);

            // Skip null values unless explicitly included
            if ($value === null && !$includeNulls) {
                continue;
            }

            // Convert property name to snake_case column
            $column = $this->propertyToColumn($name);

            // Handle special types
            $data[$column] = match (true) {
                $value instanceof DateTimeImmutable,
                $value instanceof \DateTime => $value->format('Y-m-d H:i:s'),
                is_array($value) => json_encode($value),
                is_bool($value) => $value ? 1 : 0,
                is_object($value) && method_exists($value, 'getId') => $value->getId(),
                default => $value,
            };
        }

        return $data;
    }

    /**
     * Get only the modified fields since last hydration
     * 
     * @return array<string, mixed>
     */
    public function getDirtyFields(): array
    {
        $reflection = new ReflectionClass($this);
        $dirty = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $currentValue = $property->getValue($this);
            $originalValue = $this->originalValues[$name] ?? null;

            // Compare values (handling object comparison)
            $hasChanged = match (true) {
                $currentValue instanceof DateTimeImmutable && $originalValue instanceof DateTimeImmutable 
                    => $currentValue->getTimestamp() !== $originalValue->getTimestamp(),
                is_object($currentValue) && is_object($originalValue) 
                    => $currentValue != $originalValue,
                default => $currentValue !== $originalValue,
            };

            if ($hasChanged) {
                $column = $this->propertyToColumn($name);
                $dirty[$column] = match (true) {
                    $currentValue instanceof DateTimeImmutable,
                    $currentValue instanceof \DateTime => $currentValue->format('Y-m-d H:i:s'),
                    is_array($currentValue) => json_encode($currentValue),
                    is_bool($currentValue) => $currentValue ? 1 : 0,
                    default => $currentValue,
                };
            }
        }

        return $dirty;
    }

    /**
     * Check if this is a new entity (not yet persisted)
     */
    public function isNew(): bool
    {
        return $this->isNew || $this->id === null;
    }

    /**
     * Mark the entity as persisted
     */
    public function markPersisted(): void
    {
        $this->isNew = false;
        
        // Update original values to current state
        $reflection = new ReflectionClass($this);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $this->originalValues[$property->getName()] = $property->getValue($this);
        }
    }

    /**
     * Get the entity ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set timestamps before persist
     */
    public function prePersist(): void
    {
        $now = new DateTimeImmutable();
        
        if ($this->created_at === null) {
            $this->created_at = $now;
        }
        
        $this->updated_at = $now;
    }

    /**
     * Convert snake_case column name to camelCase property
     */
    private function columnToProperty(string $column): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $column))));
    }

    /**
     * Convert camelCase property to snake_case column
     */
    private function propertyToColumn(string $property): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $property) ?? $property);
    }

    /**
     * Get the ContentType attribute metadata from this entity
     * 
     * @return ContentType|null
     */
    public static function getContentTypeMetadata(): ?ContentType
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(ContentType::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Get the table name for this entity
     */
    public static function getTableName(): string
    {
        $metadata = static::getContentTypeMetadata();
        return $metadata?->tableName ?? strtolower(
            preg_replace('/(?<!^)[A-Z]/', '_$0', (new ReflectionClass(static::class))->getShortName()) ?? ''
        );
    }
}
