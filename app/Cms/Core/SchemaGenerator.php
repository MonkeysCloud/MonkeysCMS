<?php

declare(strict_types=1);

namespace App\Cms\Core;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Attributes\Relation;
use ReflectionClass;
use ReflectionProperty;

/**
 * SchemaGenerator - Generates SQL DDL from entity class definitions
 *
 * This is the "auto-sync" engine that converts PHP entity classes with
 * attributes into MySQL CREATE TABLE statements.
 *
 * Key features:
 * - Reads #[ContentType], #[Field], #[Id], #[Relation] attributes
 * - Generates CREATE TABLE IF NOT EXISTS statements
 * - Creates indexes, unique constraints, and foreign keys
 * - Supports ALTER TABLE for schema evolution
 *
 * Unlike Drupal's hook_schema() or entity API, this is pure reflection-based,
 * requiring no configuration files or database queries for schema definition.
 *
 * Unlike WordPress, this creates proper normalized tables with typed columns,
 * not a single wp_postmeta EAV table.
 *
 * @example
 * ```php
 * $generator = new SchemaGenerator();
 * $sql = $generator->generateSql(Product::class);
 * $pdo->exec($sql);
 * ```
 */
final class SchemaGenerator
{
    /**
     * SQL statements buffer for batch execution
     * @var array<string>
     */
    private array $statements = [];

    /**
     * Inline index and constraint definitions for CREATE TABLE
     * @var array<string>
     */
    private array $tableConstraints = [];

    /**
     * Generate complete SQL schema for an entity class
     *
     * @param string $entityClass Fully qualified class name
     * @return string Complete SQL statement(s)
     * @throws \InvalidArgumentException If class is not a valid CMS entity
     */
    public function generateSql(string $entityClass): string
    {
        if (!class_exists($entityClass)) {
            throw new \InvalidArgumentException("Class '{$entityClass}' does not exist");
        }

        $reflection = new ReflectionClass($entityClass);

        // Get ContentType attribute
        $contentTypeAttrs = $reflection->getAttributes(ContentType::class);
        if (empty($contentTypeAttrs)) {
            throw new \InvalidArgumentException(
                "Class '{$entityClass}' is not a CMS ContentType (missing #[ContentType] attribute)"
            );
        }

        /** @var ContentType $contentType */
        $contentType = $contentTypeAttrs[0]->newInstance();
        $tableName = $contentType->tableName;

        // Reset state
        $this->statements = [];
        $this->tableConstraints = [];

        // Build column definitions
        $columns = $this->extractColumns($reflection);

        // Generate CREATE TABLE statement
        $createTable = $this->buildCreateTableStatement($tableName, $columns);
        $this->statements[] = $createTable;

        // Add revision table if revisionable
        if ($contentType->revisionable) {
            $this->statements[] = $this->generateRevisionTable($tableName, $columns);
        }
        
        // Wrap everything in foreign key checks disable/enable to handle circular dependencies
        // and allow creating tables with FKs to not-yet-existing tables.
        $sql = implode("\n\n", $this->statements);
        
        return "SET FOREIGN_KEY_CHECKS=0;\n\n" . $sql . "\n\nSET FOREIGN_KEY_CHECKS=1;";
    }

    /**
     * Generate SQL to alter an existing table to match entity definition
     *
     * @param string $entityClass Fully qualified class name
     * @param array<string, array<string, mixed>> $existingSchema Current table schema from DESCRIBE
     * @return string ALTER TABLE statements
     */
    public function generateAlterSql(string $entityClass, array $existingSchema): string
    {
        $reflection = new ReflectionClass($entityClass);
        $contentTypeAttrs = $reflection->getAttributes(ContentType::class);

        if (empty($contentTypeAttrs)) {
            throw new \InvalidArgumentException("Class is not a CMS ContentType");
        }

        /** @var ContentType $contentType */
        $contentType = $contentTypeAttrs[0]->newInstance();
        $tableName = $contentType->tableName;

        // Note: extractColumns populates $this->tableConstraints, but we ignore them for ALTER 
        // unless we want to diff/add missing indexes (not implemented yet)
        $this->tableConstraints = [];
        $columns = $this->extractColumns($reflection);
        $alterStatements = [];

        // Find new columns
        foreach ($columns as $columnName => $definition) {
            if (!isset($existingSchema[$columnName])) {
                $alterStatements[] = sprintf(
                    "ALTER TABLE `%s` ADD COLUMN %s;",
                    $tableName,
                    $definition
                );
            }
        }

        // Find columns that need modification (simplified - compare types)
        foreach ($columns as $columnName => $definition) {
            if (isset($existingSchema[$columnName])) {
                // Parse the definition to check if type changed
                $existingType = strtoupper($existingSchema[$columnName]['Type'] ?? '');
                $definedType = $this->extractTypeFromDefinition($definition);

                if ($existingType !== $definedType) {
                    $alterStatements[] = sprintf(
                        "ALTER TABLE `%s` MODIFY COLUMN %s;",
                        $tableName,
                        $definition
                    );
                }
            }
        }

        return implode("\n", $alterStatements);
    }

    /**
     * Generate SQL to compare and sync schema (for module enable)
     *
     * @param string $entityClass Fully qualified class name
     * @param bool $dropUnused Whether to drop columns not in entity definition
     * @return array{create: string, alter: string} SQL statements for create or alter
     */
    public function generateSyncSql(string $entityClass, bool $dropUnused = false): array
    {
        return [
            'create' => $this->generateSql($entityClass),
            'alter' => '', // Will be populated when compared against existing schema
        ];
    }

    /**
     * Extract column definitions from entity reflection
     *
     * @param ReflectionClass $reflection
     * @return array<string, string> Column name => full SQL definition
     */
    private function extractColumns(ReflectionClass $reflection): array
    {
        $columns = [];
        $tableName = '';

        // Get table name from ContentType
        $contentTypeAttrs = $reflection->getAttributes(ContentType::class);
        if (!empty($contentTypeAttrs)) {
            $tableName = $contentTypeAttrs[0]->newInstance()->tableName;
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $columnName = $this->propertyToColumn($property->getName());

            // Check for #[Id] attribute
            $idAttrs = $property->getAttributes(Id::class);
            if (!empty($idAttrs)) {
                /** @var Id $id */
                $id = $idAttrs[0]->newInstance();
                $columns[$columnName] = sprintf(
                    "`%s` %s NOT NULL%s PRIMARY KEY",
                    $columnName,
                    $id->toSqlType(),
                    $id->getAutoIncrementClause()
                );
                continue;
            }

            // Check for #[Field] attribute
            $fieldAttrs = $property->getAttributes(Field::class);
            if (!empty($fieldAttrs)) {
                /** @var Field $field */
                $field = $fieldAttrs[0]->newInstance();
                $columns[$columnName] = $this->buildColumnDefinition($columnName, $field, $tableName);
                continue;
            }

            // Check for #[Relation] attribute
            $relationAttrs = $property->getAttributes(Relation::class);
            if (!empty($relationAttrs)) {
                /** @var Relation $relation */
                $relation = $relationAttrs[0]->newInstance();

                // Only create column for owning side of ManyToOne/OneToOne
                if (
                    $relation->isOwningSide() &&
                    in_array($relation->type, [Relation::MANY_TO_ONE, Relation::ONE_TO_ONE], true)
                ) {
                    $fkColumn = $relation->getForeignKeyColumn($property->getName());
                    $columns[$fkColumn] = sprintf(
                        "`%s` INT UNSIGNED%s",
                        $fkColumn,
                        $relation->required ? ' NOT NULL' : ' DEFAULT NULL'
                    );

                    // Add foreign key constraint inline
                    $targetTable = $this->getTargetTableName($relation->target);
                    
                    // Explicit index for FK performance
                    $this->tableConstraints[] = sprintf(
                        "INDEX `idx_%s_%s` (`%s`)",
                        $tableName,
                        $fkColumn,
                        $fkColumn
                    );

                    // Inline FK Constraint
                    $this->tableConstraints[] = sprintf(
                        "CONSTRAINT `fk_%s_%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`id`) ON DELETE %s ON UPDATE %s",
                        $tableName,
                        $fkColumn,
                        $fkColumn,
                        $targetTable,
                        $relation->onDelete,
                        $relation->onUpdate
                    );
                }

                // Handle ManyToMany junction tables
                if ($relation->type === Relation::MANY_TO_MANY && $relation->isOwningSide()) {
                    $this->statements[] = $this->generateJunctionTable($tableName, $relation);
                }
            }
        }

        return $columns;
    }

    /**
     * Build a single column SQL definition from a Field attribute
     */
    private function buildColumnDefinition(string $columnName, Field $field, string $tableName): string
    {
        $parts = [sprintf("`%s`", $columnName)];

        // SQL type
        $parts[] = $field->toSqlType();

        // NOT NULL constraint
        if ($field->required) {
            $parts[] = 'NOT NULL';
        } else {
            $parts[] = 'NULL';
        }

        // Default value
        // Note: MySQL does not support defaults for BLOB/TEXT/JSON/GEOMETRY
        $noDefaultTypes = ['text', 'blob', 'json', 'geometry', 'longtext', 'mediumtext', 'tinytext'];
        $sqlTypeBase = strtolower(preg_replace('/\(.*/', '', $field->toSqlType()));

        if ($field->default !== null && !in_array($sqlTypeBase, $noDefaultTypes, true)) {
            $parts[] = sprintf("DEFAULT %s", $this->formatDefaultValue($field->default));
        } elseif (!$field->required && !in_array($sqlTypeBase, $noDefaultTypes, true)) {
            $parts[] = 'DEFAULT NULL';
        }

        // Handle unique constraint
        if ($field->unique) {
            // Inline unique key
            $this->tableConstraints[] = sprintf(
                "UNIQUE KEY `uk_%s_%s` (`%s`)",
                $tableName,
                $columnName,
                $columnName
            );
        }

        // Handle index
        if ($field->indexed && !$field->unique) {
            // Inline index
            $this->tableConstraints[] = sprintf(
                "INDEX `idx_%s_%s` (`%s`)",
                $tableName,
                $columnName,
                $columnName
            );
        }

        return implode(' ', $parts);
    }

    /**
     * Format PHP value for SQL DEFAULT clause
     */
    private function formatDefaultValue(mixed $value): string
    {
        if (is_string($value)) {
            return sprintf("'%s'", addslashes($value));
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return sprintf("'%s'", addslashes(json_encode($value)));
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return 'NULL';
    }

    /**
     * Build the CREATE TABLE statement
     */
    private function buildCreateTableStatement(string $tableName, array $columns): string
    {
        // Merge columns and inline constraints (indexes + FKs)
        $definitions = array_merge(array_values($columns), $this->tableConstraints);
        $body = implode(",\n    ", $definitions);

        return sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (\n    %s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            $tableName,
            $body
        );
    }

    /**
     * Generate a revision tracking table for revisionable content types
     */
    private function generateRevisionTable(string $baseTable, array $columns): string
    {
        $revisionTable = $baseTable . '_revision';

        // Modify columns for revision table
        $revisionColumns = [];
        $revisionColumns['revision_id'] = '`revision_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $revisionColumns['entity_id'] = '`entity_id` INT UNSIGNED NOT NULL';
        $revisionColumns['revision_created'] = '`revision_created` DATETIME NOT NULL';
        $revisionColumns['revision_user_id'] = '`revision_user_id` INT UNSIGNED DEFAULT NULL';
        $revisionColumns['revision_log'] = '`revision_log` TEXT DEFAULT NULL';

        foreach ($columns as $name => $def) {
            if ($name === 'id') {
                continue; // Skip the original PK
            }
            // Remove PRIMARY KEY from definitions for revision table
            $revisionColumns[$name] = preg_replace('/\s*PRIMARY KEY\s*/i', '', $def);
        }
        
        // Don't include unique keys in revision table as multiple revisions can have same values
        // We only want the column definitions here.
        // But $columns from extractColumns() are just strings.
        // Wait, $columns passed here are ONLY the column strings, not the inline indexes because 
        // generateSql calls extractColumns -> $columns, then buildCreateTable uses them + $this->inlineIndexes.
        // generateSql passes the raw $columns to generateRevisionTable.
        // So we are safe from including UNIQUE keys... UNLESS they are defined within the column string (which they are NOT in this implementation).
        // Correct.

        $columnDefs = implode(",\n    ", $revisionColumns);

        return sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (\n    %s,\n    INDEX `idx_entity_id` (`entity_id`),\n    FOREIGN KEY (`entity_id`) REFERENCES `%s`(`id`) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            $revisionTable,
            $columnDefs,
            $baseTable
        );
    }

    /**
     * Generate a junction table for ManyToMany relationships
     */
    private function generateJunctionTable(string $sourceTable, Relation $relation): string
    {
        $targetTable = $this->getTargetTableName($relation->target);
        $junctionTable = $relation->joinTable ?? $sourceTable . '_' . $targetTable;

        return sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
    `%s_id` INT UNSIGNED NOT NULL,
    `%s_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`%s_id`, `%s_id`),
    FOREIGN KEY (`%s_id`) REFERENCES `%s`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`%s_id`) REFERENCES `%s`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            $junctionTable,
            $sourceTable,
            $targetTable,
            $sourceTable,
            $targetTable,
            $sourceTable,
            $sourceTable,
            $targetTable,
            $targetTable
        );
    }

    /**
     * Get the table name from a target entity class
     */
    private function getTargetTableName(string $entityClass): string
    {
        if (!class_exists($entityClass)) {
            // Fallback: convert class name to table name
            $parts = explode('\\', $entityClass);
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', end($parts)) ?? '');
        }

        $reflection = new ReflectionClass($entityClass);
        $attrs = $reflection->getAttributes(ContentType::class);

        if (!empty($attrs)) {
            return $attrs[0]->newInstance()->tableName;
        }

        // Fallback: convert class name to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $reflection->getShortName()) ?? '');
    }

    /**
     * Convert camelCase property name to snake_case column name
     */
    private function propertyToColumn(string $property): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $property) ?? $property);
    }

    /**
     * Extract the SQL type from a column definition string
     */
    private function extractTypeFromDefinition(string $definition): string
    {
        // Extract type like "VARCHAR(255)" or "INT" from full definition
        if (preg_match('/`[^`]+`\s+([A-Z]+(?:\([^)]+\))?)/i', $definition, $matches)) {
            return strtoupper($matches[1]);
        }
        return '';
    }
}
