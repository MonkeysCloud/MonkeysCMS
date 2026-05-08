<?php

declare(strict_types=1);

namespace App\Cms\Seo;

use PDO;

/**
 * SitemapGenerator — Generates XML sitemaps from published content.
 *
 * Creates a sitemap index linking to per-content-type sitemaps,
 * and individual sitemaps listing all published URLs.
 */
final class SitemapGenerator
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * Generate the sitemap index XML.
     *
     * Lists all content types that have published content, each as a sub-sitemap.
     *
     * @return string XML sitemap index
     */
    public function generateIndex(string $baseUrl): string
    {
        $stmt = $this->pdo->query(
            "SELECT content_type, MAX(updated_at) AS last_mod
             FROM nodes
             WHERE status = 'published' AND deleted_at IS NULL
             GROUP BY content_type
             ORDER BY content_type"
        );

        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($types as $type) {
            $lastMod = (new \DateTimeImmutable($type['last_mod']))->format('c');
            $loc = rtrim($baseUrl, '/') . '/sitemap-' . htmlspecialchars($type['content_type']) . '.xml';

            $xml .= "  <sitemap>\n";
            $xml .= "    <loc>{$loc}</loc>\n";
            $xml .= "    <lastmod>{$lastMod}</lastmod>\n";
            $xml .= "  </sitemap>\n";
        }

        $xml .= "</sitemapindex>\n";

        return $xml;
    }

    /**
     * Generate a sitemap for a specific content type.
     *
     * @return string XML urlset
     */
    public function generateForType(string $contentType, string $baseUrl): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT slug, updated_at, content_type
             FROM nodes
             WHERE content_type = :type AND status = 'published' AND deleted_at IS NULL
             ORDER BY updated_at DESC
             LIMIT 50000"
        );
        $stmt->execute(['type' => $contentType]);
        $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Determine priority and changefreq based on content type
        $priority = $this->getPriority($contentType);
        $changefreq = $this->getChangefreq($contentType);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($nodes as $node) {
            $loc = rtrim($baseUrl, '/') . '/' . htmlspecialchars($node['slug']);
            $lastMod = (new \DateTimeImmutable($node['updated_at']))->format('c');

            $xml .= "  <url>\n";
            $xml .= "    <loc>{$loc}</loc>\n";
            $xml .= "    <lastmod>{$lastMod}</lastmod>\n";
            $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        return $xml;
    }

    /**
     * Get all content types that have published content.
     *
     * @return list<string>
     */
    public function getPublishedTypes(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT content_type FROM nodes WHERE status = 'published' AND deleted_at IS NULL ORDER BY content_type"
        );
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'content_type');
    }

    private function getPriority(string $contentType): string
    {
        return match ($contentType) {
            'page'    => '0.8',
            'article' => '0.6',
            'news'    => '0.7',
            'event'   => '0.5',
            default   => '0.5',
        };
    }

    private function getChangefreq(string $contentType): string
    {
        return match ($contentType) {
            'page'    => 'monthly',
            'article' => 'weekly',
            'news'    => 'daily',
            'event'   => 'weekly',
            default   => 'weekly',
        };
    }
}
