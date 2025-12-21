<?php

declare(strict_types=1);

namespace App\Cms\Fields;

/**
 * FieldRepositoryInterface - Contract for field persistence
 */
interface FieldRepositoryInterface
{
    public function find(int $id): ?FieldDefinition;
    public function findByMachineName(string $machineName): ?FieldDefinition;
    public function findAll(): array;
    public function findByEntityType(string $entityType, ?int $bundleId = null): array;
    public function findByIds(array $ids): array;
    public function save(FieldDefinition $field): void;
    public function delete(FieldDefinition $field): void;
}

/**
 * FieldRepository - Database repository for field definitions
 * 
 * Provides CRUD operations for field definitions with
 * support for entity type filtering and caching.
 */
final class FieldRepository implements FieldRepositoryInterface
{
    private \PDO $db;
    private string $tableName = 'field_definitions';
    private string $attachmentsTable = 'field_attachments';
    
    /** @var array<int, FieldDefinition> */
    private array $cache = [];

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Find a field by ID
     */
    public function find(int $id): ?FieldDefinition
    {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $field = $this->hydrate($row);
        $this->cache[$id] = $field;

        return $field;
    }

    /**
     * Find a field by machine name
     */
    public function findByMachineName(string $machineName): ?FieldDefinition
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->tableName} WHERE machine_name = :name LIMIT 1"
        );
        $stmt->execute(['name' => $machineName]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $field = $this->hydrate($row);
        $this->cache[$field->id] = $field;

        return $field;
    }

    /**
     * Find all field definitions
     * 
     * @return FieldDefinition[]
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM {$this->tableName} ORDER BY weight ASC, name ASC"
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->hydrateMany($rows);
    }

    /**
     * Find fields attached to an entity type
     * 
     * @return FieldDefinition[]
     */
    public function findByEntityType(string $entityType, ?int $bundleId = null): array
    {
        $sql = "SELECT f.* FROM {$this->tableName} f
                INNER JOIN {$this->attachmentsTable} a ON f.id = a.field_id
                WHERE a.entity_type = :entity_type";
        
        $params = ['entity_type' => $entityType];
        
        if ($bundleId !== null) {
            $sql .= " AND a.bundle_id = :bundle_id";
            $params['bundle_id'] = $bundleId;
        }
        
        $sql .= " ORDER BY a.weight ASC, f.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->hydrateMany($rows);
    }

    /**
     * Find fields by multiple IDs
     * 
     * @param int[] $ids
     * @return FieldDefinition[]
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->tableName} WHERE id IN ({$placeholders}) ORDER BY weight ASC"
        );
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->hydrateMany($rows);
    }

    /**
     * Save a field definition (insert or update)
     */
    public function save(FieldDefinition $field): void
    {
        $data = $field->toDatabase();

        if ($field->id === null) {
            // Insert
            $data['created_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->tableName} ({$columns}) VALUES ({$placeholders})"
            );
            $stmt->execute($data);
            
            $field->id = (int) $this->db->lastInsertId();
        } else {
            // Update
            $sets = [];
            foreach (array_keys($data) as $column) {
                $sets[] = "{$column} = :{$column}";
            }
            $data['id'] = $field->id;
            
            $stmt = $this->db->prepare(
                "UPDATE {$this->tableName} SET " . implode(', ', $sets) . " WHERE id = :id"
            );
            $stmt->execute($data);
        }

        // Update cache
        $this->cache[$field->id] = $field;
    }

    /**
     * Delete a field definition
     */
    public function delete(FieldDefinition $field): void
    {
        if ($field->id === null) {
            return;
        }

        // Delete attachments first
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->attachmentsTable} WHERE field_id = :id"
        );
        $stmt->execute(['id' => $field->id]);

        // Delete field
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->tableName} WHERE id = :id"
        );
        $stmt->execute(['id' => $field->id]);

        // Clear cache
        unset($this->cache[$field->id]);
    }

    // =========================================================================
    // Field Attachments
    // =========================================================================

    /**
     * Attach a field to an entity type/bundle
     */
    public function attachToEntity(
        FieldDefinition $field,
        string $entityType,
        ?int $bundleId = null,
        int $weight = 0,
        array $settings = []
    ): void {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->attachmentsTable} 
            (field_id, entity_type, bundle_id, weight, settings) 
            VALUES (:field_id, :entity_type, :bundle_id, :weight, :settings)
            ON DUPLICATE KEY UPDATE weight = :weight2, settings = :settings2"
        );

        $stmt->execute([
            'field_id' => $field->id,
            'entity_type' => $entityType,
            'bundle_id' => $bundleId,
            'weight' => $weight,
            'settings' => json_encode($settings),
            'weight2' => $weight,
            'settings2' => json_encode($settings),
        ]);
    }

    /**
     * Detach a field from an entity type/bundle
     */
    public function detachFromEntity(
        FieldDefinition $field,
        string $entityType,
        ?int $bundleId = null
    ): void {
        $sql = "DELETE FROM {$this->attachmentsTable} 
                WHERE field_id = :field_id AND entity_type = :entity_type";
        
        $params = [
            'field_id' => $field->id,
            'entity_type' => $entityType,
        ];

        if ($bundleId !== null) {
            $sql .= " AND bundle_id = :bundle_id";
            $params['bundle_id'] = $bundleId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Get attachment settings for a field on an entity
     */
    public function getAttachmentSettings(
        FieldDefinition $field,
        string $entityType,
        ?int $bundleId = null
    ): ?array {
        $sql = "SELECT * FROM {$this->attachmentsTable} 
                WHERE field_id = :field_id AND entity_type = :entity_type";
        
        $params = [
            'field_id' => $field->id,
            'entity_type' => $entityType,
        ];

        if ($bundleId !== null) {
            $sql .= " AND bundle_id = :bundle_id";
            $params['bundle_id'] = $bundleId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'weight' => (int) $row['weight'],
            'settings' => json_decode($row['settings'], true) ?? [],
        ];
    }

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Clear the internal cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get the table name
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): void
    {
        $this->db->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): void
    {
        $this->db->rollBack();
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function hydrate(array $row): FieldDefinition
    {
        // Decode JSON fields
        $row['settings'] = isset($row['settings']) 
            ? json_decode($row['settings'], true) ?? [] 
            : [];
        $row['widget_settings'] = isset($row['widget_settings']) 
            ? json_decode($row['widget_settings'], true) ?? [] 
            : [];
        $row['validation'] = isset($row['validation']) 
            ? json_decode($row['validation'], true) ?? [] 
            : [];

        return FieldDefinition::fromArray($row);
    }

    private function hydrateMany(array $rows): array
    {
        $fields = [];
        
        foreach ($rows as $row) {
            $field = $this->hydrate($row);
            $this->cache[$field->id] = $field;
            $fields[] = $field;
        }

        return $fields;
    }
}

/**
 * InMemoryFieldRepository - In-memory repository for testing
 */
final class InMemoryFieldRepository implements FieldRepositoryInterface
{
    /** @var array<int, FieldDefinition> */
    private array $fields = [];
    private int $nextId = 1;

    /** @var array<string, array> */
    private array $attachments = [];

    public function find(int $id): ?FieldDefinition
    {
        return $this->fields[$id] ?? null;
    }

    public function findByMachineName(string $machineName): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->machine_name === $machineName) {
                return $field;
            }
        }
        return null;
    }

    public function findAll(): array
    {
        $fields = array_values($this->fields);
        usort($fields, fn($a, $b) => $a->weight <=> $b->weight);
        return $fields;
    }

    public function findByEntityType(string $entityType, ?int $bundleId = null): array
    {
        $fieldIds = [];
        
        foreach ($this->attachments as $key => $attachment) {
            if ($attachment['entity_type'] === $entityType) {
                if ($bundleId === null || $attachment['bundle_id'] === $bundleId) {
                    $fieldIds[] = $attachment['field_id'];
                }
            }
        }

        $fields = [];
        foreach ($fieldIds as $id) {
            if (isset($this->fields[$id])) {
                $fields[] = $this->fields[$id];
            }
        }

        usort($fields, fn($a, $b) => $a->weight <=> $b->weight);
        return $fields;
    }

    public function findByIds(array $ids): array
    {
        $fields = [];
        foreach ($ids as $id) {
            if (isset($this->fields[$id])) {
                $fields[] = $this->fields[$id];
            }
        }
        return $fields;
    }

    public function save(FieldDefinition $field): void
    {
        if ($field->id === null) {
            $field->id = $this->nextId++;
            $field->created_at = new \DateTimeImmutable();
        }
        $field->updated_at = new \DateTimeImmutable();
        $this->fields[$field->id] = $field;
    }

    public function delete(FieldDefinition $field): void
    {
        if ($field->id !== null) {
            unset($this->fields[$field->id]);
            
            // Remove attachments
            foreach ($this->attachments as $key => $attachment) {
                if ($attachment['field_id'] === $field->id) {
                    unset($this->attachments[$key]);
                }
            }
        }
    }

    public function attachToEntity(
        FieldDefinition $field,
        string $entityType,
        ?int $bundleId = null,
        int $weight = 0,
        array $settings = []
    ): void {
        $key = "{$field->id}:{$entityType}:{$bundleId}";
        $this->attachments[$key] = [
            'field_id' => $field->id,
            'entity_type' => $entityType,
            'bundle_id' => $bundleId,
            'weight' => $weight,
            'settings' => $settings,
        ];
    }

    public function detachFromEntity(
        FieldDefinition $field,
        string $entityType,
        ?int $bundleId = null
    ): void {
        $key = "{$field->id}:{$entityType}:{$bundleId}";
        unset($this->attachments[$key]);
    }
}
