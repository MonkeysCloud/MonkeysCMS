<?php

declare(strict_types=1);

namespace App\Cms\Mosaic;

use MonkeysLegion\Entity\Attributes\Column;
use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Id;

/**
 * MosaicEntity — Stores the visual page layout for a content node.
 *
 * Each node can have one Mosaic layout, stored as a JSON structure of
 * sections → regions → blocks. The Mosaic editor on the frontend
 * reads/writes this structure via the admin API.
 */
#[Entity(table: 'node_mosaic')]
final class MosaicEntity
{
    #[Id]
    public ?int $id = null;

    #[Column(type: 'integer')]
    public int $node_id = 0;

    #[Column(type: 'string', length: 64)]
    public string $content_type = '';

    /**
     * The layout structure.
     *
     * Format:
     * {
     *   "sections": [
     *     {
     *       "id": "sec_abc",
     *       "layout": "two_col",
     *       "settings": { "gap": "1rem" },
     *       "regions": {
     *         "first": [
     *           { "id": "blk_1", "blockType": "text", "data": {...}, "settings": {} }
     *         ],
     *         "second": []
     *       }
     *     }
     *   ]
     * }
     */
    #[Column(type: 'json', default: '{"sections":[]}')]
    public array $sections = [];

    #[Column(type: 'integer', default: 1)]
    public int $revision = 1;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    /**
     * Hydrate from database row
     */
    public function hydrate(array $data): static
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : $this->id;
        $this->node_id = (int) ($data['node_id'] ?? $this->node_id);
        $this->content_type = $data['content_type'] ?? $this->content_type;
        $this->revision = (int) ($data['revision'] ?? $this->revision);

        $this->sections = isset($data['sections'])
            ? (is_string($data['sections']) ? json_decode($data['sections'], true) ?? [] : $data['sections'])
            : $this->sections;

        $this->created_at = isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : $this->created_at;
        $this->updated_at = isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : $this->updated_at;

        return $this;
    }

    /**
     * Serialize for API / JSON responses
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'node_id' => $this->node_id,
            'content_type' => $this->content_type,
            'sections' => $this->sections,
            'revision' => $this->revision,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
