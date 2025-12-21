<?php

declare(strict_types=1);

namespace App\Cms\User;

use App\Cms\Entity\EntityManager;
use App\Cms\Entity\EntityQuery;

/**
 * UserManager - User operations manager
 * 
 * Provides high-level operations for managing users:
 * - User CRUD
 * - Role management
 * - Password operations
 * - User queries
 */
class UserManager
{
    private EntityManager $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    // =========================================================================
    // CRUD Operations
    // =========================================================================

    /**
     * Create a new user
     */
    public function create(array $data): User
    {
        $user = new User();
        $user->setEmail($data['email'] ?? '');
        $user->setUsername($data['username'] ?? '');
        
        if (isset($data['password'])) {
            $user->setPassword($data['password']);
        }
        
        if (isset($data['display_name'])) {
            $user->setDisplayName($data['display_name']);
        }
        
        if (isset($data['status'])) {
            $user->setStatus($data['status']);
        }

        $this->em->save($user);

        // Assign default role
        $defaultRole = $data['role'] ?? 'authenticated';
        $this->assignRole($user, $defaultRole);

        return $user;
    }

    /**
     * Update a user
     */
    public function update(User $user, array $data): User
    {
        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }
        
        if (isset($data['username'])) {
            $user->setUsername($data['username']);
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $user->setPassword($data['password']);
        }
        
        if (isset($data['display_name'])) {
            $user->setDisplayName($data['display_name']);
        }
        
        if (isset($data['avatar'])) {
            $user->setAvatar($data['avatar']);
        }
        
        if (isset($data['bio'])) {
            $user->setBio($data['bio']);
        }
        
        if (isset($data['status'])) {
            $user->setStatus($data['status']);
        }

        $this->em->save($user);

        return $user;
    }

    /**
     * Delete a user (soft delete)
     */
    public function delete(User $user): void
    {
        $this->em->delete($user);
    }

    /**
     * Force delete a user (permanent)
     */
    public function forceDelete(User $user): void
    {
        // Remove role assignments
        $this->removeAllRoles($user);
        
        $this->em->forceDelete($user);
    }

    // =========================================================================
    // Finding Users
    // =========================================================================

    /**
     * Find user by ID
     */
    public function find(int $id): ?User
    {
        /** @var User|null $user */
        $user = $this->em->find(User::class, $id);
        
        if ($user) {
            $this->loadRoles($user);
        }

        return $user;
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = $this->em->findOneBy(User::class, ['email' => strtolower(trim($email))]);
        
        if ($user) {
            $this->loadRoles($user);
        }

        return $user;
    }

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?User
    {
        /** @var User|null $user */
        $user = $this->em->findOneBy(User::class, ['username' => $username]);
        
        if ($user) {
            $this->loadRoles($user);
        }

        return $user;
    }

    /**
     * Find user by email or username (for login)
     */
    public function findByCredential(string $credential): ?User
    {
        $user = $this->findByEmail($credential);
        
        if (!$user) {
            $user = $this->findByUsername($credential);
        }

        return $user;
    }

    /**
     * Find user by remember token
     */
    public function findByRememberToken(string $token): ?User
    {
        /** @var User|null $user */
        $user = $this->em->findOneBy(User::class, ['remember_token' => $token]);
        
        if ($user) {
            $this->loadRoles($user);
        }

        return $user;
    }

    /**
     * Get all users
     * 
     * @return User[]
     */
    public function all(): array
    {
        $users = $this->em->all(User::class);
        
        foreach ($users as $user) {
            $this->loadRoles($user);
        }

        return $users;
    }

    /**
     * Get paginated users
     */
    public function paginate(int $page = 1, int $perPage = 15, array $filters = []): array
    {
        $query = $this->query()->orderBy('created_at', 'DESC');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereRaw(
                "(email LIKE :search OR username LIKE :search OR display_name LIKE :search)",
                ['search' => "%{$search}%"]
            );
        }

        $result = $query->paginate($perPage, $page);

        foreach ($result['data'] as $user) {
            $this->loadRoles($user);
        }

        return $result;
    }

    /**
     * Create a query builder
     */
    public function query(): EntityQuery
    {
        return $this->em->query(User::class);
    }

    // =========================================================================
    // Role Management
    // =========================================================================

    /**
     * Load roles for a user
     */
    public function loadRoles(User $user): void
    {
        $stmt = $this->em->getConnection()->prepare("
            SELECT r.machine_name 
            FROM roles r
            INNER JOIN user_roles ur ON ur.role_id = r.id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $user->getId()]);
        
        $roles = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $user->setRoles($roles);
    }

    /**
     * Assign a role to a user
     */
    public function assignRole(User $user, string $roleMachineName): void
    {
        $roleId = $this->getRoleId($roleMachineName);
        
        if (!$roleId) {
            throw new \InvalidArgumentException("Role not found: {$roleMachineName}");
        }

        $stmt = $this->em->getConnection()->prepare("
            INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)
        ");
        $stmt->execute(['user_id' => $user->getId(), 'role_id' => $roleId]);

        // Reload roles
        $this->loadRoles($user);
    }

    /**
     * Remove a role from a user
     */
    public function removeRole(User $user, string $roleMachineName): void
    {
        $roleId = $this->getRoleId($roleMachineName);
        
        if (!$roleId) {
            return;
        }

        $stmt = $this->em->getConnection()->prepare("
            DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id
        ");
        $stmt->execute(['user_id' => $user->getId(), 'role_id' => $roleId]);

        // Reload roles
        $this->loadRoles($user);
    }

    /**
     * Sync user roles (replace all)
     */
    public function syncRoles(User $user, array $roleMachineNames): void
    {
        // Remove all current roles
        $this->removeAllRoles($user);

        // Add new roles
        foreach ($roleMachineNames as $role) {
            $this->assignRole($user, $role);
        }
    }

    /**
     * Remove all roles from a user
     */
    public function removeAllRoles(User $user): void
    {
        $stmt = $this->em->getConnection()->prepare("
            DELETE FROM user_roles WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $user->getId()]);

        $user->setRoles([]);
    }

    /**
     * Get role ID by machine name
     */
    private function getRoleId(string $machineName): ?int
    {
        $stmt = $this->em->getConnection()->prepare("
            SELECT id FROM roles WHERE machine_name = :name
        ");
        $stmt->execute(['name' => $machineName]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Get users by role
     * 
     * @return User[]
     */
    public function findByRole(string $roleMachineName): array
    {
        $roleId = $this->getRoleId($roleMachineName);
        
        if (!$roleId) {
            return [];
        }

        $stmt = $this->em->getConnection()->prepare("
            SELECT u.* FROM users u
            INNER JOIN user_roles ur ON ur.user_id = u.id
            WHERE ur.role_id = :role_id AND u.deleted_at IS NULL
        ");
        $stmt->execute(['role_id' => $roleId]);

        $users = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $user = User::fromDatabase($row);
            $this->loadRoles($user);
            $users[] = $user;
        }

        return $users;
    }

    // =========================================================================
    // Password Operations
    // =========================================================================

    /**
     * Change user password
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->setPassword($newPassword);
        $this->em->save($user);
    }

    /**
     * Verify and update password if needed
     */
    public function verifyAndRehashPassword(User $user, string $plainPassword): bool
    {
        if (!$user->verifyPassword($plainPassword)) {
            return false;
        }

        // Rehash if needed
        if ($user->needsRehash()) {
            $user->setPassword($plainPassword);
            $this->em->save($user);
        }

        return true;
    }

    /**
     * Generate password reset token
     */
    public function createPasswordResetToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        
        // Delete any existing tokens
        $stmt = $this->em->getConnection()->prepare("
            DELETE FROM password_resets WHERE email = :email
        ");
        $stmt->execute(['email' => $user->getEmail()]);

        // Create new token
        $stmt = $this->em->getConnection()->prepare("
            INSERT INTO password_resets (email, token) VALUES (:email, :token)
        ");
        $stmt->execute([
            'email' => $user->getEmail(),
            'token' => hash('sha256', $token),
        ]);

        return $token;
    }

    /**
     * Verify password reset token
     */
    public function verifyPasswordResetToken(string $email, string $token): bool
    {
        $stmt = $this->em->getConnection()->prepare("
            SELECT * FROM password_resets 
            WHERE email = :email AND token = :token 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([
            'email' => $email,
            'token' => hash('sha256', $token),
        ]);

        return $stmt->fetch() !== false;
    }

    /**
     * Delete password reset token
     */
    public function deletePasswordResetToken(string $email): void
    {
        $stmt = $this->em->getConnection()->prepare("
            DELETE FROM password_resets WHERE email = :email
        ");
        $stmt->execute(['email' => $email]);
    }

    // =========================================================================
    // Account Operations
    // =========================================================================

    /**
     * Activate a user account
     */
    public function activate(User $user): void
    {
        $user->activate();
        $this->em->save($user);
    }

    /**
     * Block a user account
     */
    public function block(User $user): void
    {
        $user->block();
        $this->em->save($user);
    }

    /**
     * Verify user email
     */
    public function verifyEmail(User $user): void
    {
        $user->verifyEmail();
        $user->activate();
        $this->em->save($user);
    }

    /**
     * Record user login
     */
    public function recordLogin(User $user, ?string $ip = null): void
    {
        $user->recordLogin($ip);
        $this->em->save($user);
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Check if email is available
     */
    public function isEmailAvailable(string $email, ?int $excludeUserId = null): bool
    {
        $query = $this->query()->where('email', strtolower(trim($email)));
        
        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return !$query->exists();
    }

    /**
     * Check if username is available
     */
    public function isUsernameAvailable(string $username, ?int $excludeUserId = null): bool
    {
        $query = $this->query()->where('username', $username);
        
        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return !$query->exists();
    }

    // =========================================================================
    // Statistics
    // =========================================================================

    /**
     * Get user statistics
     */
    public function getStatistics(): array
    {
        $connection = $this->em->getConnection();

        // Count by status
        $stmt = $connection->query("
            SELECT status, COUNT(*) as count FROM users WHERE deleted_at IS NULL GROUP BY status
        ");
        $statusCounts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $statusCounts[$row['status']] = (int) $row['count'];
        }

        // Count by role
        $stmt = $connection->query("
            SELECT r.machine_name, COUNT(ur.user_id) as count 
            FROM roles r
            LEFT JOIN user_roles ur ON ur.role_id = r.id
            GROUP BY r.id, r.machine_name
        ");
        $roleCounts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $roleCounts[$row['machine_name']] = (int) $row['count'];
        }

        // Recent registrations
        $stmt = $connection->query("
            SELECT COUNT(*) FROM users 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) AND deleted_at IS NULL
        ");
        $recentCount = (int) $stmt->fetchColumn();

        return [
            'total' => array_sum($statusCounts),
            'active' => $statusCounts[UserStatus::ACTIVE] ?? 0,
            'blocked' => $statusCounts[UserStatus::BLOCKED] ?? 0,
            'pending' => $statusCounts[UserStatus::PENDING] ?? 0,
            'by_role' => $roleCounts,
            'recent_7_days' => $recentCount,
        ];
    }

    /**
     * Get the entity manager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->em;
    }
}
