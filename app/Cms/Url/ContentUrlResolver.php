<?php

declare(strict_types=1);

namespace App\Cms\Url;

use App\Cms\Content\ContentEntity;
use App\Cms\Content\ContentRepository;
use App\Cms\Content\ContentTypeManager;

/**
 * ContentUrlResolver — URL resolver for content nodes.
 *
 * Generates frontend and admin URLs for ContentEntity using the
 * content type's url_pattern. Supports path resolution for incoming
 * requests.
 *
 * PHP 8.4+
 */
final class ContentUrlResolver implements UrlResolverInterface
{
    /** @var array<string, array{pattern: string, regex: string, type_id: string}>|null */
    private ?array $compiledRoutes = null;

    public function __construct(
        private readonly ContentTypeManager $typeManager,
        private readonly ContentRepository $contentRepo,
    ) {}

    public function getEntityType(): string
    {
        return 'node';
    }

    /**
     * Generate a frontend URL for a content node.
     */
    public function frontendUrl(object|int $entity, array $context = []): ?string
    {
        if (is_int($entity)) {
            $entity = $this->contentRepo->find($entity);
            if (!$entity) {
                return null;
            }
        }

        if (!$entity instanceof ContentEntity) {
            return null;
        }

        $this->compile();

        $route = $this->compiledRoutes[$entity->content_type] ?? null;

        if (!$route) {
            // Fallback: /{type}/{slug}
            return '/' . $entity->content_type . '/' . $entity->slug;
        }

        return $this->interpolate($route['pattern'], $entity);
    }

    /**
     * Generate an admin edit URL for a content node.
     */
    public function adminUrl(object|int $entity): ?string
    {
        if (is_int($entity)) {
            return '/admin/content/' . $entity . '/edit';
        }

        if (!$entity instanceof ContentEntity) {
            return null;
        }

        return '/admin/content/' . ($entity->id ?? 0) . '/edit';
    }

    /**
     * Resolve a path to a content node.
     */
    public function resolve(string $path): ?array
    {
        $this->compile();

        $path = '/' . trim($path, '/');

        foreach ($this->compiledRoutes as $typeId => $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                $slug = $matches['slug'] ?? '';

                if ($slug === '') {
                    continue;
                }

                $node = $this->contentRepo->findBySlug($slug, $typeId);

                if ($node) {
                    return [
                        'entity' => $node,
                        'type'   => $typeId,
                        'params' => array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Check if this resolver can handle the given path.
     */
    public function supports(string $path): bool
    {
        $this->compile();

        $path = '/' . trim($path, '/');

        foreach ($this->compiledRoutes as $route) {
            if (preg_match($route['regex'], $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the compiled URL patterns for all content types.
     *
     * @return array<string, string>
     */
    public function getPatterns(): array
    {
        $this->compile();

        $patterns = [];
        foreach ($this->compiledRoutes as $typeId => $route) {
            $patterns[$typeId] = $route['pattern'];
        }

        return $patterns;
    }

    // ── Private ─────────────────────────────────────────────────────────

    /**
     * Compile content type patterns into regex routes.
     */
    private function compile(): void
    {
        if ($this->compiledRoutes !== null) {
            return;
        }

        $this->compiledRoutes = [];

        try {
            $types = $this->typeManager->getEnabled();
        } catch (\Throwable) {
            return;
        }

        foreach ($types as $type) {
            $typeId = is_array($type) ? ($type['type_id'] ?? '') : ($type->type_id ?? '');
            $pattern = is_array($type) ? ($type['url_pattern'] ?? null) : ($type->url_pattern ?? null);

            if (!$typeId) {
                continue;
            }

            $pattern = $pattern ?: $this->defaultPattern($typeId);

            $this->compiledRoutes[$typeId] = [
                'pattern' => $pattern,
                'regex'   => $this->patternToRegex($pattern),
                'type_id' => $typeId,
            ];
        }

        // Sort by specificity: longer patterns first
        uasort($this->compiledRoutes, function ($a, $b) {
            $aDepth = substr_count($a['pattern'], '/');
            $bDepth = substr_count($b['pattern'], '/');

            if ($aDepth !== $bDepth) {
                return $bDepth - $aDepth;
            }

            return strlen($b['pattern']) - strlen($a['pattern']);
        });
    }

    /**
     * Interpolate tokens in a URL pattern with entity values.
     */
    private function interpolate(string $pattern, ContentEntity $entity): string
    {
        return strtr($pattern, [
            '{slug}'  => $entity->slug,
            '{id}'    => (string) ($entity->id ?? 0),
            '{year}'  => $entity->published_at?->format('Y') ?? date('Y'),
            '{month}' => $entity->published_at?->format('m') ?? date('m'),
            '{day}'   => $entity->published_at?->format('d') ?? date('d'),
            '{type}'  => $entity->content_type,
        ]);
    }

    /**
     * Default URL pattern for a content type.
     */
    private function defaultPattern(string $typeId): string
    {
        // All content types default to clean /{slug} URLs.
        // Users can override via admin URL Aliases with [type]/[title], [year]/[title], etc.
        return '/{slug}';
    }

    /**
     * Convert a URL pattern to a named regex.
     */
    private function patternToRegex(string $pattern): string
    {
        $regex = preg_quote($pattern, '#');

        $replacements = [
            '\\{slug\\}'  => '(?P<slug>[a-z0-9][a-z0-9-]*)',
            '\\{id\\}'    => '(?P<id>\\d+)',
            '\\{year\\}'  => '(?P<year>\\d{4})',
            '\\{month\\}' => '(?P<month>\\d{2})',
            '\\{day\\}'   => '(?P<day>\\d{2})',
            '\\{type\\}'  => '(?P<type>[a-z][a-z0-9_]*)',
        ];

        $regex = strtr($regex, $replacements);

        return '#^' . $regex . '$#';
    }
}
