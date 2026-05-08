<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Log\ActivityLogger;
use App\Cms\Media\MediaConfig;
use App\Cms\Media\MediaModule;
use App\Cms\Form\FormBuilder;
use App\Cms\Form\FormRenderer;
use MonkeysLegion\Files\Upload\UploadedFile;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MediaController — Admin UI for the media library.
 *
 * Routes:
 *   GET    /admin/media              — Library grid
 *   GET    /admin/media/upload       — Upload page
 *   POST   /admin/media/upload       — Handle upload(s)
 *   GET    /admin/media/settings     — Module settings
 *   POST   /admin/media/settings     — Save settings
 *   GET    /admin/media/{id}         — Detail/edit
 *   POST   /admin/media/{id}         — Update metadata
 *   POST   /admin/media/{id}/delete  — Delete
 */
#[RoutePrefix('/admin/media')]
final class MediaController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly MediaModule $media,
        private readonly PDO $pdo,
        private readonly FormRenderer $formRenderer,
        private readonly SessionManager $session,
        private readonly ActivityLogger $activity,
    ) {}

    // ── Library Grid ────────────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::media.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $type = $params['type'] ?? null;
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = 24;
        $offset = ($page - 1) * $perPage;

        $items = $this->media->findAll(
            type: $type,
            limit: $perPage,
            offset: $offset,
        );

        $total = $this->media->getConfig()->enabled
            ? $this->media->findAll(type: $type, limit: 999999)
            : [];

        return Response::html($this->renderer->render('admin::media.index', [
            'title'       => 'Media Library',
            'items'       => $items,
            'type'        => $type,
            'page'        => $page,
            'perPage'     => $perPage,
            'totalItems'  => count($total),
            'totalPages'  => (int) ceil(count($total) / $perPage),
            'diskUsage'   => $this->media->getDiskUsage(),
        ]));
    }

    // ── Upload Page ─────────────────────────────────────────────────────

    #[Route('GET', '/upload', name: 'admin::media.upload')]
    public function uploadForm(ServerRequestInterface $request): Response
    {
        $config = $this->media->getConfig();

        return Response::html($this->renderer->render('admin::media.upload', [
            'title'       => 'Upload Media',
            'config'      => $config,
        ]));
    }

    #[Route('POST', '/upload', name: 'admin::media.upload.store')]
    public function upload(ServerRequestInterface $request): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $userId = $request->getAttribute('cms_user')['id'] ?? null;
        $uploaded = [];
        $errors = [];

        // Support single or multiple files — flatten nested arrays
        $raw = $uploadedFiles['files'] ?? $uploadedFiles['file'] ?? null;
        if ($raw === null) {
            $files = [];
        } elseif (is_array($raw)) {
            // Could be a flat array of UploadedFile objects or nested arrays
            $files = [];
            array_walk_recursive($raw, function ($item) use (&$files) {
                if (is_object($item)) {
                    $files[] = $item;
                }
            });
        } else {
            $files = [$raw];
        }

        foreach ($files as $psrFile) {
            if (!is_object($psrFile) || $psrFile->getError() !== UPLOAD_ERR_OK) {
                continue;
            }

            try {
                $file = new UploadedFile(
                    tmpPath: $psrFile->getStream()->getMetadata('uri') ?? '',
                    clientName: $psrFile->getClientFilename() ?? 'unknown',
                    mimeType: $psrFile->getClientMediaType() ?? 'application/octet-stream',
                    size: $psrFile->getSize() ?? 0,
                );

                $entity = $this->media->upload($file, $userId);
                $uploaded[] = $entity;
            } catch (\Throwable $e) {
                $errors[] = ($psrFile->getClientFilename() ?? 'file') . ': ' . $e->getMessage();
            }
        }

        // Handle $_FILES fallback for non-PSR-7 uploads
        if (empty($uploaded) && !empty($_FILES)) {
            foreach ($_FILES as $key => $fileInfo) {
                if (is_array($fileInfo['tmp_name'])) {
                    // Multiple files
                    for ($i = 0; $i < count($fileInfo['tmp_name']); $i++) {
                        if ($fileInfo['error'][$i] !== UPLOAD_ERR_OK) continue;
                        try {
                            $file = new UploadedFile(
                                tmpPath: $fileInfo['tmp_name'][$i],
                                clientName: $fileInfo['name'][$i],
                                mimeType: $fileInfo['type'][$i],
                                size: $fileInfo['size'][$i],
                            );
                            $entity = $this->media->upload($file, $userId);
                            $uploaded[] = $entity;
                        } catch (\Throwable $e) {
                            $errors[] = $fileInfo['name'][$i] . ': ' . $e->getMessage();
                        }
                    }
                } else {
                    // Single file
                    if ($fileInfo['error'] !== UPLOAD_ERR_OK) continue;
                    try {
                        $file = UploadedFile::fromGlobal($fileInfo);
                        $entity = $this->media->upload($file, $userId);
                        $uploaded[] = $entity;
                    } catch (\Throwable $e) {
                        $errors[] = $fileInfo['name'] . ': ' . $e->getMessage();
                    }
                }
            }
        }

        $count = count($uploaded);
        $query = $count > 0 ? "uploaded={$count}" : 'error=' . urlencode(implode('; ', $errors));

        if ($count > 0) {
            $this->activity->setContext($request);
            $this->activity->log('uploaded', 'media', null, "{$count} file(s)", [
                'filenames' => array_map(fn($e) => $e->original_name, $uploaded),
            ]);
        }

        return Response::redirect("/admin/media?{$query}");
    }

    // ── Detail / Edit ───────────────────────────────────────────────────

    #[Route('GET', '/{id:\d+}', name: 'admin::media.detail')]
    public function detail(ServerRequestInterface $request, string $id): Response
    {
        $media = $this->media->findOrFail((int) $id);
        $usage = $this->media->getUsage((int) $id);

        // Build metadata edit form
        $editForm = FormBuilder::create('/admin/media/' . $id, 'POST')
            ->id('media-edit-form')
            ->text('title', 'Title')
                ->value($media->title ?? '')
            ->text('alt', 'Alt Text')
                ->value($media->alt ?? '')
                ->help('Describes the image for accessibility and SEO')
            ->textarea('description', 'Description')
                ->value($media->description ?? '')
                ->attrs(['rows' => '3'])
            ->submit('Save Changes', 'save')
            ->build($this->session);

        return Response::html($this->renderer->render('admin::media.detail', [
            'title'    => $media->title ?? $media->original_name,
            'media'    => $media,
            'usage'    => $usage,
            'styles'   => $this->media->getStyleRegistry()->getDefinitions(),
            'config'   => $this->media->getConfig(),
            'editForm' => $this->formRenderer->render($editForm),
        ]));
    }

    #[Route('POST', '/{id:\d+}', name: 'admin::media.update')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        $body = $request->getParsedBody();

        $this->media->update((int) $id, [
            'alt'         => $body['alt'] ?? null,
            'title'       => $body['title'] ?? null,
            'description' => $body['description'] ?? null,
        ]);

        $this->activity->setContext($request);
        $this->activity->log('updated', 'media', $id, $body['title'] ?? null);

        return Response::redirect("/admin/media/{$id}?saved=1");
    }

    #[Route('POST', '/{id:\d+}/delete', name: 'admin::media.delete')]
    public function destroy(ServerRequestInterface $request, string $id): Response
    {
        $this->activity->setContext($request);
        $this->activity->log('deleted', 'media', $id, null);

        $this->media->delete((int) $id);

        return Response::redirect('/admin/media?deleted=1');
    }

    // ── Bulk Operations ─────────────────────────────────────────────────

    #[Route('POST', '/bulk', name: 'admin::media.bulk')]
    public function bulk(ServerRequestInterface $request): Response
    {
        $raw = (string) $request->getBody();
        $body = json_decode($raw, true) ?? $request->getParsedBody() ?? [];
        $action = $body['action'] ?? '';
        $ids = array_map('intval', $body['ids'] ?? []);

        if (empty($ids)) {
            return Response::json(['error' => 'No items selected'], 422);
        }

        $affected = 0;

        match ($action) {
            'delete' => (function () use ($ids, &$affected) {
                // Delete files from disk/storage first
                foreach ($ids as $id) {
                    try {
                        $this->media->delete($id);
                        $affected++;
                    } catch (\Throwable) {
                        // Skip individual failures
                    }
                }
            })(),
            default => null,
        };

        $this->activity->setContext($request);
        $this->activity->log('bulk_' . $action, 'media', null, $affected . ' file(s)', [
            'ids' => $ids,
        ]);

        return Response::json([
            'message' => "{$affected} file(s) {$action}d successfully.",
            'affected' => $affected,
        ]);
    }

    // ── Settings ────────────────────────────────────────────────────────

    #[Route('GET', '/settings', name: 'admin::media.settings')]
    public function settings(ServerRequestInterface $request): Response
    {
        $config = $this->media->getConfig();
        $diskUsage = $this->media->getDiskUsage();

        // ── Build form with FormBuilder ────────────────────────────────
        $form = FormBuilder::create('/admin/media/settings', 'POST')
            ->id('media-settings-form')
            ->layout('settings-grid')

            // ── General Settings ─────────────────────────────────────
            ->group('general', 'General Settings', 'settings')
            ->toggle('enabled', 'Enable Media Module')
                ->value($config->enabled)
                ->help('Enable or disable the media module globally')
                ->inGroup('general')
            ->text('upload_path', 'Upload Path')
                ->value($config->uploadPath)
                ->placeholder('uploads')
                ->help('Base path for local file storage')
                ->inGroup('general')
            ->select('directory_pattern', 'Directory Pattern', array_map(
                fn($p, $k) => ucfirst($k) . ($p ? " ({$p})" : ' (flat)'),
                MediaConfig::DIRECTORY_PATTERNS,
                array_keys(MediaConfig::DIRECTORY_PATTERNS),
            ))
                ->value($config->directoryPattern)
                ->inGroup('general')
            ->number('max_file_size', 'Max File Size (bytes)')
                ->value($config->maxFileSize)
                ->help('Maximum upload size in bytes (default: 10 MB)')
                ->inGroup('general')
            ->range('image_quality', 'Image Quality')
                ->value($config->imageQuality)
                ->attrs(['min' => '1', 'max' => '100', 'step' => '1'])
                ->help($config->imageQuality . '%')
                ->inGroup('general')
            ->toggle('generate_thumbnails', 'Generate Thumbnails')
                ->value($config->generateThumbnails)
                ->inGroup('general')
            ->toggle('preserve_original_name', 'Preserve Original Filename')
                ->value($config->preserveOriginalName)
                ->inGroup('general')

            // ── Storage Driver ───────────────────────────────────────
            ->group('storage', 'Storage Driver', 'hard-drive')
            ->select('storage_driver', 'Driver', MediaConfig::STORAGE_DRIVERS)
                ->value($config->storageDriver)
                ->attrs(['data-driver-toggle' => 'driver'])
                ->inGroup('storage')

            // S3 fields (conditional)
            ->text('s3_bucket', 'S3 Bucket')
                ->value($config->s3Bucket)
                ->showWhen('storage_driver', 's3')
                ->inGroup('storage')
            ->text('s3_region', 'S3 Region')
                ->value($config->s3Region)
                ->placeholder('us-east-1')
                ->showWhen('storage_driver', 's3')
                ->inGroup('storage')
            ->text('s3_key', 'Access Key ID')
                ->value($config->s3Key)
                ->showWhen('storage_driver', 's3')
                ->inGroup('storage')
            ->password('s3_secret', 'Secret Access Key')
                ->value($config->s3Secret)
                ->showWhen('storage_driver', 's3')
                ->inGroup('storage')
            ->text('s3_endpoint', 'Custom Endpoint')
                ->value($config->s3Endpoint)
                ->placeholder('Leave empty for AWS default')
                ->showWhen('storage_driver', 's3')
                ->inGroup('storage')
            ->text('s3_prefix', 'S3 Prefix')
                ->value($config->s3Prefix)
                ->showWhen('storage_driver', 's3')
                ->inGroup('storage')

            // GCS fields (conditional)
            ->text('gcs_bucket', 'GCS Bucket')
                ->value($config->gcsBucket)
                ->showWhen('storage_driver', 'gcs')
                ->inGroup('storage')
            ->text('gcs_project_id', 'Project ID')
                ->value($config->gcsProjectId)
                ->showWhen('storage_driver', 'gcs')
                ->inGroup('storage')
            ->text('gcs_key_file', 'Key File Path')
                ->value($config->gcsKeyFile)
                ->showWhen('storage_driver', 'gcs')
                ->inGroup('storage')
            ->text('gcs_prefix', 'GCS Prefix')
                ->value($config->gcsPrefix)
                ->showWhen('storage_driver', 'gcs')
                ->inGroup('storage')

            // Azure fields (conditional)
            ->text('azure_connection_string', 'Connection String')
                ->value($config->azureConnectionString)
                ->showWhen('storage_driver', 'azure')
                ->inGroup('storage')
            ->text('azure_container', 'Container')
                ->value($config->azureContainer)
                ->showWhen('storage_driver', 'azure')
                ->inGroup('storage')
            ->text('azure_prefix', 'Azure Prefix')
                ->value($config->azurePrefix)
                ->showWhen('storage_driver', 'azure')
                ->inGroup('storage')

            // ── MIME Types ───────────────────────────────────────────
            ->group('mime', 'Allowed File Types', 'file-check')
            ->code('allowed_mime_types', 'Allowed MIME Types')
                ->value(implode("\n", $config->allowedMimeTypes))
                ->attrs(['rows' => '8'])
                ->help('One MIME type per line')
                ->inGroup('mime')
            ->text('denied_extensions', 'Denied Extensions')
                ->value(implode(', ', $config->deniedExtensions))
                ->help('Comma-separated list of blocked file extensions')
                ->inGroup('mime')

            ->submit('Save Settings', 'save')
            ->cancel('/admin/media')
            ->build($this->session);

        $formHtml = $this->formRenderer->render($form);

        return Response::html($this->renderer->render('admin::media.settings', [
            'title'       => 'Media Settings',
            'config'      => $config,
            'diskUsage'   => $diskUsage,
            'styles'      => $this->media->getStyleRegistry()->getDefinitions(),
            'formHtml'    => $formHtml,
        ]));
    }

    #[Route('POST', '/settings', name: 'admin::media.settings.save')]
    public function saveSettings(ServerRequestInterface $request): Response
    {
        $body = $request->getParsedBody();

        // Build new config from form data
        $config = new MediaConfig(
            enabled: (bool) ($body['enabled'] ?? false),
            storageDriver: (string) ($body['storage_driver'] ?? 'local'),
            maxFileSize: (int) ($body['max_file_size'] ?? 10_485_760),
            allowedMimeTypes: array_filter(
                array_map('trim', explode("\n", $body['allowed_mime_types'] ?? '')),
            ) ?: MediaConfig::DEFAULT_ALLOWED_MIMES,
            deniedExtensions: array_filter(
                array_map('trim', explode(',', $body['denied_extensions'] ?? '')),
            ) ?: MediaConfig::DENIED_EXTENSIONS,
            directoryPattern: (string) ($body['directory_pattern'] ?? 'date'),
            uploadPath: (string) ($body['upload_path'] ?? 'uploads'),
            imageQuality: max(1, min(100, (int) ($body['image_quality'] ?? 85))),
            generateThumbnails: (bool) ($body['generate_thumbnails'] ?? false),
            preserveOriginalName: (bool) ($body['preserve_original_name'] ?? false),
            imageStyles: json_decode($body['image_styles'] ?? '{}', true) ?: [],
            // Cloud: S3
            s3Bucket: (string) ($body['s3_bucket'] ?? ''),
            s3Region: (string) ($body['s3_region'] ?? 'us-east-1'),
            s3Key: (string) ($body['s3_key'] ?? ''),
            s3Secret: (string) ($body['s3_secret'] ?? ''),
            s3Endpoint: (string) ($body['s3_endpoint'] ?? ''),
            s3Prefix: (string) ($body['s3_prefix'] ?? ''),
            // Cloud: GCS
            gcsBucket: (string) ($body['gcs_bucket'] ?? ''),
            gcsKeyFile: (string) ($body['gcs_key_file'] ?? ''),
            gcsProjectId: (string) ($body['gcs_project_id'] ?? ''),
            gcsPrefix: (string) ($body['gcs_prefix'] ?? ''),
            // Cloud: Azure
            azureConnectionString: (string) ($body['azure_connection_string'] ?? ''),
            azureContainer: (string) ($body['azure_container'] ?? ''),
            azurePrefix: (string) ($body['azure_prefix'] ?? ''),
        );

        $config->save($this->pdo);

        return Response::redirect('/admin/media/settings?saved=1');
    }
}
