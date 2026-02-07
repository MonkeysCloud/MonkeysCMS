<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Composer\ComposerManager;
use App\Cms\Composer\ComposerData;
use App\Cms\Composer\ComposerRenderer;
use App\Cms\Composer\Layout\LayoutRegistry;
use App\Cms\Composer\Layout\CoreLayoutProvider;
use App\Cms\Blocks\BlockManager;
use App\Cms\Auth\SessionManager;
use App\Modules\Core\Services\MenuService;
use MonkeysLegion\Template\MLView;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use App\Cms\Assets\AssetManager;
use Laminas\Diactoros\Response\JsonResponse;

/**
 * ComposerController - Content Composer visual editor
 */
class ComposerController extends BaseAdminController
{
    private ComposerManager $composerManager;
    private LayoutRegistry $layoutRegistry;

    public function __construct(
        MLView $view,
        MenuService $menuService,
        SessionManager $session,
        private readonly ConnectionInterface $connection,
        AssetManager $assetManager,
        private readonly BlockManager $blockManager,
    ) {
        parent::__construct($view, $menuService, $session);
        $this->setAssetManager($assetManager);
        
        // Initialize composer services
        $this->layoutRegistry = new LayoutRegistry();
        $this->layoutRegistry->registerProvider(new CoreLayoutProvider());
        
        $this->composerManager = new ComposerManager();
        $this->composerManager->setLayoutRegistry($this->layoutRegistry);
    }

    /**
     * Open composer editor for a node
     */
    #[Route('GET', '/admin/composer/node/{id}')]
    public function editNode(ServerRequestInterface $request, string $id): ResponseInterface
    {
        try {
            // Fetch node
            $stmt = $this->connection->pdo()->prepare("SELECT * FROM nodes WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $node = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$node) {
                // Node not found, redirect to content
                return $this->redirect('/admin/content');
            }

            // Parse composer data
            $composerData = $this->composerManager->parse($node['composer_data'] ?? null);

            // Get content type fields
            $fields = $this->getContentTypeFields($node['type']);

            return $this->renderEditor([
                'entityType' => 'node',
                'entityId' => $id,
                'entity' => $node,
                'composerData' => $composerData->toArray(),
                'fields' => $fields,
                'title' => 'Edit Layout: ' . ($node['title'] ?? 'Untitled'),
            ]);
        } catch (\PDOException $e) {
            // Log the error for debugging
            error_log('ComposerController::editNode PDO Error: ' . $e->getMessage());
            // Try to check if it's a missing column issue
            if (str_contains($e->getMessage(), 'composer_data')) {
                error_log('ComposerController: nodes table missing composer_data column');
            }
            // Still redirect to demo as fallback
            return $this->redirect('/admin/composer/demo');
        } catch (\Throwable $e) {
            // Log other errors
            error_log('ComposerController::editNode Error: ' . $e->getMessage());
            return $this->redirect('/admin/composer/demo');
        }
    }

    /**
     * Demo/standalone mode - works without database
     */
    #[Route('GET', '/admin/composer/demo')]
    public function demo(ServerRequestInterface $request): ResponseInterface
    {
        // Provide sample fields for demo
        $demoFields = [
            ['machine_name' => 'title', 'name' => 'Title', 'field_type' => 'text'],
            ['machine_name' => 'body', 'name' => 'Body', 'field_type' => 'wysiwyg'],
            ['machine_name' => 'image', 'name' => 'Featured Image', 'field_type' => 'image'],
            ['machine_name' => 'tags', 'name' => 'Tags', 'field_type' => 'taxonomy'],
        ];

        // Create default layout
        $composerData = $this->composerManager->createDefaultLayout([]);

        return $this->renderEditor([
            'entityType' => 'demo',
            'entityId' => null,
            'entity' => ['type' => 'demo'],
            'composerData' => $composerData->toArray(),
            'fields' => $demoFields,
            'title' => 'Content Composer - Demo',
        ]);
    }

    /**
     * Create new with composer
     */
    #[Route('GET', '/admin/composer/node/create/{type}')]
    public function createNode(ServerRequestInterface $request, string $type): ResponseInterface
    {
        // Get content type fields
        $fields = $this->getContentTypeFields($type);

        // Create default layout with field placeholders
        $composerData = $this->composerManager->createDefaultLayout($fields);

        return $this->renderEditor([
            'entityType' => 'node',
            'entityId' => null,
            'entity' => ['type' => $type],
            'composerData' => $composerData->toArray(),
            'fields' => $fields,
            'title' => 'Create with Composer',
        ]);
    }

    /**
     * Save composer data via AJAX
     */
    #[Route('POST', '/admin/composer/save')]
    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        $entityType = $data['entityType'] ?? 'node';
        $entityId = $data['entityId'] ?? null;
        $composerData = $data['composerData'] ?? [];

        // Validate
        $parsed = ComposerData::fromArray($composerData);
        $validation = $this->composerManager->validate($parsed);

        if (!$validation['valid']) {
            return new JsonResponse([
                'success' => false,
                'errors' => $validation['errors'],
            ], 400);
        }

        // Save to database
        if ($entityType === 'node' && $entityId) {
            $stmt = $this->connection->pdo()->prepare("
                UPDATE nodes SET 
                    composer_data = :composer_data,
                    use_composer = 1,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'composer_data' => json_encode($composerData),
                'id' => $entityId,
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Layout saved successfully',
        ]);
    }

    /**
     * Get available blocks (API)
     */
    #[Route('GET', '/admin/composer/api/blocks')]
    public function getBlocks(ServerRequestInterface $request): ResponseInterface
    {
        $blocks = $this->composerManager->getAvailableBlocks();
        
        return new JsonResponse([
            'success' => true,
            'blocks' => $blocks,
        ]);
    }

    /**
     * Get available layouts (API)
     */
    #[Route('GET', '/admin/composer/api/layouts')]
    public function getLayouts(ServerRequestInterface $request): ResponseInterface
    {
        $layouts = $this->composerManager->getAvailableLayouts();
        
        return new JsonResponse([
            'success' => true,
            'layouts' => $layouts,
        ]);
    }

    /**
     * Preview render (API)
     */
    #[Route('POST', '/admin/composer/api/preview')]
    public function preview(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $composerData = ComposerData::fromArray($data['composerData'] ?? []);

        $renderer = new ComposerRenderer();
        $html = $renderer->render($composerData, [
            'fields' => $data['fields'] ?? [],
        ]);

        return new JsonResponse([
            'success' => true,
            'html' => $html,
        ]);
    }

    /**
     * Render a single block preview (API)
     * Uses BlockManager to render blocks like the frontend
     */
    #[Route('POST', '/admin/composer/api/block-preview')]
    public function renderBlockPreview(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        $blockType = $data['blockType'] ?? '';
        $blockData = $data['blockData'] ?? [];
        
        // Get the block type
        $type = $this->blockManager->getType($blockType);
        
        if (!$type) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Block type not found: ' . $blockType,
            ], 404);
        }
        
        // Create a mock block entity for rendering
        $block = new \App\Modules\Core\Entities\Block();
        $block->block_type = $blockType;
        $block->title = $blockData['title'] ?? $type['label'];
        $block->body = $blockData['body'] ?? '';
        $block->body_format = $blockData['body_format'] ?? 'html';
        $block->show_title = $blockData['show_title'] ?? true;
        $block->settings = $blockData['settings'] ?? [];
        
        // Render using BlockManager
        $html = $this->blockManager->renderBlock($block);
        
        return new JsonResponse([
            'success' => true,
            'html' => $html,
            'blockType' => $type,
        ]);
    }

    /**
     * Get saved templates (API)
     */
    #[Route('GET', '/admin/composer/api/templates')]
    public function getTemplates(ServerRequestInterface $request): ResponseInterface
    {
        $stmt = $this->connection->pdo()->query("SELECT * FROM composer_templates ORDER BY name");
        $templates = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return new JsonResponse([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * Get saved sections (API)
     */
    #[Route('GET', '/admin/composer/api/saved-sections')]
    public function getSavedSections(ServerRequestInterface $request): ResponseInterface
    {
        $stmt = $this->connection->pdo()->query("SELECT * FROM composer_saved_sections ORDER BY name");
        $sections = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return new JsonResponse([
            'success' => true,
            'sections' => $sections,
        ]);
    }

    /**
     * Save a section for reuse (API)
     */
    #[Route('POST', '/admin/composer/api/save-section')]
    public function saveSection(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        $name = $data['name'] ?? 'Untitled Section';
        $slug = $this->generateSlug($name);
        $sectionData = $data['section'] ?? [];

        $stmt = $this->connection->pdo()->prepare("
            INSERT INTO composer_saved_sections (name, slug, data, category) 
            VALUES (:name, :slug, :data, :category)
        ");
        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'data' => json_encode($sectionData),
            'category' => $data['category'] ?? 'general',
        ]);

        return new JsonResponse([
            'success' => true,
            'id' => $this->connection->pdo()->lastInsertId(),
        ]);
    }

    /**
     * Render the composer editor
     */
    private function renderEditor(array $params): ResponseInterface
    {
        // Get CMS block types from BlockManager
        $cmsBlocks = $this->blockManager->getTypesGrouped();
        
        // Convert block types to composer format
        $blocks = [];
        foreach ($cmsBlocks as $category => $types) {
            $blocks[$category] = [];
            foreach ($types as $type) {
                $blocks[$category][] = [
                    'type' => 'block:' . $type['id'],
                    'label' => $type['label'],
                    'icon' => $type['icon'] ?? '📦',
                    'description' => $type['description'] ?? '',
                    'blockType' => $type['id'],
                    'fields' => $type['fields'] ?? [],
                ];
            }
        }

        return $this->render('admin/composer/editor', array_merge($params, [
            'blocks' => $blocks,
            'layouts' => $this->composerManager->getAvailableLayouts(),
        ]));
    }

    /**
     * Get fields for a content type
     */
    private function getContentTypeFields(string $type): array
    {
        $stmt = $this->connection->pdo()->prepare("
            SELECT ctf.* FROM content_type_fields ctf
            JOIN content_types ct ON ct.id = ctf.content_type_id
            WHERE ct.type_id = :type
            ORDER BY ctf.weight
        ");
        $stmt->execute(['type' => $type]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        return trim($slug, '-') . '-' . substr(uniqid(), -6);
    }

    protected function redirect(string $url): ResponseInterface
    {
        return new \Laminas\Diactoros\Response\RedirectResponse($url);
    }
}
