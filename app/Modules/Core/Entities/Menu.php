<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * Menu Entity - Navigation menus
 */
#[ContentType(
    tableName: 'menus',
    label: 'Menu',
    description: 'Navigation menus for the site',
    icon: '📋',
    revisionable: false,
    publishable: false
)]
class Menu extends BaseEntity
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
        unique: true
    )]
    public string $machine_name = '';

    #[Field(
        type: 'text',
        label: 'Description',
        required: false
    )]
    public string $description = '';

    #[Field(
        type: 'string',
        label: 'Location',
        required: false,
        length: 100,
        widget: 'select',
        options: [
            'header' => 'Header',
            'footer' => 'Footer',
            'sidebar' => 'Sidebar',
            'mobile' => 'Mobile',
            'custom' => 'Custom'
        ]
    )]
    public string $location = 'header';

    #[Field(type: 'datetime', label: 'Created At')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime', label: 'Updated At')]
    public ?\DateTimeImmutable $updated_at = null;

    /** @var MenuItem[] */
    public array $items = [];

    public function prePersist(): void
    {
        parent::prePersist();
        if (empty($this->machine_name)) {
            $this->machine_name = strtolower(preg_replace('/[^a-z0-9]+/', '_', $this->name));
        }
    }

    /**
     * Get menu items as tree
     */
    public function getItemTree(): array
    {
        $tree = [];
        $itemMap = [];

        foreach ($this->items as $item) {
            $itemMap[$item->id] = $item;
            $item->children = [];
        }

        foreach ($this->items as $item) {
            if ($item->parent_id === null) {
                $tree[] = $item;
            } elseif (isset($itemMap[$item->parent_id])) {
                $itemMap[$item->parent_id]->children[] = $item;
            }
        }

        return $tree;
    }
    public static function getDefaults(): array
    {
        return [
            [
                'name' => 'Main Navigation',
                'machine_name' => 'main',
                'description' => 'Primary site navigation',
                'location' => 'header',
                'settings' => ['depth' => 2],
            ],
            [
                'name' => 'Footer Menu',
                'machine_name' => 'footer',
                'description' => 'Footer links',
                'location' => 'footer',
                'settings' => ['depth' => 1],
            ],
            [
                'name' => 'Administration',
                'machine_name' => 'admin',
                'description' => 'Admin dashboard menu',
                'location' => 'admin_sidebar',
                'settings' => ['depth' => 3],
            ],
        ];
    }
}
