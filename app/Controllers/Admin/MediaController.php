<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Services\MediaService;
use App\Cms\Security\PermissionService;
use Laminas\Diactoros\Response\JsonResponse;
use MonkeysLegion\Router\Attribute\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
final class MediaController
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly PermissionService $permissions,
    ) {}
    
    // =========================================================================
    // List & Get
    // =========================================================================
    
    /**
     * List media with filters and pagination
     */
    #[Route('GET', '', name: 'index')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('view_media')) {
            return $this->forbidden('You do not have permission to view media');
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
            return new JsonResponse([
                'success' => true,
                'data' => array_map(fn($m) => $m->toArray(), $items),
                'meta' => [
                    'query' => $params['q'],
                    'count' => count($items),
                ],
            ]);
        }
        
        $result = $this->mediaService->list($filters, $page, $perPage);
        
        return new JsonResponse([
            'success' => true,
            'data' => array_map(fn($m) => $m->toArray(), $result['data']),
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
    #[Route('GET', '/{id:\d+}', name: 'show')]
    public function show(int $id): ResponseInterface
    {
        if (!$this->permissions->can('view_media')) {
            return $this->forbidden('You do not have permission to view media');
        }
        
        $media = $this->mediaService->find($id);
        
        if ($media === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }
        
        return new JsonResponse([
            'success' => true,
            'data' => $media->toArray(),
            'permissions' => [
                'can_edit' => $this->permissions->can('edit_media'),
                'can_delete' => $this->permissions->can('delete_media'),
            ],
        ]);
    }
    
    /**
     * Get media by UUID
     */
    #[Route('GET', '/uuid/{uuid}', name: 'showByUuid')]
    public function showByUuid(string $uuid): ResponseInterface
    {
        if (!$this->permissions->can('view_media')) {
            return $this->forbidden('You do not have permission to view media');
        }
        
        $media = $this->mediaService->findByUuid($uuid);
        
        if ($media === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }
        
        return new JsonResponse([
            'success' => true,
            'data' => $media->toArray(),
        ]);
    }
    
    // =========================================================================
    // Single Upload
    // =========================================================================
    
    /**
     * Upload a single file
     */
    #[Route('POST', '/upload', name: 'upload')]
    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->forbidden('You do not have permission to upload media');
        }
        
        $files = $request->getUploadedFiles();
        $params = $request->getParsedBody() ?? [];
        
        if (empty($files['file'])) {
            return new JsonResponse([
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
            
            return new JsonResponse([
                'success' => true,
                'data' => $media->toArray(),
                'message' => 'File uploaded successfully',
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Upload multiple files
     */
    #[Route('POST', '/upload-multiple', name: 'uploadMultiple')]
    public function uploadMultiple(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->forbidden('You do not have permission to upload media');
        }
        
        $files = $request->getUploadedFiles();
        $params = $request->getParsedBody() ?? [];
        
        if (empty($files['files']) || !is_array($files['files'])) {
            return new JsonResponse([
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
                
                $results['uploaded'][] = $media->toArray();
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'index' => $index,
                    'filename' => $file->getClientFilename(),
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return new JsonResponse([
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
    #[Route('POST', '/upload-url', name: 'uploadUrl')]
    public function uploadUrl(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->forbidden('You do not have permission to upload media');
        }
        
        $data = json_decode((string) $request->getBody(), true);
        
        if (empty($data['url'])) {
            return new JsonResponse([
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
            
            return new JsonResponse([
                'success' => true,
                'data' => $media->toArray(),
                'message' => 'File downloaded and uploaded successfully',
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
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
    #[Route('POST', '/chunked/init', name: 'chunkedInit')]
    public function chunkedInit(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->forbidden('You do not have permission to upload media');
        }
        
        $data = json_decode((string) $request->getBody(), true);
        
        $required = ['filename', 'file_size', 'mime_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new JsonResponse([
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
            
            return new JsonResponse([
                'success' => true,
                'data' => $session,
                'message' => 'Chunked upload initialized',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Upload a chunk
     */
    #[Route('POST', '/chunked/upload', name: 'chunkedUpload')]
    public function chunkedUpload(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->forbidden('You do not have permission to upload media');
        }
        
        $params = $request->getParsedBody() ?? [];
        
        $uploadId = $params['upload_id'] ?? null;
        $chunkNumber = isset($params['chunk_number']) ? (int) $params['chunk_number'] : null;
        
        if ($uploadId === null || $chunkNumber === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'upload_id and chunk_number are required',
            ], 400);
        }
        
        $files = $request->getUploadedFiles();
        if (empty($files['chunk'])) {
            return new JsonResponse([
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
            
            return new JsonResponse([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Complete chunked upload
     */
    #[Route('POST', '/chunked/complete', name: 'chunkedComplete')]
    public function chunkedComplete(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->forbidden('You do not have permission to upload media');
        }
        
        $data = json_decode((string) $request->getBody(), true);
        
        if (empty($data['upload_id'])) {
            return new JsonResponse([
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
            
            return new JsonResponse([
                'success' => true,
                'data' => $media->toArray(),
                'message' => 'File uploaded successfully',
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Abort chunked upload
     */
    #[Route('DELETE', '/chunked/{uploadId}', name: 'chunkedAbort')]
    public function chunkedAbort(string $uploadId): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->forbidden('You do not have permission to manage uploads');
        }
        
        try {
            $this->mediaService->abortChunkedUpload($uploadId);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Upload aborted',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
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
    #[Route('PUT', '/{id:\d+}', name: 'update')]
    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('edit_media')) {
            return $this->forbidden('You do not have permission to edit media');
        }
        
        $media = $this->mediaService->find($id);
        
        if ($media === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }
        
        $data = json_decode((string) $request->getBody(), true);
        
        try {
            $media = $this->mediaService->update($media, $data);
            
            return new JsonResponse([
                'success' => true,
                'data' => $media->toArray(),
                'message' => 'Media updated successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Delete media
     */
    #[Route('DELETE', '/{id:\d+}', name: 'destroy')]
    public function destroy(int $id): ResponseInterface
    {
        if (!$this->permissions->can('delete_media')) {
            return $this->forbidden('You do not have permission to delete media');
        }
        
        $media = $this->mediaService->find($id);
        
        if ($media === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }
        
        try {
            $this->mediaService->delete($media);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Bulk delete media
     */
    #[Route('POST', '/bulk-delete', name: 'bulkDelete')]
    public function bulkDelete(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->permissions->can('delete_media')) {
            return $this->forbidden('You do not have permission to delete media');
        }
        
        $data = json_decode((string) $request->getBody(), true);
        $ids = $data['ids'] ?? [];
        
        if (empty($ids)) {
            return new JsonResponse([
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
        
        return new JsonResponse([
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
    #[Route('POST', '/{id:\d+}/variants', name: 'generateVariants')]
    public function generateVariants(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('edit_media')) {
            return $this->forbidden('You do not have permission to edit media');
        }
        
        $media = $this->mediaService->find($id);
        
        if ($media === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }
        
        if (!$media->isImage()) {
            return new JsonResponse([
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
            
            return new JsonResponse([
                'success' => true,
                'data' => $media->toArray(),
                'message' => 'Variants generated successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Crop image
     */
    #[Route('POST', '/{id:\d+}/crop', name: 'crop')]
    public function crop(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('edit_media')) {
            return $this->forbidden('You do not have permission to edit media');
        }
        
        $media = $this->mediaService->find($id);
        
        if ($media === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }
        
        $data = json_decode((string) $request->getBody(), true);
        
        $required = ['x', 'y', 'width', 'height'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return new JsonResponse([
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
            
            return new JsonResponse([
                'success' => true,
                'data' => $media->toArray(),
                'message' => $createNew ? 'Cropped copy created' : 'Image cropped successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Rotate image
     */
    #[Route('POST', '/{id:\d+}/rotate', name: 'rotate')]
    public function rotate(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('edit_media')) {
            return $this->forbidden('You do not have permission to edit media');
        }
        
        $media = $this->mediaService->find($id);
        
        if ($media === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }
        
        $data = json_decode((string) $request->getBody(), true);
        $degrees = (int) ($data['degrees'] ?? 90);
        
        try {
            $media = $this->mediaService->rotate($media, $degrees);
            
            return new JsonResponse([
                'success' => true,
                'data' => $media->toArray(),
                'message' => "Image rotated {$degrees} degrees",
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
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
    #[Route('POST', '/{id:\d+}/copy', name: 'copy')]
    public function copy(ServerRequestInterface $request, int $id): ResponseInterface
    {
        if (!$this->permissions->can('create_media')) {
            return $this->forbidden('You do not have permission to copy media');
        }
        
        $media = $this->mediaService->find($id);
        
        if ($media === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }
        
        $data = json_decode((string) $request->getBody(), true);
        
        try {
            $newMedia = $this->mediaService->copy($media, $data['folder'] ?? null);
            
            return new JsonResponse([
                'success' => true,
                'data' => $newMedia->toArray(),
                'message' => 'Media copied successfully',
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
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
            return $this->forbidden('You do not have permission to move media');
        }
        
        $media = $this->mediaService->find($id);
        
        if ($media === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }
        
        $data = json_decode((string) $request->getBody(), true);
        
        if (empty($data['folder'])) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Folder is required',
            ], 400);
        }
        
        try {
            $media = $this->mediaService->move($media, $data['folder']);
            
            return new JsonResponse([
                'success' => true,
                'data' => $media->toArray(),
                'message' => 'Media moved successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
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
    #[Route('GET', '/folders', name: 'folders')]
    public function folders(): ResponseInterface
    {
        if (!$this->permissions->can('view_media')) {
            return $this->forbidden('You do not have permission to view media');
        }
        
        $folders = $this->mediaService->getFolders();
        
        return new JsonResponse([
            'success' => true,
            'data' => $folders,
        ]);
    }
    
    /**
     * Get usage statistics
     */
    #[Route('GET', '/stats', name: 'stats')]
    public function stats(): ResponseInterface
    {
        if (!$this->permissions->can('view_media')) {
            return $this->forbidden('You do not have permission to view media');
        }
        
        $stats = $this->mediaService->getUsageStats();
        
        return new JsonResponse([
            'success' => true,
            'data' => $stats,
        ]);
    }
    
    /**
     * Get URL for media
     */
    #[Route('GET', '/{id:\d+}/url', name: 'url')]
    public function url(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $media = $this->mediaService->find($id);
        
        if ($media === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }
        
        $params = $request->getQueryParams();
        $variant = $params['variant'] ?? null;
        
        $url = $this->mediaService->getUrl($media, $variant);
        
        return new JsonResponse([
            'success' => true,
            'data' => ['url' => $url],
        ]);
    }
    
    /**
     * Get temporary URL (for private files)
     */
    #[Route('GET', '/{id:\d+}/temporary-url', name: 'temporaryUrl')]
    public function temporaryUrl(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $media = $this->mediaService->find($id);
        
        if ($media === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Media not found',
            ], 404);
        }
        
        $params = $request->getQueryParams();
        $expires = (int) ($params['expires'] ?? 3600);
        
        $url = $this->mediaService->getTemporaryUrl($media, $expires);
        
        return new JsonResponse([
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
    
    private function forbidden(string $message): ResponseInterface
    {
        return new JsonResponse([
            'success' => false,
            'error' => $message,
        ], 403);
    }
}
