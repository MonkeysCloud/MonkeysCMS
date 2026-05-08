<?php

declare(strict_types=1);

namespace App\Cms\Search;

use App\Cms\Search\Engine\DatabaseSearchEngine;
use App\Cms\Search\Engine\ElasticsearchEngine;
use App\Cms\Search\Engine\SearchEngineInterface;
use App\Cms\Search\Engine\SolrSearchEngine;
use PDO;

/**
 * SearchManager — Central orchestrator for the search subsystem.
 *
 * Manages engine registration, engine selection, content indexing,
 * and provides the primary API consumed by controllers.
 *
 * Engine lifecycle:
 *   1. Manager boots with config → registers engines
 *   2. Active engine is selected (from config or admin settings)
 *   3. Search/index calls are delegated to the active engine
 *
 * Usage:
 *   $manager = SearchManager::create($pdo, $config);
 *   $result  = $manager->search(new SearchQuery(text: 'hello'));
 *   $manager->indexContent($contentEntity);
 */
final class SearchManager
{
    /** @var array<string, SearchEngineInterface> */
    private array $engines = [];

    private ?SearchEngineInterface $activeEngine = null;

    private ?SearchSourceRegistry $sourceRegistry = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly array $config = [],
    ) {}

    // ── Factory ─────────────────────────────────────────────────────────

    /**
     * Create a SearchManager from configuration.
     *
     * @param array{
     *   engine?: string,
     *   elasticsearch?: array<string, mixed>,
     *   solr?: array<string, mixed>,
     * } $config
     */
    public static function create(PDO $pdo, array $config = []): self
    {
        $manager = new self($pdo, $config);

        // Build source registry
        $registry = new SearchSourceRegistry($pdo);
        $registry->registerDefaults();
        $registry->loadFromDatabase();
        $manager->sourceRegistry = $registry;

        // Always register the database engine (zero-config) — now with registry
        $manager->register(new DatabaseSearchEngine($pdo, $registry));

        // Register Elasticsearch if configured
        if (!empty($config['elasticsearch']['host'])) {
            $manager->register(new ElasticsearchEngine($config['elasticsearch']));
        }

        // Register Solr if configured
        if (!empty($config['solr']['host'])) {
            $manager->register(new SolrSearchEngine($config['solr']));
        }

        // Set active engine
        $activeEngineName = $config['engine'] ?? 'database';
        $manager->setActiveEngine($activeEngineName);

        return $manager;
    }

    // ── Engine Management ───────────────────────────────────────────────

    /**
     * Register a search engine adapter.
     */
    public function register(SearchEngineInterface $engine): void
    {
        $this->engines[$engine->name()] = $engine;
    }

    /**
     * Set the active search engine by name.
     */
    public function setActiveEngine(string $name): void
    {
        if (!isset($this->engines[$name])) {
            // Fall back to database engine
            $name = 'database';
        }

        $this->activeEngine = $this->engines[$name] ?? null;
    }

    /**
     * Get the currently active search engine.
     */
    public function engine(): SearchEngineInterface
    {
        if ($this->activeEngine === null) {
            throw new \RuntimeException('No search engine configured');
        }

        return $this->activeEngine;
    }

    /**
     * Get a specific engine by name.
     */
    public function getEngine(string $name): ?SearchEngineInterface
    {
        return $this->engines[$name] ?? null;
    }

    /**
     * Get the source registry for source management.
     */
    public function sources(): SearchSourceRegistry
    {
        if ($this->sourceRegistry === null) {
            $this->sourceRegistry = new SearchSourceRegistry($this->pdo);
            $this->sourceRegistry->registerDefaults();
            $this->sourceRegistry->loadFromDatabase();
        }
        return $this->sourceRegistry;
    }

    /**
     * Get all registered engine names and display names.
     *
     * @return array<string, string> [name => displayName]
     */
    public function availableEngines(): array
    {
        $result = [];
        foreach ($this->engines as $name => $engine) {
            $result[$name] = $engine->displayName();
        }
        return $result;
    }

    /**
     * Get status of all registered engines.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allEngineStatuses(): array
    {
        $statuses = [];
        foreach ($this->engines as $name => $engine) {
            $statuses[$name] = array_merge(
                $engine->status(),
                ['active' => $this->activeEngine === $engine],
            );
        }
        return $statuses;
    }

    // ── Search API ──────────────────────────────────────────────────────

    /**
     * Execute a search query on the active engine.
     */
    public function search(SearchQuery $query): SearchResult
    {
        return $this->engine()->search($query);
    }

    /**
     * Get autocomplete suggestions.
     *
     * @return list<string>
     */
    public function suggest(string $prefix, int $limit = 10): array
    {
        return $this->engine()->suggest($prefix, $limit);
    }

    /**
     * Convenience: search published content.
     */
    public function searchPublished(
        string $text,
        ?string $contentType = null,
        int $page = 1,
        int $perPage = 25,
    ): SearchResult {
        $query = new SearchQuery(
            text: $text,
            filters: array_filter([
                'status' => 'published',
                'content_type' => $contentType,
            ]),
        );

        return $this->search($query->withPage($page, $perPage));
    }

    // ── Indexing API ────────────────────────────────────────────────────

    /**
     * Index a content entity in the active search engine.
     *
     * @param array<string, mixed> $entity Content entity as array
     */
    public function indexContent(array $entity): void
    {
        $id = (string) ($entity['id'] ?? '');
        if ($id === '') {
            return;
        }

        $doc = $this->entityToDocument($entity);
        $this->engine()->index($id, $doc);
    }

    /**
     * Index multiple content entities in bulk.
     *
     * @param list<array<string, mixed>> $entities
     */
    public function bulkIndexContent(array $entities): void
    {
        $documents = [];
        foreach ($entities as $entity) {
            $id = (string) ($entity['id'] ?? '');
            if ($id !== '') {
                $documents[$id] = $this->entityToDocument($entity);
            }
        }

        if ($documents !== []) {
            $this->engine()->bulkIndex($documents);
        }
    }

    /**
     * Remove content from the search index.
     */
    public function removeContent(int|string $id): void
    {
        $this->engine()->delete((string) $id);
    }

    /**
     * Rebuild the entire search index from the database.
     *
     * @return int Number of documents indexed
     */
    public function rebuildIndex(int $batchSize = 500): int
    {
        $this->engine()->flush();

        $offset = 0;
        $total = 0;

        do {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM nodes WHERE deleted_at IS NULL ORDER BY id LIMIT :lim OFFSET :off"
            );
            $stmt->bindValue('lim', $batchSize, PDO::PARAM_INT);
            $stmt->bindValue('off', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                break;
            }

            $documents = [];
            foreach ($rows as $row) {
                $documents[(string) $row['id']] = $this->entityToDocument($row);
            }

            $this->engine()->bulkIndex($documents);
            $total += count($rows);
            $offset += $batchSize;

        } while (count($rows) === $batchSize);

        return $total;
    }

    // ── Internal ────────────────────────────────────────────────────────

    /**
     * Convert a content entity/row to a search document.
     *
     * @return array<string, mixed>
     */
    private function entityToDocument(array $entity): array
    {
        return [
            'title' => $entity['title'] ?? '',
            'body' => strip_tags($entity['body'] ?? ''),
            'summary' => $entity['summary'] ?? '',
            'slug' => $entity['slug'] ?? '',
            'content_type' => $entity['content_type'] ?? '',
            'status' => $entity['status'] ?? 'draft',
            'language' => $entity['language'] ?? 'en',
            'author_id' => (int) ($entity['author_id'] ?? 0),
            'published_at' => $entity['published_at'] ?? null,
            'created_at' => $entity['created_at'] ?? null,
            'updated_at' => $entity['updated_at'] ?? null,
        ];
    }
}
