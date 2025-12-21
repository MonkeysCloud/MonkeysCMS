<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * FieldValue - Immutable value object representing a field's value
 * 
 * Encapsulates the raw value along with type information and
 * provides type-safe access methods.
 */
final class FieldValue
{
    private function __construct(
        private readonly mixed $value,
        private readonly string $type,
        private readonly bool $isEmpty,
    ) {}

    // =========================================================================
    // Static Constructors
    // =========================================================================

    public static function of(mixed $value, string $type = 'string'): self
    {
        return new self(
            value: $value,
            type: $type,
            isEmpty: self::checkEmpty($value),
        );
    }

    public static function null(string $type = 'string'): self
    {
        return new self(null, $type, true);
    }

    public static function string(?string $value): self
    {
        return new self($value, 'string', $value === null || $value === '');
    }

    public static function integer(?int $value): self
    {
        return new self($value, 'integer', $value === null);
    }

    public static function float(?float $value): self
    {
        return new self($value, 'float', $value === null);
    }

    public static function boolean(?bool $value): self
    {
        return new self($value, 'boolean', $value === null);
    }

    public static function array(?array $value): self
    {
        return new self($value, 'array', $value === null || empty($value));
    }

    public static function date(?\DateTimeInterface $value): self
    {
        return new self($value, 'date', $value === null);
    }

    public static function fromSubmitted(mixed $value, string $fieldType): self
    {
        return new self(
            value: $value,
            type: $fieldType,
            isEmpty: self::checkEmpty($value),
        );
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function get(): mixed
    {
        return $this->value;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isEmpty(): bool
    {
        return $this->isEmpty;
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty;
    }

    // =========================================================================
    // Type-Safe Access
    // =========================================================================

    public function asString(): string
    {
        if ($this->value === null) {
            return '';
        }
        
        if (is_array($this->value)) {
            return json_encode($this->value);
        }
        
        if ($this->value instanceof \DateTimeInterface) {
            return $this->value->format('Y-m-d H:i:s');
        }
        
        return (string) $this->value;
    }

    public function asInt(): int
    {
        return (int) $this->value;
    }

    public function asFloat(): float
    {
        return (float) $this->value;
    }

    public function asBool(): bool
    {
        return (bool) $this->value;
    }

    public function asArray(): array
    {
        if (is_array($this->value)) {
            return $this->value;
        }
        
        if (is_string($this->value) && $this->value !== '') {
            $decoded = json_decode($this->value, true);
            return is_array($decoded) ? $decoded : [$this->value];
        }
        
        return $this->value !== null ? [$this->value] : [];
    }

    public function asDate(string $format = 'Y-m-d'): ?string
    {
        if ($this->value instanceof \DateTimeInterface) {
            return $this->value->format($format);
        }
        
        if (is_string($this->value) && $this->value !== '') {
            try {
                return (new \DateTimeImmutable($this->value))->format($format);
            } catch (\Exception) {
                return null;
            }
        }
        
        return null;
    }

    public function asDateTime(): ?\DateTimeImmutable
    {
        if ($this->value instanceof \DateTimeImmutable) {
            return $this->value;
        }
        
        if ($this->value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($this->value);
        }
        
        if (is_string($this->value) && $this->value !== '') {
            try {
                return new \DateTimeImmutable($this->value);
            } catch (\Exception) {
                return null;
            }
        }
        
        return null;
    }

    // =========================================================================
    // Transformations
    // =========================================================================

    public function map(callable $fn): self
    {
        if ($this->isEmpty) {
            return $this;
        }
        
        return self::of($fn($this->value), $this->type);
    }

    public function orElse(mixed $default): self
    {
        if ($this->isEmpty) {
            return self::of($default, $this->type);
        }
        
        return $this;
    }

    public function getOrDefault(mixed $default): mixed
    {
        return $this->isEmpty ? $default : $this->value;
    }

    public function filter(callable $predicate): self
    {
        if ($this->isEmpty || !$predicate($this->value)) {
            return self::null($this->type);
        }
        
        return $this;
    }

    // =========================================================================
    // Comparison
    // =========================================================================

    public function equals(self $other): bool
    {
        return $this->value === $other->value && $this->type === $other->type;
    }

    public function equalsValue(mixed $value): bool
    {
        return $this->value === $value;
    }

    // =========================================================================
    // Serialization
    // =========================================================================

    public function toJson(): string
    {
        return json_encode($this->value, JSON_THROW_ON_ERROR);
    }

    public function __toString(): string
    {
        return $this->asString();
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    private static function checkEmpty(mixed $value): bool
    {
        return $value === null 
            || $value === '' 
            || (is_array($value) && empty($value));
    }
}

/**
 * FieldValueCollection - Collection of field values
 */
final class FieldValueCollection implements \IteratorAggregate, \Countable
{
    /** @var array<string, FieldValue> */
    private array $values;

    private function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function fromArray(array $data, array $typeMap = []): self
    {
        $values = [];
        foreach ($data as $key => $value) {
            $type = $typeMap[$key] ?? 'string';
            $values[$key] = FieldValue::of($value, $type);
        }
        return new self($values);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function get(string $name): ?FieldValue
    {
        return $this->values[$name] ?? null;
    }

    public function getValue(string $name, mixed $default = null): mixed
    {
        return $this->values[$name]?->get() ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function with(string $name, FieldValue $value): self
    {
        $values = $this->values;
        $values[$name] = $value;
        return new self($values);
    }

    public function without(string $name): self
    {
        $values = $this->values;
        unset($values[$name]);
        return new self($values);
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->values, $other->values));
    }

    public function toArray(): array
    {
        return array_map(fn(FieldValue $v) => $v->get(), $this->values);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->values);
    }

    public function count(): int
    {
        return count($this->values);
    }
}
