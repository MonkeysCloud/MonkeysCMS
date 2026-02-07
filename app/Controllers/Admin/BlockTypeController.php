<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Blocks\BlockManager;
use App\Cms\Auth\SessionManager;
use App\Modules\Core\Services\MenuService;
use App\Cms\Fields\FieldType; // Added import
use App\Cms\Fields\Widget\WidgetRegistry;
use App\Cms\Assets\AssetManager;
use MonkeysLegion\Template\MLView;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\Response\JsonResponse;

/**
 * BlockTypeController - Admin UI for managing block types
 */
class BlockTypeController extends BaseAdminController
{
    public function __construct(
        MLView $view,
        MenuService $menuService,
        SessionManager $session,
        AssetManager $assetManager,
        private readonly BlockManager $blockManager,
        private readonly WidgetRegistry $widgetRegistry,
    ) {
        parent::__construct($view, $menuService, $session);
        $this->setAssetManager($assetManager);
    }

    /**
     * List all block types
     */
    #[Route('GET', '/admin/structure/block-types')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $types = $this->blockManager->getTypes();

        return $this->render('admin/structure/block_types/index', [
            'types' => $types,
            'title' => 'Block Types',
        ]);
    }

    /**
     * Create block type form
     */
    #[Route('GET', '/admin/structure/block-types/create')]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        return $this->render('admin/structure/block_types/form', [
            'type' => null,
            'title' => 'Create Block Type',
            'action' => '/admin/structure/block-types',
            'method' => 'POST'
        ]);
    }

    /**
     * Store new block type
     */
    #[Route('POST', '/admin/structure/block-types')]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        
        try {
            $this->blockManager->createDatabaseType($data);
            // Flash success? 
            return new RedirectResponse('/admin/structure/block-types');
        } catch (\Exception $e) {
            // Flash error?
            return $this->render('admin/structure/block_types/form', [
                'type' => $data,
                'error' => $e->getMessage(),
                'title' => 'Create Block Type',
                'action' => '/admin/structure/block-types',
                'method' => 'POST'
            ]);
        }
    }

    /**
     * Edit block type form
     */
    #[Route('GET', '/admin/structure/block-types/{id}/edit')]
    public function edit(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/block-types');
        }

        // Convert to array-like structure compatible with form
        $formData = [
            'label' => $type['label'],
            'id' => $type['id'],
            'description' => $type['description'] ?? '',
            'icon' => $type['icon'] ?? '🧱',
            'category' => $type['category'] ?? 'Custom',
            'cache_ttl' => $type['cache_ttl'] ?? 3600,
            'enabled' => $type['enabled'] ?? true,
        ];

        return $this->render('admin/structure/block_types/form', [
            'type' => $formData,
            'is_edit' => true,
            'title' => 'Edit Block Type',
            'action' => '/admin/structure/block-types/' . $id,
            'method' => 'POST'
        ]);
    }

    /**
     * Update block type
     */
    #[Route('POST', '/admin/structure/block-types/{id}')]
    public function update(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $type = $this->blockManager->getType($id);
        
        if (!$type || $type['source'] !== 'database') {
             return new RedirectResponse('/admin/structure/block-types');
        }

        try {
            $this->blockManager->updateDatabaseType($type['entity']->id, $data);
            return new RedirectResponse('/admin/structure/block-types');
        } catch (\Exception $e) {
             return $this->render('admin/structure/block_types/form', [
                'type' => $data,
                'is_edit' => true,
                'error' => $e->getMessage(),
                'title' => 'Edit Block Type',
                'action' => '/admin/structure/block-types/' . $id,
                'method' => 'POST'
            ]);
        }
    }
    
    /**
     * Delete block type
     */
    #[Route('POST', '/admin/structure/block-types/{id}/delete')]
    public function destroy(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        if ($type && $type['source'] === 'database') {
            $this->blockManager->deleteDatabaseType($type['entity']->id);
        }
        
        return new RedirectResponse('/admin/structure/block-types');
    }
    /**
     * Manage fields for a block type
     */
    #[Route('GET', '/admin/structure/block-types/{id}/fields')]
    public function fields(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        if (!$type) {
             return new RedirectResponse('/admin/structure/block-types');
        }

        return $this->render('admin/structure/block_types/fields', [
            'type' => $type,
            'fields' => $type['fields'],
            'base_url' => '/admin/structure/block-types/' . $id,
            'add_url' => '/admin/structure/block-types/' . $id . '/fields/add',
        ]);
    }

    /**
     * Manage form display settings (field ordering)
     */
    #[Route('GET', '/admin/structure/block-types/{id}/form-display')]
    public function formDisplay(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        if (!$type) {
             return new RedirectResponse('/admin/structure/block-types');
        }

        // Get fields and inject system fields
        $fields = $type['fields'];
        $weights = $type['entity']->default_settings['form_weights'] ?? [];

        // Add 'content' (Body)
        $fields['content'] = [
            'label' => 'Block Content',
            'widget' => 'wysiwyg', // or whatever is configured
            'weight' => $weights['content'] ?? -5, // Default to top
            'machine_name' => 'content'
        ];

        // Prepare fields for sorting by ensuring machine_name is set
        foreach ($fields as $key => &$field) {
            $field['machine_name'] = $field['machine_name'] ?? $key;
            // Use saved weight if available, otherwise fallback to existing or default
            $field['weight'] = $weights[$field['machine_name']] ?? $field['weight'] ?? 0;
        }
        unset($field);
        
        // DEBUG: Log weights before sort
        file_put_contents(__DIR__ . '/../../../../var/logs/debug_weights.log', "BlockTypeController formDisplay DEBUG:\nWeights loaded: " . json_encode($weights) . "\nFields before sort: " . json_encode(array_column($fields, 'weight', 'machine_name')) . "\n", FILE_APPEND);

        // Sort by weight
        uasort($fields, function ($a, $b) {
            $wa = $a['weight'];
            $wb = $b['weight'];
            
            if ($wa === $wb) return 0;
            return ($wa < $wb) ? -1 : 1;
        });

        return $this->render('admin/structure/block_types/form_display', [
            'type' => $type,
            'fields' => $fields,
            'base_url' => '/admin/structure/block-types/' . $id,
        ]);
    }

    /**
     * Save form display settings
     */
    /**
     * Save form display settings
     */
    #[Route('POST', '/admin/structure/block-types/{id}/form-display')]
    public function saveFormDisplay(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        $params = $request->getParsedBody();

        if ($type && $type['source'] === 'database' && !empty($params['weights'])) {
            try {
                $this->blockManager->saveFieldWeights($type['entity']->id, $params['weights'], 'form');
                
                // Return JSON if XHR request
                if ($this->isXhrRequest($request)) {
                    return new JsonResponse(['status' => 'success', 'message' => 'Weights saved']);
                }
            } catch (\Exception $e) {
                if ($this->isXhrRequest($request)) {
                    return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
                }
            }
        }
        
        return new RedirectResponse('/admin/structure/block-types/' . $id . '/form-display');
    }

    /**
     * Check if request is HTMX/XHR
     * Detects: HX-Request header (HTMX), X-Requested-With (XHR), or JSON Content-Type
     */
    private function isXhrRequest(ServerRequestInterface $request): bool
    {
        return $request->hasHeader('HX-Request')
            || strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest' 
            || str_contains($request->getHeaderLine('Content-Type'), 'application/json');
    }

    /**
     * Manage content display settings
     */
    #[Route('GET', '/admin/structure/block-types/{id}/display')]
    public function display(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        if (!$type) {
             return new RedirectResponse('/admin/structure/block-types');
        }

        // Get fields and inject system fields
        $fields = $type['fields'];
        $weights = $type['entity']->default_settings['display_weights'] ?? [];

        // Add 'title'
        $fields['title'] = [
            'label' => 'Display Title',
            'widget' => 'string',
            'weight' => $weights['title'] ?? -10,
            'machine_name' => 'title'
        ];

        // Add 'content'
        $fields['content'] = [
            'label' => 'Block Content',
            'widget' => 'html',
            'weight' => $weights['content'] ?? -5,
            'machine_name' => 'content'
        ];

        // Prepare fields for sorting by ensuring machine_name is set
        foreach ($fields as $key => &$field) {
            $field['machine_name'] = $field['machine_name'] ?? $key;
            // Use saved weight if available
            $field['weight'] = $weights[$field['machine_name']] ?? $field['weight'] ?? 0;
        }
        unset($field);

        // Sort by weight
        uasort($fields, function ($a, $b) {
            $wa = $a['weight'];
            $wb = $b['weight'];
            if ($wa === $wb) return 0;
            return ($wa < $wb) ? -1 : 1;
        });

        return $this->render('admin/structure/block_types/display', [
            'type' => $type,
            'fields' => $fields,
            'base_url' => '/admin/structure/block-types/' . $id,
        ]);
    }

    /**
     * Save content display settings
     */
    /**
     * Save content display settings
     */
    #[Route('POST', '/admin/structure/block-types/{id}/display')]
    public function saveDisplay(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        $params = $request->getParsedBody();

        if ($type && $type['source'] === 'database' && !empty($params['weights'])) {
            try {
                $this->blockManager->saveFieldWeights($type['entity']->id, $params['weights'], 'display');
                
                if ($this->isXhrRequest($request)) {
                    return new JsonResponse(['status' => 'success', 'message' => 'Weights saved']);
                }
            } catch (\Exception $e) {
                if ($this->isXhrRequest($request)) {
                    return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
                }
            }
        }

        return new RedirectResponse('/admin/structure/block-types/' . $id . '/display');
    }
    
    /**
     * Add field form
     */
    #[Route('GET', '/admin/structure/block-types/{id}/fields/add')]
    public function addField(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/block-types');
        }

        return $this->render('admin/structure/block_types/add_field', [
            'type' => $type,
            'grouped_types' => FieldType::getGrouped(),
            'action' => '/admin/structure/block-types/' . $id . '/fields',
            'cancel_url' => '/admin/structure/block-types/' . $id . '/fields',
        ]);
    }

    /**
     * Store new field
     */
    #[Route('POST', '/admin/structure/block-types/{id}/fields')]
    public function storeField(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $type = $this->blockManager->getType($id);

        if (!$type || $type['source'] !== 'database') {
             return new RedirectResponse('/admin/structure/block-types');
        }

        try {
            $field = $this->blockManager->addFieldToType($type['entity']->id, $data);
            return new RedirectResponse('/admin/structure/block-types/' . $id . '/fields/' . $field->machine_name . '/edit');
        } catch (\Exception $e) {
            return $this->render('admin/structure/block_types/add_field', [
                'type' => $type,
                'grouped_types' => FieldType::getGrouped(),
                'error' => $e->getMessage(),
                'action' => '/admin/structure/block-types/' . $id . '/fields',
                'cancel_url' => '/admin/structure/block-types/' . $id . '/fields',
            ]);
        }
    }

    /**
     * Delete field
     */
    #[Route('POST', '/admin/structure/block-types/{id}/fields/{fieldName}/delete')]
    public function deleteField(ServerRequestInterface $request, string $id, string $fieldName): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        
        if ($type && $type['source'] === 'database') {
            $this->blockManager->removeFieldFromType($type['entity']->id, $fieldName);
        }
        
        return new RedirectResponse('/admin/structure/block-types/' . $id . '/fields');
    }

    /**
     * Edit field form
     */
    #[Route('GET', '/admin/structure/block-types/{id}/fields/{fieldName}/edit')]
    public function editField(ServerRequestInterface $request, string $id, string $fieldName): ResponseInterface
    {
        $type = $this->blockManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/block-types');
        }

        $fields = $type['fields'];
        if (!isset($fields[$fieldName])) {
             return new RedirectResponse('/admin/structure/block-types/' . $id . '/fields');
        }
        
        $fieldDef = $fields[$fieldName];
        $currentWidgetId = $fieldDef['widget'] ?? $this->widgetRegistry->resolve(
            \App\Cms\Fields\FieldDefinition::fromArray($fieldDef)
        )->getId();

        $widget = $this->widgetRegistry->get($currentWidgetId);
        $settingsSchema = $widget ? $widget->getSettingsSchema() : [];
        $currentSettings = $fieldDef['settings'] ?? [];


        return $this->render('admin/structure/block_types/edit_field', [
            'type' => $type,
            'field' => $fieldDef,
            'machine_name' => $fieldName,
            'grouped_types' => FieldType::getGrouped(),
            'widget_options' => $this->widgetRegistry->getOptionsForType($fields[$fieldName]['type'] ?? 'string'),
            'widget_settings_schema' => $settingsSchema,
            'widget_settings' => $currentSettings,
            'action' => '/admin/structure/block-types/' . $id . '/fields/' . $fieldName . '/update',
            'cancel_url' => '/admin/structure/block-types/' . $id . '/fields',
        ]);
    }

    /**
     * Update field
     */
    #[Route('POST', '/admin/structure/block-types/{id}/fields/{fieldName}/update')]
    public function updateField(ServerRequestInterface $request, string $id, string $fieldName): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $type = $this->blockManager->getType($id);

        if (!$type || $type['source'] !== 'database') {
             return new RedirectResponse('/admin/structure/block-types');
        }

        try {
            $this->blockManager->updateFieldOnType($type['entity']->id, $fieldName, $data);
            return new RedirectResponse('/admin/structure/block-types/' . $id . '/fields');
        } catch (\Exception $e) {
            $fields = $type['fields'];
            return $this->render('admin/structure/block_types/edit_field', [
                'type' => $type,
                'field' => $fields[$fieldName] ?? [], // Fallback if somehow missing
                'machine_name' => $fieldName,
                'grouped_types' => FieldType::getGrouped(),
                'error' => $e->getMessage(),
                'action' => '/admin/structure/block-types/' . $id . '/fields/' . $fieldName . '/edit',
                'cancel_url' => '/admin/structure/block-types/' . $id . '/fields',
            ]);
        }
    }
}
