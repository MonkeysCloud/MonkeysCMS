<?php

declare(strict_types=1);

namespace App\Cms\Content;

/**
 * PaginatedResult — Value object for paginated query results.
 *
 * Uses PHP 8.4 property hooks for computed pagination metadata.
 *
 * @template T
 */
final class PaginatedResult
{
    /**
     * @param list<T> $items   The result items for the current page
     * @param int     $total   Total number of items across all pages
     * @param int     $page    Current page number (1-indexed)
     * @param int     $perPage Items per page
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {}

    // ── PHP 8.4 Computed Property Hooks ──────────────────────────────────

    /** Total number of pages */
    public int $totalPages {
        get => $this->perPage > 0 ? (int) ceil($this->total / $this->perPage) : 0;
    }

    /** 1-indexed start position of current page */
    public int $from {
        get => $this->total > 0 ? (($this->page - 1) * $this->perPage) + 1 : 0;
    }

    /** 1-indexed end position of current page */
    public int $to {
        get => min($this->from + count($this->items) - 1, $this->total);
    }

    /** Whether there is a next page */
    public bool $hasNextPage {
        get => $this->page < $this->totalPages;
    }

    /** Whether there is a previous page */
    public bool $hasPrevPage {
        get => $this->page > 1;
    }

    /** Whether the result set is empty */
    public bool $isEmpty {
        get => $this->total === 0;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Serialize pagination metadata for template use.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'total'      => $this->total,
            'page'       => $this->page,
            'per_page'   => $this->perPage,
            'pages'      => $this->totalPages,
            'from'       => $this->from,
            'to'         => $this->to,
            'has_next'   => $this->hasNextPage,
            'has_prev'   => $this->hasPrevPage,
        ];
    }
}
