<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use App\Cms\Media\MediaEntity;
use App\Cms\Media\MediaRepository;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MediaApiController — REST API for the media library.
 */
#[RoutePrefix('/admin/api/media')]
final class MediaApiController
{
    public function __construct(
        private readonly MediaRepository $mediaRepo,
    ) {}

    #[Route('GET', '/', name: 'admin.api.media.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $type = $params['type'] ?? null;
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['per_page'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $items = $this->mediaRepo->findAll($type, $limit, $offset);
        $total = $this->mediaRepo->count($type);

        return Response::json([
            'data' => array_map(fn(MediaEntity $m) => $m->toArray(), $items),
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $limit, 'last_page' => (int) ceil($total / $limit)],
        ]);
    }

    #[Route('GET', '/{id:\d+}', name: 'admin.api.media.show')]
    public function show(ServerRequestInterface $request, string $id): Response
    {
        $media = $this->mediaRepo->find((int) $id);
        return $media
            ? Response::json(['data' => $media->toArray()])
            : Response::json(['error' => 'Not found'], 404);
    }

    #[Route('POST', '/upload', name: 'admin.api.media.upload')]
    public function upload(ServerRequestInterface $request): Response
    {
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;

        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            return Response::json(['error' => 'No file uploaded or upload error'], 422);
        }

        $originalName = $file->getClientFilename();
        $mimeType = $file->getClientMediaType();
        $size = $file->getSize();
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $datePath = date('Y/m');
        $uploadDir = 'storage/uploads/' . $datePath;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file->moveTo($uploadDir . '/' . $filename);

        $media = new MediaEntity();
        $media->filename = $filename;
        $media->original_name = $originalName;
        $media->mime_type = $mimeType;
        $media->path = $datePath . '/' . $filename;
        $media->url = '/uploads/' . $datePath . '/' . $filename;
        $media->size = $size;

        // Extract image dimensions
        if (str_starts_with($mimeType, 'image/')) {
            $fullPath = $uploadDir . '/' . $filename;
            $info = @getimagesize($fullPath);
            if ($info) {
                $media->width = $info[0];
                $media->height = $info[1];
            }
        }

        $media = $this->mediaRepo->persist($media);

        return Response::json(['data' => $media->toArray(), 'meta' => ['uploaded' => true]], 201);
    }

    #[Route('PUT', '/{id:\d+}', name: 'admin.api.media.update')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        $media = $this->mediaRepo->find((int) $id);
        if (!$media) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $body = json_decode((string) $request->getBody(), true);
        if (isset($body['alt'])) $media->alt = $body['alt'];
        if (isset($body['title'])) $media->title = $body['title'];
        if (isset($body['description'])) $media->description = $body['description'];

        $this->mediaRepo->persist($media);

        return Response::json(['data' => $media->toArray()]);
    }

    #[Route('DELETE', '/{id:\d+}', name: 'admin.api.media.delete')]
    public function delete(ServerRequestInterface $request, string $id): Response
    {
        $media = $this->mediaRepo->find((int) $id);
        if (!$media) {
            return Response::json(['error' => 'Not found'], 404);
        }

        // Delete physical file
        $fullPath = 'storage/uploads/' . $media->path;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $this->mediaRepo->delete((int) $id);

        return Response::json(['meta' => ['deleted' => true]]);
    }
}
