<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Auth\SessionManager;
use App\Modules\Core\Services\MediaService;
use App\Cms\Security\PermissionService;
use App\Modules\Core\Services\MenuService;
use MonkeysLegion\Template\MLView;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\RedirectResponse;

/**
 * MediaController - Media library management API
 *
 * Endpoints:
 * - GET    /admin/media                    - List media with filters
 * - GET    /admin/media/{id}               - Get single media item
 * - POST   /admin/media/upload             - Upload single file
 * - POST   /admin/media/upload-url         - Upload from URL
 * - POST   /admin/media/chunked/init       - Initialize chunked upload
 * - POST   /admin/media/chunked/upload     - Upload chunk
 * - POST   /admin/media/chunked/complete   - Complete chunked upload
 * - DELETE /admin/media/chunked/{id}       - Abort chunked upload
 * - PUT    /admin/media/{id}               - Update media metadata
 * - DELETE /admin/media/{id}               - Delete media
 * - POST   /admin/media/{id}/variants      - Generate variants
 * - POST   /admin/media/{id}/crop          - Crop image
 * - POST   /admin/media/{id}/rotate        - Rotate image
 * - POST   /admin/media/{id}/copy          - Copy media
 * - POST   /admin/media/{id}/move          - Move to folder
 * - GET    /admin/media/folders            - List folders
 * - GET    /admin/media/stats              - Usage statistics
 */
#[Route('/admin/media', name: 'admin.media', middleware: ['admin'])]
final class MediaController extends BaseAdminController
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly PermissionService $permissions,
        MLView $view,
        MenuService $menuService,
        SessionManager $session,
    ) {
        parent::__construct($view, $menuService, $session);
    }


    private function denyAccess(ServerRequestInterface $request, string $message = 'You do not have permission to view media'): ResponseInterface
    {
        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return json([
                'success' => false,
                'error' => $message,
            ], 403);
        }

        return $this->render('errors.403', [
            'message' => $message,
            'title' => 'Access Denied',
        ])->withStatus(403);
    }

    // =========================================================================
    // List & Get
    // =========================================================================

    /**
     * List media with filters and pagination
     */
    #[Route('GET', '', name: 'admin.media.index')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('view_media')) {
            return $this->denyAccess($request, 'You do not have permission to view media');
        }

        $params = $request->getQueryParams();
        
        $filters = [];
        if (isset($params['type'])) {
            $filters['media_type'] = $params['type'];
        }
        if (isset($params['folder'])) {
            $filters['folder'] = $params['folder'];
        }

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 24)));

        // Fetch media data
        $result = $this->mediaService->list($filters, $page, $perPage);

        // Content Negotiation: Render View if HTML requested and not strictly JSON
        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'text/html') && !str_contains($accept, 'application/json')) {
            return $this->render('admin.media.index', [
                'title' => 'Media Library',
                'media' => $result['data'],
                'pagination' => [
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['total_pages'],
                ],
                'permissions' => [
                    'can_upload' => $this->permissions->can('create_media'),
                    'can_delete' => $this->permissions->can('delete_media'),
                    'can_edit' => $this->permissions->can('edit_media'),
                ],
            ]);
        }

        $params = $request->getQueryParams();

        $filters = [];
        if (isset($params['type'])) {
            $filters['media_type'] = $params['type'];
        }
        if (isset($params['folder'])) {
            $filters['folder'] = $params['folder'];
        }
        if (isset($params['author_id'])) {
            $filters['author_id'] = (int) $params['author_id'];
        }

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 24)));

        // Search
        if (!empty($params['q'])) {
            $items = $this->mediaService->search($params['q'], $perPage);
            return json([
                'success' => true,
                'data' => array_map(fn($m) => $m->toApiArray(), $items),
                'meta' => [
                    'query' => $params['q'],
                    'count' => count($items),
                ],
            ]);
        }

        $result = $this->mediaService->list($filters, $page, $perPage);

        return json([
            'success' => true,
            'data' => array_map(fn($m) => $m->toApiArray(), $result['data']),
            'meta' => [
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'total_pages' => $result['total_pages'],
            ],
            'permissions' => [
                'can_upload' => $this->permissions->can('create_media'),
                'can_delete' => $this->permissions->can('delete_media'),
            ],
        ]);
    }

    /**
     * Get single media item
     */
    #[Route('GET', '/{id:\d+}', name: 'admin.media.show')]
    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('view_media')) {
            return $this->denyAccess($request, 'You do not have permission to view media');
        }

        $media = $this->mediaService->find($id);

        if ($media === null) {
            $accept = $request->getHeaderLine('Accept');
            if (str_contains($accept, 'text/html') && !str_contains($accept, 'application/json')) {
                return $this->render('errors.404', [
                    'message' => 'Media not found',
                    'title' => 'Page Not Found',
                ])->withStatus(404);
            }

            return json([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }

        // Content Negotiation: Render View if HTML requested and not strictly JSON
        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'text/html') && !str_contains($accept, 'application/json')) {
            return $this->render('admin.media.show', [
                'title' => $media->title,
                'media' => $media,
                'permissions' => [
                    'can_edit' => $this->permissions->can('edit_media'),
                    'can_delete' => $this->permissions->can('delete_media'),
                ],
            ]);
        }

        return json([
            'success' => true,
            'data' => $media->toApiArray(),
            'permissions' => [
                'can_edit' => $this->permissions->can('edit_media'),
                'can_delete' => $this->permissions->can('delete_media'),
            ],
        ]);
    }

    /**
     * Get media by UUID
     */
    #[Route('GET', '/uuid/{uuid}', name: 'admin.media.show_by_uuid')]
    public function showByUuid(ServerRequestInterface $request, string $uuid): ResponseInterface
    {
        if (!$this->permissions->can('view_media')) {
            return $this->denyAccess($request, 'You do not have permission to view media');
        }

        $media = $this->mediaService->findByUuid($uuid);

        if ($media === null) {
            return json([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }

        return json([
            'success' => true,
            'data' => $media->toApiArray(),
        ]);
    }

    // =========================================================================
    // Single Upload
    // =========================================================================

    /**
     * Show upload form
     */
    #[Route('GET', '/upload', name: 'admin.media.create')]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->denyAccess($request, 'You do not have permission to upload media');
        }

        return $this->render('admin.media.upload_new', [
            'title' => 'Upload Media',
        ]);
    }

    /**
     * Upload a single file
     */
    #[Route('POST', '/upload', name: 'admin.media.upload')]
    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->denyAccess($request, 'You do not have permission to upload media');
        }

        $files = $request->getUploadedFiles();
        $params = $request->getParsedBody() ?? [];

        if (empty($files['file'])) {
            return json([
                'success' => false,
                'error' => 'No file uploaded',
            ], 400);
        }

        $file = $files['file'];

        try {
            $currentUser = $this->permissions->getCurrentUser();

            $media = $this->mediaService->upload($file, [
                'title' => $params['title'] ?? null,
                'alt' => $params['alt'] ?? null,
                'description' => $params['description'] ?? null,
                'folder' => $params['folder'] ?? null,
                'author_id' => $currentUser?->id,
                'generate_variants' => ($params['generate_variants'] ?? 'true') !== 'false',
            ]);

            return json([
                'success' => true,
                'data' => $media->toApiArray(),
                'message' => 'File uploaded successfully',
            ], 201);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Upload multiple files
     */
    #[Route('POST', '/upload-multiple', name: 'admin.media.upload_multiple')]
    public function uploadMultiple(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->denyAccess($request, 'You do not have permission to upload media');
        }

        $files = $request->getUploadedFiles();
        $params = $request->getParsedBody() ?? [];

        if (empty($files['files']) || !is_array($files['files'])) {
            return json([
                'success' => false,
                'error' => 'No files uploaded',
            ], 400);
        }

        $currentUser = $this->permissions->getCurrentUser();
        $results = [
            'uploaded' => [],
            'failed' => [],
        ];

        foreach ($files['files'] as $index => $file) {
            try {
                $media = $this->mediaService->upload($file, [
                    'folder' => $params['folder'] ?? null,
                    'author_id' => $currentUser?->id,
                    'generate_variants' => ($params['generate_variants'] ?? 'true') !== 'false',
                ]);

                $results['uploaded'][] = $media->toApiArray();
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'index' => $index,
                    'filename' => $file->getClientFilename(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return json([
            'success' => true,
            'data' => $results,
            'message' => sprintf(
                '%d uploaded, %d failed',
                count($results['uploaded']),
                count($results['failed'])
            ),
        ], count($results['uploaded']) > 0 ? 201 : 400);
    }

    /**
     * Upload from URL
     */
    #[Route('POST', '/upload-url', name: 'admin.media.upload_url')]
    public function uploadUrl(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->denyAccess($request, 'You do not have permission to upload media');
        }

        $data = json_decode((string) $request->getBody(), true);

        if (empty($data['url'])) {
            return json([
                'success' => false,
                'error' => 'URL is required',
            ], 400);
        }

        try {
            $currentUser = $this->permissions->getCurrentUser();

            $media = $this->mediaService->uploadFromUrl($data['url'], [
                'title' => $data['title'] ?? null,
                'alt' => $data['alt'] ?? null,
                'description' => $data['description'] ?? null,
                'folder' => $data['folder'] ?? null,
                'author_id' => $currentUser?->id,
            ]);

            return json([
                'success' => true,
                'data' => $media->toApiArray(),
                'message' => 'File downloaded and uploaded successfully',
            ], 201);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    // =========================================================================
    // Chunked Upload
    // =========================================================================

    /**
     * Initialize chunked upload
     */
    #[Route('POST', '/chunked/init', name: 'admin.media.chunked_init')]
    public function chunkedInit(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->denyAccess($request, 'You do not have permission to upload media');
        }

        $data = json_decode((string) $request->getBody(), true);

        $required = ['filename', 'file_size', 'mime_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return json([
                    'success' => false,
                    'error' => "Field '{$field}' is required",
                ], 400);
            }
        }

        try {
            $session = $this->mediaService->initChunkedUpload(
                filename: $data['filename'],
                fileSize: (int) $data['file_size'],
                mimeType: $data['mime_type'],
                options: [
                    'folder' => $data['folder'] ?? null,
                    'title' => $data['title'] ?? null,
                ]
            );

            return json([
                'success' => true,
                'data' => $session,
                'message' => 'Chunked upload initialized',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Upload a chunk
     */
    #[Route('POST', '/chunked/upload', name: 'admin.media.chunked_upload')]
    public function chunkedUpload(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->denyAccess($request, 'You do not have permission to upload media');
        }

        $params = $request->getParsedBody() ?? [];

        $uploadId = $params['upload_id'] ?? null;
        $chunkNumber = isset($params['chunk_number']) ? (int) $params['chunk_number'] : null;

        if ($uploadId === null || $chunkNumber === null) {
            return json([
                'success' => false,
                'error' => 'upload_id and chunk_number are required',
            ], 400);
        }

        $files = $request->getUploadedFiles();
        if (empty($files['chunk'])) {
            return json([
                'success' => false,
                'error' => 'No chunk data uploaded',
            ], 400);
        }

        try {
            $chunkData = $files['chunk']->getStream()->getContents();

            $result = $this->mediaService->uploadChunk(
                uploadId: $uploadId,
                chunkNumber: $chunkNumber,
                chunkData: $chunkData
            );

            return json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Complete chunked upload
     */
    #[Route('POST', '/chunked/complete', name: 'admin.media.chunked_complete')]
    public function chunkedComplete(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->denyAccess($request, 'You do not have permission to upload media');
        }

        $data = json_decode((string) $request->getBody(), true);

        if (empty($data['upload_id'])) {
            return json([
                'success' => false,
                'error' => 'upload_id is required',
            ], 400);
        }

        try {
            $currentUser = $this->permissions->getCurrentUser();

            $media = $this->mediaService->completeChunkedUpload(
                uploadId: $data['upload_id'],
                options: [
                    'author_id' => $currentUser?->id,
                    'title' => $data['title'] ?? null,
                    'alt' => $data['alt'] ?? null,
                    'description' => $data['description'] ?? null,
                ]
            );

            return json([
                'success' => true,
                'data' => $media->toApiArray(),
                'message' => 'File uploaded successfully',
            ], 201);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Abort chunked upload
     */
    #[Route('DELETE', '/chunked/{uploadId}', name: 'admin.media.chunked_abort')]
    public function chunkedAbort(ServerRequestInterface $request, string $uploadId): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->denyAccess($request, 'You do not have permission to manage uploads');
        }

        try {
            $this->mediaService->abortChunkedUpload($uploadId);

            return json([
                'success' => true,
                'message' => 'Upload aborted',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    // =========================================================================
    // Update & Delete
    // =========================================================================

    /**
     * Update media metadata
     */
    #[Route('PUT', '/{id:\d+}', name: 'admin.media.update')]
    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('edit_media')) {
            return $this->denyAccess($request, 'You do not have permission to edit media');
        }

        $media = $this->mediaService->find($id);

        if ($media === null) {
            return json([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }

        $data = json_decode((string) $request->getBody(), true);

        try {
            $media = $this->mediaService->update($media, $data);

            return json([
                'success' => true,
                'data' => $media->toApiArray(),
                'message' => 'Media updated successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete media
     */
    #[Route('DELETE', '/{id:\d+}', name: 'admin.media.destroy')]
    public function destroy(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('delete_media')) {
            return $this->denyAccess($request, 'You do not have permission to delete media');
        }

        $media = $this->mediaService->find($id);

        if ($media === null) {
            return json([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }

        try {
            $this->mediaService->delete($media);

            // Check if HTML request
            $accept = $request->getHeaderLine('Accept');
            if (str_contains($accept, 'text/html') && !str_contains($accept, 'application/json')) {
                return new RedirectResponse('/admin/media');
            }

            return json([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk delete media
     */
    #[Route('POST', '/bulk-delete', name: 'admin.media.bulk_delete')]
    public function bulkDelete(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('delete_media')) {
            return $this->denyAccess($request, 'You do not have permission to delete media');
        }

        $data = json_decode((string) $request->getBody(), true);
        $ids = $data['ids'] ?? [];

        if (empty($ids)) {
            return json([
                'success' => false,
                'error' => 'No IDs provided',
            ], 400);
        }

        $deleted = 0;
        $failed = [];

        foreach ($ids as $id) {
            $media = $this->mediaService->find((int) $id);
            if ($media !== null) {
                try {
                    $this->mediaService->delete($media);
                    $deleted++;
                } catch (\Exception $e) {
                    $failed[] = ['id' => $id, 'error' => $e->getMessage()];
                }
            }
        }

        return json([
            'success' => true,
            'data' => [
                'deleted' => $deleted,
                'failed' => $failed,
            ],
            'message' => "{$deleted} items deleted",
        ]);
    }

    // =========================================================================
    // Image Processing
    // =========================================================================

    /**
     * Generate image variants
     */
    #[Route('POST', '/{id:\d+}/variants', name: 'admin.media.generate_variants')]
    public function generateVariants(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('edit_media')) {
            return $this->denyAccess($request, 'You do not have permission to edit media');
        }

        $media = $this->mediaService->find($id);

        if ($media === null) {
            return json([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }

        if (!$media->isImage()) {
            return json([
                'success' => false,
                'error' => 'Media is not an image',
            ], 400);
        }

        $data = json_decode((string) $request->getBody(), true);
        $variants = $data['variants'] ?? [];

        try {
            $this->mediaService->generateImageVariants($media, $variants);

            // Refresh media
            $media = $this->mediaService->find($id);

            return json([
                'success' => true,
                'data' => $media->toApiArray(),
                'message' => 'Variants generated successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Crop image
     */
    #[Route('POST', '/{id:\d+}/crop', name: 'admin.media.crop')]
    public function crop(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('edit_media')) {
            return $this->denyAccess($request, 'You do not have permission to edit media');
        }

        $media = $this->mediaService->find($id);

        if ($media === null) {
            return json([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }

        $data = json_decode((string) $request->getBody(), true);

        $required = ['x', 'y', 'width', 'height'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return json([
                    'success' => false,
                    'error' => "Field '{$field}' is required",
                ], 400);
            }
        }

        try {
            $createNew = ($data['create_new'] ?? false) === true;

            $media = $this->mediaService->crop(
                media: $media,
                x: (int) $data['x'],
                y: (int) $data['y'],
                width: (int) $data['width'],
                height: (int) $data['height'],
                createNew: $createNew
            );

            return json([
                'success' => true,
                'data' => $media->toApiArray(),
                'message' => $createNew ? 'Cropped copy created' : 'Image cropped successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Rotate image
     */
    #[Route('POST', '/{id:\d+}/rotate', name: 'admin.media.rotate')]
    public function rotate(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('edit_media')) {
            return $this->denyAccess($request, 'You do not have permission to edit media');
        }

        $media = $this->mediaService->find($id);

        if ($media === null) {
            return json([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }

        $data = json_decode((string) $request->getBody(), true);
        $degrees = (int) ($data['degrees'] ?? 90);

        try {
            $media = $this->mediaService->rotate($media, $degrees);

            return json([
                'success' => true,
                'data' => $media->toApiArray(),
                'message' => "Image rotated {$degrees} degrees",
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    // =========================================================================
    // Copy & Move
    // =========================================================================

    /**
     * Copy media
     */
    #[Route('POST', '/{id:\d+}/copy', name: 'admin.media.copy')]
    public function copy(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->denyAccess($request, 'You do not have permission to copy media');
        }

        $media = $this->mediaService->find($id);

        if ($media === null) {
            return json([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }

        $data = json_decode((string) $request->getBody(), true);

        try {
            $newMedia = $this->mediaService->copy($media, $data['folder'] ?? null);

            return json([
                'success' => true,
                'data' => $newMedia->toApiArray(),
                'message' => 'Media copied successfully',
            ], 201);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Move media to folder
     */
    #[Route('POST', '/{id:\d+}/move', name: 'move')]
    public function move(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('edit_media')) {
            return $this->denyAccess($request, 'You do not have permission to move media');
        }

        $media = $this->mediaService->find($id);

        if ($media === null) {
            return json([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }

        $data = json_decode((string) $request->getBody(), true);

        if (empty($data['folder'])) {
            return json([
                'success' => false,
                'error' => 'Folder is required',
            ], 400);
        }

        try {
            $media = $this->mediaService->move($media, $data['folder']);

            return json([
                'success' => true,
                'data' => $media->toApiArray(),
                'message' => 'Media moved successfully',
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    // =========================================================================
    // Utilities
    // =========================================================================

    /**
     * Get folders
     */
    #[Route('GET', '/folders', name: 'admin.media.folders')]
    public function folders(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('view_media')) {
            return $this->denyAccess($request, 'You do not have permission to view media');
        }

        $folders = $this->mediaService->getFolders();

        return json([
            'success' => true,
            'data' => $folders,
        ]);
    }

    /**
     * Get usage statistics
     */
    #[Route('GET', '/stats', name: 'admin.media.stats')]
    public function stats(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('view_media')) {
            return $this->denyAccess($request, 'You do not have permission to view media');
        }

        $stats = $this->mediaService->getUsageStats();

        return json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get URL for media
     */
    #[Route('GET', '/{id:\d+}/url', name: 'admin.media.url')]
    public function url(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $media = $this->mediaService->find($id);

        if ($media === null) {
            return json([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }

        $params = $request->getQueryParams();
        $variant = $params['variant'] ?? null;

        $url = $this->mediaService->getUrl($media, $variant);

        return json([
            'success' => true,
            'data' => ['url' => $url],
        ]);
    }

    /**
     * Get temporary URL (for private files)
     */
    #[Route('GET', '/{id:\d+}/temporary-url', name: 'admin.media.temporary_url')]
    public function temporaryUrl(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $media = $this->mediaService->find($id);

        if ($media === null) {
            return json([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }

        $params = $request->getQueryParams();
        $expires = (int) ($params['expires'] ?? 3600);

        $url = $this->mediaService->getTemporaryUrl($media, $expires);

        return json([
            'success' => true,
            'data' => [
                'url' => $url,
                'expires_in' => $expires,
            ],
        ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================
}
