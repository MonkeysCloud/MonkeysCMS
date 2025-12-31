<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Security\PermissionService;
use App\Modules\Core\Entities\Role;
use App\Modules\Core\Entities\Permission;
use App\Cms\Repository\CmsRepository;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\RedirectResponse;

/**
 * PermissionPageController - Admin Interface for Permissions
 */
#[Route('/admin/permissions')]
final class PermissionPageController extends BaseAdminController
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly CmsRepository $repository,
        \MonkeysLegion\Template\MLView $view,
        \App\Modules\Core\Services\MenuService $menuService,
    ) {
        parent::__construct($view, $menuService);
    }

    /**
     * Show Permission Matrix
     */
    #[Route('GET', '/', name: 'admin.permissions.index')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        // 1. Get all Roles
        $roles = $this->repository->findAll(Role::class, ['weight' => 'DESC', 'name' => 'ASC']);

        // 2. Get all Permissions Grouped
        $groupedPermissions = $this->permissionService->getAllPermissionsGrouped();

        // 3. For each role, get assigned permission slugs for easy checking in view
        $rolePermissions = [];
        foreach ($roles as $role) {
            $perms = $this->permissionService->getRolePermissions($role);
            $rolePermissions[$role->id] = array_map(fn($p) => $p->slug, $perms);
        }

        // 4. Check for flash messages (simple query param for now)
        $query = $request->getQueryParams();
        $message = $query['message'] ?? null;
        $error = $query['error'] ?? null;

        return $this->render('admin.permissions.index', [
            'roles' => $roles,
            'groupedPermissions' => $groupedPermissions,
            'rolePermissions' => $rolePermissions, // Map[roleId] => [slug1, slug2...]
            'message' => $message,
            'error' => $error,
            'page_title' => 'Permissions',
        ]);
    }

    /**
     * Sync Permissions from Code
     */
    #[Route('POST', '/sync', name: 'admin.permissions.sync')]
    public function sync(): ResponseInterface
    {
        try {
            $stats = $this->permissionService->syncPermissions();
            $msg = "Permissions synced! Created: {$stats['created']}, Updated: {$stats['updated']}.";
            return new RedirectResponse('/admin/permissions?message=' . urlencode($msg));
        } catch (\Exception $e) {
            return new RedirectResponse('/admin/permissions?error=' . urlencode('Sync failed: ' . $e->getMessage()));
        }
    }

    /**
     * Save Permission Matrix
     */
    #[Route('POST', '/save', name: 'admin.permissions.save')]
    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        /** @var array<int, array<string, string>> $assignments */
        $assignments = $data['permissions'] ?? [];

        // Format of $assignments expected from form:
        // [ role_id => [ permission_id_1, permission_id_2 ... ] ]
        // OR standard HTML checkbox: permissions[role_id][] = permission_id

        try {
            $roles = $this->repository->findAll(Role::class);
            
            foreach ($roles as $role) {
                // Skip super admin or handle carefully? 
                // Usually super admin has all permissions implicitly, but we can store them too.
                
                $permissionIds = $assignments[$role->id] ?? [];
                
                // Convert to ints
                $permissionIds = array_map('intval', $permissionIds);
                
                $this->permissionService->setRolePermissions($role, $permissionIds);
            }

            return new RedirectResponse('/admin/permissions?message=' . urlencode('Permissions saved successfully.'));
        } catch (\Exception $e) {
            return new RedirectResponse('/admin/permissions?error=' . urlencode('Save failed: ' . $e->getMessage()));
        }
    }
}
