<?php

declare(strict_types=1);

namespace App\Cms\Blocks;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * BlockTypeEntity - Database-defined block types
 *
 * Stores block type definitions that are created via the admin UI.
 * These can have custom fields added dynamically.
 */
#[ContentType(
    tableName: 'block_types',
    label: 'Block Type',
    description: 'Database-defined block type definitions'
)]
class BlockTypeEntity extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(type: 'string', label: 'Type ID', required: true, length: 100, unique: true)]
    public string $type_id = '';

    #[Field(type: 'string', label: 'Label', required: true, length: 255)]
    public string $label = '';

    #[Field(type: 'text', label: 'Description', required: false)]
    public ?string $description = null;

    #[Field(type: 'string', label: 'Icon', required: false, length: 50)]
    public string $icon = '🧱';

    #[Field(type: 'string', label: 'Category', required: false, length: 100)]
    public string $category = 'Custom';

    #[Field(type: 'string', label: 'Template', required: false, length: 255)]
    public ?string $template = null;

    #[Field(type: 'boolean', label: 'Is System', default: false)]
    public bool $is_system = false;

    #[Field(type: 'boolean', label: 'Enabled', default: true)]
    public bool $enabled = true;

    #[Field(type: 'json', label: 'Default Settings', default: [])]
    public array $default_settings = [];

    #[Field(type: 'json', label: 'Regions', default: [])]
    public array $allowed_regions = [];

    #[Field(type: 'int', label: 'Cache TTL', default: 3600)]
    public int $cache_ttl = 3600;

    #[Field(type: 'json', label: 'CSS Assets', default: [])]
    public array $css_assets = [];

    #[Field(type: 'json', label: 'JS Assets', default: [])]
    public array $js_assets = [];

    #[Field(type: 'int', label: 'Weight', default: 0)]
    public int $weight = 0;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    /**
     * Fields attached to this block type
     * @var array<\App\Cms\Fields\FieldDefinition>
     */
    public array $fields = [];

    public function prePersist(): void
    {
        parent::prePersist();

        if (empty($this->type_id)) {
            $this->type_id = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $this->label));
        }
    }

    /**
     * Check if this block type can be placed in a region
     */
    public function canBePlacedInRegion(string $region): bool
    {
        if (empty($this->allowed_regions)) {
            return true; // All regions
        }

        return in_array($region, $this->allowed_regions, true);
    }

    /**
     * Get field definition by machine name
     */
    public function getField(string $machineName): ?\App\Cms\Fields\FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->machine_name === $machineName) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type_id' => $this->type_id,
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'category' => $this->category,
            'template' => $this->template,
            'is_system' => $this->is_system,
            'enabled' => $this->enabled,
            'default_settings' => $this->default_settings,
            'allowed_regions' => $this->allowed_regions,
            'cache_ttl' => $this->cache_ttl,
            'css_assets' => $this->css_assets,
            'js_assets' => $this->js_assets,
            'fields' => array_map(fn($f) => $f->toArray(), $this->fields),
        ];
    }
}
