<?php

declare(strict_types=1);

namespace App\Cms\Attributes;

use Attribute;

/**
 * Field Attribute - Defines a content field on a CMS entity
 *
 * This attribute marks a property as a CMS field, specifying its database mapping,
 * validation rules, and admin UI configuration.
 *
 * Key improvements over Drupal:
 * - No separate field configuration tables - field definitions live in code
 * - Type-safe field definitions with PHP enums
 * - Automatic SQL type mapping
 *
 * Key improvements over WordPress:
 * - Fields are proper columns, not EAV meta values
 * - Native SQL queries, indexes, and foreign keys
 * - No serialization of complex data
 *
 * @example
 * ```php
 * #[Field(
 *     type: FieldType::STRING,
 *     label: 'Product Name',
 *     required: true,
 *     length: 255,
 *     searchable: true
 * )]
 * public string $name;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Field
{
    /**
     * @param string      $type        SQL-compatible type (string, text, int, decimal, datetime, boolean, json)
     * @param string      $label       Human-readable label for admin forms
     * @param bool        $required    Whether field is required (NOT NULL in DB)
     * @param int|null    $length      Max length for string types (VARCHAR size)
     * @param int|null    $precision   Decimal precision (total digits)
     * @param int|null    $scale       Decimal scale (digits after decimal point)
     * @param mixed       $default     Default value for the field
     * @param bool        $unique      Whether field requires unique constraint
     * @param bool        $indexed     Whether to create a database index
     * @param bool        $searchable  Whether field should be included in fulltext search
     * @param string      $description Help text shown in admin forms
     * @param string|null $widget      Admin UI widget type (text, textarea, wysiwyg, select, date, file, image)
     * @param array       $options     Widget configuration options (e.g., select choices)
     * @param int         $weight      Display order in forms (lower = higher)
     * @param string|null $group       Field group for organizing form sections
     * @param bool        $listable    Whether to show in admin list views
     * @param bool        $filterable  Whether to allow filtering in admin list
     * @param string|null $reference   For foreign keys: referenced table name
     */
    public function __construct(
        public string $type,
        public string $label,
        public bool $required = false,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public mixed $default = null,
        public bool $unique = false,
        public bool $indexed = false,
        public bool $searchable = false,
        public string $description = '',
        public ?string $widget = null,
        public array $options = [],
        public int $weight = 0,
        public ?string $group = null,
        public bool $listable = true,
        public bool $filterable = false,
        public ?string $reference = null,
    ) {
    }

    /**
     * Get the SQL column definition for this field
     */
    public function toSqlType(): string
    {
        return match ($this->type) {
            'string' => sprintf('VARCHAR(%d)', $this->length ?? 255),
            'text' => 'LONGTEXT',
            'int', 'integer' => 'INT',
            'bigint' => 'BIGINT',
            'smallint' => 'SMALLINT',
            'tinyint' => 'TINYINT',
            'decimal', 'float' => sprintf('DECIMAL(%d,%d)', $this->precision ?? 10, $this->scale ?? 2),
            'boolean', 'bool' => 'TINYINT(1)',
            'datetime' => 'DATETIME',
            'date' => 'DATE',
            'time' => 'TIME',
            'timestamp' => 'TIMESTAMP',
            'json' => 'JSON',
            'blob' => 'BLOB',
            'uuid' => 'CHAR(36)',
            default => 'VARCHAR(255)',
        };
    }

    /**
     * Infer the admin widget type if not explicitly set
     */
    public function getWidget(): string
    {
        if ($this->widget !== null) {
            return $this->widget;
        }

        return match ($this->type) {
            'text' => 'textarea',
            'boolean', 'bool' => 'checkbox',
            'datetime', 'date' => 'date',
            'json' => 'code',
            default => 'text',
        };
    }
}
