<?php

declare(strict_types=1);

namespace App\Cms\Taxonomy;

use MonkeysLegion\Entity\Attributes\Column;
use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Id;

/**
 * TermEntity — A taxonomy term within a vocabulary.
 */
#[Entity(table: 'terms')]
class TermEntity
{
    #[Id]
    public ?int $id = null;

    #[Column(type: 'integer')]
    public int $vocabulary_id = 0;

    #[Column(type: 'integer', nullable: true)]
    public ?int $parent_id = null;

    #[Column(type: 'string', length: 255)]
    public string $name = '';

    #[Column(type: 'string', length: 300)]
    public string $slug = '' {
        set(string $value) {
            $this->slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-'));
        }
    }

    #[Column(type: 'text', nullable: true)]
    public ?string $description = null;

    #[Column(type: 'json', default: '{}')]
    public array $metadata = [];

    #[Column(type: 'integer', default: 0)]
    public int $weight = 0;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    /** @var TermEntity[] children (for hierarchical vocabularies) */
    public array $children = [];

    public function hydrate(array $data): static
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : $this->id;
        $this->vocabulary_id = (int) ($data['vocabulary_id'] ?? $this->vocabulary_id);
        $this->parent_id = isset($data['parent_id']) ? (int) $data['parent_id'] : $this->parent_id;
        $this->name = $data['name'] ?? $this->name;
        $this->slug = $data['slug'] ?? $this->slug;
        $this->description = $data['description'] ?? $this->description;
        $this->weight = (int) ($data['weight'] ?? $this->weight);

        $this->metadata = isset($data['metadata'])
            ? (is_string($data['metadata']) ? json_decode($data['metadata'], true) ?? [] : $data['metadata'])
            : $this->metadata;

        $this->created_at = isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : $this->created_at;
        $this->updated_at = isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : $this->updated_at;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'term',
            'attributes' => [
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description,
                'weight' => $this->weight,
            ],
            'relationships' => [
                'vocabulary' => ['type' => 'vocabulary', 'id' => (string) $this->vocabulary_id],
                'parent' => $this->parent_id ? ['type' => 'term', 'id' => (string) $this->parent_id] : null,
            ],
        ];
    }
}
