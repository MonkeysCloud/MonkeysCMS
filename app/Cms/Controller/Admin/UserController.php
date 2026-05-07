<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Log\ActivityLogger;
use App\Cms\User\PermissionRegistry;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * UserController — Admin UI for CMS user management.
 */
#[RoutePrefix('/admin/users')]
final class UserController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly PDO $pdo,
        private readonly SessionManager $session,
        private readonly ActivityLogger $activity,
    ) {}

    // ── List ────────────────────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::users.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $search = trim($params['search'] ?? '');
        $roleFilter = $params['role'] ?? '';
        $statusFilter = $params['status'] ?? '';
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        // Build query
        $where = [];
        $bindings = [];

        if ($search !== '') {
            $where[] = '(u.name LIKE :search OR u.email LIKE :search)';
            $bindings['search'] = "%{$search}%";
        }

        if ($roleFilter !== '') {
            $where[] = 'u.role_id = :role_id';
            $bindings['role_id'] = (int) $roleFilter;
        }

        if ($statusFilter === 'active') {
            $where[] = 'u.active = 1';
        } elseif ($statusFilter === 'inactive') {
            $where[] = 'u.active = 0';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM cms_users u {$whereClause}");
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetchColumn();

        // Fetch users with role
        $sql = "SELECT u.*, r.label AS role_label, r.machine_name AS role_machine_name
                FROM cms_users u
                LEFT JOIN cms_roles r ON r.id = u.role_id
                {$whereClause}
                ORDER BY u.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // All roles for filter
        $roles = $this->pdo->query('SELECT id, label FROM cms_roles ORDER BY weight, label')
            ->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = (int) ceil($total / $perPage);

        return Response::html($this->renderer->render('admin::users.index', [
            'title'        => 'Users',
            'users'        => $users,
            'roles'        => $roles,
            'search'       => $search,
            'roleFilter'   => $roleFilter,
            'statusFilter' => $statusFilter,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'total'        => $total,
            'perPage'      => $perPage,
        ]));
    }

    // ── Create ──────────────────────────────────────────────────────────

    #[Route('GET', '/create', name: 'admin::users.create')]
    public function create(ServerRequestInterface $request): Response
    {
        $roles = $this->pdo->query('SELECT id, label FROM cms_roles ORDER BY weight, label')
            ->fetchAll(PDO::FETCH_ASSOC);

        return Response::html($this->renderer->render('admin::users.form', [
            'title'  => 'Create User',
            'user'   => null,
            'roles'  => $roles,
            'isNew'  => true,
            'errors' => [],
        ]));
    }

    #[Route('POST', '/', name: 'admin::users.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $errors = $this->validate($body, isNew: true);

        if ($errors) {
            $roles = $this->pdo->query('SELECT id, label FROM cms_roles ORDER BY weight, label')
                ->fetchAll(PDO::FETCH_ASSOC);

            return Response::html($this->renderer->render('admin::users.form', [
                'title'  => 'Create User',
                'user'   => $body,
                'roles'  => $roles,
                'isNew'  => true,
                'errors' => $errors,
            ]));
        }

        // Handle avatar upload
        $avatarPath = $this->handleAvatarUpload($request);

        $stmt = $this->pdo->prepare(
            'INSERT INTO cms_users (name, email, password, role_id, active, locale, timezone, avatar)
             VALUES (:name, :email, :password, :role_id, :active, :locale, :timezone, :avatar)'
        );
        $stmt->execute([
            'name'     => trim($body['name']),
            'email'    => strtolower(trim($body['email'])),
            'password' => password_hash($body['password'], PASSWORD_ARGON2ID),
            'role_id'  => (int) $body['role_id'],
            'active'   => isset($body['active']) ? 1 : 0,
            'locale'   => $body['locale'] ?? 'en',
            'timezone' => $body['timezone'] ?? null,
            'avatar'   => $avatarPath,
        ]);

        $newUserId = (int) $this->pdo->lastInsertId();

        $this->activity->setContext($request);
        $this->activity->log('created', 'user', $newUserId, trim($body['name']), [
            'email'   => strtolower(trim($body['email'])),
            'role_id' => (int) $body['role_id'],
        ]);

        return Response::redirect('/admin/users');
    }

    // ── Edit ────────────────────────────────────────────────────────────

    #[Route('GET', '/{id:\d+}/edit', name: 'admin::users.edit')]
    public function edit(ServerRequestInterface $request, string $id): Response
    {
        $user = $this->findUser((int) $id);
        if (!$user) {
            return Response::redirect('/admin/users');
        }

        $roles = $this->pdo->query('SELECT id, label FROM cms_roles ORDER BY weight, label')
            ->fetchAll(PDO::FETCH_ASSOC);

        return Response::html($this->renderer->render('admin::users.form', [
            'title'  => 'Edit User: ' . $user['name'],
            'user'   => $user,
            'roles'  => $roles,
            'isNew'  => false,
            'errors' => [],
        ]));
    }

    #[Route('POST', '/{id:\d+}', name: 'admin::users.update')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        $userId = (int) $id;
        $user = $this->findUser($userId);
        if (!$user) {
            return Response::redirect('/admin/users');
        }

        $body = $this->parseBody($request);
        $errors = $this->validate($body, isNew: false, currentId: $userId);

        if ($errors) {
            $roles = $this->pdo->query('SELECT id, label FROM cms_roles ORDER BY weight, label')
                ->fetchAll(PDO::FETCH_ASSOC);

            return Response::html($this->renderer->render('admin::users.form', [
                'title'  => 'Edit User: ' . ($body['name'] ?? $user['name']),
                'user'   => array_merge($user, $body),
                'roles'  => $roles,
                'isNew'  => false,
                'errors' => $errors,
            ]));
        }

        // Handle avatar upload
        $avatarPath = $this->handleAvatarUpload($request);

        $sets = [
            'name = :name',
            'email = :email',
            'role_id = :role_id',
            'active = :active',
            'locale = :locale',
            'timezone = :timezone',
        ];
        $bindings = [
            'id'       => $userId,
            'name'     => trim($body['name']),
            'email'    => strtolower(trim($body['email'])),
            'role_id'  => (int) $body['role_id'],
            'active'   => isset($body['active']) ? 1 : 0,
            'locale'   => $body['locale'] ?? 'en',
            'timezone' => $body['timezone'] ?? null,
        ];

        // Update password only if provided
        if (!empty($body['password'])) {
            $sets[] = 'password = :password';
            $bindings['password'] = password_hash($body['password'], PASSWORD_ARGON2ID);
        }

        // Update avatar if new one uploaded
        if ($avatarPath !== null) {
            $sets[] = 'avatar = :avatar';
            $bindings['avatar'] = $avatarPath;
        }

        $sql = 'UPDATE cms_users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        $this->activity->setContext($request);
        $this->activity->log('updated', 'user', $userId, trim($body['name']), [
            'email'   => strtolower(trim($body['email'])),
            'role_id' => (int) $body['role_id'],
        ]);

        return Response::redirect('/admin/users/' . $userId . '/edit');
    }

    // ── Delete ──────────────────────────────────────────────────────────

    #[Route('POST', '/{id:\d+}/delete', name: 'admin::users.delete')]
    public function delete(ServerRequestInterface $request, string $id): Response
    {
        $userId = (int) $id;
        $currentUserId = $this->session->get('cms_user_id');

        // Cannot delete yourself
        if ($userId === $currentUserId) {
            return Response::redirect('/admin/users');
        }

        // Cannot delete last admin
        if ($this->isLastAdmin($userId)) {
            return Response::redirect('/admin/users');
        }

        $user = $this->findUser($userId);
        $this->pdo->prepare('DELETE FROM cms_users WHERE id = :id')->execute(['id' => $userId]);

        $this->activity->setContext($request);
        $this->activity->log('deleted', 'user', $userId, $user['name'] ?? "#{$userId}");

        return Response::redirect('/admin/users');
    }

    // ── Toggle Active (AJAX) ────────────────────────────────────────────

    #[Route('POST', '/{id:\d+}/toggle-active', name: 'admin::users.toggle_active')]
    public function toggleActive(ServerRequestInterface $request, string $id): Response
    {
        $userId = (int) $id;
        $currentUserId = $this->session->get('cms_user_id');

        // Cannot deactivate yourself
        if ($userId === $currentUserId) {
            return Response::json(['error' => 'Cannot deactivate your own account'], 422);
        }

        $user = $this->findUser($userId);
        if (!$user) {
            return Response::json(['error' => 'User not found'], 404);
        }

        $newStatus = $user['active'] ? 0 : 1;
        $this->pdo->prepare('UPDATE cms_users SET active = :active WHERE id = :id')
            ->execute(['active' => $newStatus, 'id' => $userId]);

        return Response::json([
            'success' => true,
            'active'  => (bool) $newStatus,
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function findUser(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, r.label AS role_label
             FROM cms_users u
             LEFT JOIN cms_roles r ON r.id = u.role_id
             WHERE u.id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function validate(array $body, bool $isNew, ?int $currentId = null): array
    {
        $errors = [];

        if (empty(trim($body['name'] ?? ''))) {
            $errors['name'] = 'Name is required.';
        }

        $email = strtolower(trim($body['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email is required.';
        } else {
            // Check uniqueness
            $sql = 'SELECT id FROM cms_users WHERE email = :email';
            $bindings = ['email' => $email];
            if ($currentId) {
                $sql .= ' AND id != :id';
                $bindings['id'] = $currentId;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);
            if ($stmt->fetch()) {
                $errors['email'] = 'This email is already in use.';
            }
        }

        if ($isNew && empty($body['password'])) {
            $errors['password'] = 'Password is required for new users.';
        }

        if (!empty($body['password']) && strlen($body['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if (empty($body['role_id'])) {
            $errors['role_id'] = 'A role must be selected.';
        }

        return $errors;
    }

    private function isLastAdmin(int $userId): bool
    {
        // Find the admin role
        $adminRole = $this->pdo->query(
            "SELECT id FROM cms_roles WHERE is_super_admin = 1 LIMIT 1"
        )->fetchColumn();

        if (!$adminRole) {
            return false;
        }

        $user = $this->findUser($userId);
        if (!$user || (int) $user['role_id'] !== (int) $adminRole) {
            return false;
        }

        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM cms_users WHERE role_id = :role_id AND active = 1'
        );
        $count->execute(['role_id' => $adminRole]);

        return (int) $count->fetchColumn() <= 1;
    }

    private function handleAvatarUpload(ServerRequestInterface $request): ?string
    {
        $files = $request->getUploadedFiles();
        $avatar = $files['avatar'] ?? null;

        if (!$avatar || $avatar->getError() !== UPLOAD_ERR_OK) {
            return null;
        }

        // Validate type
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime = $avatar->getClientMediaType();
        if (!in_array($mime, $allowed, true)) {
            return null;
        }

        // Private directory for avatars (not publicly browsable)
        $dir = dirname(__DIR__, 4) . '/storage/avatars';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => 'jpg',
        };

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $avatar->moveTo($dir . '/' . $filename);

        return $filename;
    }

    // ── Serve Avatar (private) ──────────────────────────────────────────

    #[Route('GET', '/{id:\d+}/avatar', name: 'admin::users.avatar')]
    public function avatar(ServerRequestInterface $request, string $id): Response
    {
        $user = $this->findUser((int) $id);
        if (!$user || empty($user['avatar'])) {
            // Return a 1x1 transparent PNG
            return new Response(
                status: 204,
                headers: ['Content-Type' => 'image/png'],
            );
        }

        $path = dirname(__DIR__, 4) . '/storage/avatars/' . $user['avatar'];
        if (!file_exists($path)) {
            return new Response(status: 404);
        }

        $mime = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'application/octet-stream',
        };

        return new Response(
            status: 200,
            headers: [
                'Content-Type'  => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ],
            body: file_get_contents($path),
        );
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
