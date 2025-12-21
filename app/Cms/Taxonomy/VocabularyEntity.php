<?php

declare(strict_types=1);

namespace App\Cms\Taxonomy;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * VocabularyEntity - Database-defined taxonomy vocabularies
 *
 * Vocabularies can be defined both in code and in the database.
 * Database-defined vocabularies can have custom fields added to their terms.
 */
#[ContentType(
    tableName: 'vocabularies',
    label: 'Vocabulary',
    description: 'Taxonomy vocabulary definitions'
)]
class VocabularyEntity extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(type: 'string', label: 'Vocabulary ID', required: true, length: 100, unique: true)]
    public string $vocabulary_id = '';

    #[Field(type: 'string', label: 'Name', required: true, length: 255)]
    public string $name = '';

    #[Field(type: 'text', label: 'Description', required: false)]
    public ?string $description = null;

    #[Field(type: 'string', label: 'Icon', required: false, length: 50)]
    public string $icon = '🏷️';

    #[Field(type: 'boolean', label: 'Is System', default: false)]
    public bool $is_system = false;

    #[Field(type: 'boolean', label: 'Enabled', default: true)]
    public bool $enabled = true;

    #[Field(type: 'boolean', label: 'Hierarchical', default: true)]
    public bool $hierarchical = true;

    #[Field(type: 'boolean', label: 'Multiple Selection', default: true)]
    public bool $multiple = true;

    #[Field(type: 'boolean', label: 'Required', default: false)]
    public bool $required = false;

    #[Field(type: 'int', label: 'Max Depth', default: 0)]
    public int $max_depth = 0; // 0 = unlimited

    #[Field(type: 'json', label: 'Settings', default: [])]
    public array $settings = [];

    #[Field(type: 'json', label: 'Allowed Content Types', default: [])]
    public array $allowed_content_types = [];

    #[Field(type: 'int', label: 'Weight', default: 0)]
    public int $weight = 0;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    /**
     * Custom fields for terms in this vocabulary
     * @var array<\App\Cms\Fields\FieldDefinition>
     */
    public array $fields = [];

    public function prePersist(): void
    {
        parent::prePersist();

        if (empty($this->vocabulary_id)) {
            $this->vocabulary_id = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $this->name));
        }
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
     * Check if this vocabulary allows a content type
     */
    public function allowsContentType(string $contentType): bool
    {
        if (empty($this->allowed_content_types)) {
            return true; // All content types
        }
        return in_array($contentType, $this->allowed_content_types, true);
    }

    /**
     * Get a specific setting
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'vocabulary_id' => $this->vocabulary_id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'is_system' => $this->is_system,
            'enabled' => $this->enabled,
            'hierarchical' => $this->hierarchical,
            'multiple' => $this->multiple,
            'required' => $this->required,
            'max_depth' => $this->max_depth,
            'settings' => $this->settings,
            'allowed_content_types' => $this->allowed_content_types,
            'fields' => array_map(fn($f) => $f->toArray(), $this->fields),
        ];
    }
}
