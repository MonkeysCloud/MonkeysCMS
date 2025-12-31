<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * Media Entity - File and asset management
 */
#[ContentType(
    tableName: 'media',
    label: 'Media',
    description: 'Files, images, and media assets',
    icon: '🖼️',
    revisionable: false,
    publishable: true
)]
class Media extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(type: 'string', label: 'UUID', required: true, length: 36, unique: true)]
    public string $uuid = '';

    #[Field(type: 'string', label: 'Title', required: true, length: 255, searchable: true)]
    public string $title = '';

    #[Field(type: 'string', label: 'Alt Text', required: false, length: 255)]
    public ?string $alt = null;

    #[Field(type: 'text', label: 'Description', required: false)]
    public ?string $description = null;

    #[Field(type: 'string', label: 'Filename', required: true, length: 255)]
    public string $filename = '';

    #[Field(type: 'string', label: 'Original Filename', required: true, length: 255)]
    public string $original_filename = '';

    #[Field(type: 'string', label: 'Path', required: true, length: 500)]
    public string $path = '';

    #[Field(type: 'string', label: 'URL', required: false, length: 500)]
    public ?string $url = null;

    #[Field(type: 'string', label: 'Disk', required: true, length: 50, default: 'local')]
    public string $disk = 'local';

    #[Field(type: 'string', label: 'MIME Type', required: true, length: 100, indexed: true)]
    public string $mime_type = '';

    #[Field(
        type: 'string',
        label: 'Media Type',
        required: true,
        length: 50,
        indexed: true,
        widget: 'select',
        options: [
            'image' => 'Image',
            'video' => 'Video',
            'audio' => 'Audio',
            'document' => 'Document',
            'archive' => 'Archive',
            'other' => 'Other'
        ]
    )]
    public string $media_type = 'other';

    #[Field(type: 'int', label: 'File Size', required: true)]
    public int $size = 0;

    #[Field(type: 'int', label: 'Width', required: false)]
    public ?int $width = null;

    #[Field(type: 'int', label: 'Height', required: false)]
    public ?int $height = null;

    #[Field(type: 'int', label: 'Duration', required: false)]
    public ?int $duration = null;

    #[Field(type: 'string', label: 'Checksum', required: false, length: 64)]
    public ?string $checksum = null;

    #[Field(type: 'json', label: 'Variants', default: [])]
    public array $variants = [];

    #[Field(type: 'json', label: 'Metadata', default: [])]
    public array $metadata = [];

    #[Field(type: 'string', label: 'Folder', required: false, length: 255, indexed: true)]
    public ?string $folder = null;

    #[Field(type: 'int', label: 'Author ID', required: false, indexed: true)]
    public ?int $author_id = null;

    #[Field(type: 'boolean', label: 'Published', default: true)]
    public bool $is_published = true;

    #[Field(type: 'datetime', label: 'Created At')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime', label: 'Updated At')]
    public ?\DateTimeImmutable $updated_at = null;

    public function prePersist(): void
    {
        parent::prePersist();

        if (empty($this->uuid)) {
            $this->uuid = $this->generateUuid();
        }

        if (empty($this->title)) {
            $this->title = pathinfo($this->original_filename, PATHINFO_FILENAME);
        }

        $this->media_type = $this->determineMediaType();
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    private function determineMediaType(): string
    {
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif'];
        $videoTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
        $audioTypes = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm'];
        $documentTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'text/csv'];
        $archiveTypes = ['application/zip', 'application/x-rar-compressed', 'application/x-tar', 'application/gzip'];

        if (in_array($this->mime_type, $imageTypes, true)) {
            return 'image';
        }
        if (in_array($this->mime_type, $videoTypes, true)) {
            return 'video';
        }
        if (in_array($this->mime_type, $audioTypes, true)) {
            return 'audio';
        }
        if (in_array($this->mime_type, $documentTypes, true)) {
            return 'document';
        }
        if (in_array($this->mime_type, $archiveTypes, true)) {
            return 'archive';
        }

        return 'other';
    }

    public function isImage(): bool
    {
        return $this->media_type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    public function getUrl(): string
    {
        return $this->url ?? '/uploads/' . $this->path;
    }

    /**
     * Get thumbnail URL for display in media library
     */
    public function getThumbnailUrl(): ?string
    {
        // First, try to get the 'thumbnail' variant
        if (!empty($this->variants['thumbnail']['url'])) {
            return $this->variants['thumbnail']['url'];
        }
        
        // If no variant, return the original URL for images
        if ($this->isImage()) {
            return $this->getUrl();
        }
        
        return null;
    }

    public function getVariantUrl(string $variant): ?string
    {
        return $this->variants[$variant]['url'] ?? null;
    }

    public function addVariant(string $name, array $data): void
    {
        $this->variants[$name] = $data;
    }

    /**
     * Magic getter for computed properties like 'is_image' and 'extension'
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'is_image' => $this->isImage(),
            'extension' => $this->getExtension(),
            default => null,
        };
    }

    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function getDimensions(): ?string
    {
        if ($this->width && $this->height) {
            return "{$this->width}x{$this->height}";
        }
        return null;
    }

    public function getExtension(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function setMeta(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function toApiArray(bool $includeNulls = false): array
    {
        $data = parent::toArray($includeNulls);
        $data['url'] = $this->getUrl();
        $data['formatted_size'] = $this->getFormattedSize();
        $data['dimensions'] = $this->getDimensions();
        $data['extension'] = $this->getExtension();
        $data['is_image'] = $this->isImage();
        return $data;
    }
}
