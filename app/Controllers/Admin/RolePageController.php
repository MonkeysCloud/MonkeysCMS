<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Repository\CmsRepository;
use App\Modules\Core\Entities\Role;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\RedirectResponse;

/**
 * RolePageController - Admin Interface for Roles
 */
#[Route('/admin/roles')]
final class RolePageController extends BaseAdminController
{
    public function __construct(
        private readonly CmsRepository $repository,
        \MonkeysLegion\Template\MLView $view,
        \App\Modules\Core\Services\MenuService $menuService,
    ) {
        parent::__construct($view, $menuService);
    }

    /**
     * List all roles
     */
    #[Route('GET', '/', name: 'admin.roles.index')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $roles = $this->repository->findAll(Role::class, ['weight' => 'ASC', 'name' => 'ASC']);
        
        $query = $request->getQueryParams();
        return $this->render('admin.roles.index', [
            'roles' => $roles,
            'message' => $query['message'] ?? null,
            'error' => $query['error'] ?? null,
            'page_title' => 'Roles',
        ]);
    }

    /**
     * Show create form
     */
    #[Route('GET', '/create', name: 'admin.roles.create')]
    public function create(): ResponseInterface
    {
        return $this->render('admin.roles.form', [
            'role' => null,
            'page_title' => 'Create Role',
        ]);
    }

    /**
     * Store new role
     */
    #[Route('POST', '/create', name: 'admin.roles.store')]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return $this->render('admin.roles.form', [
                'role' => null,
                'error' => 'Name is required',
                'old' => $data,
                'page_title' => 'Create Role',
            ]);
        }

        // Generate slug if empty
        $slug = $data['slug'] ?? '';
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $data['name']));
        }

        // Check uniqueness
        $existing = $this->repository->findOneBy(Role::class, ['slug' => $slug]);
        if ($existing) {
             return $this->render('admin.roles.form', [
                'role' => null,
                'error' => 'Role with this slug already exists',
                'old' => $data,
                'page_title' => 'Create Role',
            ]);
        }

        $role = new Role();
        $role->name = $data['name'];
        $role->slug = $slug;
        $role->description = $data['description'] ?? '';
        $role->color = $data['color'] ?? '#6b7280';
        $role->weight = (int) ($data['weight'] ?? 0);
        $role->is_system = false; // Only create custom roles via UI
        $role->is_default = isset($data['is_default']); // Checkbox

        $this->repository->save($role);

        return new RedirectResponse('/admin/roles?message=' . urlencode('Role created successfully'));
    }

    /**
     * Show edit form
     */
    #[Route('GET', '/{id}/edit', name: 'admin.roles.edit')]
    public function edit(int $id): ResponseInterface
    {
        $role = $this->repository->find(Role::class, $id);

        if (!$role) {
            return new RedirectResponse('/admin/roles?error=' . urlencode('Role not found'));
        }

        return $this->render('admin.roles.form', [
            'role' => $role,
            'page_title' => 'Edit Role: ' . $role->name,
        ]);
    }

    /**
     * Update role
     */
    #[Route('POST', '/{id}/edit', name: 'admin.roles.update')]
    public function update(int $id, ServerRequestInterface $request): ResponseInterface
    {
        $role = $this->repository->find(Role::class, $id);

        if (!$role) {
            return new RedirectResponse('/admin/roles?error=' . urlencode('Role not found'));
        }

        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return $this->render('admin.roles.form', [
                'role' => $role,
                'error' => 'Name is required',
                'page_title' => 'Edit Role: ' . $role->name,
            ]);
        }

        // Validate slug change if applicable
        if (!$role->is_system) {
             $slug = $data['slug'] ?? $role->slug;
             if ($slug !== $role->slug) {
                 $existing = $this->repository->findOneBy(Role::class, ['slug' => $slug]);
                 if ($existing && $existing->id !== $role->id) {
                     return $this->render('admin.roles.form', [
                        'role' => $role,
                        'error' => 'Slug is already taken by another role',
                        'page_title' => 'Edit Role: ' . $role->name,
                    ]);
                 }
                 $role->slug = $slug;
             }
             $role->name = $data['name'];
             $role->description = $data['description'] ?? '';
             $role->color = $data['color'] ?? $role->color;
             $role->weight = (int) ($data['weight'] ?? 0);
             $role->is_default = isset($data['is_default']);
        } else {
             // For system roles, only allow changing description, color, weight
             $role->description = $data['description'] ?? $role->description;
             $role->color = $data['color'] ?? $role->color;
             $role->weight = (int) ($data['weight'] ?? 0);
             // Cannot change name, slug, or is_system
        }

        $this->repository->save($role);

        return new RedirectResponse('/admin/roles?message=' . urlencode('Role updated successfully'));
    }

    /**
     * Delete role
     */
    #[Route('POST', '/{id}/delete', name: 'admin.roles.delete')]
    public function delete(int $id): ResponseInterface
    {
        $role = $this->repository->find(Role::class, $id);

        if (!$role) {
            return new RedirectResponse('/admin/roles?error=' . urlencode('Role not found'));
        }

        if ($role->is_system) {
            return new RedirectResponse('/admin/roles?error=' . urlencode('Cannot delete system roles'));
        }

        $this->repository->delete($role);

        return new RedirectResponse('/admin/roles?message=' . urlencode('Role deleted successfully'));
    }

    /**
     * Reorder Roles (Drag & Drop)
     */
    #[Route('POST', '/reorder', name: 'admin.roles.reorder')]
    public function reorder(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);
        $orderedIds = $data['terms'] ?? []; // 'terms' key used by global JS

        if (!empty($orderedIds)) {
            foreach ($orderedIds as $index => $id) {
                $role = $this->repository->find(Role::class, (int)$id);
                if ($role) {
                    $role->weight = $index;
                    $this->repository->save($role);
                }
            }
        }

        return new \Laminas\Diactoros\Response\JsonResponse(['status' => 'ok']);
    }
}
