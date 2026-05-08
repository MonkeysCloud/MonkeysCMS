<?php

declare(strict_types=1);

namespace App\Cms\Api;

/**
 * JsonApiFormatter — Builds JSON:API 1.1 spec-compliant responses.
 *
 * @see https://jsonapi.org/format/
 */
final class JsonApiFormatter
{
    private string $baseUrl;

    public function __construct(string $baseUrl = '')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Format a single resource
     */
    public function resource(
        string $type,
        int|string $id,
        array $attributes,
        array $relationships = [],
        array $links = [],
        array $meta = [],
    ): array {
        $data = [
            'type' => $type,
            'id' => (string) $id,
            'attributes' => $attributes,
        ];

        if ($relationships) {
            $data['relationships'] = $this->formatRelationships($relationships);
        }

        $data['links'] = array_merge(
            ['self' => $this->baseUrl . '/' . $type . '/' . $id],
            $links,
        );

        $response = ['data' => $data];

        if ($meta) {
            $response['meta'] = $meta;
        }

        $response['jsonapi'] = ['version' => '1.1'];

        return $response;
    }

    /**
     * Format a collection of resources
     */
    public function collection(
        string $type,
        array $items,
        array $meta = [],
        array $links = [],
        array $included = [],
    ): array {
        $data = array_map(
            fn(array $item) => [
                'type' => $type,
                'id' => (string) ($item['id'] ?? ''),
                'attributes' => $item['attributes'] ?? $item,
                'links' => ['self' => $this->baseUrl . '/' . $type . '/' . ($item['id'] ?? '')],
            ],
            $items,
        );

        $response = ['data' => $data];

        if ($meta) {
            $response['meta'] = $meta;
        }

        $collectionLinks = array_merge(
            ['self' => $this->baseUrl . '/' . $type],
            $links,
        );
        $response['links'] = $collectionLinks;

        if ($included) {
            $response['included'] = $included;
        }

        $response['jsonapi'] = ['version' => '1.1'];

        return $response;
    }

    /**
     * Format pagination links
     */
    public function paginationLinks(
        string $type,
        int $page,
        int $lastPage,
        array $queryParams = [],
    ): array {
        $base = $this->baseUrl . '/' . $type;
        $buildUrl = function (int $p) use ($base, $queryParams): string {
            $params = array_merge($queryParams, ['page[number]' => $p]);
            return $base . '?' . http_build_query($params);
        };

        $links = [
            'self' => $buildUrl($page),
            'first' => $buildUrl(1),
            'last' => $buildUrl($lastPage),
        ];

        if ($page > 1) {
            $links['prev'] = $buildUrl($page - 1);
        }
        if ($page < $lastPage) {
            $links['next'] = $buildUrl($page + 1);
        }

        return $links;
    }

    /**
     * Format pagination meta
     */
    public function paginationMeta(int $total, int $page, int $perPage, int $lastPage): array
    {
        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Format an error response
     */
    public function error(int $status, string $title, ?string $detail = null, ?string $pointer = null): array
    {
        $error = [
            'status' => (string) $status,
            'title' => $title,
        ];

        if ($detail) {
            $error['detail'] = $detail;
        }

        if ($pointer) {
            $error['source'] = ['pointer' => $pointer];
        }

        return [
            'errors' => [$error],
            'jsonapi' => ['version' => '1.1'],
        ];
    }

    /**
     * Format multiple errors
     */
    public function errors(int $status, array $errors): array
    {
        return [
            'errors' => array_map(fn(array $e) => [
                'status' => (string) $status,
                'title' => $e['title'] ?? 'Error',
                'detail' => $e['detail'] ?? null,
                'source' => isset($e['pointer']) ? ['pointer' => $e['pointer']] : null,
            ], $errors),
            'jsonapi' => ['version' => '1.1'],
        ];
    }

    /**
     * Format relationship data
     */
    private function formatRelationships(array $relationships): array
    {
        $formatted = [];
        foreach ($relationships as $name => $rel) {
            if (isset($rel['data'])) {
                // Already formatted
                $formatted[$name] = $rel;
            } elseif (isset($rel['type'], $rel['id'])) {
                // Single relation
                $formatted[$name] = [
                    'data' => ['type' => $rel['type'], 'id' => (string) $rel['id']],
                ];
            } elseif (is_array($rel) && isset($rel[0])) {
                // Many relation
                $formatted[$name] = [
                    'data' => array_map(fn($r) => [
                        'type' => $r['type'],
                        'id' => (string) $r['id'],
                    ], $rel),
                ];
            }
        }
        return $formatted;
    }
}
