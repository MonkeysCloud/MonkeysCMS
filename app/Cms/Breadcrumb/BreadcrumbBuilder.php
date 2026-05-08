<?php

declare(strict_types=1);

namespace App\Cms\Breadcrumb;

use App\Cms\Content\ContentEntity;
use App\Cms\Taxonomy\TermEntity;
use App\Cms\Taxonomy\TaxonomyRepository;
use App\Cms\Taxonomy\VocabularyEntity;

/**
 * BreadcrumbBuilder — Constructs breadcrumb trails from CMS context.
 *
 * Resolves configuration per entity type/bundle and builds an ordered
 * trail of BreadcrumbItem crumbs. Supports content nodes, taxonomy
 * terms, and listing pages.
 */
final class BreadcrumbBuilder
{
    public function __construct(
        private readonly BreadcrumbRepository $configRepo,
        private readonly TaxonomyRepository   $taxonomyRepo,
    ) {}

    /**
     * Build a breadcrumb trail for a content node.
     *
     * Trail: Home › ContentType › [Term] › Title
     */
    public function buildForNode(ContentEntity $node, ?object $contentType = null): BreadcrumbTrail
    {
        $typeName = $node->content_type ?: 'page';
        $config   = $this->configRepo->resolveConfig('node', $typeName);

        if (!$config->enabled) {
            return new BreadcrumbTrail([], $config->separator);
        }

        $items = [];

        // Home
        if ($config->show_home) {
            $items[] = new BreadcrumbItem('Home', '/');
        }

        // Content type
        if ($config->show_content_type && $contentType !== null) {
            $typeLabel = $contentType->label_plural ?? $contentType->label ?? ucfirst($typeName);
            $typeUrl   = '/' . $typeName . 's';
            $items[]   = new BreadcrumbItem($typeLabel, $typeUrl);
        }

        // Primary taxonomy term
        if ($config->show_taxonomy && $node->id !== null) {
            $termItem = $this->resolvePrimaryTerm($node->id);
            if ($termItem !== null) {
                $items[] = $termItem;
            }
        }

        // Current page
        if ($config->show_current) {
            $items[] = new BreadcrumbItem($node->title ?: 'Untitled');
        }

        return new BreadcrumbTrail($items, $config->separator);
    }

    /**
     * Build a breadcrumb trail for a taxonomy term page.
     *
     * Trail: Home › Vocabulary › Term
     */
    public function buildForTerm(TermEntity $term, VocabularyEntity $vocab): BreadcrumbTrail
    {
        $config = $this->configRepo->resolveConfig('term', $vocab->machine_name);

        if (!$config->enabled) {
            return new BreadcrumbTrail([], $config->separator);
        }

        $items = [];

        // Home
        if ($config->show_home) {
            $items[] = new BreadcrumbItem('Home', '/');
        }

        // Vocabulary name (linked to vocab listing if it exists)
        $items[] = new BreadcrumbItem($vocab->label, '/' . $vocab->machine_name);

        // Parent term chain (if hierarchical)
        if ($term->parent_id) {
            $parents = $this->resolveParentChain($term->parent_id, $vocab->machine_name);
            foreach ($parents as $parent) {
                $items[] = $parent;
            }
        }

        // Current term
        if ($config->show_current) {
            $items[] = new BreadcrumbItem($term->name);
        }

        return new BreadcrumbTrail($items, $config->separator);
    }

    /**
     * Build a breadcrumb trail for a listing page.
     *
     * Trail: Home › ContentType (plural)
     */
    public function buildForListing(?object $contentType = null, string $typeName = ''): BreadcrumbTrail
    {
        $config = $this->configRepo->resolveConfig('listing', $typeName ?: '*');

        if (!$config->enabled) {
            return new BreadcrumbTrail([], $config->separator);
        }

        $items = [];

        if ($config->show_home) {
            $items[] = new BreadcrumbItem('Home', '/');
        }

        if ($contentType !== null) {
            $label = $contentType->label_plural ?? $contentType->label ?? ucfirst($typeName);
            $items[] = new BreadcrumbItem(is_string($label) ? $label : ucfirst($typeName));
        } elseif ($typeName !== '') {
            $items[] = new BreadcrumbItem(ucfirst($typeName));
        }

        return new BreadcrumbTrail($items, $config->separator);
    }

    /**
     * Build a simple custom trail from an array of [label, url] pairs.
     *
     * @param list<array{label: string, url?: string}> $crumbs
     */
    public function buildCustom(array $crumbs, string $separator = '›'): BreadcrumbTrail
    {
        $items = array_map(
            static fn(array $c) => new BreadcrumbItem($c['label'], $c['url'] ?? null),
            $crumbs,
        );

        return new BreadcrumbTrail($items, $separator);
    }

    /**
     * Get the effective config for JSON-LD output.
     */
    public function shouldOutputJsonLd(string $entityType, string $bundle): bool
    {
        return $this->configRepo->resolveConfig($entityType, $bundle)->json_ld;
    }

    // ── Private ─────────────────────────────────────────────────────────

    /**
     * Resolve the primary taxonomy term for a node.
     *
     * Returns the first term from the first vocabulary attached to the node.
     */
    private function resolvePrimaryTerm(int $nodeId): ?BreadcrumbItem
    {
        try {
            $terms = $this->taxonomyRepo->findTermsForNode($nodeId);
            if (empty($terms)) {
                return null;
            }

            $term = $terms[0];
            // Build the URL from vocabulary + term slug
            $vocabStmt = $this->taxonomyRepo->findVocabularyById($term->vocabulary_id);
            $vocabName = $vocabStmt?->machine_name ?? 'terms';

            return new BreadcrumbItem(
                $term->name,
                '/' . $vocabName . '/' . $term->slug,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Walk up the parent chain for hierarchical terms.
     *
     * @return list<BreadcrumbItem>
     */
    private function resolveParentChain(int $parentId, string $vocabMachineName, int $maxDepth = 5): array
    {
        $chain = [];
        $currentId = $parentId;
        $depth = 0;

        while ($currentId !== null && $depth < $maxDepth) {
            try {
                $parent = $this->taxonomyRepo->findTerm($currentId);
                if ($parent === null) {
                    break;
                }

                array_unshift($chain, new BreadcrumbItem(
                    $parent->name,
                    '/' . $vocabMachineName . '/' . $parent->slug,
                ));

                $currentId = $parent->parent_id;
                $depth++;
            } catch (\Throwable) {
                break;
            }
        }

        return $chain;
    }
}
