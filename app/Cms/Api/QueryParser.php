<?php

declare(strict_types=1);

namespace App\Cms\Api;

use Psr\Http\Message\ServerRequestInterface;

/**
 * QueryParser — Parses JSON:API query parameters.
 *
 * Handles: filter, sort, page, include, fields (sparse fieldsets)
 */
final class QueryParser
{
    public readonly array $filters;
    public readonly array $sort;
    public readonly int $page;
    public readonly int $perPage;
    public readonly array $include;
    public readonly array $fields;

    public function __construct(ServerRequestInterface $request)
    {
        $params = $request->getQueryParams();

        // filter[status]=published&filter[type]=article
        $this->filters = (array) ($params['filter'] ?? []);

        // sort=-created_at,title
        $this->sort = $this->parseSort($params['sort'] ?? '');

        // page[number]=1&page[size]=25
        $pageParams = (array) ($params['page'] ?? []);
        $this->page = max(1, (int) ($pageParams['number'] ?? $params['page'] ?? 1));
        $this->perPage = min(100, max(1, (int) ($pageParams['size'] ?? $params['per_page'] ?? 25)));

        // include=author,terms
        $this->include = $this->parseInclude($params['include'] ?? '');

        // fields[articles]=title,slug,body
        $this->fields = (array) ($params['fields'] ?? []);
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function hasFilter(string $key): bool
    {
        return isset($this->filters[$key]);
    }

    public function getFilter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    public function getSortColumn(): string
    {
        return $this->sort[0]['column'] ?? 'created_at';
    }

    public function getSortDirection(): string
    {
        return $this->sort[0]['direction'] ?? 'DESC';
    }

    public function shouldInclude(string $relation): bool
    {
        return in_array($relation, $this->include, true);
    }

    /**
     * Apply sparse fieldset filtering to an attributes array
     */
    public function sparseFields(string $type, array $attributes): array
    {
        if (empty($this->fields[$type])) {
            return $attributes;
        }

        $allowed = array_map('trim', explode(',', $this->fields[$type]));
        return array_intersect_key($attributes, array_flip($allowed));
    }

    private function parseSort(string $sortString): array
    {
        if (!$sortString) return [];

        return array_map(function (string $field) {
            $field = trim($field);
            if (str_starts_with($field, '-')) {
                return ['column' => substr($field, 1), 'direction' => 'DESC'];
            }
            return ['column' => $field, 'direction' => 'ASC'];
        }, explode(',', $sortString));
    }

    private function parseInclude(string $includeString): array
    {
        if (!$includeString) return [];
        return array_map('trim', explode(',', $includeString));
    }
}
