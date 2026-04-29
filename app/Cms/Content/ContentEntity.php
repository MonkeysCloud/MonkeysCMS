<?php

declare(strict_types=1);

namespace App\Cms\Content;

use MonkeysLegion\Entity\Attributes\Column;
use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Id;

/**
 * ContentEntity — Universal content node.
 *
 * Every piece of content in MonkeysCMS is stored as a "node" in the
 * `nodes` table. The node stores core metadata; dynamic field values
 * are stored in `node_fields` (EAV) and Mosaic layout data in `node_mosaic`.
 */
#[Entity(table: 'nodes')]
class ContentEntity
{
    #[Id]
    public ?int $id = null;

    #[Column(type: 'string', length: 64)]
    public string $content_type = '';

    #[Column(type: 'string', length: 255)]
    public string $title = '';

    #[Column(type: 'string', length: 300)]
    public string $slug = '' {
        set(string $value) {
            $this->slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-'));
        }
    }

    #[Column(type: 'string', length: 20, default: 'draft')]
    public string $status = 'draft';

    #[Column(type: 'integer', nullable: true)]
    public ?int $author_id = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $body = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $summary = null;

    #[Column(type: 'string', length: 255, nullable: true)]
    public ?string $meta_title = null;

    #[Column(type: 'string', length: 500, nullable: true)]
    public ?string $meta_description = null;

    #[Column(type: 'string', length: 255, nullable: true)]
    public ?string $meta_image = null;

    #[Column(type: 'integer', nullable: true)]
    public ?int $featured_image_id = null;

    #[Column(type: 'json', default: '{}')]
    public array $fields = [];

    #[Column(type: 'boolean', default: true)]
    public bool $mosaic_mode = false;

    #[Column(type: 'integer', default: 1)]
    public int $revision = 1;

    #[Column(type: 'string', length: 10, default: 'en')]
    public string $language = 'en';

    #[Column(type: 'integer', default: 0)]
    public int $weight = 0;

    #[Column(type: 'datetime', nullable: true)]
    public ?\DateTimeImmutable $published_at = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    #[Column(type: 'datetime', nullable: true)]
    public ?\DateTimeImmutable $deleted_at = null;

    /** Computed: is this content published? */
    public bool $isPublished {
        get => $this->status === 'published' && (
            $this->published_at === null || $this->published_at <= new \DateTimeImmutable()
        );
    }

    /** Computed: is this content scheduled for future publication? */
    public bool $isScheduled {
        get => $this->status === 'published' && $this->published_at !== null && $this->published_at > new \DateTimeImmutable();
    }

    /** Computed: is this soft-deleted? */
    public bool $isTrashed {
        get => $this->deleted_at !== null;
    }

    public function hydrate(array $data): static
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : $this->id;
        $this->content_type = $data['content_type'] ?? $this->content_type;
        $this->title = $data['title'] ?? $this->title;
        $this->slug = $data['slug'] ?? $this->slug;
        $this->status = $data['status'] ?? $this->status;
        $this->author_id = isset($data['author_id']) ? (int) $data['author_id'] : $this->author_id;
        $this->body = $data['body'] ?? $this->body;
        $this->summary = $data['summary'] ?? $this->summary;
        $this->meta_title = $data['meta_title'] ?? $this->meta_title;
        $this->meta_description = $data['meta_description'] ?? $this->meta_description;
        $this->meta_image = $data['meta_image'] ?? $this->meta_image;
        $this->featured_image_id = isset($data['featured_image_id']) ? (int) $data['featured_image_id'] : $this->featured_image_id;
        $this->mosaic_mode = (bool) ($data['mosaic_mode'] ?? $this->mosaic_mode);
        $this->revision = (int) ($data['revision'] ?? $this->revision);
        $this->language = $data['language'] ?? $this->language;
        $this->weight = (int) ($data['weight'] ?? $this->weight);

        $this->fields = isset($data['fields'])
            ? (is_string($data['fields']) ? json_decode($data['fields'], true) ?? [] : $data['fields'])
            : $this->fields;

        foreach (['published_at', 'created_at', 'updated_at', 'deleted_at'] as $ts) {
            if (isset($data[$ts]) && $data[$ts] !== null) {
                $this->$ts = new \DateTimeImmutable($data[$ts]);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->content_type,
            'attributes' => [
                'title' => $this->title,
                'slug' => $this->slug,
                'status' => $this->status,
                'body' => $this->body,
                'summary' => $this->summary,
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'mosaic_mode' => $this->mosaic_mode,
                'language' => $this->language,
                'published_at' => $this->published_at?->format('c'),
                'created_at' => $this->created_at?->format('c'),
                'updated_at' => $this->updated_at?->format('c'),
            ],
            'relationships' => [
                'author' => $this->author_id ? ['type' => 'user', 'id' => (string) $this->author_id] : null,
                'featured_image' => $this->featured_image_id ? ['type' => 'media', 'id' => (string) $this->featured_image_id] : null,
            ],
            'meta' => [
                'revision' => $this->revision,
                'fields' => $this->fields,
            ],
        ];
    }
}
