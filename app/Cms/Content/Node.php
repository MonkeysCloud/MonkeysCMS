<?php

declare(strict_types=1);

namespace App\Cms\Content;

use App\Cms\Entity\BaseEntity;
use App\Cms\Entity\RevisionInterface;
use App\Cms\Entity\RevisionTrait;
use App\Cms\Entity\SoftDeleteInterface;
use App\Cms\Entity\SoftDeleteTrait;

/**
 * Node - Base content entity for the CMS
 *
 * Represents all content in the CMS including articles, pages, products, etc.
 * Each node has a type that defines its fields and behavior.
 *
 * Features:
 * - Soft delete support
 * - Revision tracking
 * - Field value storage
 * - Publishing workflow
 * - Author tracking
 * - SEO-friendly slugs
 */
class Node extends BaseEntity implements RevisionInterface, SoftDeleteInterface
{
    use RevisionTrait;
    use SoftDeleteTrait;

    // Node properties
    protected ?int $id = null;
    protected string $type = '';
    protected string $title = '';
    protected ?string $slug = null;
    protected string $status = NodeStatus::DRAFT;
    protected ?int $author_id = null;
    protected int $revision_id = 1;

    // Timestamps
    protected ?\DateTimeImmutable $created_at = null;
    protected ?\DateTimeImmutable $updated_at = null;
    protected ?\DateTimeImmutable $published_at = null;
    protected ?\DateTimeImmutable $deleted_at = null;

    // Field values (loaded separately)
    protected array $fields = [];

    // Related data (eager loaded)
    protected ?array $author = null;

    public static function getTableName(): string
    {
        return 'nodes';
    }

    public static function getFillable(): array
    {
        return [
            'type',
            'title',
            'slug',
            'status',
            'author_id',
            'published_at',
        ];
    }

    public static function getHidden(): array
    {
        return ['deleted_at'];
    }

    public static function getCasts(): array
    {
        return [
            'id' => 'int',
            'author_id' => 'int',
            'revision_id' => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'published_at' => 'datetime',
            'deleted_at' => 'datetime',
            'fields' => 'array',
        ];
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAuthorId(): ?int
    {
        return $this->author_id;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->published_at;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getAuthor(): ?array
    {
        return $this->author;
    }

    // =========================================================================
    // Setters
    // =========================================================================

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function setStatus(string $status): static
    {
        if (!NodeStatus::isValid($status)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
        $this->status = $status;
        return $this;
    }

    public function setAuthorId(?int $authorId): static
    {
        $this->author_id = $authorId;
        return $this;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->published_at = $publishedAt;
        return $this;
    }

    public function setFields(array $fields): static
    {
        $this->fields = $fields;
        return $this;
    }

    public function setField(string $name, mixed $value): static
    {
        $this->fields[$name] = $value;
        return $this;
    }

    public function setAuthor(?array $author): static
    {
        $this->author = $author;
        return $this;
    }

    // =========================================================================
    // Field Access
    // =========================================================================

    /**
     * Get a field value
     */
    public function getField(string $name, mixed $default = null): mixed
    {
        return $this->fields[$name] ?? $default;
    }

    /**
     * Check if field exists
     */
    public function hasField(string $name): bool
    {
        return array_key_exists($name, $this->fields);
    }

    /**
     * Remove a field
     */
    public function removeField(string $name): static
    {
        unset($this->fields[$name]);
        return $this;
    }

    // =========================================================================
    // Status Helpers
    // =========================================================================

    /**
     * Check if node is published
     */
    public function isPublished(): bool
    {
        return $this->status === NodeStatus::PUBLISHED;
    }

    /**
     * Check if node is a draft
     */
    public function isDraft(): bool
    {
        return $this->status === NodeStatus::DRAFT;
    }

    /**
     * Check if node is archived
     */
    public function isArchived(): bool
    {
        return $this->status === NodeStatus::ARCHIVED;
    }

    /**
     * Publish the node
     */
    public function publish(): static
    {
        $this->status = NodeStatus::PUBLISHED;
        $this->published_at = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Unpublish the node (back to draft)
     */
    public function unpublish(): static
    {
        $this->status = NodeStatus::DRAFT;
        return $this;
    }

    /**
     * Archive the node
     */
    public function archive(): static
    {
        $this->status = NodeStatus::ARCHIVED;
        return $this;
    }

    // =========================================================================
    // Slug Generation
    // =========================================================================

    /**
     * Generate slug from title
     */
    public function generateSlug(): string
    {
        $slug = strtolower($this->title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Ensure slug is set (generate if empty)
     */
    public function ensureSlug(): static
    {
        if (empty($this->slug)) {
            $this->slug = $this->generateSlug();
        }
        return $this;
    }

    // =========================================================================
    // URL Generation
    // =========================================================================

    /**
     * Get the canonical URL for this node
     */
    public function getUrl(): string
    {
        if ($this->slug) {
            return "/{$this->slug}";
        }
        return "/node/{$this->id}";
    }

    /**
     * Get the edit URL for this node
     */
    public function getEditUrl(): string
    {
        return "/admin/content/{$this->id}/edit";
    }

    // =========================================================================
    // Serialization
    // =========================================================================

    public function toArray(): array
    {
        $data = parent::toArray();

        // Include fields
        $data['fields'] = $this->fields;

        // Include author if loaded
        if ($this->author !== null) {
            $data['author'] = $this->author;
        }

        return $data;
    }

    public function toDatabase(): array
    {
        $data = parent::toDatabase();

        // Remove non-database fields
        unset($data['fields']);
        unset($data['author']);

        return $data;
    }

    /**
     * Convert to array for API response
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'attributes' => [
                'title' => $this->title,
                'slug' => $this->slug,
                'status' => $this->status,
                'created_at' => $this->created_at?->format('c'),
                'updated_at' => $this->updated_at?->format('c'),
                'published_at' => $this->published_at?->format('c'),
            ],
            'fields' => $this->fields,
            'relationships' => [
                'author' => $this->author,
            ],
            'links' => [
                'self' => $this->getUrl(),
                'edit' => $this->getEditUrl(),
            ],
        ];
    }
}

/**
 * NodeStatus - Status constants for nodes
 */
final class NodeStatus
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';
    public const PENDING = 'pending';
    public const SCHEDULED = 'scheduled';

    /**
     * Get all valid statuses
     *
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::PUBLISHED,
            self::ARCHIVED,
            self::PENDING,
            self::SCHEDULED,
        ];
    }

    /**
     * Check if status is valid
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    /**
     * Get status label
     */
    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
            self::PENDING => 'Pending Review',
            self::SCHEDULED => 'Scheduled',
            default => ucfirst($status),
        };
    }

    /**
     * Get status color (for UI)
     */
    public static function color(string $status): string
    {
        return match ($status) {
            self::DRAFT => 'gray',
            self::PUBLISHED => 'green',
            self::ARCHIVED => 'red',
            self::PENDING => 'yellow',
            self::SCHEDULED => 'blue',
            default => 'gray',
        };
    }
}

/**
 * NodeRevision - Stores historical versions of nodes
 */
class NodeRevision extends BaseEntity
{
    protected ?int $id = null;
    protected int $node_id;
    protected int $revision_id;
    protected string $title;
    protected ?array $data = null;
    protected ?int $author_id = null;
    protected ?string $log_message = null;
    protected ?\DateTimeImmutable $created_at = null;

    public static function getTableName(): string
    {
        return 'node_revisions';
    }

    public static function getFillable(): array
    {
        return [
            'node_id',
            'revision_id',
            'title',
            'data',
            'author_id',
            'log_message',
        ];
    }

    public static function getCasts(): array
    {
        return [
            'id' => 'int',
            'node_id' => 'int',
            'revision_id' => 'int',
            'author_id' => 'int',
            'data' => 'json',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Create revision from node
     */
    public static function fromNode(Node $node, ?string $logMessage = null, ?int $authorId = null): static
    {
        $revision = new static([
            'node_id' => $node->getId(),
            'revision_id' => $node->getRevisionId(),
            'title' => $node->getTitle(),
            'data' => $node->toArray(),
            'author_id' => $authorId ?? $node->getAuthorId(),
            'log_message' => $logMessage,
        ]);

        return $revision;
    }

    // Getters
    public function getNodeId(): int
    {
        return $this->node_id;
    }

    public function getRevisionNumber(): int
    {
        return $this->revision_id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function getAuthorId(): ?int
    {
        return $this->author_id;
    }

    public function getLogMessage(): ?string
    {
        return $this->log_message;
    }
}
