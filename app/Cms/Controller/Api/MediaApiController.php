<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use App\Cms\Media\MediaModule;
use MonkeysLegion\Files\Upload\UploadedFile;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MediaApiController — JSON API for the media picker modal and AJAX uploads.
 */
#[RoutePrefix('/api/cms/media')]
final class MediaApiController
{
    public function __construct(
        private readonly MediaModule $media,
    ) {}

    #[Route('GET', '/', name: 'api::media.list')]
    public function list(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $type = $params['type'] ?? null;
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = (int) ($params['per_page'] ?? 24);
        $search = $params['search'] ?? null;
        $offset = ($page - 1) * $perPage;

        $items = $this->media->findAll(
            type: $type,
            limit: $perPage,
            offset: $offset,
        );

        if ($search) {
            $term = strtolower($search);
            $items = array_values(array_filter($items, function ($m) use ($term) {
                return str_contains(strtolower($m->title ?? ''), $term)
                    || str_contains(strtolower($m->original_name), $term)
                    || str_contains(strtolower($m->filename), $term);
            }));
        }

        return Response::json([
            'data' => array_map(fn($m) => array_merge($m->toArray(), [
                'thumb_url' => $m->type === 'image'
                    ? $this->media->styleUrl($m, 'thumb')
                    : null,
            ]), $items),
            'meta' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => count($items),
            ],
        ]);
    }

    #[Route('POST', '/upload', name: 'api::media.upload')]
    public function upload(ServerRequestInterface $request): Response
    {
        $userId = $request->getAttribute('cms_user')['id'] ?? null;

        $fileInfo = $_FILES['file'] ?? null;
        if (!$fileInfo || $fileInfo['error'] !== UPLOAD_ERR_OK) {
            return Response::json([
                'status'  => 'error',
                'message' => 'No file uploaded or upload error',
            ], 400);
        }

        try {
            $file = UploadedFile::fromGlobal($fileInfo);
            $entity = $this->media->upload($file, $userId);

            return Response::json([
                'status' => 'ok',
                'data'   => array_merge($entity->toArray(), [
                    'thumb_url' => $entity->type === 'image'
                        ? $this->media->styleUrl($entity, 'thumb')
                        : null,
                ]),
            ], 201);
        } catch (\Throwable $e) {
            return Response::json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[Route('GET', '/{id:\d+}', name: 'api::media.show')]
    public function show(ServerRequestInterface $request, string $id): Response
    {
        $media = $this->media->find((int) $id);

        if (!$media) {
            return Response::json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        return Response::json([
            'data' => array_merge($media->toArray(), [
                'thumb_url'  => $media->type === 'image'
                    ? $this->media->styleUrl($media, 'thumb')
                    : null,
                'medium_url' => $media->type === 'image'
                    ? $this->media->styleUrl($media, 'medium')
                    : null,
            ]),
        ]);
    }

    #[Route('GET', '/{id:\d+}/thumb', name: 'api::media.thumb')]
    public function thumbnail(ServerRequestInterface $request, string $id): Response
    {
        return $this->serveStyle($request, $id, 'thumb');
    }

    #[Route('GET', '/{id:\d+}/{style:[a-z_]+}', name: 'api::media.style')]
    public function style(ServerRequestInterface $request, string $id, string $style): Response
    {
        return $this->serveStyle($request, $id, $style);
    }

    /**
     * Serve a media file with a specific image style.
     * Generates the style on-the-fly if it doesn't exist yet.
     */
    private function serveStyle(ServerRequestInterface $request, string $id, string $style): Response
    {
        $media = $this->media->find((int) $id);

        if (!$media) {
            return Response::json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        if ($media->type !== 'image') {
            return Response::redirect('/assets/images/file-icon.svg');
        }

        $basePath = defined('ML_BASE_PATH') ? ML_BASE_PATH : '';

        // For 'original' or 'file' style, serve the original
        if ($style === 'original' || $style === 'file') {
            $filePath = $basePath . '/public/' . ltrim($media->path, '/');
            if (!file_exists($filePath)) {
                $filePath = $basePath . '/public/uploads/' . ltrim($media->path, '/');
            }
        } else {
            // Build style path: dir/styles/{style}/filename
            $dir = dirname($media->path);
            $filename = basename($media->path);
            $stylePath = $dir . '/styles/' . $style . '/' . $filename;

            $filePath = $basePath . '/public/' . ltrim($stylePath, '/');
            if (!file_exists($filePath)) {
                $filePath = $basePath . '/public/uploads/' . ltrim($stylePath, '/');
            }

            // If style doesn't exist, try generating it on the fly
            if (!file_exists($filePath)) {
                try {
                    $this->media->generateStyle($media, $style);
                    // Re-check after generation
                    $filePath = $basePath . '/public/' . ltrim($stylePath, '/');
                    if (!file_exists($filePath)) {
                        $filePath = $basePath . '/public/uploads/' . ltrim($stylePath, '/');
                    }
                } catch (\Throwable) {
                    // Fall through to original
                }
            }

            // If still no file, fall back to original
            if (!file_exists($filePath)) {
                $filePath = $basePath . '/public/' . ltrim($media->path, '/');
                if (!file_exists($filePath)) {
                    $filePath = $basePath . '/public/uploads/' . ltrim($media->path, '/');
                }
            }
        }

        if (file_exists($filePath)) {
            $contents = file_get_contents($filePath);
            $stream = \MonkeysLegion\Http\Message\Stream::createFromString($contents);
            return new \MonkeysLegion\Http\Message\Response(
                $stream,
                200,
                [
                    'Content-Type' => $media->mime_type,
                    'Cache-Control' => 'public, max-age=86400'
                ]
            );
        }

        // Ultimate fallback
        return Response::redirect($media->url ?? ('/uploads/' . ltrim($media->path, '/')));
    }

    #[Route('DELETE', '/{id:\d+}', name: 'api::media.delete')]
    public function delete(ServerRequestInterface $request, string $id): Response
    {
        $deleted = $this->media->delete((int) $id);

        if (!$deleted) {
            return Response::json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        return Response::json(['status' => 'ok']);
    }
}
