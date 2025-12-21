<?php

declare(strict_types=1);

namespace App\Cms\User;

use App\Cms\Entity\EntityManager;
use App\Cms\Entity\EntityQuery;
use App\Cms\Entity\ScopedRepository;

/**
 * UserRepository - Specialized repository for users
 *
 * @extends ScopedRepository<User>
 */
class UserRepository extends ScopedRepository
{
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, User::class);
    }

    /**
     * Define query scopes
     */
    protected function scopes(): array
    {
        return [
            'active' => fn(EntityQuery $q) => $q->where('status', UserStatus::ACTIVE),
            'blocked' => fn(EntityQuery $q) => $q->where('status', UserStatus::BLOCKED),
            'pending' => fn(EntityQuery $q) => $q->where('status', UserStatus::PENDING),
            'verified' => fn(EntityQuery $q) => $q->whereNotNull('email_verified_at'),
            'unverified' => fn(EntityQuery $q) => $q->whereNull('email_verified_at'),
            'recent' => fn(EntityQuery $q) => $q->orderBy('created_at', 'DESC')->limit(10),
        ];
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => strtolower($email)]);
    }

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }

    /**
     * Find user by email or username
     */
    public function findByEmailOrUsername(string $identifier): ?User
    {
        return $this->createQuery()
            ->where('email', strtolower($identifier))
            ->orWhere('username', $identifier)
            ->first();
    }

    /**
     * Find user by remember token
     */
    public function findByRememberToken(string $token): ?User
    {
        return $this->findOneBy(['remember_token' => $token]);
    }

    /**
     * Find active users
     *
     * @return User[]
     */
    public function findActive(): array
    {
        return $this->withScope('active')->get();
    }

    /**
     * Find users by role
     *
     * @return User[]
     */
    public function findByRole(string $role): array
    {
        $em = $this->getEntityManager();
        $db = $em->getConnection();

        $stmt = $db->prepare("
            SELECT u.* FROM users u
            INNER JOIN user_roles ur ON ur.user_id = u.id
            INNER JOIN roles r ON r.id = ur.role_id
            WHERE r.machine_name = :role AND u.deleted_at IS NULL
            ORDER BY u.created_at DESC
        ");
        $stmt->execute(['role' => $role]);

        $users = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $users[] = User::fromDatabase($row);
        }

        return $users;
    }

    /**
     * Check if email exists
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->createQuery()->where('email', strtolower($email));

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Check if username exists
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $query = $this->createQuery()->where('username', $username);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Search users
     *
     * @return User[]
     */
    public function search(string $term, int $limit = 20): array
    {
        return $this->createQuery()
            ->where('status', UserStatus::ACTIVE)
            ->whereRaw(
                "(username LIKE :term OR email LIKE :term OR display_name LIKE :term)",
                ['term' => "%{$term}%"]
            )
            ->orderBy('username')
            ->limit($limit)
            ->get();
    }

    /**
     * Get status counts
     *
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $db = $this->getEntityManager()->getConnection();

        $stmt = $db->query("
            SELECT status, COUNT(*) as count 
            FROM users 
            WHERE deleted_at IS NULL 
            GROUP BY status
        ");

        $counts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get recent logins
     *
     * @return User[]
     */
    public function getRecentLogins(int $limit = 10): array
    {
        return $this->createQuery()
            ->whereNotNull('last_login_at')
            ->orderBy('last_login_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Load user with roles
     */
    public function findWithRoles(int $id): ?User
    {
        $user = $this->find($id);

        if ($user) {
            $this->loadRoles($user);
        }

        return $user;
    }

    /**
     * Load roles for user
     */
    public function loadRoles(User $user): void
    {
        $db = $this->getEntityManager()->getConnection();

        $stmt = $db->prepare("
            SELECT r.machine_name FROM roles r
            INNER JOIN user_roles ur ON ur.role_id = r.id
            WHERE ur.user_id = :user_id
            ORDER BY r.weight
        ");
        $stmt->execute(['user_id' => $user->getId()]);

        $user->setRoles($stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Load permissions for user
     */
    public function loadPermissions(User $user): void
    {
        $db = $this->getEntityManager()->getConnection();

        $stmt = $db->prepare("
            SELECT DISTINCT p.machine_name FROM permissions p
            INNER JOIN role_permissions rp ON rp.permission_id = p.id
            INNER JOIN user_roles ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $user->getId()]);

        $user->setPermissions($stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Load full user with roles and permissions
     */
    public function findFull(int $id): ?User
    {
        $user = $this->find($id);

        if ($user) {
            $this->loadRoles($user);
            $this->loadPermissions($user);
        }

        return $user;
    }

    /**
     * Assign role to user
     */
    public function assignRole(User $user, string $role): void
    {
        $db = $this->getEntityManager()->getConnection();

        $stmt = $db->prepare("
            INSERT IGNORE INTO user_roles (user_id, role_id)
            SELECT :user_id, id FROM roles WHERE machine_name = :role
        ");
        $stmt->execute(['user_id' => $user->getId(), 'role' => $role]);
    }

    /**
     * Remove role from user
     */
    public function removeRole(User $user, string $role): void
    {
        $db = $this->getEntityManager()->getConnection();

        $stmt = $db->prepare("
            DELETE ur FROM user_roles ur
            INNER JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :user_id AND r.machine_name = :role
        ");
        $stmt->execute(['user_id' => $user->getId(), 'role' => $role]);
    }

    /**
     * Sync user roles
     *
     * @param string[] $roles
     */
    public function syncRoles(User $user, array $roles): void
    {
        $db = $this->getEntityManager()->getConnection();

        // Remove all current roles
        $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user->getId()]);

        // Add new roles
        foreach ($roles as $role) {
            $this->assignRole($user, $role);
        }
    }
}
