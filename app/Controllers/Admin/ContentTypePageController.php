<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\ContentTypes\ContentTypeManager;
use App\Cms\Auth\SessionManager;
use App\Modules\Core\Services\MenuService;
use App\Cms\Fields\FieldType;
use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Widget\WidgetRegistry;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Assets\AssetManager;
use MonkeysLegion\Template\MLView;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\Response\JsonResponse;

/**
 * ContentTypePageController - Admin UI for managing content types (page views)
 * 
 * Provides page-based admin views at /admin/structure/content-types
 * Mirrors the BlockTypeController pattern.
 */
class ContentTypePageController extends BaseAdminController
{
    public function __construct(
        MLView $view,
        MenuService $menuService,
        SessionManager $session,
        AssetManager $assetManager,
        private readonly ContentTypeManager $contentTypeManager,
        private readonly WidgetRegistry $widgetRegistry,
    ) {
        parent::__construct($view, $menuService, $session);
        $this->setAssetManager($assetManager);
    }

    /**
     * List all content types
     */
    #[Route('GET', '/admin/structure/content-types')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $types = $this->contentTypeManager->getTypes();

        return $this->render('admin/structure/content_types/index', [
            'types' => $types,
            'title' => 'Content Types',
        ]);
    }

    /**
     * Content management dashboard
     * Shows available content types and recent content items
     */
    #[Route('GET', '/admin/content')]
    public function contentIndex(ServerRequestInterface $request): ResponseInterface
    {
        $types = $this->contentTypeManager->getTypes();
        
        // Get recent content items (placeholder - will be implemented with actual content queries)
        $recentContent = [];
        
        // In future, this will query actual content items from content tables
        // For now, just show the content types for creating new content

        return $this->render('admin/content/index', [
            'types' => $types,
            'recentContent' => $recentContent,
            'title' => 'Content',
        ]);
    }

    /**
     * Create new content of a specific type
     */
    #[Route('GET', '/admin/content/{type}/add')]
    public function contentCreate(ServerRequestInterface $request, string $type): ResponseInterface
    {
        $contentType = $this->contentTypeManager->getType($type);
        if (!$contentType) {
            return new RedirectResponse('/admin/content');
        }

        // Get fields for this content type
        $fieldsData = $contentType['fields'] ?? [];
        
        // Render all fields
        $renderedFields = [];
        foreach ($fieldsData as $fieldData) {
            $fieldDef = FieldDefinition::fromArray($fieldData);
            
            // Get value from old input if validation failed (to implement)
            // For now, default value
            $value = $fieldDef->default_value;
            
            $result = $this->widgetRegistry->renderField($fieldDef, $value, RenderContext::create());
            $this->assets->mergeCollection($result->getAssets());
            
            $renderedFields[$fieldDef->machine_name] = [
                'label' => $fieldDef->name,
                'machine_name' => $fieldDef->machine_name,
                'html' => $result->getHtml(),
                'weight' => $fieldDef->weight
            ];
        }
        
        // Sort by weight
        uasort($renderedFields, fn($a, $b) => $a['weight'] <=> $b['weight']);

        return $this->render('admin/content/create', [
            'type' => $contentType,
            'type_id' => $type,
            'renderedFields' => $renderedFields,
            'title' => 'Create ' . $contentType['label'],
            'action' => '/admin/content/' . $type,
            'cancel_url' => '/admin/content',
        ]);

    }

    /**
     * Create content type form
     */
    #[Route('GET', '/admin/structure/content-types/create')]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        return $this->render('admin/structure/content_types/form', [
            'type' => null,
            'title' => 'Create Content Type',
            'action' => '/admin/structure/content-types',
            'method' => 'POST'
        ]);
    }

    /**
     * Store new content type
     */
    #[Route('POST', '/admin/structure/content-types')]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        
        try {
            $this->contentTypeManager->createDatabaseType($data);
            return new RedirectResponse('/admin/structure/content-types');
        } catch (\Exception $e) {
            return $this->render('admin/structure/content_types/form', [
                'type' => $data,
                'error' => $e->getMessage(),
                'title' => 'Create Content Type',
                'action' => '/admin/structure/content-types',
                'method' => 'POST'
            ]);
        }
    }

    /**
     * Edit content type form
     */
    #[Route('GET', '/admin/structure/content-types/{id}/edit')]
    public function edit(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        // Convert to array-like structure compatible with form
        $formData = [
            'label' => $type['label'],
            'type_id' => $type['id'],
            'label_plural' => $type['label_plural'] ?? $type['label'] . 's',
            'description' => $type['description'] ?? '',
            'icon' => $type['icon'] ?? '📄',
            'publishable' => $type['publishable'] ?? true,
            'revisionable' => $type['revisionable'] ?? false,
            'translatable' => $type['translatable'] ?? false,
            'has_author' => $type['has_author'] ?? true,
            'has_taxonomy' => $type['has_taxonomy'] ?? true,
            'has_media' => $type['has_media'] ?? true,
            'title_field' => $type['title_field'] ?? 'title',
            'slug_field' => $type['slug_field'] ?? 'slug',
            'url_pattern' => $type['url_pattern'] ?? null,
            'enabled' => $type['enabled'] ?? true,
        ];

        return $this->render('admin/structure/content_types/form', [
            'type' => $formData,
            'is_edit' => true,
            'title' => 'Edit Content Type',
            'action' => '/admin/structure/content-types/' . $id,
            'method' => 'POST'
        ]);
    }

    /**
     * Update content type
     */
    #[Route('POST', '/admin/structure/content-types/{id}')]
    public function update(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $type = $this->contentTypeManager->getType($id);
        
        if (!$type || $type['source'] !== 'database') {
            return new RedirectResponse('/admin/structure/content-types');
        }

        try {
            $this->contentTypeManager->updateDatabaseType($type['entity']->id, $data);
            return new RedirectResponse('/admin/structure/content-types');
        } catch (\Exception $e) {
            return $this->render('admin/structure/content_types/form', [
                'type' => $data,
                'is_edit' => true,
                'error' => $e->getMessage(),
                'title' => 'Edit Content Type',
                'action' => '/admin/structure/content-types/' . $id,
                'method' => 'POST'
            ]);
        }
    }
    
    /**
     * Delete content type
     */
    #[Route('POST', '/admin/structure/content-types/{id}/delete')]
    public function destroy(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if ($type && $type['source'] === 'database') {
            $dropTable = (bool) ($request->getParsedBody()['drop_table'] ?? false);
            $this->contentTypeManager->deleteDatabaseType($type['entity']->id, $dropTable);
        }
        
        return new RedirectResponse('/admin/structure/content-types');
    }

    /**
     * Manage fields for a content type
     */
    #[Route('GET', '/admin/structure/content-types/{id}/fields')]
    public function fields(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        return $this->render('admin/structure/content_types/fields', [
            'type' => $type,
            'fields' => $type['fields'],
            'base_url' => '/admin/structure/content-types/' . $id,
            'add_url' => '/admin/structure/content-types/' . $id . '/fields/add',
        ]);
    }

    /**
     * Manage form display settings (field ordering)
     */
    #[Route('GET', '/admin/structure/content-types/{id}/form-display')]
    public function formDisplay(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        // Get fields and inject system fields
        $fields = $type['fields'];
        $weights = $type['entity']->settings['form_weights'] ?? [];



        // Prepare fields for sorting
        foreach ($fields as $key => &$field) {
            $field['machine_name'] = $field['machine_name'] ?? $key;
            $field['weight'] = $weights[$field['machine_name']] ?? $field['weight'] ?? 0;
        }
        unset($field);

        // Sort by weight
        uasort($fields, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

        return $this->render('admin/structure/content_types/form_display', [
            'type' => $type,
            'fields' => $fields,
            'base_url' => '/admin/structure/content-types/' . $id,
        ]);
    }

    /**
     * Save form display settings
     */
    #[Route('POST', '/admin/structure/content-types/{id}/form-display')]
    public function saveFormDisplay(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        $params = $request->getParsedBody();

        if ($type && $type['source'] === 'database' && !empty($params['weights'])) {
            try {
                // Save weights in settings
                $entity = $type['entity'];
                $settings = $entity->settings;
                $settings['form_weights'] = $params['weights'];
                $this->contentTypeManager->updateDatabaseType($entity->id, ['settings' => $settings]);
                
                if ($this->isAjax($request)) {
                    return new JsonResponse(['status' => 'success', 'message' => 'Weights saved']);
                }
            } catch (\Exception $e) {
                if ($this->isAjax($request)) {
                    return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
                }
            }
        }
        
        return new RedirectResponse('/admin/structure/content-types/' . $id . '/form-display');
    }

    private function isAjax(ServerRequestInterface $request): bool
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest' 
            || str_contains($request->getHeaderLine('Content-Type'), 'application/json');
    }

    /**
     * Manage display settings
     */
    #[Route('GET', '/admin/structure/content-types/{id}/display')]
    public function display(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        $fields = $type['fields'];
        $weights = $type['entity']->settings['display_weights'] ?? [];

        // Add system fields
        $fields['title'] = [
            'label' => 'Title',
            'widget' => 'string',
            'weight' => $weights['title'] ?? -10,
            'machine_name' => 'title'
        ];



        foreach ($fields as $key => &$field) {
            $field['machine_name'] = $field['machine_name'] ?? $key;
            $field['weight'] = $weights[$field['machine_name']] ?? $field['weight'] ?? 0;
        }
        unset($field);

        uasort($fields, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

        return $this->render('admin/structure/content_types/display', [
            'type' => $type,
            'fields' => $fields,
            'base_url' => '/admin/structure/content-types/' . $id,
        ]);
    }

    /**
     * Save display settings
     */
    #[Route('POST', '/admin/structure/content-types/{id}/display')]
    public function saveDisplay(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        $params = $request->getParsedBody();

        if ($type && $type['source'] === 'database' && !empty($params['weights'])) {
            try {
                $entity = $type['entity'];
                $settings = $entity->settings;
                $settings['display_weights'] = $params['weights'];
                $this->contentTypeManager->updateDatabaseType($entity->id, ['settings' => $settings]);
                
                if ($this->isAjax($request)) {
                    return new JsonResponse(['status' => 'success', 'message' => 'Weights saved']);
                }
            } catch (\Exception $e) {
                if ($this->isAjax($request)) {
                    return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
                }
            }
        }

        return new RedirectResponse('/admin/structure/content-types/' . $id . '/display');
    }
    
    /**
     * Add field form
     */
    #[Route('GET', '/admin/structure/content-types/{id}/fields/add')]
    public function addField(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        return $this->render('admin/structure/content_types/add_field', [
            'type' => $type,
            'grouped_types' => FieldType::getGrouped(),
            'action' => '/admin/structure/content-types/' . $id . '/fields',
            'cancel_url' => '/admin/structure/content-types/' . $id . '/fields',
        ]);
    }

    /**
     * Store new field
     */
    #[Route('POST', '/admin/structure/content-types/{id}/fields')]
    public function storeField(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $type = $this->contentTypeManager->getType($id);

        if (!$type || $type['source'] !== 'database') {
            return new RedirectResponse('/admin/structure/content-types');
        }

        try {
            $this->contentTypeManager->addFieldToType($type['entity']->id, $data);
            return new RedirectResponse('/admin/structure/content-types/' . $id . '/fields');
        } catch (\Exception $e) {
            return $this->render('admin/structure/content_types/add_field', [
                'type' => $type,
                'grouped_types' => FieldType::getGrouped(),
                'error' => $e->getMessage(),
                'action' => '/admin/structure/content-types/' . $id . '/fields',
                'cancel_url' => '/admin/structure/content-types/' . $id . '/fields',
            ]);
        }
    }

    /**
     * Delete field
     */
    #[Route('POST', '/admin/structure/content-types/{id}/fields/{fieldName}/delete')]
    public function deleteField(ServerRequestInterface $request, string $id, string $fieldName): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        
        if ($type && $type['source'] === 'database') {
            $dropColumn = (bool) ($request->getParsedBody()['drop_column'] ?? false);
            $this->contentTypeManager->removeFieldFromType($type['entity']->id, $fieldName, $dropColumn);
        }
        
        return new RedirectResponse('/admin/structure/content-types/' . $id . '/fields');
    }

    /**
     * Edit field form
     */
    #[Route('GET', '/admin/structure/content-types/{id}/fields/{fieldName}/edit')]
    public function editField(ServerRequestInterface $request, string $id, string $fieldName): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        $fields = $type['fields'];
        if (!isset($fields[$fieldName])) {
            return new RedirectResponse('/admin/structure/content-types/' . $id . '/fields');
        }

        return $this->render('admin/structure/content_types/edit_field', [
            'type' => $type,
            'field' => $fields[$fieldName],
            'machine_name' => $fieldName,
            'grouped_types' => FieldType::getGrouped(),
            'action' => '/admin/structure/content-types/' . $id . '/fields/' . $fieldName . '/update',
            'cancel_url' => '/admin/structure/content-types/' . $id . '/fields',
        ]);
    }

    /**
     * Update field
     */
    #[Route('POST', '/admin/structure/content-types/{id}/fields/{fieldName}/update')]
    public function updateField(ServerRequestInterface $request, string $id, string $fieldName): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $type = $this->contentTypeManager->getType($id);

        if (!$type || $type['source'] !== 'database') {
            return new RedirectResponse('/admin/structure/content-types');
        }

        try {
            // ContentTypeManager doesn't have updateFieldOnType, we need to add/re-add field
            // For now, remove and add field with new data
            $this->contentTypeManager->removeFieldFromType($type['entity']->id, $fieldName, false);
            $data['machine_name'] = $fieldName;
            $this->contentTypeManager->addFieldToType($type['entity']->id, $data);
            return new RedirectResponse('/admin/structure/content-types/' . $id . '/fields');
        } catch (\Exception $e) {
            $fields = $type['fields'];
            return $this->render('admin/structure/content_types/edit_field', [
                'type' => $type,
                'field' => $fields[$fieldName] ?? [],
                'machine_name' => $fieldName,
                'grouped_types' => FieldType::getGrouped(),
                'error' => $e->getMessage(),
                'action' => '/admin/structure/content-types/' . $id . '/fields/' . $fieldName . '/update',
                'cancel_url' => '/admin/structure/content-types/' . $id . '/fields',
            ]);
        }
    }
}
