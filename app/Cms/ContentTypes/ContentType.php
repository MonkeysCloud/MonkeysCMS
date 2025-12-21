<?php

declare(strict_types=1);

namespace App\Cms\ContentTypes;

use App\Cms\Entity\BaseEntity;

/**
 * ContentType - Defines a content type for nodes
 * 
 * Content types define the structure and behavior of nodes:
 * - Fields attached to the type
 * - Publishing options
 * - Display settings
 * - URL patterns
 */
class ContentType extends BaseEntity
{
    protected ?int $id = null;
    protected string $name = '';
    protected string $machine_name = '';
    protected ?string $description = null;

    // Display settings
    protected string $title_label = 'Title';
    protected bool $show_title = true;
    protected bool $show_author = true;
    protected bool $show_date = true;

    // Publishing options
    protected string $default_status = 'draft';
    protected bool $enable_revisions = true;
    protected bool $enable_comments = false;

    // URL pattern
    protected string $url_pattern = '/[type]/[slug]';

    // Workflow
    protected ?int $workflow_id = null;

    // Metadata
    protected string $icon = 'file-text';
    protected bool $is_system = false;
    protected int $weight = 0;

    // Timestamps
    protected ?\DateTimeImmutable $created_at = null;
    protected ?\DateTimeImmutable $updated_at = null;

    // Loaded fields
    protected array $fields = [];

    public static function getTableName(): string
    {
        return 'content_types';
    }

    public static function getFillable(): array
    {
        return [
            'name',
            'machine_name',
            'description',
            'title_label',
            'show_title',
            'show_author',
            'show_date',
            'default_status',
            'enable_revisions',
            'enable_comments',
            'url_pattern',
            'workflow_id',
            'icon',
            'weight',
        ];
    }

    public static function getCasts(): array
    {
        return [
            'id' => 'int',
            'show_title' => 'bool',
            'show_author' => 'bool',
            'show_date' => 'bool',
            'enable_revisions' => 'bool',
            'enable_comments' => 'bool',
            'is_system' => 'bool',
            'weight' => 'int',
            'workflow_id' => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'fields' => 'array',
        ];
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getName(): string
    {
        return $this->name;
    }

    public function getMachineName(): string
    {
        return $this->machine_name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getTitleLabel(): string
    {
        return $this->title_label;
    }

    public function shouldShowTitle(): bool
    {
        return $this->show_title;
    }

    public function shouldShowAuthor(): bool
    {
        return $this->show_author;
    }

    public function shouldShowDate(): bool
    {
        return $this->show_date;
    }

    public function getDefaultStatus(): string
    {
        return $this->default_status;
    }

    public function areRevisionsEnabled(): bool
    {
        return $this->enable_revisions;
    }

    public function areCommentsEnabled(): bool
    {
        return $this->enable_comments;
    }

    public function getUrlPattern(): string
    {
        return $this->url_pattern;
    }

    public function getWorkflowId(): ?int
    {
        return $this->workflow_id;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    // =========================================================================
    // Setters
    // =========================================================================

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function setMachineName(string $machineName): static
    {
        $this->machine_name = $machineName;
        return $this;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function setTitleLabel(string $label): static
    {
        $this->title_label = $label;
        return $this;
    }

    public function setShowTitle(bool $show): static
    {
        $this->show_title = $show;
        return $this;
    }

    public function setShowAuthor(bool $show): static
    {
        $this->show_author = $show;
        return $this;
    }

    public function setShowDate(bool $show): static
    {
        $this->show_date = $show;
        return $this;
    }

    public function setDefaultStatus(string $status): static
    {
        $this->default_status = $status;
        return $this;
    }

    public function setEnableRevisions(bool $enable): static
    {
        $this->enable_revisions = $enable;
        return $this;
    }

    public function setEnableComments(bool $enable): static
    {
        $this->enable_comments = $enable;
        return $this;
    }

    public function setUrlPattern(string $pattern): static
    {
        $this->url_pattern = $pattern;
        return $this;
    }

    public function setWorkflowId(?int $workflowId): static
    {
        $this->workflow_id = $workflowId;
        return $this;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function setWeight(int $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function setFields(array $fields): static
    {
        $this->fields = $fields;
        return $this;
    }

    // =========================================================================
    // URL Generation
    // =========================================================================

    /**
     * Generate URL for a node of this type
     */
    public function generateUrl(array $tokens): string
    {
        $url = $this->url_pattern;

        // Default tokens
        $tokens['type'] = $this->machine_name;

        foreach ($tokens as $key => $value) {
            $url = str_replace("[{$key}]", (string) $value, $url);
        }

        return $url;
    }

    // =========================================================================
    // Machine Name Generation
    // =========================================================================

    /**
     * Generate machine name from human name
     */
    public static function generateMachineName(string $name): string
    {
        $machineName = strtolower($name);
        $machineName = preg_replace('/[^a-z0-9]+/', '_', $machineName);
        $machineName = trim($machineName, '_');

        return $machineName;
    }

    // =========================================================================
    // Serialization
    // =========================================================================

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['fields'] = $this->fields;
        return $data;
    }

    /**
     * Convert to array for API
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'machine_name' => $this->machine_name,
            'description' => $this->description,
            'icon' => $this->icon,
            'fields' => array_map(fn($f) => [
                'id' => $f['id'] ?? null,
                'name' => $f['name'] ?? '',
                'machine_name' => $f['machine_name'] ?? '',
                'type' => $f['field_type'] ?? '',
                'required' => $f['required'] ?? false,
            ], $this->fields),
        ];
    }
}

/**
 * ContentTypeManager - Operations for content types
 */
class ContentTypeManager
{
    private \PDO $db;

    /** @var ContentType[] */
    private array $cache = [];

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get all content types
     * 
     * @return ContentType[]
     */
    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM content_types ORDER BY weight, name");
        $types = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $type = ContentType::fromDatabase($row);
            $this->loadFields($type);
            $types[] = $type;
            $this->cache[$type->getMachineName()] = $type;
        }

        return $types;
    }

    /**
     * Find content type by ID
     */
    public function find(int $id): ?ContentType
    {
        $stmt = $this->db->prepare("SELECT * FROM content_types WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $type = ContentType::fromDatabase($row);
        $this->loadFields($type);
        $this->cache[$type->getMachineName()] = $type;

        return $type;
    }

    /**
     * Find content type by machine name
     */
    public function findByMachineName(string $machineName): ?ContentType
    {
        if (isset($this->cache[$machineName])) {
            return $this->cache[$machineName];
        }

        $stmt = $this->db->prepare("SELECT * FROM content_types WHERE machine_name = :name");
        $stmt->execute(['name' => $machineName]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $type = ContentType::fromDatabase($row);
        $this->loadFields($type);
        $this->cache[$machineName] = $type;

        return $type;
    }

    /**
     * Create a content type
     */
    public function create(array $data): ContentType
    {
        $type = new ContentType($data);

        // Generate machine name if not provided
        if (empty($type->getMachineName()) && !empty($type->getName())) {
            $type->setMachineName(ContentType::generateMachineName($type->getName()));
        }

        $dbData = $type->toDatabase();
        unset($dbData['id'], $dbData['fields']);

        $columns = array_keys($dbData);
        $placeholders = array_map(fn($c) => ":{$c}", $columns);

        $sql = sprintf(
            "INSERT INTO content_types (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($dbData);

        $type->setId((int) $this->db->lastInsertId());
        $type->syncOriginal();

        return $type;
    }

    /**
     * Update a content type
     */
    public function update(ContentType $type): void
    {
        $data = $type->toDatabase();
        unset($data['id'], $data['fields'], $data['is_system']);

        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "{$column} = :{$column}";
        }

        $data['id'] = $type->getId();

        $sql = sprintf(
            "UPDATE content_types SET %s WHERE id = :id",
            implode(', ', $sets)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        $type->syncOriginal();

        // Clear cache
        unset($this->cache[$type->getMachineName()]);
    }

    /**
     * Delete a content type
     */
    public function delete(ContentType $type): void
    {
        if ($type->isSystem()) {
            throw new \RuntimeException("Cannot delete system content type");
        }

        $stmt = $this->db->prepare("DELETE FROM content_types WHERE id = :id");
        $stmt->execute(['id' => $type->getId()]);

        unset($this->cache[$type->getMachineName()]);
    }

    /**
     * Load fields for a content type
     */
    private function loadFields(ContentType $type): void
    {
        $stmt = $this->db->prepare("
            SELECT f.*, ctf.weight as field_weight, ctf.settings as attachment_settings
            FROM field_definitions f
            INNER JOIN content_type_fields ctf ON ctf.field_id = f.id
            WHERE ctf.content_type_id = :type_id
            ORDER BY ctf.weight
        ");
        $stmt->execute(['type_id' => $type->getId()]);

        $fields = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $fields[] = $row;
        }

        $type->setFields($fields);
    }

    /**
     * Attach a field to a content type
     */
    public function attachField(ContentType $type, int $fieldId, int $weight = 0, ?array $settings = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO content_type_fields (content_type_id, field_id, weight, settings)
            VALUES (:type_id, :field_id, :weight, :settings)
            ON DUPLICATE KEY UPDATE weight = :weight, settings = :settings
        ");
        $stmt->execute([
            'type_id' => $type->getId(),
            'field_id' => $fieldId,
            'weight' => $weight,
            'settings' => $settings ? json_encode($settings) : null,
        ]);

        // Reload fields
        $this->loadFields($type);
    }

    /**
     * Detach a field from a content type
     */
    public function detachField(ContentType $type, int $fieldId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM content_type_fields 
            WHERE content_type_id = :type_id AND field_id = :field_id
        ");
        $stmt->execute([
            'type_id' => $type->getId(),
            'field_id' => $fieldId,
        ]);

        // Reload fields
        $this->loadFields($type);
    }

    /**
     * Get options array for select fields
     */
    public function getOptions(): array
    {
        $options = [];
        foreach ($this->all() as $type) {
            $options[$type->getMachineName()] = $type->getName();
        }
        return $options;
    }

    /**
     * Check if machine name exists
     */
    public function machineNameExists(string $machineName, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM content_types WHERE machine_name = :name";
        $params = ['name' => $machineName];

        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}
