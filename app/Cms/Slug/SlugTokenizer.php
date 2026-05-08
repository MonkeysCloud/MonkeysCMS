<?php

declare(strict_types=1);

namespace App\Cms\Slug;

use App\Cms\Content\ContentEntity;
use App\Cms\Taxonomy\TermEntity;

/**
 * SlugTokenizer — Replaces tokens in slug patterns with actual values.
 *
 * Supports node and taxonomy term entities.
 * Handles transliteration, normalization, and cleanup.
 */
final class SlugTokenizer
{
    /** All available tokens and their descriptions */
    public const array TOKENS = [
        'node' => [
            // Content
            '[title]'        => 'Content title (slugified)',
            '[type]'         => 'Content type machine name',
            '[id]'           => 'Content ID',
            '[summary]'      => 'Summary / teaser (slugified, truncated)',
            '[language]'     => 'Language code (e.g. en, es)',
            // Author / user
            '[author]'       => 'Author username (slugified)',
            '[author:id]'    => 'Author user ID',
            '[author:name]'  => 'Author display name (slugified)',
            // Published date
            '[year]'              => 'Published year (4 digits)',
            '[month]'             => 'Published month (2 digits)',
            '[day]'               => 'Published day (2 digits)',
            '[week]'              => 'Published week number',
            '[month:name]'        => 'Published month name (e.g. january)',
            '[month:short]'       => 'Published month abbreviation (e.g. jan)',
            '[day:name]'          => 'Published day name (e.g. monday)',
            '[day:short]'         => 'Published day abbreviation (e.g. mon)',
            '[date:iso]'          => 'Published date ISO (yyyy-mm-dd)',
            '[date:timestamp]'    => 'Published UNIX timestamp',
            // Created date
            '[created:year]'      => 'Created year (4 digits)',
            '[created:month]'     => 'Created month (2 digits)',
            '[created:day]'       => 'Created day (2 digits)',
            '[created:week]'      => 'Created week number',
            '[created:month:name]'  => 'Created month name',
            '[created:month:short]' => 'Created month abbreviation',
            '[created:day:name]'    => 'Created day name',
            '[created:day:short]'   => 'Created day abbreviation',
            '[created:iso]'         => 'Created date ISO (yyyy-mm-dd)',
            '[created:timestamp]'   => 'Created UNIX timestamp',
            // Updated / last modification date
            '[updated:year]'      => 'Last modified year (4 digits)',
            '[updated:month]'     => 'Last modified month (2 digits)',
            '[updated:day]'       => 'Last modified day (2 digits)',
            '[updated:week]'      => 'Last modified week number',
            '[updated:month:name]'  => 'Last modified month name',
            '[updated:month:short]' => 'Last modified month abbreviation',
            '[updated:day:name]'    => 'Last modified day name',
            '[updated:day:short]'   => 'Last modified day abbreviation',
            '[updated:iso]'         => 'Last modified date ISO (yyyy-mm-dd)',
            '[updated:timestamp]'   => 'Last modified UNIX timestamp',
        ],
        'term' => [
            '[name]'              => 'Term name (slugified)',
            '[vocabulary]'        => 'Vocabulary machine name',
            '[id]'                => 'Term ID',
            '[description]'       => 'Term description (slugified, truncated)',
            '[weight]'            => 'Term sort weight',
            '[parent]'            => 'Parent term name (slugified)',
            '[parent:id]'         => 'Parent term ID',
            // Created date
            '[created:year]'      => 'Created year (4 digits)',
            '[created:month]'     => 'Created month (2 digits)',
            '[created:day]'       => 'Created day (2 digits)',
            '[created:iso]'       => 'Created date ISO (yyyy-mm-dd)',
            // Updated / last modification date
            '[updated:year]'      => 'Updated year (4 digits)',
            '[updated:month]'     => 'Updated month (2 digits)',
            '[updated:day]'       => 'Updated day (2 digits)',
            '[updated:iso]'       => 'Updated date ISO (yyyy-mm-dd)',
        ],
    ];

    /**
     * Apply a pattern to a content node.
     */
    public function tokenizeNode(string $pattern, ContentEntity $node): string
    {
        $pubDate = $node->published_at ?? $node->created_at ?? new \DateTimeImmutable();
        $creDate = $node->created_at ?? new \DateTimeImmutable();
        $updDate = $node->updated_at ?? $creDate;

        $replacements = [
            // Content
            '[title]'        => $this->slugify($node->title ?? ''),
            '[type]'         => $node->content_type ?? '',
            '[id]'           => (string) ($node->id ?? 0),
            '[summary]'      => $this->slugify(mb_substr($node->summary ?? '', 0, 80)),
            '[language]'     => $node->language ?? 'en',
            // Author / user
            '[author]'       => $this->slugify($node->author_name ?? 'admin'),
            '[author:id]'    => (string) ($node->author_id ?? 0),
            '[author:name]'  => $this->slugify($node->author_name ?? 'admin'),
            // Published date
            '[year]'              => $pubDate->format('Y'),
            '[month]'             => $pubDate->format('m'),
            '[day]'               => $pubDate->format('d'),
            '[week]'              => $pubDate->format('W'),
            '[month:name]'        => strtolower($pubDate->format('F')),
            '[month:short]'       => strtolower($pubDate->format('M')),
            '[day:name]'          => strtolower($pubDate->format('l')),
            '[day:short]'         => strtolower($pubDate->format('D')),
            '[date:iso]'          => $pubDate->format('Y-m-d'),
            '[date:timestamp]'    => (string) $pubDate->getTimestamp(),
            // Created date
            '[created:year]'        => $creDate->format('Y'),
            '[created:month]'       => $creDate->format('m'),
            '[created:day]'         => $creDate->format('d'),
            '[created:week]'        => $creDate->format('W'),
            '[created:month:name]'  => strtolower($creDate->format('F')),
            '[created:month:short]' => strtolower($creDate->format('M')),
            '[created:day:name]'    => strtolower($creDate->format('l')),
            '[created:day:short]'   => strtolower($creDate->format('D')),
            '[created:iso]'         => $creDate->format('Y-m-d'),
            '[created:timestamp]'   => (string) $creDate->getTimestamp(),
            // Updated / last modification date
            '[updated:year]'        => $updDate->format('Y'),
            '[updated:month]'       => $updDate->format('m'),
            '[updated:day]'         => $updDate->format('d'),
            '[updated:week]'        => $updDate->format('W'),
            '[updated:month:name]'  => strtolower($updDate->format('F')),
            '[updated:month:short]' => strtolower($updDate->format('M')),
            '[updated:day:name]'    => strtolower($updDate->format('l')),
            '[updated:day:short]'   => strtolower($updDate->format('D')),
            '[updated:iso]'         => $updDate->format('Y-m-d'),
            '[updated:timestamp]'   => (string) $updDate->getTimestamp(),
        ];

        $slug = strtr($pattern, $replacements);

        return $this->normalize($slug);
    }

    /**
     * Apply a pattern to a taxonomy term.
     *
     * @param string          $parentName  Resolved parent term name (optional)
     */
    public function tokenizeTerm(
        string $pattern,
        TermEntity $term,
        string $vocabularyName = '',
        ?string $parentName = null,
    ): string {
        $creDate = $term->created_at ?? new \DateTimeImmutable();
        $updDate = $term->updated_at ?? $creDate;

        $replacements = [
            '[name]'              => $this->slugify($term->name ?? ''),
            '[vocabulary]'        => $vocabularyName ?: 'terms',
            '[id]'                => (string) ($term->id ?? 0),
            '[description]'       => $this->slugify(mb_substr($term->description ?? '', 0, 80)),
            '[weight]'            => (string) ($term->weight ?? 0),
            '[parent]'            => $this->slugify($parentName ?? ''),
            '[parent:id]'         => (string) ($term->parent_id ?? 0),
            // Created date
            '[created:year]'      => $creDate->format('Y'),
            '[created:month]'     => $creDate->format('m'),
            '[created:day]'       => $creDate->format('d'),
            '[created:iso]'       => $creDate->format('Y-m-d'),
            // Updated date
            '[updated:year]'      => $updDate->format('Y'),
            '[updated:month]'     => $updDate->format('m'),
            '[updated:day]'       => $updDate->format('d'),
            '[updated:iso]'       => $updDate->format('Y-m-d'),
        ];

        $slug = strtr($pattern, $replacements);

        return $this->normalize($slug);
    }

    /**
     * Convert arbitrary text to a URL-safe slug.
     *
     * Handles accented characters, whitespace, and special chars.
     */
    public function slugify(string $text): string
    {
        // Transliterate accented characters
        $text = $this->transliterate($text);

        // Lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Replace non-alphanumeric with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Trim hyphens
        return trim($text, '-');
    }

    /**
     * Normalize a slug path (may contain slashes from patterns like [type]/[title]).
     */
    private function normalize(string $slug): string
    {
        // Clean each segment
        $parts = array_filter(explode('/', $slug), static fn(string $p) => $p !== '');
        $cleaned = array_map(fn(string $p) => trim(preg_replace('/-+/', '-', $p), '-'), $parts);

        return implode('/', array_filter($cleaned));
    }

    /**
     * Basic transliteration for common accented characters.
     */
    private function transliterate(string $text): string
    {
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u', 'ö' => 'o', 'ä' => 'a', 'ë' => 'e',
            'ï' => 'i', 'ÿ' => 'y', 'â' => 'a', 'ê' => 'e', 'î' => 'i',
            'ô' => 'o', 'û' => 'u', 'à' => 'a', 'è' => 'e', 'ì' => 'i',
            'ò' => 'o', 'ù' => 'u', 'ã' => 'a', 'õ' => 'o', 'ç' => 'c',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ñ' => 'N', 'Ü' => 'U', 'Ö' => 'O', 'Ä' => 'A', 'Ë' => 'E',
            'ß' => 'ss', 'æ' => 'ae', 'ø' => 'o', 'å' => 'a',
        ];

        return strtr($text, $map);
    }
}
