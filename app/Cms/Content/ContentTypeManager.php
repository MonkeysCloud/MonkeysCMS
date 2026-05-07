<?php

declare(strict_types=1);

namespace App\Cms\Content;

use App\Cms\Field\FieldDefinition;
use App\Cms\Field\FieldRepository;
use PDO;

/**
 * ContentTypeManager — Central service for content type resolution.
 *
 * Merges code-defined .mlc types with DB-stored types.
 * DB records always win on conflict (code definitions are defaults).
 *
 * Usage:
 *   $types  = $manager->getEnabled();
 *   $type   = $manager->get('article');
 *   $fields = $manager->getFieldsFor('article');
 */
final class ContentTypeManager
{
    /** @var array<string, ContentTypeEntity>|null Cached types */
    private ?array $cache = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly FieldRepository $fieldRepo,
        private readonly string $configPath,
    ) {}

    // ── Public API ──────────────────────────────────────────────────────

    /**
     * Load all content types (DB + MLC merged).
     *
     * @return array<string, ContentTypeEntity>
     */
    public function loadAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        // 1. Load DB-defined types
        $dbTypes = $this->loadFromDatabase();

        // 2. Load MLC-defined types
        $mlcTypes = $this->loadFromMlcFiles();

        // 3. Merge: DB wins on conflict, MLC provides defaults
        $merged = $mlcTypes;
        foreach ($dbTypes as $typeId => $entity) {
            $merged[$typeId] = $entity;
        }

        // 4. Sort by weight
        uasort($merged, fn(ContentTypeEntity $a, ContentTypeEntity $b) => $a->weight <=> $b->weight);

        $this->cache = $merged;
        return $this->cache;
    }

    /**
     * Get a single content type by ID.
     */
    public function get(string $typeId): ?ContentTypeEntity
    {
        return $this->loadAll()[$typeId] ?? null;
    }

    /**
     * Get a content type or throw.
     */
    public function getOrFail(string $typeId): ContentTypeEntity
    {
        return $this->get($typeId) ?? throw new \RuntimeException("Content type '{$typeId}' not found.");
    }

    /**
     * Get only enabled content types.
     *
     * @return array<string, ContentTypeEntity>
     */
    public function getEnabled(): array
    {
        return array_filter($this->loadAll(), fn(ContentTypeEntity $ct) => $ct->enabled);
    }

    /**
     * Get field definitions for a content type.
     *
     * @return list<FieldDefinition>
     */
    public function getFieldsFor(string $typeId): array
    {
        $ct = $this->get($typeId);
        if (!$ct || $ct->id === null) {
            return [];
        }

        return $this->fieldRepo->findByContentType($ct->id);
    }

    /**
     * Persist a content type to the database.
     */
    public function persist(ContentTypeEntity $entity): ContentTypeEntity
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($entity->id !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE content_types SET
                    type_id = :type_id, label = :label, label_plural = :label_plural,
                    description = :description, icon = :icon, is_system = :is_system,
                    enabled = :enabled, publishable = :publishable, revisionable = :revisionable,
                    translatable = :translatable, has_author = :has_author, has_taxonomy = :has_taxonomy,
                    has_media = :has_media, mosaic_enabled = :mosaic_enabled, mosaic_default = :mosaic_default,
                    comments_enabled = :comments_enabled,
                    title_field = :title_field, slug_field = :slug_field, url_pattern = :url_pattern,
                    default_values = :default_values, settings = :settings,
                    allowed_vocabularies = :allowed_vocabularies, weight = :weight, updated_at = :updated_at
                WHERE id = :id'
            );
            $stmt->execute($this->toParams($entity, $now, true));
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO content_types
                    (type_id, label, label_plural, description, icon, is_system, enabled,
                     publishable, revisionable, translatable, has_author, has_taxonomy, has_media,
                     mosaic_enabled, mosaic_default, comments_enabled, title_field, slug_field, url_pattern,
                     default_values, settings, allowed_vocabularies, weight, created_at, updated_at)
                VALUES
                    (:type_id, :label, :label_plural, :description, :icon, :is_system, :enabled,
                     :publishable, :revisionable, :translatable, :has_author, :has_taxonomy, :has_media,
                     :mosaic_enabled, :mosaic_default, :comments_enabled, :title_field, :slug_field, :url_pattern,
                     :default_values, :settings, :allowed_vocabularies, :weight, :created_at, :updated_at)'
            );
            $params = $this->toParams($entity, $now, false);
            $params['created_at'] = $now;
            $stmt->execute($params);
            $entity->id = (int) $this->pdo->lastInsertId();
        }

        $entity->updated_at = new \DateTimeImmutable($now);
        $this->invalidateCache();

        return $entity;
    }

    /**
     * Sync a content type from an MLC definition into the database.
     * Only inserts if the type_id doesn't exist yet in DB.
     */
    public function syncFromMlc(string $typeId, array $definition): ContentTypeEntity
    {
        $existing = $this->findInDatabase($typeId);
        if ($existing !== null) {
            return $existing;
        }

        $entity = $this->hydrateMlcDefinition($typeId, $definition);
        return $this->persist($entity);
    }

    /**
     * Delete a content type.
     */
    public function delete(string $typeId): bool
    {
        $ct = $this->get($typeId);
        if (!$ct || $ct->id === null || $ct->is_system) {
            return false;
        }

        // Delete associated field definitions
        $this->pdo->prepare('DELETE FROM field_definitions WHERE content_type_id = :id')
            ->execute(['id' => $ct->id]);

        // Delete the content type
        $this->pdo->prepare('DELETE FROM content_types WHERE id = :id')
            ->execute(['id' => $ct->id]);

        $this->invalidateCache();
        return true;
    }

    // ── Internal ────────────────────────────────────────────────────────

    /**
     * Load content types from the database.
     *
     * @return array<string, ContentTypeEntity>
     */
    private function loadFromDatabase(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM content_types ORDER BY weight ASC');
        $types = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $entity = (new ContentTypeEntity())->hydrate($row);
            $types[$entity->type_id] = $entity;
        }

        return $types;
    }

    /**
     * Find a single content type in the database.
     */
    private function findInDatabase(string $typeId): ?ContentTypeEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM content_types WHERE type_id = :type_id');
        $stmt->execute(['type_id' => $typeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new ContentTypeEntity())->hydrate($row) : null;
    }

    /**
     * Scan config/content-types/*.mlc files and parse them.
     *
     * @return array<string, ContentTypeEntity>
     */
    private function loadFromMlcFiles(): array
    {
        $dir = $this->configPath;
        if (!is_dir($dir)) {
            return [];
        }

        $types = [];
        $files = glob($dir . '/*.mlc');

        foreach ($files as $file) {
            $parsed = $this->parseMlcFile($file);
            if ($parsed === null) {
                continue;
            }

            foreach ($parsed as $typeId => $definition) {
                $types[$typeId] = $this->hydrateMlcDefinition($typeId, $definition);
            }
        }

        return $types;
    }

    /**
     * Parse a single .mlc content type definition file.
     *
     * @return array<string, array>|null
     */
    private function parseMlcFile(string $filePath): ?array
    {
        if (!is_file($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Use the framework's MLC parser if available, otherwise fallback
        if (function_exists('parse_mlc')) {
            return parse_mlc($content);
        }

        // Simple MLC parser for content type definitions
        return $this->simpleParseContentTypeMlc($content);
    }

    /**
     * Simple parser for content_type MLC blocks.
     *
     * Handles:
     *   content_type "article" { ... }
     *   key = value
     *   key = true/false
     *   nested { ... }
     *
     * @return array<string, array>
     */
    private function simpleParseContentTypeMlc(string $content): array
    {
        $result = [];
        $lines = explode("\n", $content);
        $stack = [];
        $current = null;
        $currentId = null;
        $depth = 0;
        $fieldName = null;
        $fieldData = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip comments and empty lines
            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '//')) {
                continue;
            }

            // Match content_type "id" {
            if (preg_match('/^content_type\s+"([^"]+)"\s*\{/', $trimmed, $m)) {
                $currentId = $m[1];
                $current = [];
                $depth = 1;
                continue;
            }

            // Match field "name" {
            if ($depth > 0 && preg_match('/^field\s+"([^"]+)"\s*\{/', $trimmed, $m)) {
                $fieldName = $m[1];
                $fieldData = [];
                $depth++;
                continue;
            }

            // Match nested block like settings {
            if ($depth > 0 && preg_match('/^(\w+)\s*\{/', $trimmed, $m)) {
                $stack[] = [$fieldName, $fieldData];
                $fieldName = $m[1];
                $fieldData = [];
                $depth++;
                continue;
            }

            // Closing brace
            if ($trimmed === '}') {
                $depth--;
                if ($depth === 0 && $currentId !== null) {
                    $result[$currentId] = $current;
                    $current = null;
                    $currentId = null;
                } elseif ($depth === 1 && $fieldName !== null && $current !== null) {
                    // Closing a field block
                    if (!isset($current['fields'])) {
                        $current['fields'] = [];
                    }
                    $current['fields'][$fieldName] = $fieldData;
                    $fieldName = null;
                    $fieldData = null;
                } elseif (!empty($stack)) {
                    $nestedKey = $fieldName;
                    $nestedData = $fieldData;
                    [$fieldName, $fieldData] = array_pop($stack);
                    if ($fieldData !== null && $nestedKey !== null) {
                        $fieldData[$nestedKey] = $nestedData;
                    } elseif ($current !== null && $nestedKey !== null) {
                        $current[$nestedKey] = $nestedData;
                    }
                }
                continue;
            }

            // Match key = value
            if (preg_match('/^(\w+)\s*=\s*(.+)$/', $trimmed, $m)) {
                $key = $m[1];
                $value = $this->parseMlcValue(trim($m[2]));

                if ($fieldData !== null) {
                    $fieldData[$key] = $value;
                } elseif ($current !== null) {
                    $current[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Parse an MLC value string into a typed PHP value.
     */
    private function parseMlcValue(string $raw): mixed
    {
        // Boolean
        if ($raw === 'true') return true;
        if ($raw === 'false') return false;

        // Integer
        if (ctype_digit($raw)) return (int) $raw;

        // Quoted string
        if (preg_match('/^"([^"]*)"$/', $raw, $m)) return $m[1];

        // Array (simple)
        if (str_starts_with($raw, '[') && str_ends_with($raw, ']')) {
            $inner = substr($raw, 1, -1);
            return array_map(fn($v) => $this->parseMlcValue(trim($v)), explode(',', $inner));
        }

        // Unquoted string
        return $raw;
    }

    /**
     * Hydrate a ContentTypeEntity from an MLC definition array.
     */
    private function hydrateMlcDefinition(string $typeId, array $def): ContentTypeEntity
    {
        $entity = new ContentTypeEntity();
        $entity->type_id = $typeId;
        $entity->label = $def['label'] ?? ucfirst($typeId);
        $entity->label_plural = $def['label_plural'] ?? $entity->label . 's';
        $entity->description = $def['description'] ?? null;
        $entity->icon = $def['icon'] ?? 'file-text';
        $entity->is_system = (bool) ($def['is_system'] ?? false);
        $entity->enabled = (bool) ($def['enabled'] ?? true);
        $entity->publishable = (bool) ($def['publishable'] ?? true);
        $entity->revisionable = (bool) ($def['revisionable'] ?? false);
        $entity->translatable = (bool) ($def['translatable'] ?? false);
        $entity->has_author = (bool) ($def['has_author'] ?? true);
        $entity->has_taxonomy = (bool) ($def['has_taxonomy'] ?? true);
        $entity->has_media = (bool) ($def['has_media'] ?? true);
        $entity->mosaic_enabled = (bool) ($def['mosaic_enabled'] ?? false);
        $entity->mosaic_default = (bool) ($def['mosaic_default'] ?? false);
        $entity->comments_enabled = (bool) ($def['comments_enabled'] ?? false);
        $entity->title_field = $def['title_field'] ?? 'title';
        $entity->slug_field = $def['slug_field'] ?? 'slug';
        $entity->url_pattern = $def['url_pattern'] ?? null;
        $entity->weight = (int) ($def['weight'] ?? 0);
        $entity->default_values = $def['default_values'] ?? [];
        $entity->settings = $def['settings'] ?? [];
        $entity->allowed_vocabularies = $def['allowed_vocabularies'] ?? [];

        // Parse field definitions from MLC
        if (isset($def['fields']) && is_array($def['fields'])) {
            foreach ($def['fields'] as $machineName => $fieldDef) {
                $field = FieldDefinition::create(
                    $fieldDef['label'] ?? ucfirst(str_replace('_', ' ', $machineName)),
                    $machineName,
                    $fieldDef['type'] ?? 'string',
                );

                if (isset($fieldDef['widget'])) $field->withWidget($fieldDef['widget']);
                if (isset($fieldDef['required'])) $field->required((bool) $fieldDef['required']);
                if (isset($fieldDef['weight'])) $field->withWeight((int) $fieldDef['weight']);
                if (isset($fieldDef['description'])) $field->withDescription($fieldDef['description']);
                if (isset($fieldDef['help_text'])) $field->withHelpText($fieldDef['help_text']);
                if (isset($fieldDef['settings'])) $field->withSettings($fieldDef['settings']);
                if (isset($fieldDef['searchable'])) $field->searchable((bool) $fieldDef['searchable']);

                $entity->fieldDefinitions[] = $field;
            }
        }

        return $entity;
    }

    /**
     * Build parameter array for SQL persist.
     */
    private function toParams(ContentTypeEntity $entity, string $now, bool $isUpdate): array
    {
        $params = [
            'type_id'               => $entity->type_id,
            'label'                 => $entity->label,
            'label_plural'          => $entity->label_plural,
            'description'           => $entity->description,
            'icon'                  => $entity->icon,
            'is_system'             => (int) $entity->is_system,
            'enabled'               => (int) $entity->enabled,
            'publishable'           => (int) $entity->publishable,
            'revisionable'          => (int) $entity->revisionable,
            'translatable'          => (int) $entity->translatable,
            'has_author'            => (int) $entity->has_author,
            'has_taxonomy'          => (int) $entity->has_taxonomy,
            'has_media'             => (int) $entity->has_media,
            'mosaic_enabled'        => (int) $entity->mosaic_enabled,
            'mosaic_default'        => (int) $entity->mosaic_default,
            'comments_enabled'      => (int) $entity->comments_enabled,
            'title_field'           => $entity->title_field,
            'slug_field'            => $entity->slug_field,
            'url_pattern'           => $entity->url_pattern,
            'default_values'        => json_encode($entity->default_values),
            'settings'              => json_encode($entity->settings),
            'allowed_vocabularies'  => json_encode($entity->allowed_vocabularies),
            'weight'                => $entity->weight,
            'updated_at'            => $now,
        ];

        if ($isUpdate) {
            $params['id'] = $entity->id;
        }

        return $params;
    }

    /**
     * Invalidate the in-memory cache.
     */
    private function invalidateCache(): void
    {
        $this->cache = null;
    }
}
