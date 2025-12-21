<?php

declare(strict_types=1);

namespace App\Cms\ContentTypes;

use App\Cms\Core\BaseEntity;
use App\Cms\Core\SchemaGenerator;
use App\Cms\Fields\FieldDefinition;
use App\Cms\Modules\ModuleManager;
use MonkeysLegion\Database\Connection;
use MonkeysLegion\Cache\CacheManager;

/**
 * ContentTypeManager - Registry and manager for all content types
 *
 * Manages both:
 * - Code-defined content types (PHP Entity classes with #[ContentType] attribute)
 * - Database-defined content types (stored in content_types table)
 *
 * @example
 * ```php
 * // Get all content types
 * $types = $manager->getTypes();
 *
 * // Get a specific type
 * $type = $manager->getType('article');
 *
 * // Create a database-defined type
 * $manager->createDatabaseType([
 *     'label' => 'Product',
 *     'description' => 'E-commerce products',
 *     'fields' => [
 *         ['name' => 'Price', 'type' => 'decimal', 'required' => true],
 *         ['name' => 'SKU', 'type' => 'string', 'required' => true],
 *     ]
 * ]);
 *
 * // Create content
 * $manager->createContent('product', ['title' => 'Widget', 'field_price' => 29.99]);
 * ```
 */
final class ContentTypeManager
{
    private const CACHE_KEY = 'cms:content_types';
    private const CACHE_TTL = 86400;

    /** @var array<string, array> Code-defined content types */
    private array $codeTypes = [];

    /** @var array<string, ContentTypeEntity> Database-defined content types */
    private array $dbTypes = [];

    private bool $initialized = false;

    public function __construct(
        private readonly Connection $connection,
        private readonly ?ModuleManager $moduleManager = null,
        private readonly ?SchemaGenerator $schemaGenerator = null,
        private readonly ?CacheManager $cache = null,
    ) {
    }

    /**
     * Register a code-defined content type from an entity class
     */
    public function registerEntityType(string $entityClass): void
    {
        if (!class_exists($entityClass)) {
            return;
        }

        $reflection = new \ReflectionClass($entityClass);
        $attributes = $reflection->getAttributes(\App\Cms\Attributes\ContentType::class);

        if (empty($attributes)) {
            return;
        }

        $attr = $attributes[0]->newInstance();
        $typeId = $this->getTypeIdFromTableName($attr->tableName);

        // Extract fields from entity
        $fields = $this->extractFieldsFromEntity($reflection);

        $this->codeTypes[$typeId] = [
            'id' => $typeId,
            'class' => $entityClass,
            'table_name' => $attr->tableName,
            'label' => $attr->label,
            'description' => $attr->description ?? '',
            'icon' => $attr->icon ?? '📄',
            'publishable' => $attr->publishable ?? true,
            'revisionable' => $attr->revisionable ?? false,
            'translatable' => $attr->translatable ?? false,
            'source' => 'code',
            'fields' => $fields,
        ];
    }

    /**
     * Register all entity types from a module
     */
    public function registerModuleTypes(string $modulePath): void
    {
        $entitiesPath = $modulePath . '/Entities';
        if (!is_dir($entitiesPath)) {
            return;
        }

        foreach (glob($entitiesPath . '/*.php') as $file) {
            $className = $this->getClassNameFromFile($file);
            if ($className) {
                $this->registerEntityType($className);
            }
        }
    }

    /**
     * Get all content types
     */
    public function getTypes(): array
    {
        $this->ensureInitialized();

        $types = [];

        // Code-defined types
        foreach ($this->codeTypes as $id => $type) {
            $types[$id] = $type;
        }

        // Database-defined types
        foreach ($this->dbTypes as $id => $type) {
            $types[$id] = [
                'id' => $id,
                'label' => $type->label,
                'label_plural' => $type->label_plural,
                'description' => $type->description ?? '',
                'icon' => $type->icon,
                'table_name' => $type->getTableName(),
                'publishable' => $type->publishable,
                'revisionable' => $type->revisionable,
                'translatable' => $type->translatable,
                'source' => 'database',
                'fields' => $this->getFieldsArray($type),
                'entity' => $type,
            ];
        }

        // Sort by label
        uasort($types, fn($a, $b) => strcmp($a['label'], $b['label']));

        return $types;
    }

    /**
     * Get a specific content type
     */
    public function getType(string $id): ?array
    {
        $this->ensureInitialized();

        if (isset($this->codeTypes[$id])) {
            return $this->codeTypes[$id];
        }

        if (isset($this->dbTypes[$id])) {
            $type = $this->dbTypes[$id];
            return [
                'id' => $id,
                'label' => $type->label,
                'label_plural' => $type->label_plural,
                'description' => $type->description ?? '',
                'icon' => $type->icon,
                'table_name' => $type->getTableName(),
                'publishable' => $type->publishable,
                'revisionable' => $type->revisionable,
                'translatable' => $type->translatable,
                'source' => 'database',
                'fields' => $this->getFieldsArray($type),
                'entity' => $type,
            ];
        }

        return null;
    }

    /**
     * Check if a content type exists
     */
    public function hasType(string $id): bool
    {
        $this->ensureInitialized();
        return isset($this->codeTypes[$id]) || isset($this->dbTypes[$id]);
    }

    /**
     * Create a database-defined content type
     */
    public function createDatabaseType(array $data): ContentTypeEntity
    {
        $entity = new ContentTypeEntity();
        $entity->label = $data['label'];
        $entity->type_id = $data['type_id'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '_', $data['label']));
        $entity->label_plural = $data['label_plural'] ?? $data['label'] . 's';
        $entity->description = $data['description'] ?? null;
        $entity->icon = $data['icon'] ?? '📄';
        $entity->publishable = $data['publishable'] ?? true;
        $entity->revisionable = $data['revisionable'] ?? false;
        $entity->translatable = $data['translatable'] ?? false;
        $entity->has_author = $data['has_author'] ?? true;
        $entity->has_taxonomy = $data['has_taxonomy'] ?? true;
        $entity->has_media = $data['has_media'] ?? true;
        $entity->title_field = $data['title_field'] ?? 'title';
        $entity->slug_field = $data['slug_field'] ?? 'slug';
        $entity->url_pattern = $data['url_pattern'] ?? null;
        $entity->default_values = $data['default_values'] ?? [];
        $entity->settings = $data['settings'] ?? [];
        $entity->allowed_vocabularies = $data['allowed_vocabularies'] ?? [];
        $entity->enabled = $data['enabled'] ?? true;

        $entity->prePersist();

        // Save the entity
        $stmt = $this->connection->prepare("
            INSERT INTO content_types (
                type_id, label, label_plural, description, icon, is_system, enabled,
                publishable, revisionable, translatable, has_author, has_taxonomy, has_media,
                title_field, slug_field, url_pattern, default_values, settings,
                allowed_vocabularies, weight, created_at, updated_at
            ) VALUES (
                :type_id, :label, :label_plural, :description, :icon, :is_system, :enabled,
                :publishable, :revisionable, :translatable, :has_author, :has_taxonomy, :has_media,
                :title_field, :slug_field, :url_pattern, :default_values, :settings,
                :allowed_vocabularies, :weight, :created_at, :updated_at
            )
        ");

        $stmt->execute([
            'type_id' => $entity->type_id,
            'label' => $entity->label,
            'label_plural' => $entity->label_plural,
            'description' => $entity->description,
            'icon' => $entity->icon,
            'is_system' => $entity->is_system ? 1 : 0,
            'enabled' => $entity->enabled ? 1 : 0,
            'publishable' => $entity->publishable ? 1 : 0,
            'revisionable' => $entity->revisionable ? 1 : 0,
            'translatable' => $entity->translatable ? 1 : 0,
            'has_author' => $entity->has_author ? 1 : 0,
            'has_taxonomy' => $entity->has_taxonomy ? 1 : 0,
            'has_media' => $entity->has_media ? 1 : 0,
            'title_field' => $entity->title_field,
            'slug_field' => $entity->slug_field,
            'url_pattern' => $entity->url_pattern,
            'default_values' => json_encode($entity->default_values),
            'settings' => json_encode($entity->settings),
            'allowed_vocabularies' => json_encode($entity->allowed_vocabularies),
            'weight' => $entity->weight,
            'created_at' => $entity->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $entity->updated_at->format('Y-m-d H:i:s'),
        ]);

        $entity->id = (int) $this->connection->lastInsertId();

        // Add fields if provided
        if (!empty($data['fields'])) {
            foreach ($data['fields'] as $fieldData) {
                $this->addFieldToType($entity->id, $fieldData);
            }
            $entity->fields = $this->loadTypeFields($entity->id);
        }

        // Create the content table
        $this->createContentTable($entity);

        // Update cache
        $this->dbTypes[$entity->type_id] = $entity;
        $this->invalidateCache();

        return $entity;
    }

    /**
     * Update a database-defined content type
     */
    public function updateDatabaseType(int $id, array $data): ?ContentTypeEntity
    {
        $entity = $this->getTypeEntityById($id);
        if (!$entity) {
            return null;
        }

        if (isset($data['label'])) {
            $entity->label = $data['label'];
        }
        if (isset($data['label_plural'])) {
            $entity->label_plural = $data['label_plural'];
        }
        if (isset($data['description'])) {
            $entity->description = $data['description'];
        }
        if (isset($data['icon'])) {
            $entity->icon = $data['icon'];
        }
        if (isset($data['publishable'])) {
            $entity->publishable = $data['publishable'];
        }
        if (isset($data['revisionable'])) {
            $entity->revisionable = $data['revisionable'];
        }
        if (isset($data['translatable'])) {
            $entity->translatable = $data['translatable'];
        }
        if (isset($data['url_pattern'])) {
            $entity->url_pattern = $data['url_pattern'];
        }
        if (isset($data['settings'])) {
            $entity->settings = $data['settings'];
        }
        if (isset($data['enabled'])) {
            $entity->enabled = $data['enabled'];
        }

        $entity->updated_at = new \DateTimeImmutable();

        $stmt = $this->connection->prepare("
            UPDATE content_types SET
                label = :label, label_plural = :label_plural, description = :description,
                icon = :icon, publishable = :publishable, revisionable = :revisionable,
                translatable = :translatable, url_pattern = :url_pattern, settings = :settings,
                enabled = :enabled, updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $entity->id,
            'label' => $entity->label,
            'label_plural' => $entity->label_plural,
            'description' => $entity->description,
            'icon' => $entity->icon,
            'publishable' => $entity->publishable ? 1 : 0,
            'revisionable' => $entity->revisionable ? 1 : 0,
            'translatable' => $entity->translatable ? 1 : 0,
            'url_pattern' => $entity->url_pattern,
            'settings' => json_encode($entity->settings),
            'enabled' => $entity->enabled ? 1 : 0,
            'updated_at' => $entity->updated_at->format('Y-m-d H:i:s'),
        ]);

        $this->dbTypes[$entity->type_id] = $entity;
        $this->invalidateCache();

        return $entity;
    }

    /**
     * Delete a database-defined content type
     */
    public function deleteDatabaseType(int $id, bool $dropTable = false): bool
    {
        $entity = $this->getTypeEntityById($id);
        if (!$entity || $entity->is_system) {
            return false;
        }

        // Drop the content table if requested
        if ($dropTable) {
            $tableName = $entity->getTableName();
            $this->connection->exec("DROP TABLE IF EXISTS {$tableName}");
        }

        // Delete field instances
        $stmt = $this->connection->prepare(
            "DELETE FROM content_type_fields WHERE content_type_id = :type_id"
        );
        $stmt->execute(['type_id' => $id]);

        // Delete the type
        $stmt = $this->connection->prepare(
            "DELETE FROM content_types WHERE id = :id AND is_system = 0"
        );
        $stmt->execute(['id' => $id]);

        unset($this->dbTypes[$entity->type_id]);
        $this->invalidateCache();

        return true;
    }

    /**
     * Add a field to a database-defined content type
     */
    public function addFieldToType(int $typeId, array $fieldData): FieldDefinition
    {
        $entity = $this->getTypeEntityById($typeId);

        $field = new FieldDefinition();
        $field->name = $fieldData['name'];
        $field->machine_name = $fieldData['machine_name'] ?? 'field_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $fieldData['name']));
        $field->field_type = $fieldData['type'] ?? 'string';
        $field->description = $fieldData['description'] ?? null;
        $field->help_text = $fieldData['help_text'] ?? null;
        $field->widget = $fieldData['widget'] ?? null;
        $field->required = $fieldData['required'] ?? false;
        $field->multiple = $fieldData['multiple'] ?? false;
        $field->cardinality = $fieldData['cardinality'] ?? 1;
        $field->default_value = $fieldData['default'] ?? null;
        $field->settings = $fieldData['settings'] ?? [];
        $field->validation = $fieldData['validation'] ?? [];
        $field->widget_settings = $fieldData['widget_settings'] ?? [];
        $field->weight = $fieldData['weight'] ?? 0;
        $field->searchable = $fieldData['searchable'] ?? false;
        $field->translatable = $fieldData['translatable'] ?? false;

        $field->prePersist();

        // Save to database
        $stmt = $this->connection->prepare("
            INSERT INTO content_type_fields (
                content_type_id, name, machine_name, field_type, description, help_text,
                widget, required, multiple, cardinality, default_value, settings,
                validation, widget_settings, weight, searchable, translatable,
                created_at, updated_at
            ) VALUES (
                :content_type_id, :name, :machine_name, :field_type, :description, :help_text,
                :widget, :required, :multiple, :cardinality, :default_value, :settings,
                :validation, :widget_settings, :weight, :searchable, :translatable,
                :created_at, :updated_at
            )
        ");

        $stmt->execute([
            'content_type_id' => $typeId,
            'name' => $field->name,
            'machine_name' => $field->machine_name,
            'field_type' => $field->field_type,
            'description' => $field->description,
            'help_text' => $field->help_text,
            'widget' => $field->widget,
            'required' => $field->required ? 1 : 0,
            'multiple' => $field->multiple ? 1 : 0,
            'cardinality' => $field->cardinality,
            'default_value' => $field->default_value,
            'settings' => json_encode($field->settings),
            'validation' => json_encode($field->validation),
            'widget_settings' => json_encode($field->widget_settings),
            'weight' => $field->weight,
            'searchable' => $field->searchable ? 1 : 0,
            'translatable' => $field->translatable ? 1 : 0,
            'created_at' => $field->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $field->updated_at->format('Y-m-d H:i:s'),
        ]);

        $field->id = (int) $this->connection->lastInsertId();

        // Add column to content table
        if ($entity) {
            $this->addColumnToTable($entity->getTableName(), $field);
        }

        $this->invalidateCache();

        return $field;
    }

    /**
     * Remove a field from a content type
     */
    public function removeFieldFromType(int $typeId, string $machineName, bool $dropColumn = false): bool
    {
        $entity = $this->getTypeEntityById($typeId);

        // Optionally drop the column
        if ($dropColumn && $entity) {
            try {
                $tableName = $entity->getTableName();
                $this->connection->exec("ALTER TABLE {$tableName} DROP COLUMN {$machineName}");
            } catch (\Exception $e) {
                // Column might not exist
            }
        }

        $stmt = $this->connection->prepare(
            "DELETE FROM content_type_fields WHERE content_type_id = :type_id AND machine_name = :machine_name"
        );
        $stmt->execute(['type_id' => $typeId, 'machine_name' => $machineName]);

        $this->invalidateCache();

        return $stmt->rowCount() > 0;
    }

    // =========================================================================
    // Content CRUD Operations
    // =========================================================================

    /**
     * Create content of a specific type
     */
    public function createContent(string $typeId, array $data): ?int
    {
        $type = $this->getType($typeId);
        if (!$type) {
            return null;
        }

        $tableName = $type['table_name'];
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Build insert data
        $insertData = [
            'uuid' => $this->generateUuid(),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Add title/slug
        if (!empty($type['entity']->title_field ?? 'title') && isset($data['title'])) {
            $titleField = $type['entity']->title_field ?? 'title';
            $insertData[$titleField] = $data['title'];
        }

        if (!empty($type['entity']->slug_field ?? 'slug')) {
            $slugField = $type['entity']->slug_field ?? 'slug';
            $insertData[$slugField] = $data['slug'] ?? $this->generateSlug($data['title'] ?? '');
        }

        // Add custom fields
        foreach ($type['fields'] as $fieldName => $fieldDef) {
            if (isset($data[$fieldName])) {
                $insertData[$fieldName] = $this->serializeFieldValue($data[$fieldName], $fieldDef);
            }
        }

        // Add standard fields
        if ($type['publishable'] ?? true) {
            $insertData['status'] = $data['status'] ?? 'draft';
            if ($insertData['status'] === 'published') {
                $insertData['published_at'] = $now;
            }
        }

        if ($type['entity']->has_author ?? true) {
            $insertData['author_id'] = $data['author_id'] ?? null;
        }

        // Build SQL
        $columns = array_keys($insertData);
        $placeholders = array_map(fn($c) => ":{$c}", $columns);

        $sql = "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($insertData);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update content
     */
    public function updateContent(string $typeId, int $id, array $data): bool
    {
        $type = $this->getType($typeId);
        if (!$type) {
            return false;
        }

        $tableName = $type['table_name'];
        $updateData = ['updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')];

        // Update title/slug if provided
        if (isset($data['title'])) {
            $titleField = $type['entity']->title_field ?? 'title';
            $updateData[$titleField] = $data['title'];
        }

        if (isset($data['slug'])) {
            $slugField = $type['entity']->slug_field ?? 'slug';
            $updateData[$slugField] = $data['slug'];
        }

        // Update custom fields
        foreach ($type['fields'] as $fieldName => $fieldDef) {
            if (array_key_exists($fieldName, $data)) {
                $updateData[$fieldName] = $this->serializeFieldValue($data[$fieldName], $fieldDef);
            }
        }

        // Update status
        if (isset($data['status']) && ($type['publishable'] ?? true)) {
            $updateData['status'] = $data['status'];
            if ($data['status'] === 'published' && !isset($data['published_at'])) {
                $updateData['published_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            }
        }

        // Build SQL
        $sets = array_map(fn($c) => "{$c} = :{$c}", array_keys($updateData));
        $sql = "UPDATE {$tableName} SET " . implode(', ', $sets) . " WHERE id = :id";

        $updateData['id'] = $id;

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($updateData);

        return true;
    }

    /**
     * Delete content
     */
    public function deleteContent(string $typeId, int $id): bool
    {
        $type = $this->getType($typeId);
        if (!$type) {
            return false;
        }

        $tableName = $type['table_name'];

        $stmt = $this->connection->prepare("DELETE FROM {$tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get content by ID
     */
    public function getContent(string $typeId, int $id): ?array
    {
        $type = $this->getType($typeId);
        if (!$type) {
            return null;
        }

        $tableName = $type['table_name'];

        $stmt = $this->connection->prepare("SELECT * FROM {$tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->hydrateContent($row, $type);
    }

    /**
     * List content with pagination
     */
    public function listContent(string $typeId, array $options = []): array
    {
        $type = $this->getType($typeId);
        if (!$type) {
            return ['items' => [], 'total' => 0];
        }

        $tableName = $type['table_name'];
        $page = $options['page'] ?? 1;
        $perPage = $options['per_page'] ?? 20;
        $sortField = $options['sort'] ?? 'created_at';
        $sortDir = strtoupper($options['direction'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $status = $options['status'] ?? null;
        $search = $options['search'] ?? null;

        // Build WHERE clause
        $wheres = [];
        $params = [];

        if ($status) {
            $wheres[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($search) {
            $titleField = $type['entity']->title_field ?? 'title';
            $wheres[] = "{$titleField} LIKE :search";
            $params['search'] = "%{$search}%";
        }

        $whereClause = !empty($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

        // Get total count
        $countSql = "SELECT COUNT(*) FROM {$tableName} {$whereClause}";
        $stmt = $this->connection->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Get items
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$tableName} {$whereClause} ORDER BY {$sortField} {$sortDir} LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        $items = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrateContent($row, $type);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    private function createContentTable(ContentTypeEntity $entity): void
    {
        $sql = $entity->generateTableSql();
        $this->connection->exec($sql);
    }

    private function addColumnToTable(string $tableName, FieldDefinition $field): void
    {
        $columnDef = $field->getSqlColumnDefinition();
        $sql = "ALTER TABLE {$tableName} ADD COLUMN {$columnDef}";

        try {
            $this->connection->exec($sql);
        } catch (\Exception $e) {
            // Column might already exist
        }
    }

    private function loadDatabaseTypes(): void
    {
        // Try cache first
        if ($this->cache) {
            $cached = $this->cache->store()->get(self::CACHE_KEY);
            if ($cached !== null) {
                $this->dbTypes = $cached;
                return;
            }
        }

        try {
            $stmt = $this->connection->query(
                "SELECT * FROM content_types WHERE enabled = 1 ORDER BY weight, label"
            );

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $entity = new ContentTypeEntity();
                $entity->hydrate($row);
                $entity->fields = $this->loadTypeFields($entity->id);
                $this->dbTypes[$entity->type_id] = $entity;
            }

            // Cache the types
            if ($this->cache) {
                $this->cache->store()->set(self::CACHE_KEY, $this->dbTypes, self::CACHE_TTL);
            }
        } catch (\Exception $e) {
            // Table might not exist yet
        }
    }

    private function loadTypeFields(int $typeId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM content_type_fields WHERE content_type_id = :type_id ORDER BY weight, name"
        );
        $stmt->execute(['type_id' => $typeId]);

        $fields = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $field = new FieldDefinition();
            $field->hydrate($row);
            $fields[] = $field;
        }

        return $fields;
    }

    private function getTypeEntityById(int $id): ?ContentTypeEntity
    {
        $stmt = $this->connection->prepare("SELECT * FROM content_types WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $entity = new ContentTypeEntity();
        $entity->hydrate($row);
        $entity->fields = $this->loadTypeFields($entity->id);

        return $entity;
    }

    private function getFieldsArray(ContentTypeEntity $type): array
    {
        $fields = [];
        foreach ($type->fields as $field) {
            $fields[$field->machine_name] = [
                'type' => $field->field_type,
                'label' => $field->name,
                'required' => $field->required,
                'description' => $field->description,
                'default' => $field->default_value,
                'widget' => $field->getWidget(),
                'settings' => $field->settings,
            ];
        }
        return $fields;
    }

    private function extractFieldsFromEntity(\ReflectionClass $reflection): array
    {
        $fields = [];

        foreach ($reflection->getProperties() as $property) {
            $attrs = $property->getAttributes(\App\Cms\Attributes\Field::class);
            if (empty($attrs)) {
                continue;
            }

            $attr = $attrs[0]->newInstance();
            $name = $property->getName();

            $fields[$name] = [
                'type' => $attr->type,
                'label' => $attr->label ?? ucfirst($name),
                'required' => $attr->required ?? false,
                'description' => $attr->description ?? null,
                'default' => $attr->default ?? null,
                'widget' => $attr->widget ?? null,
                'settings' => [
                    'length' => $attr->length ?? null,
                    'options' => $attr->options ?? [],
                ],
            ];
        }

        return $fields;
    }

    private function getTypeIdFromTableName(string $tableName): string
    {
        // Remove common prefixes/suffixes
        $id = preg_replace('/^(cms_|content_|tbl_)/', '', $tableName);
        $id = preg_replace('/(s|_table)$/', '', $id);
        return $id;
    }

    private function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        if (
            preg_match('/namespace\s+([^;]+);/', $content, $nsMatch) &&
            preg_match('/class\s+(\w+)/', $content, $classMatch)
        ) {
            return $nsMatch[1] . '\\' . $classMatch[1];
        }

        return null;
    }

    private function hydrateContent(array $row, array $type): array
    {
        foreach ($type['fields'] as $fieldName => $fieldDef) {
            if (isset($row[$fieldName])) {
                $row[$fieldName] = $this->castFieldValue($row[$fieldName], $fieldDef);
            }
        }
        return $row;
    }

    private function serializeFieldValue(mixed $value, array $fieldDef): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $fieldDef['type'] ?? 'string';

        return match ($type) {
            'json', 'array' => is_array($value) ? json_encode($value) : $value,
            'boolean' => $value ? 1 : 0,
            'datetime' => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : $value,
            'date' => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : $value,
            default => $value,
        };
    }

    private function castFieldValue(mixed $value, array $fieldDef): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $fieldDef['type'] ?? 'string';

        return match ($type) {
            'integer', 'int' => (int) $value,
            'float', 'decimal' => (float) $value,
            'boolean', 'bool' => (bool) $value,
            'json', 'array' => is_string($value) ? json_decode($value, true) : $value,
            'datetime' => $value instanceof \DateTimeInterface ? $value : new \DateTimeImmutable($value),
            'date' => $value instanceof \DateTimeInterface ? $value : new \DateTimeImmutable($value),
            default => $value,
        };
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'untitled';
    }

    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            $this->loadDatabaseTypes();
            $this->initialized = true;
        }
    }

    private function invalidateCache(): void
    {
        if ($this->cache) {
            $this->cache->store()->delete(self::CACHE_KEY);
        }
        $this->initialized = false;
        $this->dbTypes = [];
    }

    /**
     * Get SQL for creating content_types table
     */
    public static function getTableSql(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS content_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type_id VARCHAR(100) NOT NULL UNIQUE,
                label VARCHAR(255) NOT NULL,
                label_plural VARCHAR(255),
                description TEXT,
                icon VARCHAR(50) DEFAULT '📄',
                is_system TINYINT(1) DEFAULT 0,
                enabled TINYINT(1) DEFAULT 1,
                publishable TINYINT(1) DEFAULT 1,
                revisionable TINYINT(1) DEFAULT 0,
                translatable TINYINT(1) DEFAULT 0,
                has_author TINYINT(1) DEFAULT 1,
                has_taxonomy TINYINT(1) DEFAULT 1,
                has_media TINYINT(1) DEFAULT 1,
                title_field VARCHAR(100) DEFAULT 'title',
                slug_field VARCHAR(100) DEFAULT 'slug',
                url_pattern VARCHAR(255),
                default_values JSON,
                settings JSON,
                allowed_vocabularies JSON,
                weight INT DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_type_id (type_id),
                INDEX idx_enabled (enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }

    /**
     * Get SQL for creating content_type_fields table
     */
    public static function getFieldsTableSql(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS content_type_fields (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content_type_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                machine_name VARCHAR(100) NOT NULL,
                field_type VARCHAR(50) NOT NULL,
                description VARCHAR(500),
                help_text VARCHAR(500),
                widget VARCHAR(50),
                required TINYINT(1) DEFAULT 0,
                multiple TINYINT(1) DEFAULT 0,
                cardinality INT DEFAULT 1,
                default_value TEXT,
                settings JSON,
                validation JSON,
                widget_settings JSON,
                weight INT DEFAULT 0,
                searchable TINYINT(1) DEFAULT 0,
                translatable TINYINT(1) DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                FOREIGN KEY (content_type_id) REFERENCES content_types(id) ON DELETE CASCADE,
                UNIQUE KEY uk_type_field (content_type_id, machine_name),
                INDEX idx_field_type (field_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }
}
