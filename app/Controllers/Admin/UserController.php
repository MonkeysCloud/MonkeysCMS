<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Entities\User;
use App\Cms\Repository\CmsRepository;
use App\Cms\Security\PermissionService;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * UserController - Admin API for user management
 */
final class UserController
{
    public function __construct(
        private readonly CmsRepository $repository,
        private readonly PermissionService $permissions,
    ) {
    }

    /**
     * List users with pagination
     */
    #[Route('GET', '/admin/users')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $page = (int) ($request->getQueryParams()['page'] ?? 1);
        $perPage = (int) ($request->getQueryParams()['per_page'] ?? 20);
        $status = $request->getQueryParams()['status'] ?? null;
        $search = $request->getQueryParams()['q'] ?? null;

        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }

        if ($search) {
            $users = $this->repository->search(User::class, $search, ['email', 'username', 'display_name'], $perPage * 2);
            $total = count($users);
            $users = array_slice($users, ($page - 1) * $perPage, $perPage);

            return json([
                'data' => array_map(fn($u) => $u->toArray(), $users),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ]);
        }

        $result = $this->repository->paginate(
            User::class,
            $page,
            $perPage,
            $criteria,
            ['created_at' => 'DESC']
        );

        // Load roles for each user
        foreach ($result['data'] as $user) {
            $this->permissions->loadUserRoles($user);
        }

        return json([
            'data' => array_map(fn($u) => array_merge($u->toArray(), [
                'roles' => array_map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug, 'color' => $r->color], $u->roles),
            ]), $result['data']),
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total_pages' => $result['total_pages'],
        ]);
    }

    /**
     * Get single user
     */
    #[Route('GET', '/admin/users/{id}')]
    public function show(int $id): ResponseInterface
    {
        $user = $this->repository->find(User::class, $id);

        if (!$user) {
            return json(['error' => 'User not found'], 404);
        }

        $this->permissions->loadUserRoles($user);

        return json(array_merge($user->toArray(), [
            'roles' => array_map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'color' => $r->color,
            ], $user->roles),
            'permissions' => array_map(fn($p) => $p->slug, $user->getAllPermissions()),
        ]));
    }

    /**
     * Create user
     */
    #[Route('POST', '/admin/users')]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        // Validate required fields
        $errors = $this->validateUserData($data, true);
        if (!empty($errors)) {
            return json(['errors' => $errors], 422);
        }

        // Check email uniqueness
        $existing = $this->repository->findOneBy(User::class, ['email' => $data['email']]);
        if ($existing) {
            return json(['errors' => ['email' => 'Email already exists']], 422);
        }

        // Check username uniqueness
        $existing = $this->repository->findOneBy(User::class, ['username' => $data['username']]);
        if ($existing) {
            return json(['errors' => ['username' => 'Username already exists']], 422);
        }

        $user = new User();
        $user->email = $data['email'];
        $user->username = $data['username'];
        $user->setPassword($data['password']);
        $user->display_name = $data['display_name'] ?? $data['username'];
        $user->first_name = $data['first_name'] ?? '';
        $user->last_name = $data['last_name'] ?? '';
        $user->status = $data['status'] ?? 'active';
        $user->locale = $data['locale'] ?? 'en';
        $user->timezone = $data['timezone'] ?? 'UTC';

        $this->repository->save($user);

        // Assign roles
        if (!empty($data['role_ids'])) {
            $this->permissions->setUserRoles($user, $data['role_ids']);
        }

        return json([
            'success' => true,
            'message' => 'User created successfully',
            'user' => $user->toArray(),
        ], 201);
    }

    /**
     * Update user
     */
    #[Route('PUT', '/admin/users/{id}')]
    public function update(int $id, ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->repository->find(User::class, $id);

        if (!$user) {
            return json(['error' => 'User not found'], 404);
        }

        $data = json_decode((string) $request->getBody(), true) ?? [];

        // Validate
        $errors = $this->validateUserData($data, false);
        if (!empty($errors)) {
            return json(['errors' => $errors], 422);
        }

        // Check email uniqueness
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $existing = $this->repository->findOneBy(User::class, ['email' => $data['email']]);
            if ($existing) {
                return json(['errors' => ['email' => 'Email already exists']], 422);
            }
            $user->email = $data['email'];
        }

        // Check username uniqueness
        if (isset($data['username']) && $data['username'] !== $user->username) {
            $existing = $this->repository->findOneBy(User::class, ['username' => $data['username']]);
            if ($existing) {
                return json(['errors' => ['username' => 'Username already exists']], 422);
            }
            $user->username = $data['username'];
        }

        // Update fields
        if (isset($data['password']) && !empty($data['password'])) {
            $user->setPassword($data['password']);
        }
        if (isset($data['display_name'])) {
            $user->display_name = $data['display_name'];
        }
        if (isset($data['first_name'])) {
            $user->first_name = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $user->last_name = $data['last_name'];
        }
        if (isset($data['status'])) {
            $user->status = $data['status'];
        }
        if (isset($data['locale'])) {
            $user->locale = $data['locale'];
        }
        if (isset($data['timezone'])) {
            $user->timezone = $data['timezone'];
        }
        if (isset($data['avatar'])) {
            $user->avatar = $data['avatar'];
        }

        $this->repository->save($user);

        // Update roles
        if (isset($data['role_ids'])) {
            $this->permissions->setUserRoles($user, $data['role_ids']);
        }

        return json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => $user->toArray(),
        ]);
    }

    /**
     * Delete user
     */
    #[Route('DELETE', '/admin/users/{id}')]
    public function delete(int $id): ResponseInterface
    {
        $user = $this->repository->find(User::class, $id);

        if (!$user) {
            return json(['error' => 'User not found'], 404);
        }

        // Prevent self-deletion
        $currentUser = $this->permissions->getCurrentUser();
        if ($currentUser && $currentUser->id === $user->id) {
            return json(['error' => 'Cannot delete your own account'], 400);
        }

        // Remove role assignments
        $this->permissions->setUserRoles($user, []);

        // Delete user
        $this->repository->delete($user);

        return json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Get user's roles
     */
    #[Route('GET', '/admin/users/{id}/roles')]
    public function getRoles(int $id): ResponseInterface
    {
        $user = $this->repository->find(User::class, $id);

        if (!$user) {
            return json(['error' => 'User not found'], 404);
        }

        $this->permissions->loadUserRoles($user);

        return json([
            'user_id' => $user->id,
            'roles' => array_map(fn($r) => $r->toArray(), $user->roles),
        ]);
    }

    /**
     * Set user's roles
     */
    #[Route('PUT', '/admin/users/{id}/roles')]
    public function setRoles(int $id, ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->repository->find(User::class, $id);

        if (!$user) {
            return json(['error' => 'User not found'], 404);
        }

        $data = json_decode((string) $request->getBody(), true) ?? [];
        $roleIds = $data['role_ids'] ?? [];

        $this->permissions->setUserRoles($user, $roleIds);

        return json([
            'success' => true,
            'message' => 'User roles updated',
        ]);
    }

    /**
     * Get user's permissions
     */
    #[Route('GET', '/admin/users/{id}/permissions')]
    public function getPermissions(int $id): ResponseInterface
    {
        $user = $this->repository->find(User::class, $id);

        if (!$user) {
            return json(['error' => 'User not found'], 404);
        }

        $this->permissions->loadUserRoles($user);
        $allPermissions = $user->getAllPermissions();

        return json([
            'user_id' => $user->id,
            'permissions' => array_map(fn($p) => $p->toArray(), $allPermissions),
        ]);
    }

    /**
     * Validate user data
     */
    private function validateUserData(array $data, bool $isNew): array
    {
        $errors = [];

        if ($isNew) {
            if (empty($data['email'])) {
                $errors['email'] = 'Email is required';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }

            if (empty($data['username'])) {
                $errors['username'] = 'Username is required';
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data['username'])) {
                $errors['username'] = 'Username must be 3-50 alphanumeric characters or underscores';
            }

            if (empty($data['password'])) {
                $errors['password'] = 'Password is required';
            } elseif (strlen($data['password']) < 8) {
                $errors['password'] = 'Password must be at least 8 characters';
            }
        } else {
            if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }

            if (isset($data['username']) && !preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data['username'])) {
                $errors['username'] = 'Username must be 3-50 alphanumeric characters or underscores';
            }

            if (isset($data['password']) && !empty($data['password']) && strlen($data['password']) < 8) {
                $errors['password'] = 'Password must be at least 8 characters';
            }
        }

        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive', 'blocked', 'pending'])) {
            $errors['status'] = 'Invalid status';
        }

        return $errors;
    }
}
