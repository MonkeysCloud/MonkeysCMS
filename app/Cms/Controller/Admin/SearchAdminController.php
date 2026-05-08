<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Search\SearchManager;
use App\Cms\Search\SearchQuery;
use App\Cms\Search\Engine\DatabaseSearchEngine;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * SearchAdminController — Admin pages for search engine management.
 *
 * Provides:
 * - Dashboard: engine status, document count, health
 * - Test: live query testing with result inspection
 * - Settings: engine selection, configuration
 * - Indexing: rebuild, flush, status
 */
#[RoutePrefix('/admin/search')]
final class SearchAdminController
{
    private readonly SearchManager $searchManager;

    public function __construct(
        private readonly Renderer $renderer,
        private readonly PDO $pdo,
    ) {
        // Build search manager from settings
        $this->searchManager = $this->buildSearchManager();
    }

    // ── Dashboard ───────────────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::search.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $engineStatuses = $this->searchManager->allEngineStatuses();
        $availableEngines = $this->searchManager->availableEngines();

        // Get active engine name
        $activeEngine = $this->searchManager->engine()->name();

        // Recent search stats (if we have a search_log table)
        $searchStats = $this->getSearchStats();

        return Response::html($this->renderer->render('admin::search.index', [
            'title'            => 'Search',
            'engineStatuses'   => $engineStatuses,
            'availableEngines' => $availableEngines,
            'activeEngine'     => $activeEngine,
            'searchStats'      => $searchStats,
        ]));
    }

    // ── Test Query ──────────────────────────────────────────────────────

    #[Route('GET', '/test', name: 'admin::search.test')]
    public function test(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $queryText = trim($params['q'] ?? '');
        $engine = $params['engine'] ?? null;
        $contentType = $params['type'] ?? null;
        $showAll = ($params['status'] ?? 'published') === 'all';

        $result = null;

        if ($queryText !== '') {
            $query = new SearchQuery(
                text: $queryText,
                filters: array_filter([
                    'status' => $showAll ? null : 'published',
                    'content_type' => $contentType ?: null,
                ]),
                facetFields: ['content_type', 'status'],
                highlight: true,
                highlightLength: 250,
            );

            // Use specific engine if requested
            if ($engine !== null) {
                $eng = $this->searchManager->getEngine($engine);
                if ($eng) {
                    $result = $eng->search($query);
                }
            }

            $result ??= $this->searchManager->search($query);
        }

        return Response::html($this->renderer->render('admin::search.test', [
            'title'            => 'Search Test',
            'queryText'        => $queryText,
            'result'           => $result,
            'engines'          => $this->searchManager->availableEngines(),
            'selectedEngine'   => $engine,
            'selectedType'     => $contentType,
            'showAll'          => $showAll,
        ]));
    }

    // ── Settings ────────────────────────────────────────────────────────

    #[Route('GET', '/settings', name: 'admin::search.settings')]
    public function settings(ServerRequestInterface $request): Response
    {
        $session = $request->getAttribute('session');
        $flash = $session?->pull('flash_success', '');

        // Load current settings
        $settings = $this->loadSettings();

        // All possible engines — always show all options even if not yet configured
        $allEngines = [
            'database'      => 'Database (MySQL / PostgreSQL / SQLite)',
            'elasticsearch' => 'Elasticsearch / OpenSearch',
            'solr'          => 'Apache Solr',
        ];

        // Merge with any dynamically registered engines
        foreach ($this->searchManager->availableEngines() as $name => $label) {
            if (!isset($allEngines[$name])) {
                $allEngines[$name] = $label;
            }
        }

        // Add DB driver info to settings
        try {
            $settings['_db_driver'] = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable) {
            $settings['_db_driver'] = 'unknown';
        }

        return Response::html($this->renderer->render('admin::search.settings', [
            'title'    => 'Search Settings',
            'settings' => $settings,
            'engines'  => $allEngines,
            'flash'    => $flash,
        ]));
    }

    #[Route('POST', '/settings', name: 'admin::search.settings.save')]
    public function saveSettings(ServerRequestInterface $request): Response
    {
        $body = $request->getParsedBody() ?? [];

        // Save settings
        $settings = [
            'search_engine'              => $body['search_engine'] ?? 'database',
            // Elasticsearch
            'elasticsearch_host'         => $body['elasticsearch_host'] ?? '',
            'elasticsearch_index'        => $body['elasticsearch_index'] ?? 'monkeyscms_content',
            'elasticsearch_api_key'      => $body['elasticsearch_api_key'] ?? '',
            'elasticsearch_username'     => $body['elasticsearch_username'] ?? '',
            'elasticsearch_password'     => $body['elasticsearch_password'] ?? '',
            'elasticsearch_ssl_verify'   => isset($body['elasticsearch_ssl_verify']) ? '1' : '0',
            'elasticsearch_prefix'       => $body['elasticsearch_prefix'] ?? '',
            'elasticsearch_timeout'      => $body['elasticsearch_timeout'] ?? '30',
            // Solr
            'solr_host'                  => $body['solr_host'] ?? '',
            'solr_core'                  => $body['solr_core'] ?? 'monkeyscms',
            'solr_username'              => $body['solr_username'] ?? '',
            'solr_password'              => $body['solr_password'] ?? '',
            'solr_timeout'               => $body['solr_timeout'] ?? '30',
        ];

        foreach ($settings as $key => $value) {
            $this->saveSetting($key, $value);
        }

        $session = $request->getAttribute('session');
        $session?->set('flash_success', 'Search settings saved.');

        return Response::redirect('/admin/search/settings');
    }

    // ── Indexing ────────────────────────────────────────────────────────

    #[Route('POST', '/reindex', name: 'admin::search.reindex')]
    public function reindex(ServerRequestInterface $request): Response
    {
        $count = $this->searchManager->rebuildIndex();

        $session = $request->getAttribute('session');
        $session?->set('flash_success', "Rebuilt search index: {$count} documents indexed.");

        return Response::redirect('/admin/search');
    }

    #[Route('POST', '/flush', name: 'admin::search.flush')]
    public function flush(ServerRequestInterface $request): Response
    {
        $this->searchManager->engine()->flush();

        $session = $request->getAttribute('session');
        $session?->set('flash_success', 'Search index flushed.');

        return Response::redirect('/admin/search');
    }

    // ── Sources Configuration ───────────────────────────────────────────

    #[Route('GET', '/sources', name: 'admin::search.sources')]
    public function sources(ServerRequestInterface $request): Response
    {
        $registry = $this->searchManager->sources();
        $allSources = $registry->all();

        // Get columns for each source table
        $columns = [];
        $counts = [];
        foreach ($allSources as $key => $source) {
            $columns[$key] = $registry->getTableColumns($source->table);
            try {
                $where = '1=1';
                if ($source->deletedField) {
                    $where .= " AND {$source->deletedField} IS NULL";
                }
                $counts[$key] = (int) $this->pdo->query(
                    "SELECT COUNT(*) FROM {$source->table} WHERE {$where}"
                )->fetchColumn();
            } catch (\Throwable) {
                $counts[$key] = 0;
            }
        }

        // Load dynamic field definitions for content types (EAV fields)
        $contentTypeFields = [];
        try {
            $stmt = $this->pdo->query(
                "SELECT ct.type_id, ct.name AS ct_name,
                        fd.machine_name, fd.name AS field_label, fd.field_type, fd.searchable
                 FROM field_definitions fd
                 JOIN content_types ct ON ct.id = fd.content_type_id
                 WHERE fd.field_type IN ('text','textarea','wysiwyg','string','email','url','markdown')
                 ORDER BY ct.name, fd.weight"
            );
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $typeId = $row['type_id'];
                $contentTypeFields[$typeId] ??= [
                    'label' => $row['ct_name'],
                    'fields' => [],
                ];
                $contentTypeFields[$typeId]['fields'][] = [
                    'machine_name' => $row['machine_name'],
                    'label' => $row['field_label'],
                    'field_type' => $row['field_type'],
                    'searchable' => (bool) $row['searchable'],
                ];
            }
        } catch (\Throwable) {
            // field_definitions table may not exist yet
        }

        $session = $request->getAttribute('session');
        $flash = $session?->pull('flash_success', '');

        return Response::html($this->renderer->render('admin::search.sources', [
            'title'             => 'Search Sources',
            'sources'           => $allSources,
            'columns'           => $columns,
            'counts'            => $counts,
            'contentTypeFields' => $contentTypeFields,
            'success'           => $flash,
        ]));
    }

    #[Route('POST', '/sources', name: 'admin::search.sources.save')]
    public function saveSources(ServerRequestInterface $request): Response
    {
        $body = $request->getParsedBody() ?? [];
        $sourcesData = $body['sources'] ?? [];

        $registry = $this->searchManager->sources();
        $allSources = $registry->all();

        foreach ($allSources as $key => $existing) {
            $data = $sourcesData[$key] ?? [];

            $searchFields = $data['search_fields'] ?? $existing->searchFields;
            $weights = $data['weights'] ?? [];

            // Build field weights
            $fieldWeights = [];
            foreach ($searchFields as $field) {
                $fieldWeights[$field] = (float) ($weights[$field] ?? $existing->fieldWeights[$field] ?? 1.0);
            }

            $updated = new \App\Cms\Search\SearchSource(
                key: $key,
                table: $existing->table,
                label: $existing->label,
                entityType: $data['entity_type'] ?? $existing->entityType,
                titleField: $data['title_field'] ?? $existing->titleField,
                searchFields: $searchFields,
                fieldWeights: $fieldWeights,
                urlPattern: $data['url_pattern'] ?? $existing->urlPattern,
                summaryField: ($data['summary_field'] ?? '') !== '' ? $data['summary_field'] : null,
                statusField: ($data['status_field'] ?? '') !== '' ? $data['status_field'] : null,
                statusValue: ($data['status_value'] ?? '') !== '' ? $data['status_value'] : null,
                deletedField: $existing->deletedField,
                dateField: ($data['date_field'] ?? '') !== '' ? $data['date_field'] : null,
                authorJoin: $existing->authorJoin,
                authorField: $existing->authorField,
                facetFields: $existing->facetFields,
                typeField: $existing->typeField,
                enabled: isset($data['enabled']),
                priority: (int) ($data['priority'] ?? $existing->priority),
                icon: $existing->icon,
            );

            $registry->save($updated);
        }

        $session = $request->getAttribute('session');
        $session?->set('flash_success', 'Search source configuration saved.');

        return Response::redirect('/admin/search/sources');
    }

    #[Route('POST', '/sources/fields', name: 'admin::search.sources.fields.save')]
    public function saveFields(ServerRequestInterface $request): Response
    {
        $body = $request->getParsedBody() ?? [];
        $enabledFields = $body['fields'] ?? [];

        try {
            // Get all text-type field definitions
            $stmt = $this->pdo->query(
                "SELECT id, machine_name FROM field_definitions
                 WHERE field_type IN ('text','textarea','wysiwyg','string','email','url','markdown')"
            );

            $updateStmt = $this->pdo->prepare(
                "UPDATE field_definitions SET searchable = :searchable WHERE id = :id"
            );

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $isSearchable = isset($enabledFields[$row['machine_name']]) ? 1 : 0;
                $updateStmt->execute([
                    'searchable' => $isSearchable,
                    'id' => (int) $row['id'],
                ]);
            }
        } catch (\Throwable) {
            // field_definitions table may not exist
        }

        $session = $request->getAttribute('session');
        $session?->set('flash_success', 'Field searchability updated.');

        return Response::redirect('/admin/search/sources');
    }

    // ── API Endpoints ───────────────────────────────────────────────────

    #[Route('GET', '/api/suggest', name: 'admin::search.api.suggest')]
    public function apiSuggest(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $prefix = trim($params['q'] ?? '');

        $suggestions = $this->searchManager->suggest($prefix, 10);

        return Response::json(['suggestions' => $suggestions]);
    }

    #[Route('GET', '/api/status', name: 'admin::search.api.status')]
    public function apiStatus(ServerRequestInterface $request): Response
    {
        return Response::json($this->searchManager->allEngineStatuses());
    }

    #[Route('GET', '/api/columns', name: 'admin::search.api.columns')]
    public function apiColumns(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $table = $params['table'] ?? '';

        if ($table === '') {
            return Response::json(['columns' => []]);
        }

        $columns = $this->searchManager->sources()->getTableColumns($table);
        return Response::json(['columns' => $columns]);
    }

    // ── Internal Helpers ────────────────────────────────────────────────

    private function buildSearchManager(): SearchManager
    {
        $settings = $this->loadSettings();

        $config = [
            'engine' => $settings['search_engine'] ?? 'database',
        ];

        if (!empty($settings['elasticsearch_host'])) {
            $config['elasticsearch'] = [
                'host'    => $settings['elasticsearch_host'],
                'index'   => $settings['elasticsearch_index'] ?? 'monkeyscms_content',
                'api_key' => $settings['elasticsearch_api_key'] ?: null,
            ];
        }

        if (!empty($settings['solr_host'])) {
            $config['solr'] = [
                'host' => $settings['solr_host'],
                'core' => $settings['solr_core'] ?? 'monkeyscms',
            ];
        }

        return SearchManager::create($this->pdo, $config);
    }

    /** @return array<string, string> */
    private function loadSettings(): array
    {
        $settings = [];

        try {
            $stmt = $this->pdo->query(
                "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'search_%' OR setting_key LIKE 'elasticsearch_%' OR setting_key LIKE 'solr_%'"
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable) {
            // Settings table may not exist yet
        }

        return $settings;
    }

    private function saveSetting(string $key, string $value): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO settings (setting_key, setting_value, autoload, updated_at)
                 VALUES (:key, :value, 1, NOW())
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
            );
            $stmt->execute(['key' => $key, 'value' => $value]);
        } catch (\Throwable) {
            // Ignore if settings table doesn't exist
        }
    }

    /** @return array<string, mixed> */
    private function getSearchStats(): array
    {
        try {
            $total = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM nodes WHERE deleted_at IS NULL"
            )->fetchColumn();

            $published = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM nodes WHERE status = 'published' AND deleted_at IS NULL"
            )->fetchColumn();

            $types = $this->pdo->query(
                "SELECT content_type, COUNT(*) AS cnt FROM nodes
                 WHERE deleted_at IS NULL GROUP BY content_type ORDER BY cnt DESC"
            )->fetchAll(PDO::FETCH_KEY_PAIR);

            return [
                'total_documents' => $total,
                'published' => $published,
                'by_type' => $types,
            ];
        } catch (\Throwable) {
            return ['total_documents' => 0, 'published' => 0, 'by_type' => []];
        }
    }
}
