<?php

declare(strict_types=1);

namespace App\Cms\Controller\JsonApi;

use App\Cms\Api\JsonApiFormatter;
use App\Cms\Api\QueryParser;
use App\Cms\Media\MediaEntity;
use App\Cms\Media\MediaRepository;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MediaJsonApiController — Public JSON:API for media files.
 */
#[RoutePrefix('/api/v1/media')]
final class MediaJsonApiController
{
    private readonly JsonApiFormatter $jsonApi;

    public function __construct(
        private readonly MediaRepository $mediaRepo,
    ) {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/api/v1';
        $this->jsonApi = new JsonApiFormatter($baseUrl);
    }

    #[Route('GET', '/', name: 'api.v1.media.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $query = new QueryParser($request);
        $type = $query->getFilter('type');

        $items = $this->mediaRepo->findAll(
            $type,
            $query->perPage,
            $query->getOffset(),
        );
        $total = $this->mediaRepo->count($type);
        $lastPage = (int) ceil($total / $query->perPage);

        $data = array_map(fn(MediaEntity $m) => [
            'id' => $m->id,
            'attributes' => $query->sparseFields('media', $m->toArray()['attributes'] ?? $m->toArray()),
        ], $items);

        return Response::json($this->jsonApi->collection(
            'media',
            $data,
            $this->jsonApi->paginationMeta($total, $query->page, $query->perPage, $lastPage),
            $this->jsonApi->paginationLinks('media', $query->page, $lastPage),
        ));
    }

    #[Route('GET', '/{id:\d+}', name: 'api.v1.media.show')]
    public function show(ServerRequestInterface $request, string $id): Response
    {
        $media = $this->mediaRepo->find((int) $id);

        if (!$media) {
            return Response::json($this->jsonApi->error(404, 'Not Found'), 404);
        }

        return Response::json($this->jsonApi->resource(
            'media',
            $media->id,
            $media->toArray()['attributes'] ?? $media->toArray(),
        ));
    }
}
