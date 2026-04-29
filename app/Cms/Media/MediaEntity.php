<?php

declare(strict_types=1);

namespace App\Cms\Media;

use MonkeysLegion\Entity\Attributes\Column;
use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Id;

/**
 * MediaEntity — File/image/video stored in the media library.
 */
#[Entity(table: 'media')]
class MediaEntity
{
    #[Id]
    public ?int $id = null;

    #[Column(type: 'string', length: 255)]
    public string $filename = '';

    #[Column(type: 'string', length: 255)]
    public string $original_name = '';

    #[Column(type: 'string', length: 100)]
    public string $mime_type = '';

    #[Column(type: 'string', length: 500)]
    public string $path = '';

    #[Column(type: 'string', length: 500, nullable: true)]
    public ?string $url = null;

    #[Column(type: 'string', length: 255, nullable: true)]
    public ?string $alt = null;

    #[Column(type: 'string', length: 255, nullable: true)]
    public ?string $title = null;

    #[Column(type: 'string', length: 500, nullable: true)]
    public ?string $description = null;

    #[Column(type: 'integer', default: 0)]
    public int $size = 0;

    #[Column(type: 'integer', nullable: true)]
    public ?int $width = null;

    #[Column(type: 'integer', nullable: true)]
    public ?int $height = null;

    #[Column(type: 'json', default: '{}')]
    public array $metadata = [];

    #[Column(type: 'integer', nullable: true)]
    public ?int $uploaded_by = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Column(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    /** Computed: media type category */
    public string $type {
        get => match (true) {
            str_starts_with($this->mime_type, 'image/') => 'image',
            str_starts_with($this->mime_type, 'video/') => 'video',
            str_starts_with($this->mime_type, 'audio/') => 'audio',
            str_contains($this->mime_type, 'pdf') => 'document',
            default => 'file',
        };
    }

    /** Computed: human-readable size */
    public string $formattedSize {
        get {
            $units = ['B', 'KB', 'MB', 'GB'];
            $bytes = $this->size;
            $i = 0;
            while ($bytes >= 1024 && $i < count($units) - 1) {
                $bytes /= 1024;
                $i++;
            }
            return round($bytes, 2) . ' ' . $units[$i];
        }
    }

    public function hydrate(array $data): static
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : $this->id;
        $this->filename = $data['filename'] ?? $this->filename;
        $this->original_name = $data['original_name'] ?? $this->original_name;
        $this->mime_type = $data['mime_type'] ?? $this->mime_type;
        $this->path = $data['path'] ?? $this->path;
        $this->url = $data['url'] ?? $this->url;
        $this->alt = $data['alt'] ?? $this->alt;
        $this->title = $data['title'] ?? $this->title;
        $this->description = $data['description'] ?? $this->description;
        $this->size = (int) ($data['size'] ?? $this->size);
        $this->width = isset($data['width']) ? (int) $data['width'] : $this->width;
        $this->height = isset($data['height']) ? (int) $data['height'] : $this->height;
        $this->uploaded_by = isset($data['uploaded_by']) ? (int) $data['uploaded_by'] : $this->uploaded_by;

        $this->metadata = isset($data['metadata'])
            ? (is_string($data['metadata']) ? json_decode($data['metadata'], true) ?? [] : $data['metadata'])
            : $this->metadata;

        $this->created_at = isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : $this->created_at;
        $this->updated_at = isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : $this->updated_at;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'media',
            'attributes' => [
                'filename' => $this->filename,
                'original_name' => $this->original_name,
                'mime_type' => $this->mime_type,
                'url' => $this->url ?? ('/uploads/' . $this->path),
                'alt' => $this->alt,
                'title' => $this->title,
                'size' => $this->size,
                'formatted_size' => $this->formattedSize,
                'media_type' => $this->type,
                'width' => $this->width,
                'height' => $this->height,
            ],
        ];
    }
}
