<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Entities\User;
use App\Modules\Core\Entities\Role;
use App\Modules\Core\Entities\Media; // Corrected path
use App\Cms\Repository\CmsRepository;
use App\Cms\Security\PermissionService;
use App\Modules\Core\Services\MenuService;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Template\MLView;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\RedirectResponse;

/**
 * UsersPageController - Admin HTML pages for user management
 */
final class UsersPageController extends BaseAdminController
{
    public function __construct(
        private readonly CmsRepository $repository,
        private readonly PermissionService $permissions,
        MLView $view,
        MenuService $menuService,
    ) {
        parent::__construct($view, $menuService);
    }

    /**
     * List all users with pagination and search
     */
    #[Route('GET', '/admin/users')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $page = (int) ($request->getQueryParams()['page'] ?? 1);
        $perPage = 20;
        $search = $request->getQueryParams()['q'] ?? null;
        $status = $request->getQueryParams()['status'] ?? null;

        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }

        if ($search) {
            $users = $this->repository->search(
                User::class,
                $search,
                ['email', 'username', 'display_name'],
                $perPage * 3
            );
            $total = count($users);
            $users = array_slice($users, ($page - 1) * $perPage, $perPage);
            $totalPages = (int) ceil($total / $perPage);
        } else {
            $result = $this->repository->paginate(
                User::class,
                $page,
                $perPage,
                $criteria,
                ['created_at' => 'DESC']
            );
            $users = $result['data'];
            $total = $result['total'];
            $totalPages = $result['total_pages'];
        }

        // Load roles for each user
        foreach ($users as $user) {
            $this->permissions->loadUserRoles($user);
        }

        // Get all roles for filter dropdown
        $roles = $this->repository->findAll(Role::class, ['weight' => 'DESC']);

        return $this->render('admin.users.index', [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'search' => $search,
            'status' => $status,
            'roles' => $roles,
        ]);
    }

    /**
     * Show user detail
     */
    #[Route('GET', '/admin/users/{id}/view')]
    public function show(int $id): ResponseInterface
    {
        $user = $this->repository->find(User::class, $id);

        if (!$user) {
            return $this->redirect('/admin/users');
        }

        $this->permissions->loadUserRoles($user);

        return $this->render('admin.users.show', [
            'user' => $user,
        ]);
    }

    /**
     * Create user form
     */
    #[Route('GET', '/admin/users/create')]
    public function create(): ResponseInterface
    {
        $roles = $this->repository->findAll(Role::class, ['weight' => 'DESC']);

        return $this->render('admin.users.form', [
            'user' => null,
            'roles' => $roles,
            'old' => [],
            'errors' => [],
        ]);
    }

    /**
     * Store new user
     */
    #[Route('POST', '/admin/users/store')]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        if (empty($data)) {
            $data = $_POST;
        }

        $errors = $this->validateUserData($data, true);

        // Check email uniqueness
        if (empty($errors['email'])) {
            $existing = $this->repository->findOneBy(User::class, ['email' => $data['email']]);
            if ($existing) {
                $errors['email'] = 'Email already exists';
            }
        }

        // Check username uniqueness
        if (empty($errors['username'])) {
            $existing = $this->repository->findOneBy(User::class, ['username' => $data['username']]);
            if ($existing) {
                $errors['username'] = 'Username already exists';
            }
        }

        if (!empty($errors)) {
            $roles = $this->repository->findAll(Role::class, ['weight' => 'DESC']);
            return $this->render('admin.users.form', [
                'user' => null,
                'roles' => $roles,
                'old' => $data,
                'errors' => $errors,
            ]);
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
        $user->timezone = $data['timezone'] ?? 'UTC';
        
        // Handle avatar (ID or URL)
        if (!empty($data['avatar'])) {
            if (is_numeric($data['avatar'])) {
                $media = $this->repository->find(Media::class, (int)$data['avatar']);
                $user->avatar = $media ? $media->getUrl() : null;
            } else {
                $user->avatar = $data['avatar'];
            }
        } else {
            $user->avatar = null;
        }

        $this->repository->save($user);

        // Assign roles
        if (!empty($data['role_ids'])) {
            $this->permissions->setUserRoles($user, array_map('intval', $data['role_ids']));
        }

        return $this->redirect('/admin/users?success=created');
    }

    /**
     * Edit user form
     */
    #[Route('GET', '/admin/users/{id}/edit')]
    public function edit(int $id): ResponseInterface
    {
        $user = $this->repository->find(User::class, $id);

        if (!$user) {
            return $this->redirect('/admin/users');
        }

        $this->permissions->loadUserRoles($user);
        $roles = $this->repository->findAll(Role::class, ['weight' => 'DESC']);

        return $this->render('admin.users.form', [
            'user' => $user,
            'roles' => $roles,
            'old' => [],
            'errors' => [],
        ]);
    }

    /**
     * Update user
     */
    #[Route('POST', '/admin/users/{id}/update')]
    public function update(int $id, ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->repository->find(User::class, $id);

        if (!$user) {
            return $this->redirect('/admin/users');
        }

        $data = (array) $request->getParsedBody();
        if (empty($data)) {
            $data = $_POST;
        }

        $errors = $this->validateUserData($data, false);

        // Check email uniqueness
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $existing = $this->repository->findOneBy(User::class, ['email' => $data['email']]);
            if ($existing) {
                $errors['email'] = 'Email already exists';
            }
        }

        // Check username uniqueness
        if (isset($data['username']) && $data['username'] !== $user->username) {
            $existing = $this->repository->findOneBy(User::class, ['username' => $data['username']]);
            if ($existing) {
                $errors['username'] = 'Username already exists';
            }
        }

        if (!empty($errors)) {
            $this->permissions->loadUserRoles($user);
            $roles = $this->repository->findAll(Role::class, ['weight' => 'DESC']);
            return $this->render('admin.users.form', [
                'user' => $user,
                'roles' => $roles,
                'old' => $data,
                'errors' => $errors,
            ]);
        }

        // Update fields
        if (!empty($data['email'])) {
            $user->email = $data['email'];
        }
        if (!empty($data['username'])) {
            $user->username = $data['username'];
        }
        if (!empty($data['password'])) {
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
             if (is_numeric($data['avatar'])) {
                $media = $this->repository->find(Media::class, (int)$data['avatar']);
                $user->avatar = $media ? $media->getUrl() : null;
            } else {
                $user->avatar = !empty($data['avatar']) ? $data['avatar'] : null;
            }
        }

        $this->repository->save($user);

        // Update roles
        if (isset($data['role_ids'])) {
            $this->permissions->setUserRoles($user, array_map('intval', $data['role_ids']));
        } else {
            // If no roles submitted, clear all roles
            $this->permissions->setUserRoles($user, []);
        }

        return $this->redirect('/admin/users/' . $id . '/view?success=updated');
    }

    /**
     * Delete user
     */
    #[Route('GET', '/admin/users/{id}/delete')]
    public function delete(int $id): ResponseInterface
    {
        $user = $this->repository->find(User::class, $id);

        if (!$user) {
            return $this->redirect('/admin/users');
        }

        // Prevent self-deletion
        $currentUser = $this->permissions->getCurrentUser();
        if ($currentUser && $currentUser->id === $user->id) {
            return $this->redirect('/admin/users?error=self-delete');
        }

        // Remove role assignments
        $this->permissions->setUserRoles($user, []);

        // Delete user
        $this->repository->delete($user);

        return $this->redirect('/admin/users?success=deleted');
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
            if (isset($data['email']) && !empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }

            if (isset($data['username']) && !empty($data['username']) && !preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data['username'])) {
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
