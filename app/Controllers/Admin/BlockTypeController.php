<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Blocks\BlockManager;
use App\Cms\Auth\SessionManager;
use App\Modules\Core\Services\MenuService;
use App\Cms\Fields\FieldType; // Added import
use App\Cms\Assets\AssetManager;
use MonkeysLegion\Template\MLView;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\RedirectResponse;

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
            $this->blockManager->addFieldToType($type['entity']->id, $data);
            return new RedirectResponse('/admin/structure/block-types/' . $id . '/fields');
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
}
