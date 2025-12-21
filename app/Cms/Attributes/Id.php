<?php

declare(strict_types=1);

namespace App\Cms\Attributes;

use Attribute;

/**
 * Id Attribute - Marks the primary key field of a content type
 * 
 * This attribute designates a property as the primary key, with support
 * for various ID generation strategies.
 * 
 * @example
 * ```php
 * #[Id(strategy: 'auto')]
 * public int $id;
 * 
 * #[Id(strategy: 'uuid')]
 * public string $id;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Id
{
    public const STRATEGY_AUTO = 'auto';      // AUTO_INCREMENT
    public const STRATEGY_UUID = 'uuid';      // UUID v4
    public const STRATEGY_ULID = 'ulid';      // ULID (sortable UUID)
    public const STRATEGY_SEQUENCE = 'sequence'; // Named sequence
    public const STRATEGY_NONE = 'none';      // Manually assigned

    /**
     * @param string      $strategy ID generation strategy
     * @param string|null $sequence Sequence name for 'sequence' strategy
     */
    public function __construct(
        public string $strategy = self::STRATEGY_AUTO,
        public ?string $sequence = null,
    ) {}

    /**
     * Get the SQL column definition for this ID
     */
    public function toSqlType(): string
    {
        return match ($this->strategy) {
            self::STRATEGY_UUID, self::STRATEGY_ULID => 'CHAR(36)',
            default => 'INT UNSIGNED',
        };
    }

    /**
     * Get the SQL AUTO_INCREMENT clause if applicable
     */
    public function getAutoIncrementClause(): string
    {
        return $this->strategy === self::STRATEGY_AUTO ? ' AUTO_INCREMENT' : '';
    }
}
