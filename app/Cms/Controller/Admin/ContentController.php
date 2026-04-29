<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Content\ContentRepository;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * ContentController — Admin UI for content management.
 */
#[RoutePrefix('/admin/content')]
final class ContentController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly ContentRepository $contentRepo,
        private readonly PDO $pdo,
    ) {}

    #[Route('GET', '/', name: 'admin.content.index')]
    public function index(ServerRequestInterface $request): Response
    {
        // Load content types for the type tabs
        $stmt = $this->pdo->query('SELECT * FROM content_types WHERE enabled = 1 ORDER BY weight ASC');
        $contentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $params = $request->getQueryParams();
        $activeType = $params['type'] ?? ($contentTypes[0]['type_id'] ?? 'page');

        return Response::html($this->renderer->render('admin.content.index', [
            'title' => 'Content',
            'contentTypes' => $contentTypes,
            'activeType' => $activeType,
        ]));
    }

    #[Route('GET', '/create/{type}', name: 'admin.content.create')]
    public function create(ServerRequestInterface $request, string $type): Response
    {
        $stmt = $this->pdo->prepare('SELECT * FROM content_types WHERE type_id = :type');
        $stmt->execute(['type' => $type]);
        $contentType = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contentType) {
            return Response::redirect('/admin/content');
        }

        // Load field definitions
        $fstmt = $this->pdo->prepare('SELECT * FROM field_definitions WHERE content_type_id = :id ORDER BY weight ASC');
        $fstmt->execute(['id' => $contentType['id']]);
        $fields = $fstmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::html($this->renderer->render('admin.content.form', [
            'title' => 'Create ' . $contentType['label'],
            'contentType' => $contentType,
            'fields' => $fields,
            'node' => null,
            'isNew' => true,
        ]));
    }

    #[Route('GET', '/{id:\d+}/edit', name: 'admin.content.edit')]
    public function edit(ServerRequestInterface $request, string $id): Response
    {
        $node = $this->contentRepo->findOrFail((int) $id);

        $stmt = $this->pdo->prepare('SELECT * FROM content_types WHERE type_id = :type');
        $stmt->execute(['type' => $node->content_type]);
        $contentType = $stmt->fetch(PDO::FETCH_ASSOC);

        $fstmt = $this->pdo->prepare('SELECT * FROM field_definitions WHERE content_type_id = :id ORDER BY weight ASC');
        $fstmt->execute(['id' => $contentType['id']]);
        $fields = $fstmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::html($this->renderer->render('admin.content.form', [
            'title' => 'Edit: ' . $node->title,
            'contentType' => $contentType,
            'fields' => $fields,
            'node' => $node,
            'isNew' => false,
        ]));
    }
}
