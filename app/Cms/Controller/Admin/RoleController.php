<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\User\PermissionRegistry;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * RoleController — Admin UI for role & permission management.
 */
#[RoutePrefix('/admin/roles')]
final class RoleController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly PDO $pdo,
    ) {}

    // ── List ────────────────────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::roles.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $roles = $this->pdo->query(
            'SELECT r.*, (SELECT COUNT(*) FROM cms_users u WHERE u.role_id = r.id) AS user_count
             FROM cms_roles r
             ORDER BY r.weight ASC, r.label ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        // Parse permissions JSON to count
        foreach ($roles as &$role) {
            $perms = json_decode($role['permissions'] ?? '[]', true) ?: [];
            $role['permission_count'] = count($perms);
            $role['permissions_list'] = $perms;
        }
        unset($role);

        return Response::html($this->renderer->render('admin::roles.index', [
            'title' => 'Roles & Permissions',
            'roles' => $roles,
        ]));
    }

    // ── Create ──────────────────────────────────────────────────────────

    #[Route('GET', '/create', name: 'admin::roles.create')]
    public function create(ServerRequestInterface $request): Response
    {
        return Response::html($this->renderer->render('admin::roles.form', [
            'title'             => 'Create Role',
            'role'              => null,
            'isNew'             => true,
            'errors'            => [],
            'permissionGroups'  => PermissionRegistry::all(),
            'grantedPermissions' => [],
        ]));
    }

    #[Route('POST', '/', name: 'admin::roles.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $errors = $this->validate($body, isNew: true);

        $selectedPerms = $body['permissions'] ?? [];
        if (isset($body['is_super_admin']) && $body['is_super_admin']) {
            $selectedPerms = ['*'];
        }

        if ($errors) {
            return Response::html($this->renderer->render('admin::roles.form', [
                'title'             => 'Create Role',
                'role'              => $body,
                'isNew'             => true,
                'errors'            => $errors,
                'permissionGroups'  => PermissionRegistry::all(),
                'grantedPermissions' => $selectedPerms,
            ]));
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO cms_roles (machine_name, label, description, permissions, is_super_admin, weight)
             VALUES (:machine_name, :label, :description, :permissions, :is_super_admin, :weight)'
        );
        $stmt->execute([
            'machine_name'   => $this->slugify($body['machine_name'] ?? $body['label']),
            'label'          => trim($body['label']),
            'description'    => trim($body['description'] ?? ''),
            'permissions'    => json_encode($selectedPerms),
            'is_super_admin' => isset($body['is_super_admin']) ? 1 : 0,
            'weight'         => (int) ($body['weight'] ?? 0),
        ]);

        return Response::redirect('/admin/roles');
    }

    // ── Edit ────────────────────────────────────────────────────────────

    #[Route('GET', '/{id:\d+}/edit', name: 'admin::roles.edit')]
    public function edit(ServerRequestInterface $request, string $id): Response
    {
        $role = $this->findRole((int) $id);
        if (!$role) {
            return Response::redirect('/admin/roles');
        }

        $grantedPerms = json_decode($role['permissions'] ?? '[]', true) ?: [];

        return Response::html($this->renderer->render('admin::roles.form', [
            'title'             => 'Edit Role: ' . $role['label'],
            'role'              => $role,
            'isNew'             => false,
            'errors'            => [],
            'permissionGroups'  => PermissionRegistry::all(),
            'grantedPermissions' => $grantedPerms,
        ]));
    }

    #[Route('POST', '/{id:\d+}', name: 'admin::roles.update')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        $roleId = (int) $id;
        $role = $this->findRole($roleId);
        if (!$role) {
            return Response::redirect('/admin/roles');
        }

        $body = $this->parseBody($request);
        $errors = $this->validate($body, isNew: false, currentId: $roleId);

        $selectedPerms = $body['permissions'] ?? [];
        if (isset($body['is_super_admin']) && $body['is_super_admin']) {
            $selectedPerms = ['*'];
        }

        if ($errors) {
            return Response::html($this->renderer->render('admin::roles.form', [
                'title'             => 'Edit Role: ' . ($body['label'] ?? $role['label']),
                'role'              => array_merge($role, $body),
                'isNew'             => false,
                'errors'            => $errors,
                'permissionGroups'  => PermissionRegistry::all(),
                'grantedPermissions' => $selectedPerms,
            ]));
        }

        $sets = [
            'label = :label',
            'description = :description',
            'permissions = :permissions',
            'is_super_admin = :is_super_admin',
            'weight = :weight',
        ];
        $bindings = [
            'id'             => $roleId,
            'label'          => trim($body['label']),
            'description'    => trim($body['description'] ?? ''),
            'permissions'    => json_encode($selectedPerms),
            'is_super_admin' => isset($body['is_super_admin']) ? 1 : 0,
            'weight'         => (int) ($body['weight'] ?? 0),
        ];

        // Allow machine_name update only for non-system roles
        if (!$role['is_system']) {
            $sets[] = 'machine_name = :machine_name';
            $bindings['machine_name'] = $this->slugify($body['machine_name'] ?? $body['label']);
        }

        $sql = 'UPDATE cms_roles SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($bindings);

        return Response::redirect('/admin/roles/' . $roleId . '/edit');
    }

    // ── Delete ──────────────────────────────────────────────────────────

    #[Route('POST', '/{id:\d+}/delete', name: 'admin::roles.delete')]
    public function delete(ServerRequestInterface $request, string $id): Response
    {
        $roleId = (int) $id;
        $role = $this->findRole($roleId);

        // Cannot delete system roles
        if (!$role || $role['is_system']) {
            return Response::redirect('/admin/roles');
        }

        // Check if users are assigned
        $userCount = $this->pdo->prepare('SELECT COUNT(*) FROM cms_users WHERE role_id = :id');
        $userCount->execute(['id' => $roleId]);
        if ((int) $userCount->fetchColumn() > 0) {
            // Redirect back — in production you'd show an error
            return Response::redirect('/admin/roles');
        }

        $this->pdo->prepare('DELETE FROM cms_roles WHERE id = :id')->execute(['id' => $roleId]);

        return Response::redirect('/admin/roles');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function findRole(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cms_roles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function validate(array $body, bool $isNew, ?int $currentId = null): array
    {
        $errors = [];

        if (empty(trim($body['label'] ?? ''))) {
            $errors['label'] = 'Role name is required.';
        }

        $machineName = $this->slugify($body['machine_name'] ?? $body['label'] ?? '');
        if ($machineName === '') {
            $errors['machine_name'] = 'Machine name is required.';
        } else {
            $sql = 'SELECT id FROM cms_roles WHERE machine_name = :mn';
            $bindings = ['mn' => $machineName];
            if ($currentId) {
                $sql .= ' AND id != :id';
                $bindings['id'] = $currentId;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);
            if ($stmt->fetch()) {
                $errors['machine_name'] = 'This machine name is already in use.';
            }
        }

        return $errors;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value);
        return trim($value, '_');
    }

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody() ?? [];
        if (empty($body)) {
            $stream = $request->getBody();
            $stream->rewind();
            $raw = $stream->getContents();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
        return $body;
    }
}
