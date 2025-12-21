<?php

declare(strict_types=1);

namespace App\Cms\ContentTypes;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * ContentTypeEntity - Database-defined content types
 * 
 * Content types can be defined both in code (as Entity classes) and
 * in the database (via this entity). Database-defined types can have
 * custom fields added dynamically via the admin UI.
 */
#[ContentType(
    tableName: 'content_types',
    label: 'Content Type',
    description: 'Database-defined content type definitions'
)]
class ContentTypeEntity extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(type: 'string', label: 'Type ID', required: true, length: 100, unique: true)]
    public string $type_id = '';

    #[Field(type: 'string', label: 'Label', required: true, length: 255)]
    public string $label = '';

    #[Field(type: 'string', label: 'Label Plural', required: false, length: 255)]
    public ?string $label_plural = null;

    #[Field(type: 'text', label: 'Description', required: false)]
    public ?string $description = null;

    #[Field(type: 'string', label: 'Icon', required: false, length: 50)]
    public string $icon = '📄';

    #[Field(type: 'boolean', label: 'Is System', default: false)]
    public bool $is_system = false;

    #[Field(type: 'boolean', label: 'Enabled', default: true)]
    public bool $enabled = true;

    #[Field(type: 'boolean', label: 'Publishable', default: true)]
    public bool $publishable = true;

    #[Field(type: 'boolean', label: 'Revisionable', default: false)]
    public bool $revisionable = false;

    #[Field(type: 'boolean', label: 'Translatable', default: false)]
    public bool $translatable = false;

    #[Field(type: 'boolean', label: 'Has Author', default: true)]
    public bool $has_author = true;

    #[Field(type: 'boolean', label: 'Has Taxonomy', default: true)]
    public bool $has_taxonomy = true;

    #[Field(type: 'boolean', label: 'Has Media', default: true)]
    public bool $has_media = true;

    #[Field(type: 'string', label: 'Title Field', required: false, length: 100)]
    public string $title_field = 'title';

    #[Field(type: 'string', label: 'Slug Field', required: false, length: 100)]
    public ?string $slug_field = 'slug';

    #[Field(type: 'string', label: 'URL Pattern', required: false, length: 255)]
    public ?string $url_pattern = null;

    #[Field(type: 'json', label: 'Default Values', default: [])]
    public array $default_values = [];

    #[Field(type: 'json', label: 'Settings', default: [])]
    public array $settings = [];

    #[Field(type: 'json', label: 'Allowed Vocabularies', default: [])]
    public array $allowed_vocabularies = [];

    #[Field(type: 'int', label: 'Weight', default: 0)]
    public int $weight = 0;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    /**
     * Fields attached to this content type
     * @var array<\App\Cms\Fields\FieldDefinition>
     */
    public array $fields = [];

    public function prePersist(): void
    {
        parent::prePersist();
        
        if (empty($this->type_id)) {
            $this->type_id = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $this->label));
        }
        
        if (empty($this->label_plural)) {
            $this->label_plural = $this->label . 's';
        }
    }

    /**
     * Get the table name for content of this type
     */
    public function getTableName(): string
    {
        return 'content_' . $this->type_id;
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
     * Get URL for a content item of this type
     */
    public function getUrl(array $content): string
    {
        if (!$this->url_pattern) {
            return '/' . $this->type_id . '/' . ($content[$this->slug_field] ?? $content['id']);
        }
        
        $url = $this->url_pattern;
        foreach ($content as $key => $value) {
            if (is_scalar($value)) {
                $url = str_replace('{' . $key . '}', (string) $value, $url);
            }
        }
        
        return $url;
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
            'type_id' => $this->type_id,
            'label' => $this->label,
            'label_plural' => $this->label_plural,
            'description' => $this->description,
            'icon' => $this->icon,
            'is_system' => $this->is_system,
            'enabled' => $this->enabled,
            'publishable' => $this->publishable,
            'revisionable' => $this->revisionable,
            'translatable' => $this->translatable,
            'has_author' => $this->has_author,
            'has_taxonomy' => $this->has_taxonomy,
            'has_media' => $this->has_media,
            'title_field' => $this->title_field,
            'slug_field' => $this->slug_field,
            'url_pattern' => $this->url_pattern,
            'default_values' => $this->default_values,
            'settings' => $this->settings,
            'allowed_vocabularies' => $this->allowed_vocabularies,
            'fields' => array_map(fn($f) => $f->toArray(), $this->fields),
        ];
    }

    /**
     * Generate SQL for creating the content table
     */
    public function generateTableSql(): string
    {
        $tableName = $this->getTableName();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (\n";
        $sql .= "    id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $sql .= "    uuid VARCHAR(36) NOT NULL UNIQUE,\n";
        
        // Title field
        if ($this->title_field) {
            $sql .= "    {$this->title_field} VARCHAR(255) NOT NULL,\n";
        }
        
        // Slug field
        if ($this->slug_field) {
            $sql .= "    {$this->slug_field} VARCHAR(255) NOT NULL UNIQUE,\n";
        }
        
        // Custom fields
        foreach ($this->fields as $field) {
            $sql .= "    " . $field->getSqlColumnDefinition() . ",\n";
        }
        
        // Standard fields
        if ($this->publishable) {
            $sql .= "    status VARCHAR(20) DEFAULT 'draft',\n";
            $sql .= "    published_at DATETIME,\n";
        }
        
        if ($this->has_author) {
            $sql .= "    author_id INT,\n";
        }
        
        if ($this->revisionable) {
            $sql .= "    revision_id INT,\n";
        }
        
        if ($this->translatable) {
            $sql .= "    language VARCHAR(10) DEFAULT 'en',\n";
            $sql .= "    translation_of INT,\n";
        }
        
        $sql .= "    created_at DATETIME NOT NULL,\n";
        $sql .= "    updated_at DATETIME NOT NULL,\n";
        
        // Indexes
        $sql .= "    INDEX idx_status (status),\n";
        $sql .= "    INDEX idx_created (created_at),\n";
        
        if ($this->has_author) {
            $sql .= "    INDEX idx_author (author_id),\n";
        }
        
        if ($this->translatable) {
            $sql .= "    INDEX idx_language (language),\n";
        }
        
        // Remove trailing comma
        $sql = rtrim($sql, ",\n") . "\n";
        
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        return $sql;
    }
}
