<?php

declare(strict_types=1);

namespace App\Cms\Fields\Definition;

/**
 * FieldCollection - Collection of field definitions
 * 
 * Provides iteration, filtering, and sorting capabilities for fields.
 * Implements domain logic for field management.
 */
final class FieldCollection implements \IteratorAggregate, \Countable
{
    /** @var array<string, Field> */
    private array $fields;

    private function __construct(array $fields)
    {
        $this->fields = $fields;
    }

    // =========================================================================
    // Factory Methods
    // =========================================================================

    public static function empty(): self
    {
        return new self([]);
    }

    public static function of(Field ...$fields): self
    {
        $indexed = [];
        foreach ($fields as $field) {
            $indexed[$field->getMachineName()] = $field;
        }
        return new self($indexed);
    }

    public static function fromArray(array $definitions): self
    {
        $fields = [];
        foreach ($definitions as $definition) {
            $field = Field::fromArray($definition);
            $fields[$field->getMachineName()] = $field;
        }
        return new self($fields);
    }

    // =========================================================================
    // Access
    // =========================================================================

    public function get(string $machineName): ?Field
    {
        return $this->fields[$machineName] ?? null;
    }

    public function getOrFail(string $machineName): Field
    {
        if (!isset($this->fields[$machineName])) {
            throw new \OutOfBoundsException("Field '{$machineName}' not found");
        }
        return $this->fields[$machineName];
    }

    public function has(string $machineName): bool
    {
        return isset($this->fields[$machineName]);
    }

    public function first(): ?Field
    {
        return reset($this->fields) ?: null;
    }

    public function last(): ?Field
    {
        return end($this->fields) ?: null;
    }

    /**
     * Get all fields as array
     * 
     * @return Field[]
     */
    public function all(): array
    {
        return array_values($this->fields);
    }

    /**
     * Get machine names
     * 
     * @return string[]
     */
    public function getMachineNames(): array
    {
        return array_keys($this->fields);
    }

    // =========================================================================
    // Mutations (return new instance)
    // =========================================================================

    public function add(Field $field): self
    {
        $fields = $this->fields;
        $fields[$field->getMachineName()] = $field;
        return new self($fields);
    }

    public function remove(string $machineName): self
    {
        $fields = $this->fields;
        unset($fields[$machineName]);
        return new self($fields);
    }

    public function replace(Field $field): self
    {
        return $this->add($field);
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->fields, $other->fields));
    }

    // =========================================================================
    // Filtering
    // =========================================================================

    public function filter(callable $predicate): self
    {
        return new self(array_filter($this->fields, $predicate));
    }

    public function filterByType(string $type): self
    {
        return $this->filter(fn(Field $f) => $f->getTypeName() === $type);
    }

    public function filterByTypes(array $types): self
    {
        return $this->filter(fn(Field $f) => in_array($f->getTypeName(), $types, true));
    }

    public function filterRequired(): self
    {
        return $this->filter(fn(Field $f) => $f->isRequired());
    }

    public function filterSearchable(): self
    {
        return $this->filter(fn(Field $f) => $f->isSearchable());
    }

    public function filterTranslatable(): self
    {
        return $this->filter(fn(Field $f) => $f->isTranslatable());
    }

    public function filterByGroup(string $group): self
    {
        return $this->filter(fn(Field $f) => $f->getSetting('group') === $group);
    }

    // =========================================================================
    // Sorting
    // =========================================================================

    public function sortByWeight(): self
    {
        $fields = $this->fields;
        uasort($fields, fn(Field $a, Field $b) => $a->getWeight() <=> $b->getWeight());
        return new self($fields);
    }

    public function sortByName(): self
    {
        $fields = $this->fields;
        uasort($fields, fn(Field $a, Field $b) => $a->getName() <=> $b->getName());
        return new self($fields);
    }

    public function sortBy(callable $comparator): self
    {
        $fields = $this->fields;
        uasort($fields, $comparator);
        return new self($fields);
    }

    public function reverse(): self
    {
        return new self(array_reverse($this->fields, true));
    }

    // =========================================================================
    // Grouping
    // =========================================================================

    /**
     * Group fields by their 'group' setting
     * 
     * @return array<string, self>
     */
    public function groupByGroup(): array
    {
        $groups = [];
        
        foreach ($this->fields as $field) {
            $group = $field->getSetting('group', 'General');
            
            if (!isset($groups[$group])) {
                $groups[$group] = [];
            }
            
            $groups[$group][$field->getMachineName()] = $field;
        }
        
        return array_map(fn($fields) => new self($fields), $groups);
    }

    /**
     * Group fields by type category
     * 
     * @return array<string, self>
     */
    public function groupByCategory(): array
    {
        $categories = [];
        
        foreach ($this->fields as $field) {
            $category = match (true) {
                $field->getType()->isText() => 'Text',
                $field->getType()->isNumeric() => 'Number',
                $field->getType()->isSelection() => 'Selection',
                $field->getType()->isDate() => 'Date/Time',
                $field->getType()->isMedia() => 'Media',
                $field->getType()->isReference() => 'Reference',
                default => 'Other',
            };
            
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            
            $categories[$category][$field->getMachineName()] = $field;
        }
        
        return array_map(fn($fields) => new self($fields), $categories);
    }

    // =========================================================================
    // Transformation
    // =========================================================================

    /**
     * Map over fields
     * 
     * @template T
     * @param callable(Field): T $fn
     * @return array<T>
     */
    public function map(callable $fn): array
    {
        return array_map($fn, array_values($this->fields));
    }

    /**
     * Reduce fields to a single value
     * 
     * @template T
     * @param callable(T, Field): T $fn
     * @param T $initial
     * @return T
     */
    public function reduce(callable $fn, mixed $initial): mixed
    {
        return array_reduce(array_values($this->fields), $fn, $initial);
    }

    /**
     * Check if any field matches predicate
     */
    public function any(callable $predicate): bool
    {
        foreach ($this->fields as $field) {
            if ($predicate($field)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if all fields match predicate
     */
    public function every(callable $predicate): bool
    {
        foreach ($this->fields as $field) {
            if (!$predicate($field)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Find first field matching predicate
     */
    public function find(callable $predicate): ?Field
    {
        foreach ($this->fields as $field) {
            if ($predicate($field)) {
                return $field;
            }
        }
        return null;
    }

    // =========================================================================
    // Serialization
    // =========================================================================

    public function toArray(): array
    {
        return $this->map(fn(Field $f) => $f->toArray());
    }

    // =========================================================================
    // Countable & IteratorAggregate
    // =========================================================================

    public function count(): int
    {
        return count($this->fields);
    }

    public function isEmpty(): bool
    {
        return empty($this->fields);
    }

    public function isNotEmpty(): bool
    {
        return !empty($this->fields);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(array_values($this->fields));
    }
}
