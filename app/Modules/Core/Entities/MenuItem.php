<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * MenuItem Entity - Individual menu links
 */
#[ContentType(
    tableName: 'menu_items',
    label: 'Menu Item',
    description: 'Menu links and navigation items',
    icon: '🔗',
    revisionable: false,
    publishable: true
)]
class MenuItem extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(type: 'int', label: 'Menu ID', required: true, indexed: true)]
    public int $menu_id = 0;

    #[Field(type: 'int', label: 'Parent ID', required: false, indexed: true)]
    public ?int $parent_id = null;

    #[Field(type: 'string', label: 'Title', required: true, length: 255)]
    public string $title = '';

    #[Field(type: 'string', label: 'URL', required: false, length: 500)]
    public ?string $url = null;

    #[Field(
        type: 'string',
        label: 'Link Type',
        required: true,
        length: 50,
        default: 'custom',
        widget: 'select',
        options: [
            'custom' => 'Custom URL',
            'internal' => 'Internal Page',
            'entity' => 'Content Link',
            'external' => 'External URL',
            'anchor' => 'Anchor Link',
            'nolink' => 'No Link (Parent Only)'
        ]
    )]
    public string $link_type = 'custom';

    #[Field(type: 'string', label: 'Entity Type', required: false, length: 100)]
    public ?string $entity_type = null;

    #[Field(type: 'int', label: 'Entity ID', required: false)]
    public ?int $entity_id = null;

    #[Field(type: 'string', label: 'Icon', required: false, length: 100)]
    public ?string $icon = null;

    #[Field(type: 'string', label: 'CSS Classes', required: false, length: 255)]
    public ?string $css_class = null;

    #[Field(
        type: 'string',
        label: 'Target',
        required: false,
        length: 20,
        widget: 'select',
        options: ['_self' => 'Same Window', '_blank' => 'New Window']
    )]
    public string $target = '_self';

    #[Field(type: 'int', label: 'Weight', default: 0, indexed: true)]
    public int $weight = 0;

    #[Field(type: 'int', label: 'Depth', default: 0)]
    public int $depth = 0;

    #[Field(type: 'boolean', label: 'Expanded', default: false)]
    public bool $expanded = false;

    #[Field(type: 'boolean', label: 'Published', default: true)]
    public bool $is_published = true;

    #[Field(type: 'json', label: 'Attributes', default: [])]
    public array $attributes = [];

    #[Field(type: 'json', label: 'Visibility Rules', default: [])]
    public array $visibility = [];

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    /** @var MenuItem[] */
    public array $children = [];

    /**
     * Get the resolved URL
     */
    public function getResolvedUrl(): ?string
    {
        return match ($this->link_type) {
            'nolink' => null,
            'anchor' => '#' . ltrim($this->url ?? '', '#'),
            'external' => $this->url,
            'custom', 'internal' => $this->url,
            'entity' => $this->entity_type && $this->entity_id
                ? "/{$this->entity_type}/{$this->entity_id}"
                : null,
            default => $this->url,
        };
    }

    /**
     * Check if menu item is visible based on rules
     */
    public function isVisible(?User $user = null): bool
    {
        if (!$this->is_published) {
            return false;
        }

        if (empty($this->visibility)) {
            return true;
        }

        // Check role-based visibility
        if (isset($this->visibility['roles'])) {
            if ($user === null) {
                return in_array('anonymous', $this->visibility['roles'], true);
            }
            foreach ($this->visibility['roles'] as $roleSlug) {
                if ($user->hasRole($roleSlug)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['resolved_url'] = $this->getResolvedUrl();
        $data['has_children'] = !empty($this->children);
        return $data;
    }
}
