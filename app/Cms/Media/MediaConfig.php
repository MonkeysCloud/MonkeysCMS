<?php

declare(strict_types=1);

namespace App\Cms\Media;

use PDO;

/**
 * MediaConfig — PHP 8.4 value object for media module configuration.
 *
 * Resolves configuration from the `settings` table (group = 'media').
 * Uses PHP 8.4 property hooks for computed properties.
 */
final class MediaConfig
{
    /** Supported storage driver labels */
    public const array STORAGE_DRIVERS = [
        'local' => 'Local Filesystem',
        's3'    => 'Amazon S3',
        'gcs'   => 'Google Cloud Storage',
        'azure' => 'Azure Blob Storage',
    ];

    /** Default allowed MIME types */
    public const array DEFAULT_ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif',
        'video/mp4', 'video/webm', 'video/quicktime',
        'audio/mpeg', 'audio/wav', 'audio/ogg',
        'application/pdf',
        'application/zip', 'application/x-gzip',
        'text/plain', 'text/csv',
    ];

    /** Extensions always denied */
    public const array DENIED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phps',
        'exe', 'bat', 'cmd', 'sh', 'bash', 'cgi',
        'htaccess', 'htpasswd',
    ];

    /** Default directory pattern tokens */
    public const array DIRECTORY_PATTERNS = [
        'date'  => '{year}/{month}/{day}',
        'month' => '{year}/{month}',
        'flat'  => '',
        'type'  => '{media_type}',
    ];

    // ── Stored Properties ───────────────────────────────────────────────

    public function __construct(
        public readonly bool $enabled = true,
        public readonly string $storageDriver = 'local',
        public readonly int $maxFileSize = 10_485_760,           // 10 MB
        public readonly array $allowedMimeTypes = self::DEFAULT_ALLOWED_MIMES,
        public readonly array $deniedExtensions = self::DENIED_EXTENSIONS,
        public readonly string $directoryPattern = 'date',       // date, month, flat, type
        public readonly string $uploadPath = 'uploads',
        public readonly int $imageQuality = 85,
        public readonly bool $generateThumbnails = true,
        public readonly bool $preserveOriginalName = false,
        public readonly array $imageStyles = [],                 // override defaults
        // ── Cloud storage settings ──
        public readonly string $s3Bucket = '',
        public readonly string $s3Region = 'us-east-1',
        public readonly string $s3Key = '',
        public readonly string $s3Secret = '',
        public readonly string $s3Endpoint = '',
        public readonly string $s3Prefix = '',
        public readonly string $gcsBucket = '',
        public readonly string $gcsKeyFile = '',
        public readonly string $gcsProjectId = '',
        public readonly string $gcsPrefix = '',
        public readonly string $azureConnectionString = '',
        public readonly string $azureContainer = '',
        public readonly string $azurePrefix = '',
    ) {}

    // ── PHP 8.4 Computed Property Hooks ─────────────────────────────────

    /** Human-readable max file size */
    public string $maxFileSizeFormatted {
        get => match (true) {
            $this->maxFileSize >= 1_073_741_824 => round($this->maxFileSize / 1_073_741_824, 1) . ' GB',
            $this->maxFileSize >= 1_048_576     => round($this->maxFileSize / 1_048_576, 1) . ' MB',
            $this->maxFileSize >= 1_024         => round($this->maxFileSize / 1_024, 1) . ' KB',
            default                             => $this->maxFileSize . ' B',
        };
    }

    /** Whether cloud storage is configured */
    public bool $isCloudStorage {
        get => in_array($this->storageDriver, ['s3', 'gcs', 'azure'], true);
    }

    /** Storage driver label */
    public string $storageDriverLabel {
        get => self::STORAGE_DRIVERS[$this->storageDriver] ?? ucfirst($this->storageDriver);
    }

    /** Whether the config allows image uploads */
    public bool $allowsImages {
        get => !empty(array_filter($this->allowedMimeTypes, fn(string $m) => str_starts_with($m, 'image/')));
    }

    /** Whether the config allows video uploads */
    public bool $allowsVideos {
        get => !empty(array_filter($this->allowedMimeTypes, fn(string $m) => str_starts_with($m, 'video/')));
    }

    /** Resolved directory pattern string */
    public string $resolvedDirectoryPattern {
        get => self::DIRECTORY_PATTERNS[$this->directoryPattern] ?? self::DIRECTORY_PATTERNS['date'];
    }

    // ── Factory ─────────────────────────────────────────────────────────

    /**
     * Build config from settings rows.
     *
     * @param array<string, mixed> $settings Key-value pairs from `settings` table (group=media)
     */
    public static function fromSettings(array $settings): self
    {
        return new self(
            enabled: (bool) ($settings['enabled'] ?? true),
            storageDriver: (string) ($settings['storage_driver'] ?? $_ENV['MEDIA_STORAGE_DRIVER'] ?? 'local'),
            maxFileSize: (int) ($settings['max_file_size'] ?? 10_485_760),
            allowedMimeTypes: is_string($settings['allowed_mime_types'] ?? null)
                ? (json_decode($settings['allowed_mime_types'], true) ?? self::DEFAULT_ALLOWED_MIMES)
                : ($settings['allowed_mime_types'] ?? self::DEFAULT_ALLOWED_MIMES),
            deniedExtensions: is_string($settings['denied_extensions'] ?? null)
                ? (json_decode($settings['denied_extensions'], true) ?? self::DENIED_EXTENSIONS)
                : ($settings['denied_extensions'] ?? self::DENIED_EXTENSIONS),
            directoryPattern: (string) ($settings['directory_pattern'] ?? 'date'),
            uploadPath: (string) ($settings['upload_path'] ?? 'uploads'),
            imageQuality: (int) ($settings['image_quality'] ?? 85),
            generateThumbnails: (bool) ($settings['generate_thumbnails'] ?? true),
            preserveOriginalName: (bool) ($settings['preserve_original_name'] ?? false),
            imageStyles: is_string($settings['image_styles'] ?? null)
                ? (json_decode($settings['image_styles'], true) ?? [])
                : ($settings['image_styles'] ?? []),
            // Cloud storage
            s3Bucket: (string) ($settings['s3_bucket'] ?? $_ENV['MEDIA_S3_BUCKET'] ?? ''),
            s3Region: (string) ($settings['s3_region'] ?? $_ENV['MEDIA_S3_REGION'] ?? 'us-east-1'),
            s3Key: (string) ($settings['s3_key'] ?? $_ENV['MEDIA_S3_KEY'] ?? ''),
            s3Secret: (string) ($settings['s3_secret'] ?? $_ENV['MEDIA_S3_SECRET'] ?? ''),
            s3Endpoint: (string) ($settings['s3_endpoint'] ?? $_ENV['MEDIA_S3_ENDPOINT'] ?? ''),
            s3Prefix: (string) ($settings['s3_prefix'] ?? ''),
            gcsBucket: (string) ($settings['gcs_bucket'] ?? $_ENV['MEDIA_GCS_BUCKET'] ?? ''),
            gcsKeyFile: (string) ($settings['gcs_key_file'] ?? $_ENV['MEDIA_GCS_KEY_FILE'] ?? ''),
            gcsProjectId: (string) ($settings['gcs_project_id'] ?? $_ENV['MEDIA_GCS_PROJECT_ID'] ?? ''),
            gcsPrefix: (string) ($settings['gcs_prefix'] ?? ''),
            azureConnectionString: (string) ($settings['azure_connection_string'] ?? $_ENV['MEDIA_AZURE_CONNECTION'] ?? ''),
            azureContainer: (string) ($settings['azure_container'] ?? $_ENV['MEDIA_AZURE_CONTAINER'] ?? ''),
            azurePrefix: (string) ($settings['azure_prefix'] ?? ''),
        );
    }

    /**
     * Load from the database settings table.
     */
    public static function fromDatabase(PDO $pdo): self
    {
        try {
            $stmt = $pdo->prepare("SELECT `key`, `value` FROM settings WHERE `group` = 'media'");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return self::fromSettings($rows);
        } catch (\Throwable) {
            return new self();
        }
    }

    /**
     * Serialize to settings table format.
     *
     * @return array<string, string>
     */
    public function toSettings(): array
    {
        return [
            'enabled'                => $this->enabled ? '1' : '0',
            'storage_driver'         => $this->storageDriver,
            'max_file_size'          => (string) $this->maxFileSize,
            'allowed_mime_types'     => json_encode($this->allowedMimeTypes),
            'denied_extensions'      => json_encode($this->deniedExtensions),
            'directory_pattern'      => $this->directoryPattern,
            'upload_path'            => $this->uploadPath,
            'image_quality'          => (string) $this->imageQuality,
            'generate_thumbnails'    => $this->generateThumbnails ? '1' : '0',
            'preserve_original_name' => $this->preserveOriginalName ? '1' : '0',
            'image_styles'           => json_encode($this->imageStyles),
            's3_bucket'              => $this->s3Bucket,
            's3_region'              => $this->s3Region,
            's3_key'                 => $this->s3Key,
            's3_secret'              => $this->s3Secret,
            's3_endpoint'            => $this->s3Endpoint,
            's3_prefix'              => $this->s3Prefix,
            'gcs_bucket'             => $this->gcsBucket,
            'gcs_key_file'           => $this->gcsKeyFile,
            'gcs_project_id'         => $this->gcsProjectId,
            'gcs_prefix'             => $this->gcsPrefix,
            'azure_connection_string' => $this->azureConnectionString,
            'azure_container'        => $this->azureContainer,
            'azure_prefix'           => $this->azurePrefix,
        ];
    }

    /**
     * Persist settings to the database.
     */
    public function save(PDO $pdo): void
    {
        $settings = $this->toSettings();

        $stmt = $pdo->prepare(
            "INSERT INTO settings (`group`, `key`, `value`) VALUES ('media', :key, :value)
             ON DUPLICATE KEY UPDATE `value` = :value2"
        );

        foreach ($settings as $key => $value) {
            $stmt->execute(['key' => $key, 'value' => $value, 'value2' => $value]);
        }
    }

    /**
     * Resolve the upload directory for the current date.
     */
    public function resolveUploadDirectory(): string
    {
        $base = rtrim($this->uploadPath, '/');
        $pattern = $this->resolvedDirectoryPattern;

        if ($pattern === '') {
            return $base;
        }

        $now = new \DateTimeImmutable();

        $dir = strtr($pattern, [
            '{year}'  => $now->format('Y'),
            '{month}' => $now->format('m'),
            '{day}'   => $now->format('d'),
        ]);

        return $base . '/' . $dir;
    }

    /**
     * Get the storage driver config array for FilesServiceProvider.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDisksConfig(string $basePath): array
    {
        return match ($this->storageDriver) {
            's3' => [
                'media' => [
                    'driver'   => 's3',
                    'bucket'   => $this->s3Bucket,
                    'region'   => $this->s3Region,
                    'key'      => $this->s3Key,
                    'secret'   => $this->s3Secret,
                    'endpoint' => $this->s3Endpoint ?: null,
                    'prefix'   => $this->s3Prefix,
                ],
            ],
            'gcs' => [
                'media' => [
                    'driver'     => 'gcs',
                    'bucket'     => $this->gcsBucket,
                    'key_file'   => $this->gcsKeyFile,
                    'project_id' => $this->gcsProjectId,
                    'prefix'     => $this->gcsPrefix,
                ],
            ],
            'azure' => [
                'media' => [
                    'driver'            => 'azure',
                    'connection_string' => $this->azureConnectionString,
                    'container'         => $this->azureContainer,
                    'prefix'            => $this->azurePrefix,
                ],
            ],
            default => [
                'media' => [
                    'driver'    => 'local',
                    'base_path' => $basePath . '/public/' . $this->uploadPath,
                    'base_url'  => '/' . $this->uploadPath,
                ],
            ],
        };
    }
}
