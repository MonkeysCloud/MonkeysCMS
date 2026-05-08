<?php

declare(strict_types=1);

namespace App\Cms\Search;

use PDO;

/**
 * SearchSourceRegistry — Manages which entities and fields are searchable.
 *
 * Provides default source definitions for all CMS entities (nodes, terms,
 * users, media, menus) and allows admin users to dynamically enable/disable
 * sources and configure which fields to search.
 *
 * Configuration is persisted in the `search_sources` database table.
 * When no DB config exists, sensible defaults are used.
 */
final class SearchSourceRegistry
{
    /** @var array<string, SearchSource> */
    private array $sources = [];

    private bool $loaded = false;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    // ── Registration ────────────────────────────────────────────────────

    /**
     * Register a search source definition.
     */
    public function register(SearchSource $source): void
    {
        $this->sources[$source->key] = $source;
    }

    /**
     * Get a specific source by key.
     */
    public function get(string $key): ?SearchSource
    {
        $this->ensureLoaded();
        return $this->sources[$key] ?? null;
    }

    /**
     * Get all registered sources.
     *
     * @return array<string, SearchSource>
     */
    public function all(): array
    {
        $this->ensureLoaded();
        return $this->sources;
    }

    /**
     * Get only enabled sources, sorted by priority.
     *
     * @return list<SearchSource>
     */
    public function enabled(): array
    {
        $this->ensureLoaded();
        $enabled = array_filter($this->sources, fn(SearchSource $s) => $s->enabled);
        usort($enabled, fn(SearchSource $a, SearchSource $b) => $a->priority <=> $b->priority);
        return $enabled;
    }

    // ── Defaults ────────────────────────────────────────────────────────

    /**
     * Register all default CMS entity sources.
     */
    public function registerDefaults(): void
    {
        // Content (nodes) — the primary searchable entity
        $this->register(new SearchSource(
            key: 'nodes',
            table: 'nodes',
            label: 'Content',
            entityType: 'content',
            titleField: 'title',
            searchFields: ['title', 'body', 'summary'],
            fieldWeights: ['title' => 3.0, 'summary' => 1.5, 'body' => 1.0],
            urlPattern: '/{slug}',
            summaryField: 'summary',
            statusField: 'status',
            statusValue: 'published',
            deletedField: 'deleted_at',
            dateField: 'published_at',
            authorJoin: 'LEFT JOIN cms_users cu ON n.author_id = cu.id',
            authorField: 'cu.name',
            facetFields: ['content_type', 'status', 'language'],
            typeField: 'content_type',
            enabled: true,
            priority: 10,
            icon: 'file-text',
        ));

        // Taxonomy terms
        $this->register(new SearchSource(
            key: 'terms',
            table: 'terms',
            label: 'Taxonomy Terms',
            entityType: 'term',
            titleField: 'name',
            searchFields: ['name', 'description'],
            fieldWeights: ['name' => 3.0, 'description' => 1.0],
            urlPattern: '/taxonomy/{slug}',
            summaryField: 'description',
            statusField: null,
            statusValue: null,
            deletedField: null,
            dateField: 'created_at',
            authorJoin: null,
            authorField: null,
            facetFields: ['vocabulary_id'],
            typeField: null,
            enabled: true,
            priority: 30,
            icon: 'tags',
        ));

        // Users
        $this->register(new SearchSource(
            key: 'users',
            table: 'cms_users',
            label: 'Users',
            entityType: 'user',
            titleField: 'name',
            searchFields: ['name', 'email'],
            fieldWeights: ['name' => 2.0, 'email' => 1.0],
            urlPattern: '/admin/users/{id}/edit',
            summaryField: 'email',
            statusField: 'active',
            statusValue: '1',
            deletedField: null,
            dateField: 'created_at',
            authorJoin: null,
            authorField: null,
            facetFields: ['role_id'],
            typeField: null,
            enabled: true, // users searchable by default
            priority: 40,
            icon: 'users',
        ));

        // Media
        $this->register(new SearchSource(
            key: 'media',
            table: 'media',
            label: 'Media',
            entityType: 'media',
            titleField: 'title',
            searchFields: ['title', 'alt', 'description', 'original_name'],
            fieldWeights: ['title' => 3.0, 'alt' => 2.0, 'description' => 1.0, 'original_name' => 1.0],
            urlPattern: '/admin/media/{id}',
            summaryField: 'description',
            statusField: null,
            statusValue: null,
            deletedField: null,
            dateField: 'created_at',
            authorJoin: null,
            authorField: null,
            facetFields: ['mime_type'],
            typeField: null,
            enabled: false, // disabled by default — admin-only
            priority: 50,
            icon: 'image',
        ));

        // Menus
        $this->register(new SearchSource(
            key: 'menus',
            table: 'menus',
            label: 'Menus',
            entityType: 'menu',
            titleField: 'name',
            searchFields: ['name', 'description'],
            fieldWeights: ['name' => 3.0, 'description' => 1.0],
            urlPattern: '/admin/menus/{id}/edit',
            summaryField: 'description',
            statusField: null,
            statusValue: null,
            deletedField: null,
            dateField: 'created_at',
            authorJoin: null,
            authorField: null,
            facetFields: [],
            typeField: null,
            enabled: false, // disabled by default — admin-only
            priority: 60,
            icon: 'menu',
        ));
    }

    // ── Persistence ─────────────────────────────────────────────────────

    /**
     * Load source overrides from the database.
     * Merges DB config on top of defaults (fields, enabled, weights).
     */
    public function loadFromDatabase(): void
    {
        try {
            $this->ensureTable();

            $stmt = $this->pdo->query("SELECT * FROM search_sources ORDER BY priority ASC");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $config = json_decode($row['config'] ?? '{}', true) ?? [];

                $merged = array_merge($config, [
                    'key' => $row['source_key'],
                    'table' => $config['table'] ?? $row['source_key'],
                    'label' => $row['label'],
                    'enabled' => (bool) $row['enabled'],
                    'priority' => (int) $row['priority'],
                ]);

                // If we have a default source, merge over it
                if (isset($this->sources[$row['source_key']])) {
                    $defaults = $this->sources[$row['source_key']]->toArray();
                    $merged = array_merge($defaults, $merged);
                }

                $this->sources[$row['source_key']] = SearchSource::fromArray($merged);
            }
        } catch (\Throwable) {
            // Table might not exist yet; use defaults
        }

        $this->loaded = true;
    }

    /**
     * Save a source configuration to the database.
     */
    public function save(SearchSource $source): void
    {
        $this->ensureTable();

        $config = $source->toArray();
        unset($config['key'], $config['enabled'], $config['priority'], $config['label']);

        $stmt = $this->pdo->prepare("
            INSERT INTO search_sources (source_key, label, enabled, priority, config, updated_at)
            VALUES (:key, :label, :enabled, :priority, :config, NOW())
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                enabled = VALUES(enabled),
                priority = VALUES(priority),
                config = VALUES(config),
                updated_at = NOW()
        ");

        $stmt->execute([
            'key' => $source->key,
            'label' => $source->label,
            'enabled' => $source->enabled ? 1 : 0,
            'priority' => $source->priority,
            'config' => json_encode($config, JSON_UNESCAPED_SLASHES),
        ]);

        // Update local cache
        $this->sources[$source->key] = $source;
    }

    /**
     * Bulk-update enabled/disabled state and priorities.
     *
     * @param array<string, array{enabled: bool, priority: int}> $updates
     */
    public function bulkUpdate(array $updates): void
    {
        foreach ($updates as $key => $data) {
            $source = $this->get($key);
            if (!$source) {
                continue;
            }

            $updated = SearchSource::fromArray(array_merge($source->toArray(), [
                'enabled' => $data['enabled'] ?? $source->enabled,
                'priority' => $data['priority'] ?? $source->priority,
            ]));

            $this->save($updated);
        }
    }

    /**
     * Get available columns for a table (for admin field picker).
     *
     * @return list<string>
     */
    public function getTableColumns(string $table): array
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM {$safeTable}");
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        } catch (\Throwable) {
            // Try PostgreSQL information_schema
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT column_name FROM information_schema.columns WHERE table_name = :t ORDER BY ordinal_position"
                );
                $stmt->execute(['t' => $safeTable]);
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (\Throwable) {
                return [];
            }
        }
    }

    // ── Internal ────────────────────────────────────────────────────────

    private function ensureLoaded(): void
    {
        if (!$this->loaded) {
            $this->registerDefaults();
            $this->loadFromDatabase();
        }
    }

    private function ensureTable(): void
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS search_sources (
                    source_key VARCHAR(64) PRIMARY KEY,
                    label VARCHAR(255) NOT NULL DEFAULT '',
                    enabled TINYINT(1) NOT NULL DEFAULT 1,
                    priority INT NOT NULL DEFAULT 50,
                    config JSON,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable) {
            // Ignore — may already exist or be non-MySQL
        }
    }
}
