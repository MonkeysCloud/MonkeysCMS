<?php

declare(strict_types=1);

namespace App\Cms\Search;

/**
 * SearchHit — A single search result with metadata.
 *
 * Represents one matched document returned by a search engine,
 * including its relevance score, highlighted excerpts, and
 * any additional engine-specific metadata.
 */
final class SearchHit
{
    /**
     * @param string                  $id          Document ID
     * @param string                  $type        Content type (article, page, etc.)
     * @param string                  $title       Document title
     * @param string                  $url         URL/slug
     * @param float                   $score       Relevance score
     * @param array<string, string>   $highlights  Highlighted excerpts per field
     * @param array<string, mixed>    $source      Raw document data
     * @param \DateTimeImmutable|null $publishedAt Published date
     * @param string|null             $summary     Summary/excerpt text
     * @param string|null             $author      Author name
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $title,
        public readonly string $url,
        public readonly float $score = 0.0,
        public readonly array $highlights = [],
        public readonly array $source = [],
        public readonly ?\DateTimeImmutable $publishedAt = null,
        public readonly ?string $summary = null,
        public readonly ?string $author = null,
    ) {}

    /**
     * Get the best highlight for a given field, or fallback to source.
     */
    public function highlight(string $field, int $maxLength = 300): string
    {
        if (isset($this->highlights[$field]) && $this->highlights[$field] !== '') {
            return $this->highlights[$field];
        }

        $raw = $this->source[$field] ?? '';
        if (!is_string($raw)) {
            return '';
        }

        $text = strip_tags($raw);
        return mb_strlen($text) > $maxLength
            ? mb_substr($text, 0, $maxLength) . '…'
            : $text;
    }

    /**
     * Get the best available excerpt (highlight > summary > body truncation).
     */
    public function excerpt(int $maxLength = 200): string
    {
        // Prefer body highlight
        if (isset($this->highlights['body']) && $this->highlights['body'] !== '') {
            return $this->highlights['body'];
        }

        // Fall back to summary
        if ($this->summary !== null && $this->summary !== '') {
            return mb_strlen($this->summary) > $maxLength
                ? mb_substr($this->summary, 0, $maxLength) . '…'
                : $this->summary;
        }

        // Fall back to body truncation
        return $this->highlight('body', $maxLength);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'url' => $this->url,
            'score' => $this->score,
            'highlights' => $this->highlights,
            'published_at' => $this->publishedAt?->format('c'),
            'summary' => $this->summary,
            'author' => $this->author,
        ];
    }
}
