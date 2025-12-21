<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Cms\Repository\CmsRepository;
use App\Modules\Core\Entities\Media;
use MonkeysLegion\Files\Storage\StorageManager;
use MonkeysLegion\Files\Upload\ChunkedUploadManager;
use MonkeysLegion\Files\Upload\UploadValidator;
use MonkeysLegion\Files\Image\ImageProcessor;
use MonkeysLegion\Files\Scanner\VirusScanner;
use MonkeysLegion\Files\Exception\UploadException;
use MonkeysLegion\Files\Exception\StorageException;
use MonkeysLegion\Files\Exception\ValidationException;
use MonkeysLegion\Mlc\Config;
use Psr\Http\Message\UploadedFileInterface;

/**
 * MediaService - Complete media library management
 * 
 * Integrates with MonkeysLegion-Files for:
 * - File uploads (single and chunked)
 * - Multiple storage backends (local, S3, GCS)
 * - Image processing (resize, crop, thumbnails)
 * - MIME validation and virus scanning
 * - Media variants generation
 * 
 * @example
 * ```php
 * // Upload a file
 * $media = $mediaService->upload($uploadedFile, [
 *     'folder' => 'products',
 *     'title' => 'Product Image',
 *     'generate_variants' => true,
 * ]);
 * 
 * // Get thumbnail URL
 * $thumbUrl = $media->getVariantUrl('thumbnail');
 * ```
 */
final class MediaService
{
    private Config $config;
    private array $allowedMimes = [];
    private array $blockedMimes = [];
    private array $blockedExtensions = [];
    private array $imageVariants = [];
    
    public function __construct(
        private readonly CmsRepository $repository,
        private readonly StorageManager $storage,
        private readonly ChunkedUploadManager $chunkedUpload,
        private readonly UploadValidator $validator,
        private readonly ImageProcessor $imageProcessor,
        private readonly ?VirusScanner $virusScanner = null,
        ?Config $config = null,
    ) {
        $this->config = $config ?? new Config([]);
        $this->loadConfig();
    }
    
    /**
     * Load configuration from files.mlc
     */
    private function loadConfig(): void
    {
        $this->allowedMimes = $this->config->get('upload.allowed_mimes', [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'video/mp4', 'audio/mpeg',
        ]);
        
        $this->blockedMimes = $this->config->get('upload.blocked_mimes', [
            'application/x-php', 'application/x-httpd-php',
            'application/x-executable', 'text/html',
        ]);
        
        $this->blockedExtensions = $this->config->get('upload.blocked_extensions', [
            'php', 'phtml', 'exe', 'sh', 'bat', 'htaccess',
        ]);
        
        $this->imageVariants = $this->config->get('image.variants', [
            'thumbnail' => ['width' => 150, 'height' => 150, 'mode' => 'cover'],
            'small' => ['width' => 320, 'height' => 240, 'mode' => 'fit'],
            'medium' => ['width' => 800, 'height' => 600, 'mode' => 'fit'],
            'large' => ['width' => 1920, 'height' => 1080, 'mode' => 'fit'],
        ]);
    }
    
    // =========================================================================
    // Single File Upload
    // =========================================================================
    
    /**
     * Upload a single file
     * 
     * @param UploadedFileInterface $file PSR-7 uploaded file
     * @param array $options Upload options
     * @return Media Created media entity
     * @throws UploadException|ValidationException
     */
    public function upload(UploadedFileInterface $file, array $options = []): Media
    {
        // Validate the file
        $this->validateFile($file);
        
        // Virus scan if enabled
        if ($this->virusScanner !== null && $this->config->get('virusscan.enabled', false)) {
            $this->scanForVirus($file);
        }
        
        // Generate unique filename
        $originalName = $file->getClientFilename() ?? 'unnamed';
        $extension = $this->getExtension($originalName);
        $filename = $this->generateFilename($extension);
        
        // Determine storage path
        $folder = $options['folder'] ?? $this->getDateFolder();
        $path = $folder . '/' . $filename;
        
        // Get storage disk
        $disk = $options['disk'] ?? $this->config->get('public_disk', 'public');
        
        // Store the file
        $stream = $file->getStream();
        $this->storage->disk($disk)->write($path, $stream->getContents());
        
        // Get file info
        $mimeType = $this->detectMimeType($file);
        $size = $file->getSize() ?? 0;
        $checksum = $this->config->get('upload.generate_checksum', true)
            ? $this->generateChecksum($file)
            : null;
        
        // Create media entity
        $media = new Media();
        $media->title = $options['title'] ?? pathinfo($originalName, PATHINFO_FILENAME);
        $media->alt = $options['alt'] ?? null;
        $media->description = $options['description'] ?? null;
        $media->filename = $filename;
        $media->original_filename = $originalName;
        $media->path = $path;
        $media->disk = $disk;
        $media->mime_type = $mimeType;
        $media->size = $size;
        $media->checksum = $checksum;
        $media->folder = $folder;
        $media->author_id = $options['author_id'] ?? null;
        $media->metadata = $options['metadata'] ?? [];
        
        // Process images
        if ($this->isImage($mimeType)) {
            $this->processImage($media, $options);
        }
        
        // Save to database
        $this->repository->save($media);
        
        return $media;
    }
    
    /**
     * Upload from a URL
     */
    public function uploadFromUrl(string $url, array $options = []): Media
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'follow_location' => true,
                'max_redirects' => 5,
            ],
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            throw new UploadException("Failed to download file from URL: {$url}");
        }
        
        // Get filename from URL
        $parsedUrl = parse_url($url);
        $originalName = basename($parsedUrl['path'] ?? 'downloaded');
        
        // Create temp file
        $tempPath = sys_get_temp_dir() . '/' . uniqid('upload_');
        file_put_contents($tempPath, $content);
        
        try {
            $media = $this->uploadFromPath($tempPath, $originalName, $options);
        } finally {
            @unlink($tempPath);
        }
        
        return $media;
    }
    
    /**
     * Upload from a local file path
     */
    public function uploadFromPath(string $filePath, string $originalName, array $options = []): Media
    {
        if (!file_exists($filePath)) {
            throw new UploadException("File not found: {$filePath}");
        }
        
        // Validate
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $this->validateMimeType($mimeType);
        $this->validateExtension($originalName);
        
        // Generate unique filename
        $extension = $this->getExtension($originalName);
        $filename = $this->generateFilename($extension);
        
        // Determine storage path
        $folder = $options['folder'] ?? $this->getDateFolder();
        $path = $folder . '/' . $filename;
        
        // Get storage disk
        $disk = $options['disk'] ?? $this->config->get('public_disk', 'public');
        
        // Store the file
        $content = file_get_contents($filePath);
        $this->storage->disk($disk)->write($path, $content);
        
        // Get file info
        $size = filesize($filePath);
        $checksum = $this->config->get('upload.generate_checksum', true)
            ? hash_file('sha256', $filePath)
            : null;
        
        // Create media entity
        $media = new Media();
        $media->title = $options['title'] ?? pathinfo($originalName, PATHINFO_FILENAME);
        $media->alt = $options['alt'] ?? null;
        $media->description = $options['description'] ?? null;
        $media->filename = $filename;
        $media->original_filename = $originalName;
        $media->path = $path;
        $media->disk = $disk;
        $media->mime_type = $mimeType;
        $media->size = $size;
        $media->checksum = $checksum;
        $media->folder = $folder;
        $media->author_id = $options['author_id'] ?? null;
        $media->metadata = $options['metadata'] ?? [];
        
        // Process images
        if ($this->isImage($mimeType)) {
            $this->processImage($media, $options);
        }
        
        // Save to database
        $this->repository->save($media);
        
        return $media;
    }
    
    // =========================================================================
    // Chunked Upload
    // =========================================================================
    
    /**
     * Initialize a chunked upload session
     * 
     * @return array Upload session info
     */
    public function initChunkedUpload(
        string $filename,
        int $fileSize,
        string $mimeType,
        array $options = []
    ): array {
        // Validate before starting
        $this->validateMimeType($mimeType);
        $this->validateExtension($filename);
        $this->validateFileSize($fileSize);
        
        // Create upload session
        $session = $this->chunkedUpload->initiate(
            filename: $filename,
            fileSize: $fileSize,
            mimeType: $mimeType,
            chunkSize: $this->config->get('upload.chunk_size', 5 * 1024 * 1024),
            metadata: $options,
        );
        
        return [
            'upload_id' => $session->getId(),
            'chunk_size' => $session->getChunkSize(),
            'total_chunks' => $session->getTotalChunks(),
            'expires_at' => $session->getExpiresAt()->format('c'),
        ];
    }
    
    /**
     * Upload a chunk
     */
    public function uploadChunk(
        string $uploadId,
        int $chunkNumber,
        string $chunkData
    ): array {
        $result = $this->chunkedUpload->uploadChunk(
            uploadId: $uploadId,
            chunkNumber: $chunkNumber,
            data: $chunkData,
        );
        
        return [
            'chunk_number' => $chunkNumber,
            'received' => strlen($chunkData),
            'chunks_uploaded' => $result->getUploadedChunks(),
            'total_chunks' => $result->getTotalChunks(),
            'is_complete' => $result->isComplete(),
        ];
    }
    
    /**
     * Complete a chunked upload
     */
    public function completeChunkedUpload(string $uploadId, array $options = []): Media
    {
        // Assemble the file
        $result = $this->chunkedUpload->complete($uploadId);
        
        $tempPath = $result->getTempPath();
        $originalName = $result->getFilename();
        
        try {
            // Virus scan the assembled file
            if ($this->virusScanner !== null && $this->config->get('virusscan.enabled', false)) {
                $scanResult = $this->virusScanner->scanFile($tempPath);
                if (!$scanResult->isClean()) {
                    throw new UploadException('Virus detected: ' . $scanResult->getThreatName());
                }
            }
            
            // Upload the assembled file
            $media = $this->uploadFromPath($tempPath, $originalName, array_merge(
                $result->getMetadata(),
                $options
            ));
        } finally {
            // Cleanup temp file
            $this->chunkedUpload->cleanup($uploadId);
        }
        
        return $media;
    }
    
    /**
     * Abort a chunked upload
     */
    public function abortChunkedUpload(string $uploadId): void
    {
        $this->chunkedUpload->abort($uploadId);
    }
    
    // =========================================================================
    // Image Processing
    // =========================================================================
    
    /**
     * Process an uploaded image (extract dimensions, generate variants)
     */
    private function processImage(Media $media, array $options = []): void
    {
        $disk = $this->storage->disk($media->disk);
        $content = $disk->read($media->path);
        
        // Get image dimensions
        $dimensions = $this->imageProcessor->getDimensions($content);
        $media->width = $dimensions['width'];
        $media->height = $dimensions['height'];
        
        // Extract EXIF data
        $exif = $this->imageProcessor->getExif($content);
        if (!empty($exif)) {
            $media->setMeta('exif', $exif);
        }
        
        // Generate variants if requested
        $generateVariants = $options['generate_variants'] ?? true;
        $variants = $options['variants'] ?? array_keys($this->imageVariants);
        
        if ($generateVariants && !empty($variants)) {
            $this->generateImageVariants($media, $variants);
        }
    }
    
    /**
     * Generate image variants (thumbnails)
     */
    public function generateImageVariants(Media $media, array $variants = []): void
    {
        if (!$media->isImage()) {
            return;
        }
        
        if (empty($variants)) {
            $variants = array_keys($this->imageVariants);
        }
        
        $disk = $this->storage->disk($media->disk);
        $content = $disk->read($media->path);
        
        $pathInfo = pathinfo($media->path);
        $baseDir = $pathInfo['dirname'];
        $baseName = $pathInfo['filename'];
        
        foreach ($variants as $variantName) {
            $config = $this->imageVariants[$variantName] ?? null;
            if ($config === null) {
                continue;
            }
            
            // Process the image
            $processed = $this->imageProcessor->resize(
                content: $content,
                width: $config['width'],
                height: $config['height'],
                mode: $config['mode'] ?? 'fit',
                quality: $config['quality'] ?? 85,
                format: $config['format'] ?? null,
            );
            
            // Determine variant extension
            $extension = $config['format'] ?? $pathInfo['extension'];
            $variantFilename = "{$baseName}_{$variantName}.{$extension}";
            $variantPath = "{$baseDir}/{$variantFilename}";
            
            // Store variant
            $disk->write($variantPath, $processed->getContent());
            
            // Record variant info
            $media->addVariant($variantName, [
                'path' => $variantPath,
                'url' => $disk->url($variantPath),
                'width' => $processed->getWidth(),
                'height' => $processed->getHeight(),
                'size' => strlen($processed->getContent()),
                'format' => $extension,
            ]);
        }
        
        // Update media record
        $this->repository->save($media);
    }
    
    /**
     * Crop an image
     */
    public function crop(
        Media $media,
        int $x,
        int $y,
        int $width,
        int $height,
        bool $createNew = false
    ): Media {
        if (!$media->isImage()) {
            throw new \InvalidArgumentException('Media is not an image');
        }
        
        $disk = $this->storage->disk($media->disk);
        $content = $disk->read($media->path);
        
        // Crop the image
        $cropped = $this->imageProcessor->crop($content, $x, $y, $width, $height);
        
        if ($createNew) {
            // Create a new media entry
            return $this->uploadFromPath(
                $this->saveTempFile($cropped->getContent()),
                'cropped_' . $media->original_filename,
                [
                    'folder' => $media->folder,
                    'disk' => $media->disk,
                    'author_id' => $media->author_id,
                ]
            );
        }
        
        // Replace original
        $disk->write($media->path, $cropped->getContent());
        $media->width = $cropped->getWidth();
        $media->height = $cropped->getHeight();
        $media->size = strlen($cropped->getContent());
        $media->variants = []; // Clear variants
        
        $this->repository->save($media);
        
        // Regenerate variants
        $this->generateImageVariants($media);
        
        return $media;
    }
    
    /**
     * Rotate an image
     */
    public function rotate(Media $media, int $degrees): Media
    {
        if (!$media->isImage()) {
            throw new \InvalidArgumentException('Media is not an image');
        }
        
        $disk = $this->storage->disk($media->disk);
        $content = $disk->read($media->path);
        
        $rotated = $this->imageProcessor->rotate($content, $degrees);
        
        $disk->write($media->path, $rotated->getContent());
        $media->width = $rotated->getWidth();
        $media->height = $rotated->getHeight();
        $media->variants = [];
        
        $this->repository->save($media);
        $this->generateImageVariants($media);
        
        return $media;
    }
    
    // =========================================================================
    // Media Management
    // =========================================================================
    
    /**
     * Get media by ID
     */
    public function find(int $id): ?Media
    {
        return $this->repository->find(Media::class, $id);
    }
    
    /**
     * Get media by UUID
     */
    public function findByUuid(string $uuid): ?Media
    {
        return $this->repository->findOneBy(Media::class, ['uuid' => $uuid]);
    }
    
    /**
     * List media with filters
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $criteria = [];
        
        if (isset($filters['media_type'])) {
            $criteria['media_type'] = $filters['media_type'];
        }
        
        if (isset($filters['folder'])) {
            $criteria['folder'] = $filters['folder'];
        }
        
        if (isset($filters['author_id'])) {
            $criteria['author_id'] = $filters['author_id'];
        }
        
        if (isset($filters['is_published'])) {
            $criteria['is_published'] = $filters['is_published'];
        }
        
        $orderBy = ['created_at' => 'DESC'];
        
        return $this->repository->paginate(
            Media::class,
            $page,
            $perPage,
            $criteria,
            $orderBy
        );
    }
    
    /**
     * Search media by title or filename
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->repository->search(
            Media::class,
            $query,
            ['title', 'original_filename', 'description'],
            $limit
        );
    }
    
    /**
     * Update media metadata
     */
    public function update(Media $media, array $data): Media
    {
        $allowedFields = ['title', 'alt', 'description', 'folder', 'is_published', 'metadata'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $media->$field = $data[$field];
            }
        }
        
        $this->repository->save($media);
        
        return $media;
    }
    
    /**
     * Move media to a different folder
     */
    public function move(Media $media, string $newFolder): Media
    {
        $disk = $this->storage->disk($media->disk);
        
        // Move main file
        $newPath = $newFolder . '/' . $media->filename;
        $disk->move($media->path, $newPath);
        
        // Move variants
        $newVariants = [];
        foreach ($media->variants as $name => $variant) {
            $variantFilename = basename($variant['path']);
            $newVariantPath = $newFolder . '/' . $variantFilename;
            $disk->move($variant['path'], $newVariantPath);
            
            $newVariants[$name] = array_merge($variant, [
                'path' => $newVariantPath,
                'url' => $disk->url($newVariantPath),
            ]);
        }
        
        $media->path = $newPath;
        $media->folder = $newFolder;
        $media->variants = $newVariants;
        
        $this->repository->save($media);
        
        return $media;
    }
    
    /**
     * Copy media
     */
    public function copy(Media $media, ?string $newFolder = null): Media
    {
        $disk = $this->storage->disk($media->disk);
        $content = $disk->read($media->path);
        
        $folder = $newFolder ?? $media->folder;
        $newFilename = $this->generateFilename($this->getExtension($media->filename));
        $newPath = $folder . '/' . $newFilename;
        
        $disk->write($newPath, $content);
        
        // Create new media entity
        $newMedia = new Media();
        $newMedia->title = $media->title . ' (copy)';
        $newMedia->alt = $media->alt;
        $newMedia->description = $media->description;
        $newMedia->filename = $newFilename;
        $newMedia->original_filename = $media->original_filename;
        $newMedia->path = $newPath;
        $newMedia->disk = $media->disk;
        $newMedia->mime_type = $media->mime_type;
        $newMedia->size = $media->size;
        $newMedia->width = $media->width;
        $newMedia->height = $media->height;
        $newMedia->folder = $folder;
        $newMedia->author_id = $media->author_id;
        $newMedia->metadata = $media->metadata;
        
        $this->repository->save($newMedia);
        
        // Generate variants for the copy
        if ($newMedia->isImage()) {
            $this->generateImageVariants($newMedia);
        }
        
        return $newMedia;
    }
    
    /**
     * Delete media and all variants
     */
    public function delete(Media $media): void
    {
        $disk = $this->storage->disk($media->disk);
        
        // Delete variants first
        $cascadeDelete = $this->config->get('cleanup.cascade_delete', true);
        if ($cascadeDelete) {
            foreach ($media->variants as $variant) {
                try {
                    $disk->delete($variant['path']);
                } catch (StorageException) {
                    // Variant might already be deleted
                }
            }
        }
        
        // Delete main file
        try {
            $disk->delete($media->path);
        } catch (StorageException) {
            // File might already be deleted
        }
        
        // Delete from database
        $this->repository->delete($media);
    }
    
    /**
     * Get URL for a media item
     */
    public function getUrl(Media $media, ?string $variant = null): string
    {
        if ($variant !== null) {
            $url = $media->getVariantUrl($variant);
            if ($url !== null) {
                return $this->applyCdn($url);
            }
        }
        
        $disk = $this->storage->disk($media->disk);
        $url = $disk->url($media->path);
        
        return $this->applyCdn($url);
    }
    
    /**
     * Get a temporary URL (for private files)
     */
    public function getTemporaryUrl(Media $media, int $expiresInSeconds = 3600): string
    {
        $disk = $this->storage->disk($media->disk);
        
        if (method_exists($disk, 'temporaryUrl')) {
            return $disk->temporaryUrl($media->path, $expiresInSeconds);
        }
        
        // Fallback to regular URL
        return $this->getUrl($media);
    }
    
    /**
     * Get folders with media count
     */
    public function getFolders(): array
    {
        // This would ideally use a GROUP BY query
        // For now, we'll get unique folders from media
        $all = $this->repository->findAll(Media::class);
        $folders = [];
        
        foreach ($all as $media) {
            $folder = $media->folder ?? 'Uncategorized';
            if (!isset($folders[$folder])) {
                $folders[$folder] = 0;
            }
            $folders[$folder]++;
        }
        
        ksort($folders);
        
        return $folders;
    }
    
    /**
     * Get disk usage statistics
     */
    public function getUsageStats(): array
    {
        $all = $this->repository->findAll(Media::class);
        
        $stats = [
            'total_files' => count($all),
            'total_size' => 0,
            'by_type' => [],
            'by_disk' => [],
        ];
        
        foreach ($all as $media) {
            $stats['total_size'] += $media->size;
            
            $type = $media->media_type;
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
            
            $disk = $media->disk;
            $stats['by_disk'][$disk] = ($stats['by_disk'][$disk] ?? 0) + $media->size;
        }
        
        $stats['total_size_formatted'] = $this->formatSize($stats['total_size']);
        
        return $stats;
    }
    
    // =========================================================================
    // Validation
    // =========================================================================
    
    /**
     * Validate an uploaded file
     */
    private function validateFile(UploadedFileInterface $file): void
    {
        // Check upload error
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new UploadException($this->getUploadErrorMessage($file->getError()));
        }
        
        // Validate size
        $maxSize = $this->config->get('upload.max_size', 50 * 1024 * 1024);
        if ($file->getSize() > $maxSize) {
            throw new ValidationException(sprintf(
                'File size %s exceeds maximum allowed %s',
                $this->formatSize($file->getSize()),
                $this->formatSize($maxSize)
            ));
        }
        
        // Validate MIME type
        $mimeType = $this->detectMimeType($file);
        $this->validateMimeType($mimeType);
        
        // Validate extension
        $filename = $file->getClientFilename() ?? '';
        $this->validateExtension($filename);
    }
    
    /**
     * Validate MIME type
     */
    private function validateMimeType(string $mimeType): void
    {
        // Check blocked types
        if (in_array($mimeType, $this->blockedMimes, true)) {
            throw new ValidationException("File type '{$mimeType}' is not allowed (security)");
        }
        
        // Check allowed types
        if (!empty($this->allowedMimes) && !in_array($mimeType, $this->allowedMimes, true)) {
            throw new ValidationException(sprintf(
                "File type '%s' is not allowed. Allowed types: %s",
                $mimeType,
                implode(', ', $this->allowedMimes)
            ));
        }
    }
    
    /**
     * Validate file extension
     */
    private function validateExtension(string $filename): void
    {
        $extension = strtolower($this->getExtension($filename));
        
        if (in_array($extension, $this->blockedExtensions, true)) {
            throw new ValidationException("File extension '.{$extension}' is not allowed (security)");
        }
    }
    
    /**
     * Validate file size
     */
    private function validateFileSize(int $size): void
    {
        $maxSize = $this->config->get('upload.max_size', 50 * 1024 * 1024);
        if ($size > $maxSize) {
            throw new ValidationException(sprintf(
                'File size %s exceeds maximum allowed %s',
                $this->formatSize($size),
                $this->formatSize($maxSize)
            ));
        }
    }
    
    /**
     * Scan file for viruses
     */
    private function scanForVirus(UploadedFileInterface $file): void
    {
        if ($this->virusScanner === null) {
            return;
        }
        
        $stream = $file->getStream();
        $result = $this->virusScanner->scanStream($stream);
        
        if (!$result->isClean()) {
            throw new UploadException('Virus detected: ' . $result->getThreatName());
        }
    }
    
    // =========================================================================
    // Utilities
    // =========================================================================
    
    /**
     * Detect MIME type from uploaded file
     */
    private function detectMimeType(UploadedFileInterface $file): string
    {
        // First try the client-provided type
        $clientType = $file->getClientMediaType();
        
        // If verification is enabled, detect from content
        if ($this->config->get('upload.verify_mime', true)) {
            $stream = $file->getStream();
            $content = $stream->read(8192);
            $stream->rewind();
            
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($content);
            
            if ($detected !== false) {
                return $detected;
            }
        }
        
        return $clientType ?? 'application/octet-stream';
    }
    
    /**
     * Check if MIME type is an image
     */
    private function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }
    
    /**
     * Generate unique filename
     */
    private function generateFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }
    
    /**
     * Get file extension
     */
    private function getExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'bin';
    }
    
    /**
     * Generate date-based folder path
     */
    private function getDateFolder(): string
    {
        return date('Y/m');
    }
    
    /**
     * Generate checksum for uploaded file
     */
    private function generateChecksum(UploadedFileInterface $file): string
    {
        $stream = $file->getStream();
        $content = $stream->getContents();
        $stream->rewind();
        
        return hash('sha256', $content);
    }
    
    /**
     * Apply CDN URL prefix
     */
    private function applyCdn(string $url): string
    {
        if (!$this->config->get('cdn.enabled', false)) {
            return $url;
        }
        
        $cdnUrl = $this->config->get('cdn.url', '');
        if (empty($cdnUrl)) {
            return $url;
        }
        
        // Replace origin with CDN
        return rtrim($cdnUrl, '/') . '/' . ltrim($url, '/');
    }
    
    /**
     * Format file size for display
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;
        
        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unit];
    }
    
    /**
     * Save content to temp file
     */
    private function saveTempFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('media_');
        file_put_contents($path, $content);
        return $path;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            default => 'Unknown upload error',
        };
    }
}
