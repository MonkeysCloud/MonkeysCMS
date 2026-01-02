<?php

declare(strict_types=1);

namespace App\Cms\Blocks;

use App\Cms\Blocks\Types\BlockTypeInterface;
use App\Cms\Fields\FieldDefinition;
use App\Modules\Core\Entities\Block;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Cache\CacheManager;

/**
 * BlockManager - Registry and manager for all block types
 *
 * Manages both:
 * - Code-defined block types (PHP classes implementing BlockTypeInterface)
 * - Database-defined block types (stored in block_types table)
 *
 * @example
 * ```php
 * // Register code-defined type
 * $manager->registerType(new HtmlBlock());
 *
 * // Get all available types
 * $types = $manager->getTypes();
 *
 * // Get a specific type
 * $type = $manager->getType('html');
 *
 * // Create a database-defined type
 * $manager->createDatabaseType([
 *     'label' => 'FAQ Block',
 *     'description' => 'FAQ accordion block',
 *     'fields' => [...]
 * ]);
 * ```
 */
final class BlockManager
{
    private const CACHE_KEY = 'cms:block_types';
    private const CACHE_TTL = 86400;

    /** @var array<string, BlockTypeInterface> Code-defined block types */
    private array $codeTypes = [];

    /** @var array<string, BlockTypeEntity> Database-defined block types */
    private array $dbTypes = [];

    private bool $initialized = false;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ?CacheManager $cache = null,
    ) {
    }

    /**
     * Register a code-defined block type
     */
    public function registerType(BlockTypeInterface $type): void
    {
        $this->codeTypes[$type::getId()] = $type;
    }

    /**
     * Register multiple code-defined block types
     */
    public function registerTypes(array $types): void
    {
        foreach ($types as $type) {
            $this->registerType($type);
        }
    }

    /**
     * Get all block types (code + database)
     *
     * @return array<string, array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     icon: string,
     *     category: string,
     *     source: string,
     *     fields: array
     * }>
     */
    public function getTypes(): array
    {
        $this->ensureInitialized();

        $types = [];

        // Code-defined types
        foreach ($this->codeTypes as $id => $type) {
            $types[$id] = [
                'id' => $id,
                'label' => $type::getLabel(),
                'description' => $type::getDescription(),
                'icon' => $type::getIcon(),
                'category' => $type::getCategory(),
                'source' => 'code',
                'fields' => $type::getFields(),
                'class' => get_class($type),
            ];
        }

        // Database-defined types
        foreach ($this->dbTypes as $id => $type) {
            $types[$id] = [
                'id' => $id,
                'label' => $type->label,
                'description' => $type->description ?? '',
                'icon' => $type->icon,
                'category' => $type->category,
                'source' => 'database',
                'fields' => $this->getFieldsArray($type),
                'entity' => $type,
            ];
        }

        // Sort by category then label
        uasort($types, function ($a, $b) {
            $catCmp = strcmp($a['category'], $b['category']);
            if ($catCmp !== 0) {
                return $catCmp;
            }
            return strcmp($a['label'], $b['label']);
        });

        return $types;
    }

    /**
     * Get types grouped by category
     */
    public function getTypesGrouped(): array
    {
        $types = $this->getTypes();
        $grouped = [];

        foreach ($types as $type) {
            $category = $type['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $type;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Get a specific block type
     */
    public function getType(string $id): ?array
    {
        $this->ensureInitialized();

        // Check code types first
        if (isset($this->codeTypes[$id])) {
            $type = $this->codeTypes[$id];
            return [
                'id' => $id,
                'label' => $type::getLabel(),
                'description' => $type::getDescription(),
                'icon' => $type::getIcon(),
                'category' => $type::getCategory(),
                'source' => 'code',
                'fields' => $type::getFields(),
                'instance' => $type,
            ];
        }

        // Check database types
        if (isset($this->dbTypes[$id])) {
            $type = $this->dbTypes[$id];
            return [
                'id' => $id,
                'label' => $type->label,
                'description' => $type->description ?? '',
                'icon' => $type->icon,
                'category' => $type->category,
                'source' => 'database',
                'fields' => $this->getFieldsArray($type),
                'entity' => $type,
            ];
        }

        return null;
    }

    /**
     * Get the BlockTypeInterface instance for code-defined types
     */
    public function getTypeInstance(string $id): ?BlockTypeInterface
    {
        $this->ensureInitialized();
        return $this->codeTypes[$id] ?? null;
    }

    /**
     * Get the BlockTypeEntity for database-defined types
     */
    public function getTypeEntity(string $id): ?BlockTypeEntity
    {
        $this->ensureInitialized();
        return $this->dbTypes[$id] ?? null;
    }

    /**
     * Check if a block type exists
     */
    public function hasType(string $id): bool
    {
        $this->ensureInitialized();
        return isset($this->codeTypes[$id]) || isset($this->dbTypes[$id]);
    }

    /**
     * Create a database-defined block type
     */
    public function createDatabaseType(array $data): BlockTypeEntity
    {
        $entity = new BlockTypeEntity();
        $entity->label = $data['label'];
        $entity->type_id = $data['type_id'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '_', $data['label']));
        $entity->description = $data['description'] ?? null;
        $entity->icon = $data['icon'] ?? '🧱';
        $entity->category = $data['category'] ?? 'Custom';
        $entity->template = $data['template'] ?? null;
        $entity->default_settings = $data['default_settings'] ?? [];
        $entity->allowed_regions = $data['allowed_regions'] ?? [];
        $entity->cache_ttl = $data['cache_ttl'] ?? 3600;
        $entity->css_assets = $data['css_assets'] ?? [];
        $entity->js_assets = $data['js_assets'] ?? [];
        $entity->enabled = $data['enabled'] ?? true;

        // Save the entity
        $entity->prePersist();

        $stmt = $this->connection->pdo()->prepare("
            INSERT INTO block_types (
                type_id, label, description, icon, category, template,
                is_system, enabled, default_settings, allowed_regions,
                cache_ttl, css_assets, js_assets, weight, created_at, updated_at
            ) VALUES (
                :type_id, :label, :description, :icon, :category, :template,
                :is_system, :enabled, :default_settings, :allowed_regions,
                :cache_ttl, :css_assets, :js_assets, :weight, :created_at, :updated_at
            )
        ");

        $stmt->execute([
            'type_id' => $entity->type_id,
            'label' => $entity->label,
            'description' => $entity->description,
            'icon' => $entity->icon,
            'category' => $entity->category,
            'template' => $entity->template,
            'is_system' => $entity->is_system ? 1 : 0,
            'enabled' => $entity->enabled ? 1 : 0,
            'default_settings' => json_encode($entity->default_settings),
            'allowed_regions' => json_encode($entity->allowed_regions),
            'cache_ttl' => $entity->cache_ttl,
            'css_assets' => json_encode($entity->css_assets),
            'js_assets' => json_encode($entity->js_assets),
            'weight' => $entity->weight,
            'created_at' => $entity->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $entity->updated_at->format('Y-m-d H:i:s'),
        ]);

        $entity->id = (int) $this->connection->pdo()->lastInsertId();

        // Add fields if provided
        if (!empty($data['fields'])) {
            foreach ($data['fields'] as $fieldData) {
                $this->addFieldToType($entity->id, $fieldData);
            }
        }

        // Update cache
        $this->dbTypes[$entity->type_id] = $entity;
        $this->invalidateCache();

        return $entity;
    }

    /**
     * Update a database-defined block type
     */
    public function updateDatabaseType(int $id, array $data): ?BlockTypeEntity
    {
        $entity = $this->getTypeEntityById($id);
        if (!$entity) {
            return null;
        }

        if (isset($data['label'])) {
            $entity->label = $data['label'];
        }
        if (isset($data['description'])) {
            $entity->description = $data['description'];
        }
        if (isset($data['icon'])) {
            $entity->icon = $data['icon'];
        }
        if (isset($data['category'])) {
            $entity->category = $data['category'];
        }
        if (isset($data['template'])) {
            $entity->template = $data['template'];
        }
        if (isset($data['default_settings'])) {
            $entity->default_settings = $data['default_settings'];
        }
        if (isset($data['allowed_regions'])) {
            $entity->allowed_regions = $data['allowed_regions'];
        }
        if (isset($data['cache_ttl'])) {
            $entity->cache_ttl = $data['cache_ttl'];
        }
        if (isset($data['css_assets'])) {
            $entity->css_assets = $data['css_assets'];
        }
        if (isset($data['js_assets'])) {
            $entity->js_assets = $data['js_assets'];
        }
        if (isset($data['enabled'])) {
            $entity->enabled = $data['enabled'];
        }

        $entity->updated_at = new \DateTimeImmutable();

        $stmt = $this->connection->pdo()->prepare("
            UPDATE block_types SET
                label = :label, description = :description, icon = :icon,
                category = :category, template = :template, enabled = :enabled,
                default_settings = :default_settings, allowed_regions = :allowed_regions,
                cache_ttl = :cache_ttl, css_assets = :css_assets, js_assets = :js_assets,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $entity->id,
            'label' => $entity->label,
            'description' => $entity->description,
            'icon' => $entity->icon,
            'category' => $entity->category,
            'template' => $entity->template,
            'enabled' => $entity->enabled ? 1 : 0,
            'default_settings' => json_encode($entity->default_settings),
            'allowed_regions' => json_encode($entity->allowed_regions),
            'cache_ttl' => $entity->cache_ttl,
            'css_assets' => json_encode($entity->css_assets),
            'js_assets' => json_encode($entity->js_assets),
            'updated_at' => $entity->updated_at->format('Y-m-d H:i:s'),
        ]);

        $this->dbTypes[$entity->type_id] = $entity;
        $this->invalidateCache();

        return $entity;
    }

    /**
     * Delete a database-defined block type
     */
    public function deleteDatabaseType(int $id): bool
    {
        $entity = $this->getTypeEntityById($id);
        if (!$entity || $entity->is_system) {
            return false;
        }

        // Delete field instances
        $stmt = $this->connection->pdo()->prepare(
            "DELETE FROM block_type_fields WHERE block_type_id = :type_id"
        );
        $stmt->execute(['type_id' => $id]);

        // Delete the type
        $stmt = $this->connection->pdo()->prepare(
            "DELETE FROM block_types WHERE id = :id AND is_system = 0"
        );
        $stmt->execute(['id' => $id]);

        unset($this->dbTypes[$entity->type_id]);
        $this->invalidateCache();

        return true;
    }

    /**
     * Add a field to a database-defined block type
     */
    public function addFieldToType(int $typeId, array $fieldData): FieldDefinition
    {
        $field = new FieldDefinition();
        $field->name = $fieldData['name'];
        $field->machine_name = $fieldData['machine_name'] ?? 'field_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $fieldData['name']));
        $field->field_type = $fieldData['type'] ?? 'string';
        $field->description = $fieldData['description'] ?? null;
        $field->help_text = $fieldData['help_text'] ?? null;
        $field->widget = $fieldData['widget'] ?? null;
        $field->required = (bool) ($fieldData['required'] ?? false);
        $field->multiple = (bool) ($fieldData['multiple'] ?? false);
        $field->cardinality = $fieldData['cardinality'] ?? 1;
        $field->default_value = $fieldData['default'] ?? null;
        $field->settings = $fieldData['settings'] ?? [];
        $field->validation = $fieldData['validation'] ?? [];
        $field->widget_settings = $fieldData['widget_settings'] ?? [];
        $field->weight = $fieldData['weight'] ?? 0;
        $field->searchable = (bool) ($fieldData['searchable'] ?? false);
        $field->translatable = (bool) ($fieldData['translatable'] ?? false);

        $field->prePersist();

        $stmt = $this->connection->pdo()->prepare("
            INSERT INTO block_type_fields (
                block_type_id, name, machine_name, field_type, description, help_text,
                widget, required, multiple, cardinality, default_value, settings,
                validation, widget_settings, weight, searchable, translatable,
                created_at, updated_at
            ) VALUES (
                :block_type_id, :name, :machine_name, :field_type, :description, :help_text,
                :widget, :required, :multiple, :cardinality, :default_value, :settings,
                :validation, :widget_settings, :weight, :searchable, :translatable,
                :created_at, :updated_at
            )
        ");

        $stmt->execute([
            'block_type_id' => $typeId,
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

        $field->id = (int) $this->connection->pdo()->lastInsertId();

        $this->invalidateCache();

        return $field;
    }

    /**
     * Remove a field from a block type
     */
    public function removeFieldFromType(int $typeId, string $machineName): bool
    {
        $stmt = $this->connection->pdo()->prepare(
            "DELETE FROM block_type_fields WHERE block_type_id = :type_id AND machine_name = :machine_name"
        );
        $stmt->execute(['type_id' => $typeId, 'machine_name' => $machineName]);

        $this->invalidateCache();

        return $stmt->rowCount() > 0;
    }

    /**
     * Get fields for a block type
     */
    public function getFieldsForType(string $typeId): array
    {
        $type = $this->getType($typeId);
        if (!$type) {
            return [];
        }

        return $type['fields'] ?? [];
    }

    /**
     * Render a block using its type
     */
    public function renderBlock(Block $block, array $context = []): string
    {
        $typeId = $block->block_type;

        // Try code-defined type first
        if (isset($this->codeTypes[$typeId])) {
            return $this->codeTypes[$typeId]->render($block, $context);
        }

        // Try database-defined type
        if (isset($this->dbTypes[$typeId])) {
            return $this->renderDatabaseBlock($this->dbTypes[$typeId], $block, $context);
        }

        // Fallback: render as basic content
        return $block->getRenderedBody();
    }

    /**
     * Render a database-defined block
     */
    private function renderDatabaseBlock(BlockTypeEntity $type, Block $block, array $context): string
    {
        // If there's a template, use it
        if ($type->template && file_exists($type->template)) {
            ob_start();
            $blockData = $block->toArray();
            $fields = $this->getFieldsArray($type);
            extract(['block' => $block, 'type' => $type, 'context' => $context]);
            include $type->template;
            return ob_get_clean() ?: '';
        }

        // Default rendering
        $html = '<div class="block block--' . htmlspecialchars($type->type_id) . '">';

        if ($block->show_title && $block->title) {
            $html .= '<h3 class="block__title">' . htmlspecialchars($block->title) . '</h3>';
        }

        $html .= '<div class="block__content">';
        $html .= $block->getRenderedBody();

        // Render custom fields
        foreach ($type->fields as $field) {
            $value = $block->settings[$field->machine_name] ?? null;
            if ($value !== null) {
                $html .= '<div class="block__field block__field--' . $field->machine_name . '">';
                $html .= '<label>' . htmlspecialchars($field->name) . '</label>';
                $html .= '<div>' . $this->renderFieldValue($field, $value) . '</div>';
                $html .= '</div>';
            }
        }

        $html .= '</div></div>';

        return $html;
    }

    private function renderFieldValue(FieldDefinition $field, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $type = $field->getFieldTypeEnum();

        return match ($type->value) {
            'boolean' => $value ? 'Yes' : 'No',
            'image' => "<img src=\"/media/{$value}\" alt=\"\">",
            'url' => "<a href=\"{$value}\">{$value}</a>",
            'email' => "<a href=\"mailto:{$value}\">{$value}</a>",
            'html' => $value,
            'json' => '<pre>' . json_encode($value, JSON_PRETTY_PRINT) . '</pre>',
            default => htmlspecialchars((string) $value),
        };
    }

    /**
     * Load database types
     */
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

        $stmt = $this->connection->pdo()->query(
            "SELECT * FROM block_types WHERE enabled = 1 ORDER BY weight, label"
        );

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $entity = new BlockTypeEntity();
            $entity->hydrate($row);

            // Load fields
            $entity->fields = $this->loadTypeFields($entity->id);

            $this->dbTypes[$entity->type_id] = $entity;
        }

        // Cache the types
        if ($this->cache) {
            $this->cache->store()->set(self::CACHE_KEY, $this->dbTypes, self::CACHE_TTL);
        }
    }

    private function loadTypeFields(int $typeId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT * FROM block_type_fields WHERE block_type_id = :type_id ORDER BY weight, name"
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

    private function getTypeEntityById(int $id): ?BlockTypeEntity
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT * FROM block_types WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $entity = new BlockTypeEntity();
        $entity->hydrate($row);
        $entity->fields = $this->loadTypeFields($entity->id);

        return $entity;
    }

    private function getFieldsArray(BlockTypeEntity $type): array
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

        // Force reload on next access
        $this->initialized = false;
        $this->dbTypes = [];
    }

    /**
     * Get SQL for creating block_types table
     */
    public static function getTableSql(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS block_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type_id VARCHAR(100) NOT NULL UNIQUE,
                label VARCHAR(255) NOT NULL,
                description TEXT,
                icon VARCHAR(50) DEFAULT '🧱',
                category VARCHAR(100) DEFAULT 'Custom',
                template VARCHAR(255),
                is_system TINYINT(1) DEFAULT 0,
                enabled TINYINT(1) DEFAULT 1,
                default_settings JSON,
                allowed_regions JSON,
                cache_ttl INT DEFAULT 3600,
                css_assets JSON,
                js_assets JSON,
                weight INT DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_type_id (type_id),
                INDEX idx_category (category),
                INDEX idx_enabled (enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }

    /**
     * Get SQL for creating block_type_fields table
     */
    public static function getFieldsTableSql(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS block_type_fields (
                id INT AUTO_INCREMENT PRIMARY KEY,
                block_type_id INT NOT NULL,
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
                FOREIGN KEY (block_type_id) REFERENCES block_types(id) ON DELETE CASCADE,
                UNIQUE KEY uk_type_field (block_type_id, machine_name),
                INDEX idx_field_type (field_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }
}
