<?php

declare(strict_types=1);

namespace App\Cms\Menu;

use MonkeysLegion\Entity\Attributes\Column;
use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Id;

/**
 * MenuItemEntity — A single item in a menu, supporting nested tree structure.
 */
#[Entity(table: 'menu_items')]
class MenuItemEntity
{
    #[Id]
    public ?int $id = null;

    #[Column(type: 'integer')]
    public int $menu_id = 0;

    #[Column(type: 'integer', nullable: true)]
    public ?int $parent_id = null;

    #[Column(type: 'string', length: 255)]
    public string $title = '';

    #[Column(type: 'string', length: 500, nullable: true)]
    public ?string $url = null;

    #[Column(type: 'string', length: 255, nullable: true)]
    public ?string $route_name = null;

    #[Column(type: 'json', default: '{}')]
    public array $route_params = [];

    #[Column(type: 'string', length: 50, nullable: true)]
    public ?string $target = null;

    #[Column(type: 'string', length: 50, nullable: true)]
    public ?string $icon = null;

    #[Column(type: 'json', default: '{}')]
    public array $attributes = [];

    #[Column(type: 'integer', default: 0)]
    public int $weight = 0;

    #[Column(type: 'boolean', default: true)]
    public bool $enabled = true;

    /** @var MenuItemEntity[] child items */
    public array $children = [];

    /** Nested set: depth for rendering */
    public int $depth = 0;

    public function hydrate(array $data): static
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : $this->id;
        $this->menu_id = (int) ($data['menu_id'] ?? $this->menu_id);
        $this->parent_id = isset($data['parent_id']) ? (int) $data['parent_id'] : $this->parent_id;
        $this->title = $data['title'] ?? $this->title;
        $this->url = $data['url'] ?? $this->url;
        $this->route_name = $data['route_name'] ?? $this->route_name;
        $this->target = $data['target'] ?? $this->target;
        $this->icon = $data['icon'] ?? $this->icon;
        $this->weight = (int) ($data['weight'] ?? $this->weight);
        $this->enabled = (bool) ($data['enabled'] ?? $this->enabled);

        foreach (['route_params', 'attributes'] as $jsonField) {
            if (isset($data[$jsonField])) {
                $this->$jsonField = is_string($data[$jsonField])
                    ? (json_decode($data[$jsonField], true) ?? [])
                    : $data[$jsonField];
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'target' => $this->target,
            'icon' => $this->icon,
            'weight' => $this->weight,
            'enabled' => $this->enabled,
            'children' => array_map(fn(MenuItemEntity $c) => $c->toArray(), $this->children),
        ];
    }
}
