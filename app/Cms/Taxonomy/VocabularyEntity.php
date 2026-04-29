<?php

declare(strict_types=1);

namespace App\Cms\Taxonomy;

use MonkeysLegion\Entity\Attributes\Column;
use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Id;

/**
 * VocabularyEntity — A taxonomy vocabulary (e.g., "Categories", "Tags").
 */
#[Entity(table: 'vocabularies')]
class VocabularyEntity
{
    #[Id]
    public ?int $id = null;

    #[Column(type: 'string', length: 64, unique: true)]
    public string $machine_name = '';

    #[Column(type: 'string', length: 128)]
    public string $label = '';

    #[Column(type: 'text', nullable: true)]
    public ?string $description = null;

    #[Column(type: 'boolean', default: true)]
    public bool $hierarchical = false;

    #[Column(type: 'boolean', default: false)]
    public bool $multiple = true;

    #[Column(type: 'integer', default: 0)]
    public int $weight = 0;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    public function hydrate(array $data): static
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : $this->id;
        $this->machine_name = $data['machine_name'] ?? $this->machine_name;
        $this->label = $data['label'] ?? $this->label;
        $this->description = $data['description'] ?? $this->description;
        $this->hierarchical = (bool) ($data['hierarchical'] ?? $this->hierarchical);
        $this->multiple = (bool) ($data['multiple'] ?? $this->multiple);
        $this->weight = (int) ($data['weight'] ?? $this->weight);
        $this->created_at = isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : $this->created_at;
        $this->updated_at = isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : $this->updated_at;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'machine_name' => $this->machine_name,
            'label' => $this->label,
            'description' => $this->description,
            'hierarchical' => $this->hierarchical,
            'multiple' => $this->multiple,
        ];
    }
}
