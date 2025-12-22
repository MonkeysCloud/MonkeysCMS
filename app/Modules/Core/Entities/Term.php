<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * Term Entity - Individual taxonomy terms within a vocabulary
 */
#[ContentType(
    tableName: 'taxonomy_terms',
    label: 'Term',
    description: 'Taxonomy terms for categorizing content',
    icon: '🏷️',
    revisionable: false,
    publishable: true
)]
class Term extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(
        type: 'int',
        label: 'Vocabulary ID',
        required: true,
        indexed: true
    )]
    public int $vocabulary_id = 0;

    #[Field(
        type: 'int',
        label: 'Parent Term ID',
        required: false,
        indexed: true
    )]
    public ?int $parent_id = null;

    #[Field(
        type: 'string',
        label: 'Name',
        required: true,
        length: 255,
        searchable: true
    )]
    public string $name = '';

    #[Field(
        type: 'string',
        label: 'Slug',
        required: true,
        length: 255,
        indexed: true
    )]
    public string $slug = '';

    #[Field(
        type: 'text',
        label: 'Description',
        required: false,
        widget: 'textarea'
    )]
    public string $description = '';

    #[Field(
        type: 'string',
        label: 'Color',
        required: false,
        length: 20,
        widget: 'color'
    )]
    public ?string $color = null;

    #[Field(
        type: 'string',
        label: 'Icon',
        required: false,
        length: 100
    )]
    public ?string $icon = null;

    #[Field(
        type: 'string',
        label: 'Image URL',
        required: false,
        length: 500,
        widget: 'image'
    )]
    public ?string $image = null;

    #[Field(
        type: 'int',
        label: 'Weight',
        default: 0,
        indexed: true
    )]
    public int $weight = 0;

    #[Field(
        type: 'int',
        label: 'Depth',
        default: 0
    )]
    public int $depth = 0;

    #[Field(
        type: 'string',
        label: 'Path',
        required: false,
        length: 1000
    )]
    public ?string $path = null;

    #[Field(
        type: 'boolean',
        label: 'Published',
        default: true
    )]
    public bool $is_published = true;

    #[Field(
        type: 'json',
        label: 'Metadata',
        default: []
    )]
    public array $metadata = [];

    #[Field(
        type: 'string',
        label: 'SEO Title',
        required: false,
        length: 255
    )]
    public ?string $meta_title = null;

    #[Field(
        type: 'text',
        label: 'SEO Description',
        required: false
    )]
    public ?string $meta_description = null;

    #[Field(type: 'datetime', label: 'Created At')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime', label: 'Updated At')]
    public ?\DateTimeImmutable $updated_at = null;

    /**
     * Parent term (loaded separately)
     */
    public ?Term $parent = null;

    /**
     * Child terms (loaded separately)
     * @var Term[]
     */
    public array $children = [];

    /**
     * Vocabulary (loaded separately)
     */
    public ?Vocabulary $vocabulary = null;

    // ─────────────────────────────────────────────────────────────
    // Business Logic
    // ─────────────────────────────────────────────────────────────

    public function prePersist(): void
    {
        parent::prePersist();

        if (empty($this->slug)) {
            $this->slug = $this->generateSlug($this->name);
        }

        // Calculate path for hierarchical terms
        $this->updatePath();
    }

    /**
     * Generate URL-friendly slug
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Update materialized path
     */
    public function updatePath(): void
    {
        if ($this->parent !== null) {
            $this->path = $this->parent->path . '/' . $this->id;
            $this->depth = $this->parent->depth + 1;
        } else {
            $this->path = '/' . ($this->id ?? '');
            $this->depth = 0;
        }
    }

    /**
     * Check if term has children
     */
    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    /**
     * Check if term is a root term
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if term is a leaf (no children)
     */
    public function isLeaf(): bool
    {
        return empty($this->children);
    }

    /**
     * Check if this term is an ancestor of another term
     */
    public function isAncestorOf(Term $term): bool
    {
        if ($term->path === null || $this->path === null) {
            return false;
        }
        return str_starts_with($term->path, $this->path . '/');
    }

    /**
     * Check if this term is a descendant of another term
     */
    public function isDescendantOf(Term $term): bool
    {
        return $term->isAncestorOf($this);
    }

    /**
     * Get all ancestor term IDs
     *
     * @return int[]
     */
    public function getAncestorIds(): array
    {
        if (empty($this->path)) {
            return [];
        }

        $parts = explode('/', trim($this->path, '/'));
        array_pop($parts); // Remove self

        return array_map('intval', array_filter($parts));
    }

    /**
     * Get full hierarchical name (e.g., "Electronics > Computers > Laptops")
     */
    public function getHierarchicalName(string $separator = ' > '): string
    {
        if ($this->parent === null) {
            return $this->name;
        }

        return $this->parent->getHierarchicalName($separator) . $separator . $this->name;
    }

    /**
     * Get metadata value
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value
     */
    public function setMeta(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    /**
     * Convert to array with additional computed fields
     */
    public function toArray(bool $includeNulls = false): array
    {
        $data = parent::toArray($includeNulls);
        $data['hierarchical_name'] = $this->getHierarchicalName();
        $data['has_children'] = $this->hasChildren();
        $data['is_root'] = $this->isRoot();
        return $data;
    }

    /**
     * Convert to option format for select widgets
     */
    public function toOption(): array
    {
        $prefix = str_repeat('— ', $this->depth);
        return [
            'value' => $this->id,
            'label' => $prefix . $this->name,
            'depth' => $this->depth,
        ];
    }
}
