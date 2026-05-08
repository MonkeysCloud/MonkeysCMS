<?php

declare(strict_types=1);

namespace App\Cms\Seo;

use App\Cms\Service\SettingsService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface;

/**
 * SitemapController — Serves XML sitemaps for search engine crawlers.
 *
 * Routes:
 *   GET /sitemap.xml           → Sitemap index
 *   GET /sitemap-{type}.xml    → Per-content-type sitemap
 *   GET /robots.txt            → Dynamic robots.txt
 */
final class SitemapController
{
    public function __construct(
        private readonly SitemapGenerator $generator,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Sitemap index — links to per-content-type sitemaps.
     */
    #[Route('GET', '/sitemap.xml', name: 'seo.sitemap.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $baseUrl = $this->getBaseUrl($request);
        $xml = $this->generator->generateIndex($baseUrl);

        return $this->xmlResponse($xml);
    }

    /**
     * Per-content-type sitemap.
     */
    #[Route('GET', '/sitemap-{type:[a-z_]+}.xml', name: 'seo.sitemap.type')]
    public function contentType(ServerRequestInterface $request, string $type): Response
    {
        $baseUrl = $this->getBaseUrl($request);

        // Verify this content type has published content
        $publishedTypes = $this->generator->getPublishedTypes();
        if (!in_array($type, $publishedTypes, true)) {
            return Response::html('Not Found', 404);
        }

        $xml = $this->generator->generateForType($type, $baseUrl);

        return $this->xmlResponse($xml);
    }

    /**
     * Dynamic robots.txt — configurable via admin settings.
     */
    #[Route('GET', '/robots.txt', name: 'seo.robots')]
    public function robots(ServerRequestInterface $request): Response
    {
        $baseUrl = $this->getBaseUrl($request);

        $custom = $this->settings->get('seo_robots_txt');
        if ($custom !== null && $custom !== '') {
            $content = $custom;
        } else {
            $content = "User-agent: *\n";
            $content .= "Allow: /\n";
            $content .= "Disallow: /admin/\n";
            $content .= "Disallow: /api/\n";
            $content .= "Disallow: /install\n\n";
            $content .= "Sitemap: {$baseUrl}/sitemap.xml\n";
        }

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'text/plain; charset=utf-8'],
            body: $content,
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function getBaseUrl(ServerRequestInterface $request): string
    {
        // Prefer configured site URL, fall back to request URI
        $siteUrl = $this->settings->get('site_url');
        if ($siteUrl !== null && $siteUrl !== '') {
            return rtrim($siteUrl, '/');
        }

        $uri = $request->getUri();
        return $uri->getScheme() . '://' . $uri->getHost() . ($uri->getPort() ? ':' . $uri->getPort() : '');
    }

    private function xmlResponse(string $xml): Response
    {
        return new Response(
            status: 200,
            headers: [
                'Content-Type'  => 'application/xml; charset=utf-8',
                'Cache-Control' => 'public, max-age=3600',
            ],
            body: $xml,
        );
    }
}
