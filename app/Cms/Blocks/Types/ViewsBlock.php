<?php

declare(strict_types=1);

namespace App\Cms\Blocks\Types;

use App\Modules\Core\Entities\Block;
use App\Cms\Repository\CmsRepository;
use MonkeysLegion\Database\Connection;

/**
 * ViewsBlock - Dynamic content queries block
 * 
 * This block type allows you to display dynamic content based on queries.
 * Similar to Drupal's Views module.
 */
class ViewsBlock extends AbstractBlockType
{
    protected const ID = 'views';
    protected const LABEL = 'Dynamic Content Block';
    protected const DESCRIPTION = 'Display dynamic content from database queries';
    protected const ICON = '🔍';
    protected const CATEGORY = 'Content';
    protected const CACHE_TTL = 1800; // 30 minutes

    private ?CmsRepository $repository = null;
    private ?Connection $connection = null;

    public function setRepository(CmsRepository $repository): void
    {
        $this->repository = $repository;
    }

    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    public static function getFields(): array
    {
        return [
            'content_type' => [
                'type' => 'select',
                'label' => 'Content Type',
                'required' => true,
                'description' => 'Select the type of content to display',
                'settings' => [
                    'options_callback' => 'getContentTypeOptions',
                ],
            ],
            'display_mode' => [
                'type' => 'select',
                'label' => 'Display Mode',
                'required' => false,
                'default' => 'list',
                'settings' => [
                    'options' => [
                        'list' => 'List',
                        'grid' => 'Grid',
                        'table' => 'Table',
                        'teaser' => 'Teasers',
                        'full' => 'Full Content',
                        'custom' => 'Custom Template',
                    ],
                ],
            ],
            'items_per_page' => [
                'type' => 'integer',
                'label' => 'Items Per Page',
                'required' => false,
                'default' => 10,
                'description' => '0 = unlimited',
            ],
            'offset' => [
                'type' => 'integer',
                'label' => 'Offset',
                'required' => false,
                'default' => 0,
                'description' => 'Skip this many items',
            ],
            'sort_field' => [
                'type' => 'string',
                'label' => 'Sort By Field',
                'required' => false,
                'default' => 'created_at',
            ],
            'sort_direction' => [
                'type' => 'select',
                'label' => 'Sort Direction',
                'required' => false,
                'default' => 'DESC',
                'settings' => [
                    'options' => [
                        'ASC' => 'Ascending',
                        'DESC' => 'Descending',
                    ],
                ],
            ],
            'filters' => [
                'type' => 'json',
                'label' => 'Filters',
                'required' => false,
                'default' => [],
                'description' => 'JSON array of filter conditions',
                'widget' => 'filters_builder',
            ],
            'fields' => [
                'type' => 'json',
                'label' => 'Fields to Display',
                'required' => false,
                'default' => [],
                'description' => 'JSON array of field names to display',
            ],
            'columns' => [
                'type' => 'select',
                'label' => 'Grid Columns',
                'required' => false,
                'default' => '3',
                'settings' => [
                    'options' => [
                        '2' => '2 Columns',
                        '3' => '3 Columns',
                        '4' => '4 Columns',
                    ],
                ],
            ],
            'show_pager' => [
                'type' => 'boolean',
                'label' => 'Show Pagination',
                'default' => true,
            ],
            'empty_message' => [
                'type' => 'string',
                'label' => 'Empty Results Message',
                'required' => false,
                'default' => 'No content found.',
            ],
            'header_text' => [
                'type' => 'text',
                'label' => 'Header Text',
                'required' => false,
                'description' => 'Text to display above results',
            ],
            'footer_text' => [
                'type' => 'text',
                'label' => 'Footer Text',
                'required' => false,
                'description' => 'Text to display below results',
            ],
            'custom_template' => [
                'type' => 'string',
                'label' => 'Custom Template',
                'required' => false,
                'description' => 'Template path for custom display mode',
            ],
            'taxonomy_filter' => [
                'type' => 'taxonomy_reference',
                'label' => 'Taxonomy Filter',
                'required' => false,
                'description' => 'Filter by taxonomy term',
            ],
        ];
    }

    public function render(Block $block, array $context = []): string
    {
        $contentType = $this->getFieldValue($block, 'content_type');
        $displayMode = $this->getFieldValue($block, 'display_mode', 'list');
        $itemsPerPage = (int) $this->getFieldValue($block, 'items_per_page', 10);
        $offset = (int) $this->getFieldValue($block, 'offset', 0);
        $sortField = $this->getFieldValue($block, 'sort_field', 'created_at');
        $sortDirection = $this->getFieldValue($block, 'sort_direction', 'DESC');
        $filters = $this->getFieldValue($block, 'filters', []);
        $displayFields = $this->getFieldValue($block, 'fields', []);
        $columns = $this->getFieldValue($block, 'columns', '3');
        $showPager = $this->getFieldValue($block, 'show_pager', true);
        $emptyMessage = $this->getFieldValue($block, 'empty_message', 'No content found.');
        $headerText = $this->getFieldValue($block, 'header_text', '');
        $footerText = $this->getFieldValue($block, 'footer_text', '');
        $taxonomyFilter = $this->getFieldValue($block, 'taxonomy_filter');

        if (!$contentType) {
            return '<div class="views-block views-block--error">Content type not configured</div>';
        }

        // Get current page from context
        $currentPage = (int) ($context['page'] ?? 1);
        
        // Build and execute query
        $results = $this->executeQuery($contentType, [
            'filters' => $filters,
            'taxonomy_term' => $taxonomyFilter,
            'sort_field' => $sortField,
            'sort_direction' => $sortDirection,
            'limit' => $itemsPerPage > 0 ? $itemsPerPage : null,
            'offset' => $itemsPerPage > 0 ? (($currentPage - 1) * $itemsPerPage) + $offset : $offset,
        ]);

        $totalCount = $this->getCount($contentType, $filters, $taxonomyFilter);

        // Build HTML
        $html = '<div class="views-block views-block--' . $displayMode . '">';

        // Header
        if ($headerText) {
            $html .= '<div class="views-block__header">' . $headerText . '</div>';
        }

        // Content
        if (empty($results)) {
            $html .= '<div class="views-block__empty">' . $this->escape($emptyMessage) . '</div>';
        } else {
            $html .= match ($displayMode) {
                'grid' => $this->renderGrid($results, $displayFields, (int) $columns),
                'table' => $this->renderTable($results, $displayFields),
                'teaser' => $this->renderTeasers($results, $displayFields),
                'full' => $this->renderFull($results),
                'custom' => $this->renderCustom($results, $this->getFieldValue($block, 'custom_template')),
                default => $this->renderList($results, $displayFields),
            };
        }

        // Pager
        if ($showPager && $itemsPerPage > 0 && $totalCount > $itemsPerPage) {
            $totalPages = ceil($totalCount / $itemsPerPage);
            $html .= $this->renderPager($currentPage, (int) $totalPages, $block->id);
        }

        // Footer
        if ($footerText) {
            $html .= '<div class="views-block__footer">' . $footerText . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function renderList(array $items, array $fields): string
    {
        $html = '<ul class="views-block__list">';
        
        foreach ($items as $item) {
            $html .= '<li class="views-block__item">';
            $html .= $this->renderItemFields($item, $fields);
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        return $html;
    }

    private function renderGrid(array $items, array $fields, int $columns): string
    {
        $html = '<div class="views-block__grid" style="--columns: ' . $columns . ';">';
        
        foreach ($items as $item) {
            $html .= '<div class="views-block__grid-item">';
            $html .= $this->renderItemFields($item, $fields);
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    private function renderTable(array $items, array $fields): string
    {
        if (empty($items)) {
            return '';
        }

        // Use provided fields or all fields from first item
        $columns = !empty($fields) ? $fields : array_keys((array) $items[0]);
        
        $html = '<table class="views-block__table">';
        
        // Header
        $html .= '<thead><tr>';
        foreach ($columns as $column) {
            $label = $this->formatFieldLabel($column);
            $html .= "<th>{$label}</th>";
        }
        $html .= '</tr></thead>';
        
        // Body
        $html .= '<tbody>';
        foreach ($items as $item) {
            $itemArray = (array) $item;
            $html .= '<tr>';
            foreach ($columns as $column) {
                $value = $itemArray[$column] ?? '';
                $html .= '<td>' . $this->formatFieldValue($value, $column) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        return $html;
    }

    private function renderTeasers(array $items, array $fields): string
    {
        $html = '<div class="views-block__teasers">';
        
        foreach ($items as $item) {
            $itemArray = (array) $item;
            $html .= '<article class="views-block__teaser">';
            
            // Title
            if (isset($itemArray['title']) || isset($itemArray['name'])) {
                $title = $itemArray['title'] ?? $itemArray['name'];
                $id = $itemArray['id'] ?? null;
                $html .= '<h3 class="views-block__teaser-title">';
                if ($id) {
                    $html .= "<a href=\"/content/{$id}\">{$this->escape($title)}</a>";
                } else {
                    $html .= $this->escape($title);
                }
                $html .= '</h3>';
            }
            
            // Summary/excerpt
            if (isset($itemArray['summary'])) {
                $html .= '<div class="views-block__teaser-summary">' . $itemArray['summary'] . '</div>';
            } elseif (isset($itemArray['body'])) {
                $excerpt = strip_tags($itemArray['body']);
                $excerpt = mb_substr($excerpt, 0, 200) . '...';
                $html .= '<div class="views-block__teaser-summary">' . $this->escape($excerpt) . '</div>';
            }
            
            // Meta
            if (isset($itemArray['created_at'])) {
                $date = $itemArray['created_at'] instanceof \DateTimeInterface 
                    ? $itemArray['created_at']->format('M j, Y')
                    : $itemArray['created_at'];
                $html .= '<div class="views-block__teaser-meta">' . $date . '</div>';
            }
            
            $html .= '</article>';
        }
        
        $html .= '</div>';
        return $html;
    }

    private function renderFull(array $items): string
    {
        $html = '<div class="views-block__full">';
        
        foreach ($items as $item) {
            $itemArray = (array) $item;
            $html .= '<article class="views-block__full-item">';
            
            foreach ($itemArray as $field => $value) {
                if ($field === 'id' || str_ends_with($field, '_id')) {
                    continue;
                }
                $html .= '<div class="views-block__field views-block__field--' . $field . '">';
                $html .= '<label class="views-block__field-label">' . $this->formatFieldLabel($field) . '</label>';
                $html .= '<div class="views-block__field-value">' . $this->formatFieldValue($value, $field) . '</div>';
                $html .= '</div>';
            }
            
            $html .= '</article>';
        }
        
        $html .= '</div>';
        return $html;
    }

    private function renderCustom(array $items, ?string $template): string
    {
        if (!$template || !file_exists($template)) {
            return $this->renderList($items, []);
        }

        ob_start();
        $items = $items; // Make available to template
        include $template;
        return ob_get_clean() ?: '';
    }

    private function renderItemFields(object|array $item, array $fields): string
    {
        $itemArray = (array) $item;
        
        if (empty($fields)) {
            $fields = ['title', 'name'];
        }
        
        $html = '';
        foreach ($fields as $field) {
            if (isset($itemArray[$field])) {
                $html .= '<span class="views-block__field views-block__field--' . $field . '">';
                $html .= $this->formatFieldValue($itemArray[$field], $field);
                $html .= '</span>';
            }
        }
        
        return $html;
    }

    private function renderPager(int $currentPage, int $totalPages, int $blockId): string
    {
        $html = '<nav class="views-block__pager" aria-label="Pagination">';
        $html .= '<ul class="views-block__pager-list">';
        
        // Previous
        if ($currentPage > 1) {
            $html .= '<li class="views-block__pager-item views-block__pager-item--prev">';
            $html .= '<a href="?block_' . $blockId . '_page=' . ($currentPage - 1) . '">« Previous</a>';
            $html .= '</li>';
        }
        
        // Page numbers
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $activeClass = $i === $currentPage ? ' views-block__pager-item--active' : '';
            $html .= '<li class="views-block__pager-item' . $activeClass . '">';
            if ($i === $currentPage) {
                $html .= '<span>' . $i . '</span>';
            } else {
                $html .= '<a href="?block_' . $blockId . '_page=' . $i . '">' . $i . '</a>';
            }
            $html .= '</li>';
        }
        
        // Next
        if ($currentPage < $totalPages) {
            $html .= '<li class="views-block__pager-item views-block__pager-item--next">';
            $html .= '<a href="?block_' . $blockId . '_page=' . ($currentPage + 1) . '">Next »</a>';
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</nav>';
        
        return $html;
    }

    private function formatFieldLabel(string $field): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $field));
    }

    private function formatFieldValue(mixed $value, string $field): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('M j, Y g:i A');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        // Auto-link URLs
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return '<a href="' . $this->escape($value) . '" target="_blank">' . $this->escape($value) . '</a>';
        }

        return $this->escape((string) $value);
    }

    private function executeQuery(string $contentType, array $options): array
    {
        if (!$this->connection) {
            return [];
        }

        $tableName = $this->getTableName($contentType);
        
        $sql = "SELECT * FROM {$tableName}";
        $params = [];
        $wheres = [];

        // Apply filters
        foreach ($options['filters'] as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? null;
            
            if ($field && $value !== null) {
                $paramName = ':filter_' . $field;
                $wheres[] = "{$field} {$operator} {$paramName}";
                $params[$paramName] = $value;
            }
        }

        // Published filter (if applicable)
        $wheres[] = "(is_published = 1 OR status = 'published')";

        if (!empty($wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $wheres);
        }

        // Sort
        $sortField = preg_replace('/[^a-z0-9_]/i', '', $options['sort_field'] ?? 'created_at');
        $sortDir = strtoupper($options['sort_direction'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$sortField} {$sortDir}";

        // Limit/offset
        if ($options['limit']) {
            $sql .= " LIMIT {$options['limit']}";
        }
        if ($options['offset']) {
            $sql .= " OFFSET {$options['offset']}";
        }

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getCount(string $contentType, array $filters, ?int $taxonomyTerm): int
    {
        if (!$this->connection) {
            return 0;
        }

        $tableName = $this->getTableName($contentType);
        
        $sql = "SELECT COUNT(*) FROM {$tableName} WHERE (is_published = 1 OR status = 'published')";

        try {
            $stmt = $this->connection->query($sql);
            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getTableName(string $contentType): string
    {
        // Map content type to table name
        return match ($contentType) {
            'user', 'users' => 'users',
            'block', 'blocks' => 'blocks',
            'media' => 'media',
            default => $contentType,
        };
    }

    public function getCacheTags(Block $block): array
    {
        $tags = parent::getCacheTags($block);
        
        $contentType = $this->getFieldValue($block, 'content_type');
        if ($contentType) {
            $tags[] = 'content_type:' . $contentType;
        }
        
        return $tags;
    }

    public static function getCssAssets(): array
    {
        return [
            '/css/blocks/views-block.css',
        ];
    }
}
