<?php

declare(strict_types=1);

namespace App\Cms\Content;

/**
 * ContentRouter — Dynamic URL resolver for content nodes.
 *
 * Reads `url_pattern` from content types and matches incoming
 * request paths to content nodes. Supports patterns like:
 *   /blog/{slug}        → article type
 *   /{slug}             → page type (catch-all)
 *   /news/{year}/{slug} → news type with year token
 *
 * PHP 8.4 property hooks for computed route metadata.
 */
final class ContentRouter
{
    /** @var array<string, ContentTypeEntity> */
    private array $types = [];

    /** @var array<string, array{pattern: string, regex: string, type: ContentTypeEntity}> */
    private array $routes = [];

    private bool $compiled = false;

    public function __construct(
        private readonly ContentTypeManager $typeManager,
        private readonly ContentRepository $contentRepo,
    ) {}

    /**
     * Resolve a request path to a content node.
     *
     * @return array{node: ContentEntity, type: ContentTypeEntity, params: array}|null
     */
    public function resolve(string $path): ?array
    {
        $this->compile();

        $path = '/' . trim($path, '/');

        // Try each route in priority order (most specific first)
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                $slug = $matches['slug'] ?? '';

                if ($slug === '') {
                    continue;
                }

                $node = $this->contentRepo->findBySlug($slug, $route['type']->type_id);

                if ($node && $node->status === 'published') {
                    return [
                        'node'   => $node,
                        'type'   => $route['type'],
                        'params' => array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Generate a URL for a content node.
     */
    public function urlFor(ContentEntity $node): string
    {
        $this->compile();

        $route = $this->routes[$node->content_type] ?? null;

        if (!$route) {
            return '/' . $node->slug;
        }

        $url = $route['pattern'];

        // Replace tokens
        $replacements = [
            '{slug}'  => $node->slug,
            '{id}'    => (string) $node->id,
            '{year}'  => $node->published_at?->format('Y') ?? date('Y'),
            '{month}' => $node->published_at?->format('m') ?? date('m'),
        ];

        return strtr($url, $replacements);
    }

    /**
     * Get all registered route patterns.
     *
     * @return array<string, string>
     */
    public function getPatterns(): array
    {
        $this->compile();

        $patterns = [];
        foreach ($this->routes as $typeId => $route) {
            $patterns[$typeId] = $route['pattern'];
        }

        return $patterns;
    }

    /**
     * Get the template name for a content type.
     */
    public function templateFor(ContentTypeEntity $type): string
    {
        // First try type-specific template (e.g., "article", "page")
        // Falls back to generic "content"
        return $type->type_id;
    }

    // ── Private ─────────────────────────────────────────────────────────

    /**
     * Compile content type patterns into regex routes.
     */
    private function compile(): void
    {
        if ($this->compiled) {
            return;
        }

        $types = $this->typeManager->getEnabled();

        foreach ($types as $type) {
            $pattern = $type->url_pattern ?: $this->defaultPattern($type->type_id);

            // Convert pattern tokens to regex
            $regex = $this->patternToRegex($pattern);

            $this->routes[$type->type_id] = [
                'pattern' => $pattern,
                'regex'   => $regex,
                'type'    => $type,
            ];
        }

        // Sort by specificity: longer patterns first, catch-all last
        uasort($this->routes, function ($a, $b) {
            $aDepth = substr_count($a['pattern'], '/');
            $bDepth = substr_count($b['pattern'], '/');

            if ($aDepth !== $bDepth) {
                return $bDepth - $aDepth; // More segments = higher priority
            }

            return strlen($b['pattern']) - strlen($a['pattern']);
        });

        $this->compiled = true;
    }

    /**
     * Default URL pattern for a content type when none is configured in the DB.
     */
    private function defaultPattern(string $typeId): string
    {
        return match ($typeId) {
            'page' => '/{slug}',
            default => '/' . $typeId . '/{slug}',
        };
    }

    /**
     * Convert a URL pattern to a named regex.
     *
     * Tokens: {slug}, {id}, {year}, {month}
     */
    private function patternToRegex(string $pattern): string
    {
        $regex = preg_quote($pattern, '#');

        $replacements = [
            '\\{slug\\}'  => '(?P<slug>[a-z0-9][a-z0-9-]*)',
            '\\{id\\}'    => '(?P<id>\\d+)',
            '\\{year\\}'  => '(?P<year>\\d{4})',
            '\\{month\\}' => '(?P<month>\\d{2})',
        ];

        $regex = strtr($regex, $replacements);

        return '#^' . $regex . '$#';
    }
}
