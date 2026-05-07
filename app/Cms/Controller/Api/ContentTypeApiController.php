<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use App\Cms\Content\ContentTypeEntity;
use App\Cms\Content\ContentTypeManager;
use App\Cms\Field\FieldRepository;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ContentTypeApiController — JSON API for content type management.
 */
#[RoutePrefix('/api/cms/content-types')]
final class ContentTypeApiController
{
    public function __construct(
        private readonly ContentTypeManager $typeManager,
        private readonly FieldRepository $fieldRepo,
    ) {}

    #[Route('GET', '/', name: 'api::content-types.index')]
    public function index(): Response
    {
        $types = $this->typeManager->getEnabled();

        return Response::json([
            'data' => array_map(fn(ContentTypeEntity $ct) => $ct->toArray(), array_values($types)),
        ]);
    }

    #[Route('GET', '/{typeId}', name: 'api::content-types.show')]
    public function show(ServerRequestInterface $request, string $typeId): Response
    {
        $ct = $this->typeManager->get($typeId);
        if (!$ct) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $fields = $this->typeManager->getFieldsFor($typeId);

        return Response::json([
            'data' => $ct->toArray(),
            'fields' => array_map(fn($f) => $f->toArray(), $fields),
        ]);
    }

    #[Route('GET', '/{typeId}/fields', name: 'api::content-types.fields')]
    public function fields(ServerRequestInterface $request, string $typeId): Response
    {
        $ct = $this->typeManager->get($typeId);
        if (!$ct) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $fields = $this->typeManager->getFieldsFor($typeId);

        return Response::json([
            'data' => array_map(fn($f) => $f->toArray(), $fields),
        ]);
    }
}
