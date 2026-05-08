<?php

declare(strict_types=1);

namespace App\Cms\Controller;

use App\Cms\Content\ContentRepository;
use App\Cms\I18n\LanguageService;
use App\Cms\I18n\TranslationService;
use App\Cms\Url\UrlManager;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use PDO;

/**
 * SitemapController — Generates multilingual XML sitemaps.
 *
 * Follows Google's multilingual sitemap guidelines:
 * https://developers.google.com/search/docs/specialty/international/localized-versions
 *
 * Each <url> includes <xhtml:link rel="alternate"> entries for every
 * language translation, enabling search engines to serve the correct
 * language version to users.
 */
final class SitemapController
{
    public function __construct(
        private readonly ContentRepository $contentRepo,
        private readonly LanguageService $languageService,
        private readonly TranslationService $translationService,
        private readonly UrlManager $urlManager,
        private readonly PDO $pdo,
    ) {}

    /**
     * GET /sitemap.xml — Generate XML sitemap with multilingual alternates.
     */
    #[Route('GET', '/sitemap.xml', name: 'front.sitemap')]
    public function index(): Response
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        $multilingualEnabled = $this->languageService->isEnabled();
        $defaultLang = $this->languageService->getDefaultCode();
        $enabledLangs = $multilingualEnabled ? $this->languageService->getEnabledCodes() : [];

        // Load all published content
        $stmt = $this->pdo->query(
            "SELECT id, slug, content_type, language, updated_at FROM nodes 
             WHERE status = 'published' AND deleted_at IS NULL 
             ORDER BY updated_at DESC"
        );
        $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Batch-load all translations if multilingual is active
        $translationMap = [];
        if ($multilingualEnabled && !empty($nodes)) {
            $nodeIds = array_column($nodes, 'id');
            $translationMap = $this->translationService->getTranslationsForMany('node', array_map('intval', $nodeIds));
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // Homepage
        $xml .= $this->buildUrlEntry($appUrl, '/', date('c'), '1.0', 'daily',
            $multilingualEnabled ? $enabledLangs : [], $appUrl, $defaultLang);

        // Content nodes
        foreach ($nodes as $row) {
            $nodeId = (int) $row['id'];
            $nodeLang = $row['language'] ?? $defaultLang;
            $slug = $row['slug'] ?? '';
            $contentType = $row['content_type'] ?? 'page';
            $updatedAt = $row['updated_at'] ?? date('Y-m-d');

            // Build the node's base URL
            $baseUrl = '/' . $slug;
            $nodeUrl = $this->urlManager->prefixLocale($baseUrl, $multilingualEnabled ? $nodeLang : null);

            // Determine priority based on content type
            $priority = match ($contentType) {
                'page' => '0.8',
                'article' => '0.7',
                'news' => '0.6',
                default => '0.5',
            };

            // Build alternate URLs for multilingual
            $alternates = [];
            if ($multilingualEnabled) {
                // Current node's language
                $alternates[$nodeLang] = $nodeUrl;

                // Translation siblings
                $translations = $translationMap[$nodeId] ?? [];
                foreach ($translations as $lang => $targetId) {
                    // Look up the translated node's slug
                    $targetSlug = $this->resolveSlug($targetId);
                    if ($targetSlug !== null) {
                        $alternates[$lang] = $this->urlManager->prefixLocale('/' . $targetSlug, $lang);
                    }
                }
            }

            $xml .= $this->buildContentUrlEntry(
                $appUrl, $nodeUrl, $updatedAt, $priority, 'weekly',
                $alternates, $defaultLang,
            );
        }

        $xml .= '</urlset>' . "\n";

        return new Response(
            status: 200,
            headers: [
                'Content-Type' => 'application/xml; charset=utf-8',
                'Cache-Control' => 'public, max-age=3600',
                'X-Robots-Tag' => 'noindex',
            ],
            body: $xml,
        );
    }

    /**
     * Build a <url> entry for a page with optional xhtml:link alternates.
     */
    private function buildUrlEntry(
        string $appUrl,
        string $path,
        string $lastmod,
        string $priority,
        string $changefreq,
        array $enabledLangs,
        string $baseUrl,
        string $defaultLang,
    ): string {
        $xml = "  <url>\n";
        $xml .= "    <loc>{$appUrl}{$path}</loc>\n";
        $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
        $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
        $xml .= "    <priority>{$priority}</priority>\n";

        // Homepage alternates: link each language to its prefixed root
        foreach ($enabledLangs as $lang) {
            $langUrl = ($lang === $defaultLang) ? '/' : "/{$lang}/";
            $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"{$lang}\" href=\"{$appUrl}{$langUrl}\"/>\n";
        }
        if (!empty($enabledLangs)) {
            $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"{$appUrl}/\"/>\n";
        }

        $xml .= "  </url>\n";
        return $xml;
    }

    /**
     * Build a <url> entry for content with translation alternates.
     */
    private function buildContentUrlEntry(
        string $appUrl,
        string $path,
        string $lastmod,
        string $priority,
        string $changefreq,
        array $alternates,
        string $defaultLang,
    ): string {
        $xml = "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars("{$appUrl}{$path}") . "</loc>\n";
        $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
        $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
        $xml .= "    <priority>{$priority}</priority>\n";

        // xhtml:link alternates for each translation
        foreach ($alternates as $lang => $altPath) {
            $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"{$lang}\" href=\""
                   . htmlspecialchars("{$appUrl}{$altPath}") . "\"/>\n";
        }

        // x-default = default language version
        if (count($alternates) > 1 && isset($alternates[$defaultLang])) {
            $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\""
                   . htmlspecialchars("{$appUrl}{$alternates[$defaultLang]}") . "\"/>\n";
        }

        $xml .= "  </url>\n";
        return $xml;
    }

    /**
     * Resolve a node's slug by ID (lightweight query).
     */
    private function resolveSlug(int $nodeId): ?string
    {
        static $cache = [];
        if (isset($cache[$nodeId])) {
            return $cache[$nodeId];
        }

        $stmt = $this->pdo->prepare('SELECT slug FROM nodes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $nodeId]);
        $slug = $stmt->fetchColumn();

        $cache[$nodeId] = $slug !== false ? (string) $slug : null;
        return $cache[$nodeId];
    }
}
