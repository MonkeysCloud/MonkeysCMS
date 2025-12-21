<?php

declare(strict_types=1);

namespace App\Cms\Fields\Storage;

use App\Cms\Fields\FieldDefinition;

/**
 * FieldValueStorageInterface - Contract for storing field values
 */
interface FieldValueStorageInterface
{
    /**
     * Get field value(s) for an entity
     * 
     * @param int $fieldId Field definition ID
     * @param string $entityType Entity type (e.g., 'node', 'user')
     * @param int $entityId Entity ID
     * @param string $langcode Language code
     * @return mixed Field value or array of values for multiple fields
     */
    public function getValue(int $fieldId, string $entityType, int $entityId, string $langcode = 'en'): mixed;

    /**
     * Get all field values for an entity
     * 
     * @return array<string, mixed> Field machine_name => value
     */
    public function getEntityValues(string $entityType, int $entityId, string $langcode = 'en'): array;

    /**
     * Set field value(s) for an entity
     * 
     * @param int $fieldId Field definition ID
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param mixed $value Field value
     * @param string $langcode Language code
     */
    public function setValue(int $fieldId, string $entityType, int $entityId, mixed $value, string $langcode = 'en'): void;

    /**
     * Set multiple field values for an entity
     * 
     * @param array<int, mixed> $values Field ID => value
     */
    public function setValues(string $entityType, int $entityId, array $values, string $langcode = 'en'): void;

    /**
     * Delete field value(s) for an entity
     */
    public function deleteValue(int $fieldId, string $entityType, int $entityId, string $langcode = 'en'): void;

    /**
     * Delete all field values for an entity
     */
    public function deleteEntityValues(string $entityType, int $entityId): void;

    /**
     * Copy field values to a new revision
     */
    public function createRevision(string $entityType, int $entityId, int $revisionId): void;
}

/**
 * FieldValueStorage - Database implementation for field value storage
 * 
 * Uses the EAV (Entity-Attribute-Value) pattern to store field values
 * with support for multiple value types and revisions.
 */
final class FieldValueStorage implements FieldValueStorageInterface
{
    private \PDO $db;
    private array $fieldCache = [];

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function getValue(int $fieldId, string $entityType, int $entityId, string $langcode = 'en'): mixed
    {
        $field = $this->getFieldDefinition($fieldId);
        if (!$field) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM field_values 
             WHERE field_id = :field_id 
             AND entity_type = :entity_type 
             AND entity_id = :entity_id 
             AND langcode = :langcode
             ORDER BY delta ASC"
        );

        $stmt->execute([
            'field_id' => $fieldId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'langcode' => $langcode,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return null;
        }

        $values = array_map(fn($row) => $this->extractValue($row, $field), $rows);

        // Return single value or array based on cardinality
        if ($field['multiple'] || $field['cardinality'] > 1) {
            return $values;
        }

        return $values[0] ?? null;
    }

    public function getEntityValues(string $entityType, int $entityId, string $langcode = 'en'): array
    {
        $stmt = $this->db->prepare(
            "SELECT fv.*, fd.machine_name, fd.field_type, fd.multiple, fd.cardinality
             FROM field_values fv
             JOIN field_definitions fd ON fd.id = fv.field_id
             WHERE fv.entity_type = :entity_type 
             AND fv.entity_id = :entity_id 
             AND fv.langcode = :langcode
             ORDER BY fv.field_id, fv.delta ASC"
        );

        $stmt->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'langcode' => $langcode,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $values = [];
        $fieldValues = [];

        // Group by field
        foreach ($rows as $row) {
            $machineName = $row['machine_name'];
            $fieldValues[$machineName][] = $row;
        }

        // Extract values
        foreach ($fieldValues as $machineName => $fieldRows) {
            $field = $fieldRows[0]; // Use first row for field info
            $extracted = array_map(fn($row) => $this->extractValue($row, $field), $fieldRows);

            if ($field['multiple'] || $field['cardinality'] > 1) {
                $values[$machineName] = $extracted;
            } else {
                $values[$machineName] = $extracted[0] ?? null;
            }
        }

        return $values;
    }

    public function setValue(int $fieldId, string $entityType, int $entityId, mixed $value, string $langcode = 'en'): void
    {
        $field = $this->getFieldDefinition($fieldId);
        if (!$field) {
            throw new \InvalidArgumentException("Field with ID {$fieldId} not found");
        }

        // Delete existing values
        $this->deleteValue($fieldId, $entityType, $entityId, $langcode);

        // Handle multiple values
        $values = is_array($value) && ($field['multiple'] || $field['cardinality'] > 1) 
            ? $value 
            : [$value];

        // Respect cardinality
        if ($field['cardinality'] > 0) {
            $values = array_slice($values, 0, $field['cardinality']);
        }

        // Insert new values
        $stmt = $this->db->prepare(
            "INSERT INTO field_values 
             (field_id, entity_type, entity_id, langcode, delta, 
              value_string, value_text, value_int, value_decimal, 
              value_boolean, value_date, value_datetime, value_json)
             VALUES 
             (:field_id, :entity_type, :entity_id, :langcode, :delta,
              :value_string, :value_text, :value_int, :value_decimal,
              :value_boolean, :value_date, :value_datetime, :value_json)"
        );

        foreach ($values as $delta => $val) {
            $params = $this->prepareValueParams($val, $field);
            $params['field_id'] = $fieldId;
            $params['entity_type'] = $entityType;
            $params['entity_id'] = $entityId;
            $params['langcode'] = $langcode;
            $params['delta'] = $delta;

            $stmt->execute($params);
        }
    }

    public function setValues(string $entityType, int $entityId, array $values, string $langcode = 'en'): void
    {
        $this->db->beginTransaction();

        try {
            foreach ($values as $fieldId => $value) {
                $this->setValue($fieldId, $entityType, $entityId, $value, $langcode);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteValue(int $fieldId, string $entityType, int $entityId, string $langcode = 'en'): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM field_values 
             WHERE field_id = :field_id 
             AND entity_type = :entity_type 
             AND entity_id = :entity_id 
             AND langcode = :langcode"
        );

        $stmt->execute([
            'field_id' => $fieldId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'langcode' => $langcode,
        ]);
    }

    public function deleteEntityValues(string $entityType, int $entityId): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM field_values 
             WHERE entity_type = :entity_type 
             AND entity_id = :entity_id"
        );

        $stmt->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    public function createRevision(string $entityType, int $entityId, int $revisionId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO field_revisions 
             (field_value_id, field_id, entity_type, entity_id, revision_id, langcode, delta,
              value_string, value_text, value_int, value_decimal, 
              value_boolean, value_date, value_datetime, value_json)
             SELECT id, field_id, entity_type, entity_id, :revision_id, langcode, delta,
                    value_string, value_text, value_int, value_decimal,
                    value_boolean, value_date, value_datetime, value_json
             FROM field_values
             WHERE entity_type = :entity_type AND entity_id = :entity_id"
        );

        $stmt->execute([
            'revision_id' => $revisionId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    /**
     * Get revision values
     */
    public function getRevisionValues(string $entityType, int $entityId, int $revisionId, string $langcode = 'en'): array
    {
        $stmt = $this->db->prepare(
            "SELECT fr.*, fd.machine_name, fd.field_type, fd.multiple, fd.cardinality
             FROM field_revisions fr
             JOIN field_definitions fd ON fd.id = fr.field_id
             WHERE fr.entity_type = :entity_type 
             AND fr.entity_id = :entity_id 
             AND fr.revision_id = :revision_id
             AND fr.langcode = :langcode
             ORDER BY fr.field_id, fr.delta ASC"
        );

        $stmt->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'revision_id' => $revisionId,
            'langcode' => $langcode,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $values = [];
        $fieldValues = [];

        foreach ($rows as $row) {
            $machineName = $row['machine_name'];
            $fieldValues[$machineName][] = $row;
        }

        foreach ($fieldValues as $machineName => $fieldRows) {
            $field = $fieldRows[0];
            $extracted = array_map(fn($row) => $this->extractValue($row, $field), $fieldRows);

            if ($field['multiple'] || $field['cardinality'] > 1) {
                $values[$machineName] = $extracted;
            } else {
                $values[$machineName] = $extracted[0] ?? null;
            }
        }

        return $values;
    }

    /**
     * Restore values from a revision
     */
    public function restoreRevision(string $entityType, int $entityId, int $revisionId): void
    {
        $this->db->beginTransaction();

        try {
            // Delete current values
            $this->deleteEntityValues($entityType, $entityId);

            // Copy revision values to current
            $stmt = $this->db->prepare(
                "INSERT INTO field_values 
                 (field_id, entity_type, entity_id, langcode, delta,
                  value_string, value_text, value_int, value_decimal,
                  value_boolean, value_date, value_datetime, value_json)
                 SELECT field_id, entity_type, entity_id, langcode, delta,
                        value_string, value_text, value_int, value_decimal,
                        value_boolean, value_date, value_datetime, value_json
                 FROM field_revisions
                 WHERE entity_type = :entity_type 
                 AND entity_id = :entity_id 
                 AND revision_id = :revision_id"
            );

            $stmt->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'revision_id' => $revisionId,
            ]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Extract value from database row based on field type
     */
    private function extractValue(array $row, array $field): mixed
    {
        $type = $field['field_type'];

        return match ($type) {
            'string', 'email', 'url', 'phone', 'slug', 'color', 'password' => $row['value_string'],
            'text', 'textarea', 'html', 'wysiwyg', 'markdown', 'code' => $row['value_text'],
            'integer' => $row['value_int'] !== null ? (int) $row['value_int'] : null,
            'float', 'decimal' => $row['value_decimal'] !== null ? (float) $row['value_decimal'] : null,
            'boolean' => $row['value_boolean'] !== null ? (bool) $row['value_boolean'] : null,
            'date' => $row['value_date'],
            'datetime', 'time' => $row['value_datetime'],
            'json', 'object', 'array', 'address', 'geolocation', 'link', 'gallery', 
            'entity_reference', 'taxonomy_reference', 'user_reference', 'repeater' => 
                $row['value_json'] ? json_decode($row['value_json'], true) : null,
            default => $row['value_string'] ?? $row['value_text'] ?? $row['value_json'],
        };
    }

    /**
     * Prepare value parameters for database insert
     */
    private function prepareValueParams(mixed $value, array $field): array
    {
        $params = [
            'value_string' => null,
            'value_text' => null,
            'value_int' => null,
            'value_decimal' => null,
            'value_boolean' => null,
            'value_date' => null,
            'value_datetime' => null,
            'value_json' => null,
        ];

        if ($value === null) {
            return $params;
        }

        $type = $field['field_type'];

        switch ($type) {
            case 'string':
            case 'email':
            case 'url':
            case 'phone':
            case 'slug':
            case 'color':
            case 'password':
                $params['value_string'] = (string) $value;
                break;

            case 'text':
            case 'textarea':
            case 'html':
            case 'wysiwyg':
            case 'markdown':
            case 'code':
                $params['value_text'] = (string) $value;
                break;

            case 'integer':
                $params['value_int'] = (int) $value;
                break;

            case 'float':
            case 'decimal':
                $params['value_decimal'] = (float) $value;
                break;

            case 'boolean':
                $params['value_boolean'] = $value ? 1 : 0;
                break;

            case 'date':
                $params['value_date'] = $value;
                break;

            case 'datetime':
            case 'time':
                $params['value_datetime'] = $value;
                break;

            case 'json':
            case 'object':
            case 'array':
            case 'address':
            case 'geolocation':
            case 'link':
            case 'gallery':
            case 'entity_reference':
            case 'taxonomy_reference':
            case 'user_reference':
            case 'repeater':
                $params['value_json'] = is_string($value) ? $value : json_encode($value);
                break;

            default:
                // Try to determine best storage
                if (is_array($value) || is_object($value)) {
                    $params['value_json'] = json_encode($value);
                } elseif (is_int($value)) {
                    $params['value_int'] = $value;
                } elseif (is_float($value)) {
                    $params['value_decimal'] = $value;
                } elseif (is_bool($value)) {
                    $params['value_boolean'] = $value ? 1 : 0;
                } elseif (strlen((string) $value) > 255) {
                    $params['value_text'] = (string) $value;
                } else {
                    $params['value_string'] = (string) $value;
                }
        }

        return $params;
    }

    /**
     * Get field definition by ID
     */
    private function getFieldDefinition(int $fieldId): ?array
    {
        if (isset($this->fieldCache[$fieldId])) {
            return $this->fieldCache[$fieldId];
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM field_definitions WHERE id = :id"
        );
        $stmt->execute(['id' => $fieldId]);

        $field = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($field) {
            $this->fieldCache[$fieldId] = $field;
        }

        return $field ?: null;
    }

    /**
     * Clear field cache
     */
    public function clearCache(): void
    {
        $this->fieldCache = [];
    }
}

/**
 * InMemoryFieldValueStorage - In-memory implementation for testing
 */
final class InMemoryFieldValueStorage implements FieldValueStorageInterface
{
    private array $values = [];
    private array $revisions = [];
    private array $fields = [];

    public function setFieldDefinitions(array $fields): void
    {
        foreach ($fields as $field) {
            $this->fields[$field['id']] = $field;
        }
    }

    public function getValue(int $fieldId, string $entityType, int $entityId, string $langcode = 'en'): mixed
    {
        $key = "{$entityType}:{$entityId}:{$langcode}";
        return $this->values[$key][$fieldId] ?? null;
    }

    public function getEntityValues(string $entityType, int $entityId, string $langcode = 'en'): array
    {
        $key = "{$entityType}:{$entityId}:{$langcode}";
        $result = [];

        foreach ($this->values[$key] ?? [] as $fieldId => $value) {
            $field = $this->fields[$fieldId] ?? null;
            if ($field) {
                $result[$field['machine_name']] = $value;
            }
        }

        return $result;
    }

    public function setValue(int $fieldId, string $entityType, int $entityId, mixed $value, string $langcode = 'en'): void
    {
        $key = "{$entityType}:{$entityId}:{$langcode}";
        $this->values[$key][$fieldId] = $value;
    }

    public function setValues(string $entityType, int $entityId, array $values, string $langcode = 'en'): void
    {
        foreach ($values as $fieldId => $value) {
            $this->setValue($fieldId, $entityType, $entityId, $value, $langcode);
        }
    }

    public function deleteValue(int $fieldId, string $entityType, int $entityId, string $langcode = 'en'): void
    {
        $key = "{$entityType}:{$entityId}:{$langcode}";
        unset($this->values[$key][$fieldId]);
    }

    public function deleteEntityValues(string $entityType, int $entityId): void
    {
        foreach (array_keys($this->values) as $key) {
            if (str_starts_with($key, "{$entityType}:{$entityId}:")) {
                unset($this->values[$key]);
            }
        }
    }

    public function createRevision(string $entityType, int $entityId, int $revisionId): void
    {
        $revKey = "{$entityType}:{$entityId}:{$revisionId}";
        
        foreach ($this->values as $key => $fieldValues) {
            if (str_starts_with($key, "{$entityType}:{$entityId}:")) {
                $this->revisions[$revKey] = $fieldValues;
            }
        }
    }
}
