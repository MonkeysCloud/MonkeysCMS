<?php

declare(strict_types=1);

namespace App\Cms\Media;

use MonkeysLegion\Files\FilesManager;
use MonkeysLegion\Files\Image\ImageProcessor;
use MonkeysLegion\Files\Upload\UploadedFile;
use MonkeysLegion\Files\Upload\UploadResult;
use PDO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * MediaModule — Central facade for all media operations.
 *
 * Follows the same architectural pattern as ThemeManager:
 * a singleton service that orchestrates storage, processing, and persistence.
 *
 * PHP 8.4+ — property hooks, readonly properties, strict typing.
 */
final class MediaModule
{
    // ── PHP 8.4 Computed Property Hooks ─────────────────────────────────

    /** Whether the module is globally enabled */
    public bool $isEnabled {
        get => $this->config->enabled;
    }

    /** Total media count in DB */
    public int $totalMedia {
        get => $this->repository->count();
    }

    /** All registered image style names */
    public array $styleNames {
        get => $this->styleRegistry->names;
    }

    /** Active storage driver label */
    public string $storageLabel {
        get => $this->config->storageDriverLabel;
    }

    // ── Constructor ─────────────────────────────────────────────────────

    public function __construct(
        private readonly MediaConfig $config,
        private readonly MediaRepository $repository,
        private readonly MediaStyleRegistry $styleRegistry,
        private readonly FilesManager $files,
        private readonly ImageProcessor $imageProcessor,
        private readonly PDO $pdo,
        private readonly string $basePath = '',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    // ── Upload ──────────────────────────────────────────────────────────

    /**
     * Upload a file from a PSR-7 / $_FILES entry.
     *
     * 1. Validates via FilesManager
     * 2. Stores to the configured disk with date-based directory
     * 3. Generates image styles (thumbnails) if applicable
     * 4. Persists a MediaEntity to the database
     */
    public function upload(UploadedFile $file, ?int $uploadedBy = null): MediaEntity
    {
        $directory = $this->config->resolveUploadDirectory();

        // Let FilesManager handle validation + storage
        $result = $this->files->upload($file, $directory, options: [
            'preserve_name' => $this->config->preserveOriginalName,
        ]);

        if ($result->failed) {
            throw new \RuntimeException(
                'Upload failed: ' . implode(', ', $result->errors)
            );
        }

        $record = $result->file;

        // Build the MediaEntity
        $entity = new MediaEntity();
        $entity->filename = basename($record->path);
        $entity->original_name = $file->clientName;
        $entity->mime_type = $file->mimeType;
        $entity->path = $record->path;
        $entity->url = $this->files->url($record->path);
        $entity->alt = pathinfo($file->clientName, PATHINFO_FILENAME);
        $entity->title = pathinfo($file->clientName, PATHINFO_FILENAME);
        $entity->size = $file->size;
        $entity->uploaded_by = $uploadedBy;

        // Image-specific processing
        if ($file->isImage && !str_contains($file->mimeType, 'svg')) {
            $this->processImage($entity, $record->path);
        }

        // Persist to DB
        $entity = $this->repository->persist($entity);

        $this->logger->info('Media uploaded', [
            'id'   => $entity->id,
            'file' => $entity->original_name,
            'size' => $entity->formattedSize,
        ]);

        return $entity;
    }

    /**
     * Upload from a local file path (e.g., AI-generated image).
     */
    public function uploadFromPath(
        string $sourcePath,
        string $originalName,
        ?int $uploadedBy = null,
    ): MediaEntity {
        $mimeType = mime_content_type($sourcePath) ?: 'application/octet-stream';
        $size = filesize($sourcePath) ?: 0;

        $file = new UploadedFile(
            tmpPath: $sourcePath,
            clientName: $originalName,
            mimeType: $mimeType,
            size: $size,
        );

        return $this->upload($file, $uploadedBy);
    }

    // ── URL Generation ──────────────────────────────────────────────────

    /**
     * Get the public URL for a media item.
     */
    public function url(MediaEntity|int $media): string
    {
        if (is_int($media)) {
            $media = $this->repository->findOrFail($media);
        }

        return $media->url ?? $this->files->url($media->path);
    }

    /**
     * Get the URL for a specific image style.
     */
    public function styleUrl(MediaEntity|int $media, string $style = 'thumb'): string
    {
        if (is_int($media)) {
            $media = $this->repository->findOrFail($media);
        }

        $stylePath = $this->buildStylePath($media->path, $style);

        if ($this->files->exists($stylePath)) {
            return $this->files->url($stylePath);
        }

        // Fallback: generate on the fly
        if ($media->type === 'image' && !str_contains($media->mime_type, 'svg')) {
            $this->generateStyle($media, $style);
            return $this->files->url($stylePath);
        }

        // Non-image: return original
        return $this->url($media);
    }

    /**
     * Alias for backward compatibility — serves thumbnail URL.
     */
    public function thumbnailUrl(MediaEntity|int $media): string
    {
        return $this->styleUrl($media, 'thumb');
    }

    // ── Thumbnail / Style Generation ────────────────────────────────────

    /**
     * Generate all registered image styles for a media entity.
     */
    public function generateStyles(MediaEntity $media): void
    {
        if ($media->type !== 'image' || str_contains($media->mime_type, 'svg')) {
            return;
        }

        foreach ($this->styleRegistry->all() as $name => $conversion) {
            $this->generateStyle($media, $name);
        }
    }

    /**
     * Generate a single image style.
     */
    public function generateStyle(MediaEntity $media, string $style): void
    {
        $conversion = $this->styleRegistry->get($style);
        if (!$conversion) {
            return;
        }

        $sourcePath = $this->resolveLocalPath($media->path);
        if (!$sourcePath || !file_exists($sourcePath)) {
            return;
        }

        try {
            $imageData = $this->imageProcessor->applyConversion($sourcePath, $conversion);
            $stylePath = $this->buildStylePath($media->path, $style);
            $this->files->put($stylePath, $imageData);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to generate style', [
                'media_id' => $media->id,
                'style'    => $style,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Regenerate all styles for all media.
     *
     * @return int Number of media items processed
     */
    public function regenerateAll(): int
    {
        $count = 0;
        $offset = 0;
        $limit = 50;

        do {
            $items = $this->repository->findAll(type: 'image', limit: $limit, offset: $offset);

            foreach ($items as $media) {
                $this->generateStyles($media);
                $count++;
            }

            $offset += $limit;
        } while (count($items) === $limit);

        return $count;
    }

    // ── CRUD Delegation ─────────────────────────────────────────────────

    /**
     * Find a single media by ID.
     */
    public function find(int $id): ?MediaEntity
    {
        return $this->repository->find($id);
    }

    /**
     * Find a single media or throw.
     */
    public function findOrFail(int $id): MediaEntity
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * List media with filtering and pagination.
     *
     * @return MediaEntity[]
     */
    public function findAll(
        ?string $type = null,
        int $limit = 50,
        int $offset = 0,
        string $orderBy = 'created_at',
        string $direction = 'DESC',
    ): array {
        return $this->repository->findAll($type, $limit, $offset, $orderBy, $direction);
    }

    /**
     * Update media metadata (alt, title, description).
     */
    public function update(int $id, array $data): MediaEntity
    {
        $media = $this->repository->findOrFail($id);

        if (isset($data['alt'])) {
            $media->alt = $data['alt'];
        }
        if (isset($data['title'])) {
            $media->title = $data['title'];
        }
        if (isset($data['description'])) {
            $media->description = $data['description'];
        }

        return $this->repository->persist($media);
    }

    /**
     * Delete a media item — removes file, styles, and DB record.
     */
    public function delete(int $id): bool
    {
        $media = $this->repository->find($id);
        if (!$media) {
            return false;
        }

        // Delete the original file
        try {
            $this->files->delete($media->path);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to delete media file', [
                'id'    => $id,
                'path'  => $media->path,
                'error' => $e->getMessage(),
            ]);
        }

        // Delete image styles
        if ($media->type === 'image') {
            foreach ($this->styleRegistry->names as $style) {
                $stylePath = $this->buildStylePath($media->path, $style);
                try {
                    $this->files->delete($stylePath);
                } catch (\Throwable) {
                    // Ignore missing styles
                }
            }
        }

        // Delete DB record
        $this->repository->delete($id);

        $this->logger->info('Media deleted', ['id' => $id, 'file' => $media->original_name]);

        return true;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Get which content nodes reference a given media ID.
     *
     * @return array<int, array{id: int, title: string, content_type: string}>
     */
    public function getUsage(int $mediaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, content_type FROM nodes
             WHERE featured_image_id = :mid OR body LIKE :pattern"
        );
        $stmt->execute(['mid' => $mediaId, 'pattern' => "%/uploads/%"]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get aggregate disk usage statistics.
     *
     * @return array{total_size: int, formatted_size: string, count: int, by_type: array}
     */
    public function getDiskUsage(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                COUNT(*) as total_count,
                COALESCE(SUM(size), 0) as total_size,
                SUM(CASE WHEN mime_type LIKE 'image/%' THEN 1 ELSE 0 END) as images,
                SUM(CASE WHEN mime_type LIKE 'video/%' THEN 1 ELSE 0 END) as videos,
                SUM(CASE WHEN mime_type LIKE 'audio/%' THEN 1 ELSE 0 END) as audio,
                SUM(CASE WHEN mime_type NOT LIKE 'image/%'
                     AND mime_type NOT LIKE 'video/%'
                     AND mime_type NOT LIKE 'audio/%' THEN 1 ELSE 0 END) as documents
             FROM media"
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalSize = (int) ($row['total_size'] ?? 0);

        return [
            'total_size'     => $totalSize,
            'formatted_size' => $this->formatBytes($totalSize),
            'count'          => (int) ($row['total_count'] ?? 0),
            'by_type'        => [
                'images'    => (int) ($row['images'] ?? 0),
                'videos'    => (int) ($row['videos'] ?? 0),
                'audio'     => (int) ($row['audio'] ?? 0),
                'documents' => (int) ($row['documents'] ?? 0),
            ],
        ];
    }

    /**
     * Get the MediaConfig instance (for templates/controllers).
     */
    public function getConfig(): MediaConfig
    {
        return $this->config;
    }

    /**
     * Get the style registry (for templates/controllers).
     */
    public function getStyleRegistry(): MediaStyleRegistry
    {
        return $this->styleRegistry;
    }

    // ── Internal ────────────────────────────────────────────────────────

    /**
     * Process an uploaded image: extract dimensions, generate styles.
     */
    private function processImage(MediaEntity $entity, string $storedPath): void
    {
        $localPath = $this->resolveLocalPath($storedPath);
        if (!$localPath || !file_exists($localPath)) {
            return;
        }

        try {
            $dims = $this->imageProcessor->getDimensions($localPath);
            $entity->width = $dims['width'] ?? $dims[0] ?? null;
            $entity->height = $dims['height'] ?? $dims[1] ?? null;
        } catch (\Throwable) {
            // Non-critical — skip dimension extraction
        }

        // Generate image styles
        if ($this->config->generateThumbnails) {
            foreach ($this->styleRegistry->all() as $name => $conversion) {
                try {
                    $imageData = $this->imageProcessor->applyConversion($localPath, $conversion);
                    $stylePath = $this->buildStylePath($storedPath, $name);
                    $this->files->put($stylePath, $imageData);
                } catch (\Throwable $e) {
                    $this->logger->warning("Style '{$name}' generation failed", [
                        'file'  => $entity->original_name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Build the path for an image style derivative.
     *
     * e.g., "uploads/2026/05/01/abc123.jpg" → "uploads/2026/05/01/styles/thumb/abc123.jpg"
     */
    private function buildStylePath(string $originalPath, string $style): string
    {
        $dir = dirname($originalPath);
        $file = basename($originalPath);

        return $dir . '/styles/' . $style . '/' . $file;
    }

    /**
     * Resolve a stored path to an absolute local filesystem path.
     * Only works for the 'local' storage driver.
     */
    private function resolveLocalPath(string $storedPath): ?string
    {
        if ($this->config->storageDriver !== 'local') {
            // For cloud storage, download to tmp
            $contents = $this->files->get($storedPath);
            if ($contents === null) {
                return null;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'ml_media_');
            file_put_contents($tmp, $contents);

            return $tmp;
        }

        $path = $this->basePath . '/public/' . $storedPath;

        return file_exists($path) ? $path : null;
    }

    private function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_073_741_824 => round($bytes / 1_073_741_824, 2) . ' GB',
            $bytes >= 1_048_576     => round($bytes / 1_048_576, 2) . ' MB',
            $bytes >= 1_024         => round($bytes / 1_024, 2) . ' KB',
            default                 => $bytes . ' B',
        };
    }
}
