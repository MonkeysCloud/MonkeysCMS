<?php

declare(strict_types=1);

namespace App\Cms\Block;

use PDO;

/**
 * BlockService — Central service for block type CRUD operations.
 *
 * Manages database-defined block types with full lifecycle support:
 * create, update, delete, field management, duplication, import/export,
 * and revision tracking.
 *
 * Code-defined blocks (PHP classes implementing BlockTypeInterface)
 * are managed by BlockTypeRegistry and shown as read-only in the admin.
 */
final class BlockService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly BlockTypeRegistry $registry,
    ) {}

    // ── Block Type CRUD ────────────────────────────────────────────────

    /**
     * Get all block types: code-defined + DB-defined merged.
     *
     * @return list<array{id: string, label: string, description: string, icon: string, category: string, fields: array, source: string, enabled: bool, is_system: bool, weight: int}>
     */
    public function getAll(): array
    {
        $this->registry->loadFromDatabase($this->pdo);
        $all = $this->registry->all();

        // Enrich with source info and DB metadata
        $dbRows = $this->loadDbRows();
        $result = [];

        foreach ($all as $id => $meta) {
            $dbRow = $dbRows[$id] ?? null;
            $result[] = array_merge($meta, [
                'source'    => $meta['dynamic'] ? 'database' : 'code',
                'is_system' => $meta['dynamic'] ? (bool) ($dbRow['is_system'] ?? false) : true,
                'enabled'   => $meta['dynamic'] ? (bool) ($dbRow['enabled'] ?? true) : true,
                'weight'    => $meta['dynamic'] ? (int) ($dbRow['weight'] ?? 0) : 0,
                'settings'  => $meta['dynamic'] ? json_decode($dbRow['settings'] ?? '{}', true) : [],
                'template'  => $meta['dynamic'] ? ($dbRow['template'] ?? null) : null,
                'db_id'     => $dbRow['id'] ?? null,
            ]);
        }

        return $result;
    }

    /**
     * Get all block types grouped by category.
     */
    public function getGrouped(): array
    {
        $all = $this->getAll();
        $grouped = [];

        foreach ($all as $type) {
            $grouped[$type['category']][] = $type;
        }

        ksort($grouped);
        return $grouped;
    }

    /**
     * Get a single block type by ID.
     */
    public function get(string $typeId): ?array
    {
        $all = $this->getAll();
        foreach ($all as $type) {
            if ($type['id'] === $typeId) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Get a single block type or throw.
     */
    public function getOrFail(string $typeId): array
    {
        return $this->get($typeId) ?? throw new \RuntimeException("Block type '{$typeId}' not found.");
    }

    /**
     * Create a new database-defined block type.
     */
    public function create(array $data): array
    {
        $typeId = $data['type_id'] ?? throw new \InvalidArgumentException('type_id is required');

        if (empty($typeId)) {
            throw new \InvalidArgumentException('type_id cannot be empty');
        }

        // Validate uniqueness
        if ($this->registry->has($typeId)) {
            throw new \InvalidArgumentException("Block type '{$typeId}' already exists.");
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $fields = $data['fields'] ?? [];
        $template = $data['template'] ?? null;
        $settings = $data['settings'] ?? [];

        try {
            // Try full insert with all columns
            $stmt = $this->pdo->prepare(
                'INSERT INTO block_types (type_id, label, description, icon, category, fields, template, settings, enabled, is_system, weight, created_at, updated_at)
                 VALUES (:type_id, :label, :description, :icon, :category, :fields, :template, :settings, :enabled, :is_system, :weight, :created_at, :updated_at)'
            );

            $stmt->execute([
                'type_id'     => $typeId,
                'label'       => $data['label'] ?? ucfirst($typeId),
                'description' => $data['description'] ?? null,
                'icon'        => $data['icon'] ?? 'puzzle',
                'category'    => $data['category'] ?? 'Custom',
                'fields'      => json_encode($fields),
                'template'    => $template,
                'settings'    => json_encode($settings),
                'enabled'     => (int) ($data['enabled'] ?? true),
                'is_system'   => 0,
                'weight'      => (int) ($data['weight'] ?? 0),
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        } catch (\PDOException $e) {
            // If columns don't exist yet, try basic insert
            if (str_contains($e->getMessage(), 'Unknown column') || str_contains($e->getMessage(), "doesn't have a default")) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO block_types (type_id, label, description, icon, category, fields, enabled, weight, created_at, updated_at)
                     VALUES (:type_id, :label, :description, :icon, :category, :fields, :enabled, :weight, :created_at, :updated_at)'
                );

                $stmt->execute([
                    'type_id'     => $typeId,
                    'label'       => $data['label'] ?? ucfirst($typeId),
                    'description' => $data['description'] ?? null,
                    'icon'        => $data['icon'] ?? 'puzzle',
                    'category'    => $data['category'] ?? 'Custom',
                    'fields'      => json_encode($fields),
                    'enabled'     => (int) ($data['enabled'] ?? true),
                    'weight'      => (int) ($data['weight'] ?? 0),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            } else {
                throw $e;
            }
        }

        $dbId = (int) $this->pdo->lastInsertId();

        // Snapshot initial revision
        $this->snapshotRevision($typeId, $fields, $template, $settings, null, 'Block type created');

        // Reload registry
        $this->registry->invalidate();

        return $this->getOrFail($typeId);
    }

    /**
     * Update an existing database-defined block type.
     */
    public function update(string $typeId, array $data, ?int $changedBy = null): array
    {
        $existing = $this->getDbRow($typeId);
        if (!$existing) {
            throw new \RuntimeException("Block type '{$typeId}' not found in database.");
        }

        if ((bool) ($existing['is_system'] ?? false)) {
            throw new \RuntimeException("Cannot modify system block type '{$typeId}'.");
        }

        $fields = isset($data['fields']) ? $data['fields'] : json_decode($existing['fields'] ?? '{}', true);
        $template = $data['template'] ?? ($existing['template'] ?? null);
        $settings = isset($data['settings']) ? $data['settings'] : json_decode($existing['settings'] ?? '{}', true);

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE block_types SET
                    label = :label, description = :description, icon = :icon, category = :category,
                    fields = :fields, template = :template, settings = :settings,
                    enabled = :enabled, weight = :weight, updated_at = :updated_at
                 WHERE type_id = :type_id'
            );

            $stmt->execute([
                'type_id'     => $typeId,
                'label'       => $data['label'] ?? $existing['label'],
                'description' => $data['description'] ?? ($existing['description'] ?? null),
                'icon'        => $data['icon'] ?? ($existing['icon'] ?? 'puzzle'),
                'category'    => $data['category'] ?? ($existing['category'] ?? 'Custom'),
                'fields'      => json_encode($fields),
                'template'    => $template,
                'settings'    => json_encode($settings),
                'enabled'     => (int) ($data['enabled'] ?? ($existing['enabled'] ?? true)),
                'weight'      => (int) ($data['weight'] ?? ($existing['weight'] ?? 0)),
                'updated_at'  => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Unknown column')) {
                $stmt = $this->pdo->prepare(
                    'UPDATE block_types SET
                        label = :label, description = :description, icon = :icon, category = :category,
                        fields = :fields, enabled = :enabled, weight = :weight, updated_at = :updated_at
                     WHERE type_id = :type_id'
                );

                $stmt->execute([
                    'type_id'     => $typeId,
                    'label'       => $data['label'] ?? $existing['label'],
                    'description' => $data['description'] ?? ($existing['description'] ?? null),
                    'icon'        => $data['icon'] ?? ($existing['icon'] ?? 'puzzle'),
                    'category'    => $data['category'] ?? ($existing['category'] ?? 'Custom'),
                    'fields'      => json_encode($fields),
                    'enabled'     => (int) ($data['enabled'] ?? ($existing['enabled'] ?? true)),
                    'weight'      => (int) ($data['weight'] ?? ($existing['weight'] ?? 0)),
                    'updated_at'  => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
            } else {
                throw $e;
            }
        }

        // Snapshot revision
        $summary = $data['change_summary'] ?? 'Block type updated';
        $this->snapshotRevision($typeId, $fields, $template, $settings, $changedBy, $summary);

        $this->registry->invalidate();

        return $this->getOrFail($typeId);
    }

    /**
     * Delete a database-defined block type (non-system only).
     */
    public function delete(string $typeId): bool
    {
        $existing = $this->getDbRow($typeId);
        if (!$existing) {
            return false;
        }

        if ((bool) ($existing['is_system'] ?? false)) {
            throw new \RuntimeException("Cannot delete system block type '{$typeId}'.");
        }

        $this->pdo->prepare('DELETE FROM block_types WHERE type_id = :type_id')
            ->execute(['type_id' => $typeId]);

        // Clean up revisions (table may not exist yet)
        try {
            $this->pdo->prepare('DELETE FROM block_type_revisions WHERE block_type_id = :type_id')
                ->execute(['type_id' => $typeId]);
        } catch (\Throwable) {}

        // Clean up instances (table may not exist yet)
        try {
            $this->pdo->prepare('DELETE FROM block_instances WHERE block_type = :type_id')
                ->execute(['type_id' => $typeId]);
        } catch (\Throwable) {}

        $this->registry->invalidate();

        return true;
    }

    // ── Field Management ───────────────────────────────────────────────

    /**
     * Add a field to a block type.
     */
    public function addField(string $typeId, string $fieldName, array $fieldDef, ?int $changedBy = null): void
    {
        $row = $this->getDbRowOrFail($typeId);
        $fields = json_decode($row['fields'], true) ?: [];

        if (isset($fields[$fieldName])) {
            throw new \InvalidArgumentException("Field '{$fieldName}' already exists in block type '{$typeId}'.");
        }

        $maxWeight = -1;
        foreach ($fields as $def) {
            if (isset($def['weight']) && $def['weight'] > $maxWeight) {
                $maxWeight = $def['weight'];
            }
        }
        $fieldDef['weight'] = $maxWeight + 1;

        $fields[$fieldName] = $fieldDef;

        $this->pdo->prepare('UPDATE block_types SET fields = :fields, updated_at = NOW() WHERE type_id = :type_id')
            ->execute(['fields' => json_encode($fields), 'type_id' => $typeId]);

        $this->snapshotRevision($typeId, $fields, $row['template'] ?? null, json_decode($row['settings'] ?? '{}', true), $changedBy, "Added field: {$fieldName}");
        $this->registry->invalidate();
    }

    /**
     * Remove a field from a block type.
     */
    public function removeField(string $typeId, string $fieldName, ?int $changedBy = null): void
    {
        $row = $this->getDbRowOrFail($typeId);
        $fields = json_decode($row['fields'], true) ?: [];

        if (!isset($fields[$fieldName])) {
            throw new \InvalidArgumentException("Field '{$fieldName}' not found in block type '{$typeId}'.");
        }

        unset($fields[$fieldName]);

        $this->pdo->prepare('UPDATE block_types SET fields = :fields, updated_at = NOW() WHERE type_id = :type_id')
            ->execute(['fields' => json_encode($fields), 'type_id' => $typeId]);

        $this->snapshotRevision($typeId, $fields, $row['template'] ?? null, json_decode($row['settings'] ?? '{}', true), $changedBy, "Removed field: {$fieldName}");
        $this->registry->invalidate();
    }

    /**
     * Update properties of an existing field (label, required, help_text, default).
     */
    public function updateField(string $typeId, string $fieldName, array $updates, ?int $changedBy = null): void
    {
        $row = $this->getDbRowOrFail($typeId);
        $fields = json_decode($row['fields'], true) ?: [];

        if (!isset($fields[$fieldName])) {
            throw new \InvalidArgumentException("Field '{$fieldName}' not found in block type '{$typeId}'.");
        }

        if (isset($updates['label']) && $updates['label'] !== '') {
            $fields[$fieldName]['label'] = $updates['label'];
        }
        if (array_key_exists('required', $updates)) {
            $fields[$fieldName]['required'] = (bool) $updates['required'];
        }
        if (array_key_exists('help_text', $updates)) {
            $fields[$fieldName]['help_text'] = $updates['help_text'];
        }
        if (array_key_exists('default', $updates)) {
            $fields[$fieldName]['default'] = $updates['default'];
        }

        $this->pdo->prepare('UPDATE block_types SET fields = :fields, updated_at = NOW() WHERE type_id = :type_id')
            ->execute(['fields' => json_encode($fields), 'type_id' => $typeId]);

        $this->snapshotRevision($typeId, $fields, $row['template'] ?? null, json_decode($row['settings'] ?? '{}', true), $changedBy, "Updated field: {$fieldName}");
        $this->registry->invalidate();
    }

    /**
     * Reorder fields for a block type.
     *
     * @param array<string> $order  Field names in desired order
     */
    public function reorderFields(string $typeId, array $order, ?int $changedBy = null): void
    {
        $row = $this->getDbRowOrFail($typeId);
        $fields = json_decode($row['fields'], true) ?: [];
        $reordered = [];
        $weight = 0;

        foreach ($order as $name) {
            if (isset($fields[$name])) {
                $fields[$name]['weight'] = $weight++;
                $reordered[$name] = $fields[$name];
            }
        }

        // Append any fields not in the order list
        foreach ($fields as $name => $def) {
            if (!isset($reordered[$name])) {
                $def['weight'] = $weight++;
                $reordered[$name] = $def;
            }
        }

        $this->pdo->prepare('UPDATE block_types SET fields = :fields, updated_at = NOW() WHERE type_id = :type_id')
            ->execute(['fields' => json_encode($reordered), 'type_id' => $typeId]);

        $this->snapshotRevision($typeId, $reordered, $row['template'] ?? null, json_decode($row['settings'] ?? '{}', true), $changedBy, 'Fields reordered');
        $this->registry->invalidate();
    }

    // ── Duplication ────────────────────────────────────────────────────

    /**
     * Duplicate a block type with a new ID.
     */
    public function duplicate(string $typeId, string $newTypeId): array
    {
        if ($this->registry->has($newTypeId)) {
            throw new \InvalidArgumentException("Block type '{$newTypeId}' already exists.");
        }

        $source = $this->get($typeId);
        if (!$source) {
            throw new \RuntimeException("Source block type '{$typeId}' not found.");
        }

        // For code-defined blocks, read fields from the interface
        $fields = $source['fields'] ?? [];
        $template = $source['template'] ?? null;
        $settings = $source['settings'] ?? [];

        return $this->create([
            'type_id'     => $newTypeId,
            'label'       => $source['label'] . ' (Copy)',
            'description' => $source['description'],
            'icon'        => $source['icon'],
            'category'    => $source['category'],
            'fields'      => $fields,
            'template'    => $template,
            'settings'    => $settings,
            'enabled'     => true,
            'weight'      => ($source['weight'] ?? 0) + 1,
        ]);
    }

    // ── Import / Export ─────────────────────────────────────────────────

    /**
     * Export a block type as a JSON-serializable array.
     */
    public function export(string $typeId): array
    {
        $type = $this->getOrFail($typeId);

        return [
            'version'     => '1.0',
            'type_id'     => $type['id'],
            'label'       => $type['label'],
            'description' => $type['description'],
            'icon'        => $type['icon'],
            'category'    => $type['category'],
            'fields'      => $type['fields'],
            'template'    => $type['template'] ?? null,
            'settings'    => $type['settings'] ?? [],
        ];
    }

    /**
     * Import a block type from a JSON array.
     */
    public function import(array $data): array
    {
        $typeId = $data['type_id'] ?? throw new \InvalidArgumentException('Import data must include type_id');

        // If already exists, treat as update
        if ($this->getDbRow($typeId)) {
            return $this->update($typeId, $data, null);
        }

        return $this->create($data);
    }

    // ── Revisions ──────────────────────────────────────────────────────

    /**
     * Get revision history for a block type.
     *
     * @return list<array{id: int, revision: int, change_summary: ?string, changed_by: ?int, created_at: string}>
     */
    public function getRevisions(string $typeId, int $limit = 50): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, block_type_id, revision, change_summary, changed_by, created_at
                 FROM block_type_revisions
                 WHERE block_type_id = :type_id
                 ORDER BY revision DESC
                 LIMIT :limit'
            );
            $stmt->bindValue('type_id', $typeId);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get a specific revision snapshot.
     */
    public function getRevision(string $typeId, int $revision): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM block_type_revisions WHERE block_type_id = :type_id AND revision = :revision LIMIT 1'
            );
            $stmt->execute(['type_id' => $typeId, 'revision' => $revision]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) return null;

            $row['fields_snapshot'] = json_decode($row['fields_snapshot'], true);
            $row['settings_snapshot'] = json_decode($row['settings_snapshot'], true);

            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Categories ─────────────────────────────────────────────────────

    /**
     * Get all unique categories from both code and DB blocks.
     *
     * @return list<string>
     */
    public function getCategories(): array
    {
        $cats = [];
        foreach ($this->getAll() as $type) {
            $cats[$type['category']] = true;
        }
        ksort($cats);
        return array_keys($cats);
    }

    // ── Private Helpers ────────────────────────────────────────────────

    /**
     * Load all DB rows indexed by type_id.
     */
    private function loadDbRows(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT * FROM block_types ORDER BY weight ASC, label ASC');
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[$row['type_id']] = $row;
            }
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get a single DB row by type_id.
     */
    private function getDbRow(string $typeId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM block_types WHERE type_id = :type_id');
            $stmt->execute(['type_id' => $typeId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getDbRowOrFail(string $typeId): array
    {
        return $this->getDbRow($typeId) ?? throw new \RuntimeException("Block type '{$typeId}' not found in database.");
    }

    /**
     * Snapshot the current state of a block type into the revisions table.
     */
    private function snapshotRevision(
        string $typeId,
        array $fields,
        ?string $template,
        array $settings,
        ?int $changedBy,
        string $summary,
    ): void {
        try {
            // Get next revision number
            $stmt = $this->pdo->prepare(
                'SELECT MAX(revision) FROM block_type_revisions WHERE block_type_id = :type_id'
            );
            $stmt->execute(['type_id' => $typeId]);
            $maxRev = (int) $stmt->fetchColumn();

            $stmt = $this->pdo->prepare(
                'INSERT INTO block_type_revisions (block_type_id, revision, fields_snapshot, template_snapshot, settings_snapshot, changed_by, change_summary, created_at)
                 VALUES (:type_id, :revision, :fields, :template, :settings, :changed_by, :summary, :created_at)'
            );
            $stmt->execute([
                'type_id'    => $typeId,
                'revision'   => $maxRev + 1,
                'fields'     => json_encode($fields),
                'template'   => $template,
                'settings'   => json_encode($settings),
                'changed_by' => $changedBy,
                'summary'    => $summary,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Pre-migration — silently skip
        }
    }
}
