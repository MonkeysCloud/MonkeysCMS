<?php

declare(strict_types=1);

namespace App\Cms\Content;

use MonkeysLegion\Entity\Attributes\Column;
use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Id;

/**
 * ContentTypeEntity — Defines a content type (Article, Page, Event, etc.)
 *
 * Content types are stored in the `content_types` table and define the
 * schema for user-created content. Each type has configurable fields,
 * publishing options, and Mosaic page builder support.
 */
#[Entity(table: 'content_types')]
final class ContentTypeEntity
{
    #[Id]
    public ?int $id = null;

    #[Column(type: 'string', length: 64, unique: true)]
    public string $type_id = '';

    #[Column(type: 'string', length: 128)]
    public string $label = '';

    #[Column(type: 'string', length: 128)]
    public string $label_plural = '';

    #[Column(type: 'text', nullable: true)]
    public ?string $description = null;

    #[Column(type: 'string', length: 10, default: '📄')]
    public string $icon = '📄';

    #[Column(type: 'boolean', default: false)]
    public bool $is_system = false;

    #[Column(type: 'boolean', default: true)]
    public bool $enabled = true;

    #[Column(type: 'boolean', default: true)]
    public bool $publishable = true;

    #[Column(type: 'boolean', default: false)]
    public bool $revisionable = false;

    #[Column(type: 'boolean', default: false)]
    public bool $translatable = false;

    #[Column(type: 'boolean', default: true)]
    public bool $has_author = true;

    #[Column(type: 'boolean', default: true)]
    public bool $has_taxonomy = true;

    #[Column(type: 'boolean', default: true)]
    public bool $has_media = true;

    /** Whether this type uses the Mosaic page builder */
    #[Column(type: 'boolean', default: false)]
    public bool $mosaic_enabled = false;

    /** Whether new content of this type defaults to Mosaic editing */
    #[Column(type: 'boolean', default: false)]
    public bool $mosaic_default = false;

    #[Column(type: 'string', length: 64, default: 'title')]
    public string $title_field = 'title';

    #[Column(type: 'string', length: 64, default: 'slug')]
    public string $slug_field = 'slug';

    #[Column(type: 'string', length: 255, nullable: true)]
    public ?string $url_pattern = null;

    #[Column(type: 'json', default: '[]')]
    public array $default_values = [];

    #[Column(type: 'json', default: '{}')]
    public array $settings = [];

    #[Column(type: 'json', default: '[]')]
    public array $allowed_vocabularies = [];

    #[Column(type: 'integer', default: 0)]
    public int $weight = 0;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    /** @var array<FieldDefinition> Loaded via ContentTypeManager */
    public array $fields = [];

    /**
     * Get the dynamic content table name for this type
     */
    public function getTableName(): string
    {
        return 'content_' . $this->type_id;
    }

    /**
     * Hydrate from database row
     */
    public function hydrate(array $data): static
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : $this->id;
        $this->type_id = $data['type_id'] ?? $this->type_id;
        $this->label = $data['label'] ?? $this->label;
        $this->label_plural = $data['label_plural'] ?? $this->label_plural;
        $this->description = $data['description'] ?? $this->description;
        $this->icon = $data['icon'] ?? $this->icon;
        $this->is_system = (bool) ($data['is_system'] ?? $this->is_system);
        $this->enabled = (bool) ($data['enabled'] ?? $this->enabled);
        $this->publishable = (bool) ($data['publishable'] ?? $this->publishable);
        $this->revisionable = (bool) ($data['revisionable'] ?? $this->revisionable);
        $this->translatable = (bool) ($data['translatable'] ?? $this->translatable);
        $this->has_author = (bool) ($data['has_author'] ?? $this->has_author);
        $this->has_taxonomy = (bool) ($data['has_taxonomy'] ?? $this->has_taxonomy);
        $this->has_media = (bool) ($data['has_media'] ?? $this->has_media);
        $this->mosaic_enabled = (bool) ($data['mosaic_enabled'] ?? $this->mosaic_enabled);
        $this->mosaic_default = (bool) ($data['mosaic_default'] ?? $this->mosaic_default);
        $this->title_field = $data['title_field'] ?? $this->title_field;
        $this->slug_field = $data['slug_field'] ?? $this->slug_field;
        $this->url_pattern = $data['url_pattern'] ?? $this->url_pattern;
        $this->weight = (int) ($data['weight'] ?? $this->weight);

        $this->default_values = isset($data['default_values'])
            ? (is_string($data['default_values']) ? json_decode($data['default_values'], true) ?? [] : $data['default_values'])
            : $this->default_values;

        $this->settings = isset($data['settings'])
            ? (is_string($data['settings']) ? json_decode($data['settings'], true) ?? [] : $data['settings'])
            : $this->settings;

        $this->allowed_vocabularies = isset($data['allowed_vocabularies'])
            ? (is_string($data['allowed_vocabularies']) ? json_decode($data['allowed_vocabularies'], true) ?? [] : $data['allowed_vocabularies'])
            : $this->allowed_vocabularies;

        $this->created_at = isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : $this->created_at;
        $this->updated_at = isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : $this->updated_at;

        return $this;
    }

    /**
     * Serialize for API responses
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type_id' => $this->type_id,
            'label' => $this->label,
            'label_plural' => $this->label_plural,
            'description' => $this->description,
            'icon' => $this->icon,
            'publishable' => $this->publishable,
            'revisionable' => $this->revisionable,
            'translatable' => $this->translatable,
            'has_author' => $this->has_author,
            'has_taxonomy' => $this->has_taxonomy,
            'has_media' => $this->has_media,
            'mosaic_enabled' => $this->mosaic_enabled,
            'mosaic_default' => $this->mosaic_default,
            'url_pattern' => $this->url_pattern,
            'weight' => $this->weight,
        ];
    }
}
