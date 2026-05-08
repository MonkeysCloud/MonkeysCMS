<?php

declare(strict_types=1);

namespace App\Cms\Search;

/**
 * SearchSource — Defines a searchable entity source.
 *
 * Each source maps a database table to the search system with
 * configurable fields, weights, URL patterns, and filter columns.
 *
 * Examples:
 *   - Content (nodes): searchable by title, body, summary
 *   - Taxonomy (terms): searchable by name, description
 *   - Users (cms_users): searchable by name, email
 *   - Media (media): searchable by title, alt, description
 */
final readonly class SearchSource
{
    /**
     * @param string               $key             Unique identifier (e.g. 'nodes', 'terms', 'users')
     * @param string               $table           Database table name
     * @param string               $label           Human-readable label
     * @param string               $entityType      Type tag for SearchHit (e.g. 'content', 'term', 'user')
     * @param string               $titleField      Column used as the hit title
     * @param list<string>         $searchFields    Columns to full-text search in
     * @param array<string, float> $fieldWeights    Boost weight per search field
     * @param string               $urlPattern      URL pattern; {slug} and {id} are replaced
     * @param string|null          $summaryField    Column for excerpt/summary (null = none)
     * @param string|null          $statusField     Column for status filtering (null = no filter)
     * @param string|null          $statusValue     Required status value (e.g. 'published')
     * @param string|null          $deletedField    Soft-delete column (IS NULL check)
     * @param string|null          $dateField       Column for published/created date
     * @param string|null          $authorJoin      JOIN clause for author name (null = no join)
     * @param string|null          $authorField     Column alias for author name after join
     * @param list<string>         $facetFields     Columns available for facet aggregation
     * @param string|null          $typeField       Column that provides a sub-type (e.g. content_type)
     * @param bool                 $enabled         Whether this source is enabled for search
     * @param int                  $priority        Display/search priority (lower = first)
     * @param string|null          $icon            Lucide icon name for admin UI
     */
    public function __construct(
        public string $key,
        public string $table,
        public string $label,
        public string $entityType,
        public string $titleField = 'title',
        public array $searchFields = ['title'],
        public array $fieldWeights = [],
        public string $urlPattern = '/{slug}',
        public ?string $summaryField = null,
        public ?string $statusField = null,
        public ?string $statusValue = null,
        public ?string $deletedField = 'deleted_at',
        public ?string $dateField = null,
        public ?string $authorJoin = null,
        public ?string $authorField = null,
        public array $facetFields = [],
        public ?string $typeField = null,
        public bool $enabled = true,
        public int $priority = 50,
        public ?string $icon = null,
    ) {}

    /**
     * Build the URL for a search hit from this source.
     */
    public function buildUrl(array $row): string
    {
        $url = $this->urlPattern;
        $url = str_replace('{id}', (string) ($row['id'] ?? ''), $url);
        $url = str_replace('{slug}', $row['slug'] ?? $row[$this->titleField] ?? '', $url);
        return '/' . ltrim($url, '/');
    }

    /**
     * Get the type label for a row (uses typeField if available).
     */
    public function getType(array $row): string
    {
        if ($this->typeField && isset($row[$this->typeField])) {
            return $row[$this->typeField];
        }
        return $this->entityType;
    }

    /**
     * Serialize to array for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'table' => $this->table,
            'label' => $this->label,
            'entity_type' => $this->entityType,
            'title_field' => $this->titleField,
            'search_fields' => $this->searchFields,
            'field_weights' => $this->fieldWeights,
            'url_pattern' => $this->urlPattern,
            'summary_field' => $this->summaryField,
            'status_field' => $this->statusField,
            'status_value' => $this->statusValue,
            'deleted_field' => $this->deletedField,
            'date_field' => $this->dateField,
            'author_join' => $this->authorJoin,
            'author_field' => $this->authorField,
            'facet_fields' => $this->facetFields,
            'type_field' => $this->typeField,
            'enabled' => $this->enabled,
            'priority' => $this->priority,
            'icon' => $this->icon,
        ];
    }

    /**
     * Create from saved array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            table: $data['table'],
            label: $data['label'] ?? $data['key'],
            entityType: $data['entity_type'] ?? $data['key'],
            titleField: $data['title_field'] ?? 'title',
            searchFields: $data['search_fields'] ?? ['title'],
            fieldWeights: $data['field_weights'] ?? [],
            urlPattern: $data['url_pattern'] ?? '/{slug}',
            summaryField: $data['summary_field'] ?? null,
            statusField: $data['status_field'] ?? null,
            statusValue: $data['status_value'] ?? null,
            deletedField: array_key_exists('deleted_field', $data) ? $data['deleted_field'] : 'deleted_at',
            dateField: $data['date_field'] ?? null,
            authorJoin: $data['author_join'] ?? null,
            authorField: $data['author_field'] ?? null,
            facetFields: $data['facet_fields'] ?? [],
            typeField: $data['type_field'] ?? null,
            enabled: $data['enabled'] ?? true,
            priority: $data['priority'] ?? 50,
            icon: $data['icon'] ?? null,
        );
    }
}
