<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Blocks\BlockManager;
use App\Cms\Auth\SessionManager;
use App\Modules\Core\Services\MenuService;
use App\Modules\Core\Entities\Block;
use MonkeysLegion\Template\MLView;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use App\Cms\Assets\AssetManager;
use App\Cms\Fields\Widget\WidgetRegistry;
use App\Cms\Fields\Rendering\RenderContext;

/**
 * BlockController - Manage content block instances
 * 
 * Handles CRUD for 'blocks' table using manual entity hydration
 */
class BlockController extends BaseAdminController
{
    public function __construct(
        MLView $view,
        MenuService $menuService,
        SessionManager $session,
        private readonly BlockManager $blockManager,
        private readonly ConnectionInterface $connection,
        AssetManager $assetManager,
        private readonly WidgetRegistry $widgetRegistry,
    ) {
        parent::__construct($view, $menuService, $session);
        $this->setAssetManager($assetManager);
    }

    #[Route('GET', '/admin/blocks')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        // Fetch all blocks
        $stmt = $this->connection->pdo()->query("SELECT * FROM blocks ORDER BY created_at DESC");
        $blocks = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $block = new Block();
            $block->hydrate($row);
            $blocks[] = $block;
        }

        return $this->render('admin/blocks/index', [
            'blocks' => $blocks,
            'title' => 'Blocks',
        ]);
    }

    #[Route('GET', '/admin/blocks/create')]
    public function selectType(ServerRequestInterface $request): ResponseInterface
    {
        return $this->render('admin/blocks/select_type', [
            'typesGrouped' => $this->blockManager->getTypesGrouped(),
            'title' => 'Select Block Type',
        ]);
    }

    #[Route('GET', '/admin/blocks/create/{type}')]
    public function create(ServerRequestInterface $request, string $type): ResponseInterface
    {
        // Verify type exists
        if (!$this->blockManager->hasType($type)) {
            return $this->redirect('/admin/blocks/create');
        }

        $block = new Block();
        $block->block_type = $type;

        $types = $this->blockManager->getTypes();
        
        // Render body field dynamically based on body_format
        $bodyFormat = $request->getQueryParams()['body_format'] ?? ($block->body_format ?? 'html');
        $bodyField = new \App\Cms\Fields\FieldDefinition();
        $bodyField->name = 'Content';
        $bodyField->machine_name = 'body';
        $bodyField->field_type = match($bodyFormat) {
            'markdown' => 'markdown',
            'plain' => 'textarea',
            default => 'html'
        };
        
        $bodyResult = $this->widgetRegistry->renderField($bodyField, $block->body ?? '', RenderContext::create());
        $this->assets->mergeCollection($bodyResult->getAssets());
        
        $renderedBodyField = [
            'label' => 'Content',
            'machine_name' => 'body',
            'html' => $bodyResult->getHtml()
        ];
        
        // Render dynamic fields
        $renderedFields = [];
        $currentTypeFields = $this->blockManager->getFieldsForType($block->block_type);
        
        foreach ($currentTypeFields as $key => $fieldData) {
             // Convert array to FieldDefinition if needed
             if (is_array($fieldData)) {
                 $field = new \App\Cms\Fields\FieldDefinition();
                 // Map simple array to FieldDefinition
                 $field->name = $fieldData['label'] ?? $key;
                 $field->machine_name = $fieldData['machine_name'] ?? $key;
                 $field->field_type = $fieldData['type'] ?? 'string';
                 // $field->label property does not exist, name is used.
                 $field->description = $fieldData['description'] ?? null;
                 $field->default_value = $fieldData['default'] ?? null;
                 $field->required = $fieldData['required'] ?? false;
                 // Add other properties as needed
                 $field->settings = $fieldData['settings'] ?? [];
                 $field->widget_settings = $fieldData['widget_settings'] ?? [];
                 $field->multiple = $fieldData['multiple'] ?? false;
                 $field->widget = $fieldData['widget'] ?? null;
             } else {
                 $field = $fieldData;
             }

             // Basic context
             $value = $field->default_value;
             
             // Render field and capture assets
             $result = $this->widgetRegistry->renderField($field, $value, RenderContext::create());
             $this->assets->mergeCollection($result->getAssets());

             // Ensure field name is used as input name
             $renderedFields[] = [
                 'label' => $field->name,
                 'machine_name' => $field->machine_name,
                 'html' => $result->getHtml()
             ];
        }

        return $this->render('admin/blocks/form', [
            'block' => $block,
            'types' => $types,
            'renderedBodyField' => $renderedBodyField,
            'renderedFields' => $renderedFields,
            'title' => 'Create Block',
            'action' => '/admin/blocks',
            'method' => 'POST'
        ]);
    }

    #[Route('POST', '/admin/blocks')]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = (array) $request->getParsedBody();

        $block = new Block();
        $block->admin_title = $data['admin_title'] ?? ''; // Safely handle missing key
        $block->machine_name = $data['machine_name'] ?? ''; // Auto-generated in prePersist if empty
        $block->show_title = isset($data['show_title']);
        $block->title = $data['title'] ?? null;
        
        // Basic Validation
        if (empty($block->admin_title)) {
             $this->assets->addFile('/vendor/quill/quill.snow.css');
             $this->assets->addFile('/vendor/quill/quill.js');
             $this->assets->addFile('/js/fields/quill-init.js');
             return $this->render('admin/blocks/form', [
                'block' => $block,
                'types' => $this->blockManager->getTypes(),
                'title' => 'Create Block',
                'action' => '/admin/blocks',
                'method' => 'POST',
                'errors' => ['Block Title is required']
            ]);
        }
        $block->block_type = $data['block_type'] ?? 'content';
        $block->body = $data['body'] ?? null;
        $block->body_format = $data['body_format'] ?? 'html';
        $block->region = $data['region'] ?? null;
        $block->weight = (int) ($data['weight'] ?? 0);
        $block->is_published = isset($data['is_published']);
        $block->visibility_mode = $data['visibility_mode'] ?? 'all';
        $block->visibility_pages = array_filter(array_map('trim', explode("\n", $data['visibility_pages'] ?? '')));
        $block->visibility_roles = $data['visibility_roles'] ?? [];
        $block->css_class = $data['css_class'] ?? null;
        $block->css_id = $data['css_id'] ?? null;
        
        // Handle dynamic fields
        $fields = $this->blockManager->getFieldsForType($block->block_type);
        $settings = [];
        foreach ($fields as $field) {
            $machineName = is_array($field) ? ($field['machine_name'] ?? null) : $field->machine_name;
            if ($machineName && isset($data[$machineName])) {
                $settings[$machineName] = $data[$machineName];
            }
        }
        $block->settings = $settings;

        $block->prePersist();

        // SQL Insert
        $stmt = $this->connection->pdo()->prepare("
            INSERT INTO blocks (
                admin_title, machine_name, title, show_title, block_type, 
                body, body_format, region, weight, is_published, 
                visibility_mode, visibility_pages, visibility_roles, 
                css_class, css_id, settings, created_at, updated_at
            ) VALUES (
                :admin_title, :machine_name, :title, :show_title, :block_type, 
                :body, :body_format, :region, :weight, :is_published, 
                :visibility_mode, :visibility_pages, :visibility_roles, 
                :css_class, :css_id, :settings, :created_at, :updated_at
            )
        ");

        $success = $stmt->execute([
            'admin_title' => $block->admin_title,
            'machine_name' => $block->machine_name,
            'title' => $block->title,
            'show_title' => $block->show_title ? 1 : 0,
            'block_type' => $block->block_type,
            'body' => $block->body,
            'body_format' => $block->body_format,
            'region' => $block->region,
            'weight' => $block->weight,
            'is_published' => $block->is_published ? 1 : 0,
            'visibility_mode' => $block->visibility_mode,
            'visibility_pages' => json_encode($block->visibility_pages),
            'visibility_roles' => json_encode($block->visibility_roles),
            'css_class' => $block->css_class,
            'css_id' => $block->css_id,
            'settings' => json_encode($block->settings),
            'created_at' => $block->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $block->updated_at->format('Y-m-d H:i:s'),
        ]);

        if (!$success) {
            // Need to reload types and assets if we return view
            $this->assets->addFile('/vendor/quill/quill.snow.css');
            $this->assets->addFile('/vendor/quill/quill.js');
            $this->assets->addFile('/js/fields/quill-init.js');
            return $this->render('admin/blocks/form', [
                'block' => $block,
                'types' => $this->blockManager->getTypes(),
                'title' => 'Create Block',
                'action' => '/admin/blocks',
                'method' => 'POST',
                'errors' => ['Failed to save block']
            ]);
        }

        return $this->redirect('/admin/blocks');
    }

    #[Route('GET', '/admin/blocks/{id}/edit')]
    public function edit(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $stmt = $this->connection->pdo()->prepare("SELECT * FROM blocks WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return $this->redirect('/admin/blocks');
        }

        $block = new Block();
        $block->hydrate($row);
        
        // Render body field dynamically based on body_format
        $bodyFormat = $request->getQueryParams()['body_format'] ?? ($block->body_format ?? 'html');
        $bodyField = new \App\Cms\Fields\FieldDefinition();
        $bodyField->name = 'Content';
        $bodyField->machine_name = 'body';
        $bodyField->field_type = match($bodyFormat) {
            'markdown' => 'markdown',
            'plain' => 'textarea',
            default => 'html'
        };
        
        $bodyResult = $this->widgetRegistry->renderField($bodyField, $block->body ?? '', RenderContext::create());
        $this->assets->mergeCollection($bodyResult->getAssets());
        
        $renderedBodyField = [
            'label' => 'Content',
            'machine_name' => 'body',
            'html' => $bodyResult->getHtml()
        ];

        // Render dynamic fields
        $renderedFields = [];
        $currentTypeFields = $this->blockManager->getFieldsForType($block->block_type);
        foreach ($currentTypeFields as $key => $fieldData) {
             // Convert array to FieldDefinition if needed
             if (is_array($fieldData)) {
                 $field = new \App\Cms\Fields\FieldDefinition();
                 $field->name = $fieldData['label'] ?? $key;
                 $field->machine_name = $fieldData['machine_name'] ?? $key;
                 $field->field_type = $fieldData['type'] ?? 'string';
                 $field->description = $fieldData['description'] ?? null;
                 $field->default_value = $fieldData['default'] ?? null;
                 $field->required = $fieldData['required'] ?? false;
                 $field->settings = $fieldData['settings'] ?? [];
                 $field->widget_settings = $fieldData['widget_settings'] ?? [];
                 $field->multiple = $fieldData['multiple'] ?? false;
                 $field->widget = $fieldData['widget'] ?? null;
             } else {
                 $field = $fieldData;
             }

             $value = $block->settings[$field->machine_name] ?? null;
             
             // Render field and capture assets
             $result = $this->widgetRegistry->renderField($field, $value, RenderContext::create());
             $this->assets->mergeCollection($result->getAssets());
             
             $renderedFields[] = [
                 'label' => $field->name,
                 'machine_name' => $field->machine_name,
                 'html' => $result->getHtml()
             ];
        }

        return $this->render('admin/blocks/form', [
            'block' => $block,
            'types' => $this->blockManager->getTypes(),
            'renderedBodyField' => $renderedBodyField,
            'renderedFields' => $renderedFields,
            'title' => 'Edit Block: ' . $block->admin_title,
            'action' => '/admin/blocks/' . $id,
            'method' => 'POST' // using _method=PUT in form
        ]);
    }

    #[Route('POST', '/admin/blocks/{id}')]
    public function update(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        // Check for _method=PUT/DELETE override if you handle it that way, 
        // or just accept POST for update if convenient
        if (isset($data['_method']) && strtoupper($data['_method']) === 'DELETE') {
            return $this->destroy($request, $id);
        }


        $stmt = $this->connection->pdo()->prepare("SELECT * FROM blocks WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
             return $this->redirect('/admin/blocks');
        }

        $block = new Block();
        $block->hydrate($row);

        $block->admin_title = $data['admin_title'];
        $block->show_title = isset($data['show_title']);
        $block->title = $data['title'] ?? null;
        if (!empty($data['machine_name'])) {
            $block->machine_name = $data['machine_name'];
        }
        $block->block_type = $data['block_type'] ?? $block->block_type;
        $block->body = $data['body'] ?? null;
        $block->body_format = $data['body_format'] ?? 'html';
        $block->region = $data['region'] ?? null;
        $block->weight = (int) ($data['weight'] ?? 0);
        $block->is_published = isset($data['is_published']);
        $block->visibility_mode = $data['visibility_mode'] ?? 'all';
        $block->visibility_pages = array_filter(array_map('trim', explode("\n", $data['visibility_pages'] ?? '')));
        $block->visibility_roles = $data['visibility_roles'] ?? [];
        $block->css_class = $data['css_class'] ?? null;
        $block->css_id = $data['css_id'] ?? null;
        
        // Handle dynamic fields
        $fields = $this->blockManager->getFieldsForType($block->block_type);
        $settings = $block->settings; // Start with existing
        foreach ($fields as $key => $field) {
            $machineName = is_array($field) ? ($field['machine_name'] ?? $key) : $field->machine_name;
            if ($machineName && isset($data[$machineName])) {
                $settings[$machineName] = $data[$machineName];
            }
        }
        $block->settings = $settings;
        
        $block->updated_at = new \DateTimeImmutable();

        $stmt = $this->connection->pdo()->prepare("
            UPDATE blocks SET 
                admin_title = :admin_title,
                machine_name = :machine_name,
                title = :title,
                show_title = :show_title,
                block_type = :block_type,
                body = :body,
                region = :region,
                is_published = :is_published,
                settings = :settings,
                body_format = :body_format,
                weight = :weight,
                visibility_mode = :visibility_mode,
                visibility_pages = :visibility_pages,
                visibility_roles = :visibility_roles,
                css_class = :css_class,
                css_id = :css_id,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'admin_title' => $block->admin_title,
            'machine_name' => $block->machine_name,
            'title' => $block->title,
            'show_title' => $block->show_title ? 1 : 0,
            'block_type' => $block->block_type,
            'body' => $block->body,
            'region' => $block->region,
            'is_published' => $block->is_published ? 1 : 0,
            'settings' => json_encode($block->settings),
            'body_format' => $block->body_format,
            'weight' => $block->weight,
            'visibility_mode' => $block->visibility_mode,
            'visibility_pages' => json_encode($block->visibility_pages),
            'visibility_roles' => json_encode($block->visibility_roles),
            'css_class' => $block->css_class,
            'css_id' => $block->css_id,
            'updated_at' => $block->updated_at->format('Y-m-d H:i:s'),
            'id' => $id
        ]);

        return $this->redirect('/admin/blocks');
    }

    #[Route('POST', '/admin/blocks/{id}/delete')]
    public function destroy(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $stmt = $this->connection->pdo()->prepare("DELETE FROM blocks WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        return $this->redirect('/admin/blocks');
    }
    
    // Helper redirect match BaseAdminController usually implies access to router or simple header/response
    protected function redirect(string $url): ResponseInterface
    {
        return new \Laminas\Diactoros\Response\RedirectResponse($url);
    }
}
