<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * Vocabulary Entity - Defines taxonomy vocabularies (like Drupal)
 *
 * A vocabulary is a container for terms. Examples:
 * - Categories (hierarchical)
 * - Tags (flat)
 * - Genres
 * - Product Types
 */
#[ContentType(
    tableName: 'vocabularies',
    label: 'Vocabulary',
    description: 'Taxonomy vocabularies for organizing content',
    icon: '📂',
    revisionable: false,
    publishable: false
)]
class Vocabulary extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

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
        label: 'Machine Name',
        required: true,
        length: 100,
        unique: true,
        indexed: true
    )]
    public string $machine_name = '';

    #[Field(
        type: 'text',
        label: 'Description',
        required: false,
        widget: 'textarea'
    )]
    public string $description = '';

    #[Field(
        type: 'boolean',
        label: 'Hierarchical',
        default: false
    )]
    public bool $hierarchical = false;

    #[Field(
        type: 'boolean',
        label: 'Allow Multiple',
        default: true
    )]
    public bool $multiple = true;

    #[Field(
        type: 'boolean',
        label: 'Required',
        default: false
    )]
    public bool $required = false;

    #[Field(
        type: 'int',
        label: 'Weight',
        default: 0,
        indexed: true
    )]
    public int $weight = 0;

    #[Field(
        type: 'json',
        label: 'Allowed Entity Types',
        default: []
    )]
    public array $entity_types = [];

    #[Field(
        type: 'json',
        label: 'Settings',
        default: []
    )]
    public array $settings = [];

    #[Field(type: 'datetime', label: 'Created At')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime', label: 'Updated At')]
    public ?\DateTimeImmutable $updated_at = null;

    /**
     * Terms in this vocabulary (loaded separately)
     * @var Term[]
     */
    public array $terms = [];

    // ─────────────────────────────────────────────────────────────
    // Business Logic
    // ─────────────────────────────────────────────────────────────

    public function prePersist(): void
    {
        parent::prePersist();

        if (empty($this->machine_name)) {
            $this->machine_name = $this->generateMachineName($this->name);
        }
    }

    /**
     * Generate machine name from human name
     */
    private function generateMachineName(string $name): string
    {
        $machine = strtolower($name);
        $machine = preg_replace('/[^a-z0-9]+/', '_', $machine);
        $machine = trim($machine, '_');
        return $machine;
    }

    /**
     * Check if vocabulary allows an entity type
     */
    public function allowsEntityType(string $entityType): bool
    {
        if (empty($this->entity_types)) {
            return true; // Allow all if not restricted
        }
        return in_array($entityType, $this->entity_types, true);
    }

    /**
     * Add allowed entity type
     */
    public function addEntityType(string $entityType): void
    {
        if (!in_array($entityType, $this->entity_types, true)) {
            $this->entity_types[] = $entityType;
        }
    }

    /**
     * Remove allowed entity type
     */
    public function removeEntityType(string $entityType): void
    {
        $this->entity_types = array_filter(
            $this->entity_types,
            fn($t) => $t !== $entityType
        );
    }

    /**
     * Get setting value
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set setting value
     */
    public function setSetting(string $key, mixed $value): void
    {
        $this->settings[$key] = $value;
    }

    /**
     * Get root terms (terms without parent)
     *
     * @return Term[]
     */
    public function getRootTerms(): array
    {
        return array_filter(
            $this->terms,
            fn(Term $term) => $term->parent_id === null
        );
    }

    /**
     * Build term tree (hierarchical structure)
     */
    public function getTermTree(): array
    {
        if (!$this->hierarchical) {
            return $this->terms;
        }

        $tree = [];
        $termMap = [];

        // Index terms by ID
        foreach ($this->terms as $term) {
            $termMap[$term->id] = $term;
            $term->children = [];
        }

        // Build tree
        foreach ($this->terms as $term) {
            if ($term->parent_id === null) {
                $tree[] = $term;
            } elseif (isset($termMap[$term->parent_id])) {
                $termMap[$term->parent_id]->children[] = $term;
            }
        }

        return $tree;
    }

    /**
     * Default vocabularies
     */
    public static function getDefaults(): array
    {
        return [
            [
                'name' => 'Categories',
                'machine_name' => 'categories',
                'description' => 'General content categories',
                'hierarchical' => true,
                'multiple' => true,
                'required' => false,
            ],
            [
                'name' => 'Tags',
                'machine_name' => 'tags',
                'description' => 'Free-form tags for content',
                'hierarchical' => false,
                'multiple' => true,
                'required' => false,
            ],
        ];
    }
}
