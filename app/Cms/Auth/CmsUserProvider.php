<?php

declare(strict_types=1);

namespace App\Cms\Auth;

use App\Cms\User\User;
use App\Cms\User\UserRepository;
use App\Cms\Entity\EntityManager;
use MonkeysLegion\Auth\Contract\UserProviderInterface;
use MonkeysLegion\Auth\Contract\AuthenticatableInterface;

/**
 * CmsUserProvider - Implements UserProviderInterface for MonkeysLegion-Auth
 * 
 * Bridges the CMS User entity with the authentication library.
 */
class CmsUserProvider implements UserProviderInterface
{
    private EntityManager $em;
    private \PDO $db;
    private UserRepository $repository;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
        $this->db = $em->getConnection();
        $this->repository = new UserRepository($em);
    }

    // =========================================================================
    // UserProviderInterface Implementation
    // =========================================================================

    /**
     * Find user by unique identifier (ID)
     */
    public function findById(int|string $id): ?AuthenticatableInterface
    {
        $user = $this->repository->find((int) $id);
        
        if ($user) {
            $this->loadRolesAndPermissions($user);
        }

        return $user;
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?AuthenticatableInterface
    {
        $user = $this->repository->findByEmail($email);
        
        if ($user) {
            $this->loadRolesAndPermissions($user);
        }

        return $user;
    }

    /**
     * Find user by credentials (email or username)
     */
    public function findByCredentials(string $identifier): ?AuthenticatableInterface
    {
        $user = $this->repository->findByEmailOrUsername($identifier);
        
        if ($user) {
            $this->loadRolesAndPermissions($user);
        }

        return $user;
    }

    /**
     * Validate user credentials
     */
    public function validateCredentials(AuthenticatableInterface $user, string $password): bool
    {
        return $user->verifyPassword($password);
    }

    /**
     * Get token version for user (for token invalidation)
     */
    public function getTokenVersion(int|string $userId): int
    {
        $stmt = $this->db->prepare("SELECT token_version FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return (int) ($row['token_version'] ?? 1);
    }

    /**
     * Increment token version (invalidates all existing tokens)
     */
    public function incrementTokenVersion(int|string $userId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET token_version = token_version + 1 WHERE id = :id"
        );
        $stmt->execute(['id' => $userId]);
    }

    // =========================================================================
    // Extended Methods for CMS
    // =========================================================================

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?User
    {
        $user = $this->repository->findByUsername($username);
        
        if ($user) {
            $this->loadRolesAndPermissions($user);
        }

        return $user;
    }

    /**
     * Find user by remember token
     */
    public function findByRememberToken(string $token): ?User
    {
        $user = $this->repository->findOneBy(['remember_token' => $token]);
        
        if ($user) {
            $this->loadRolesAndPermissions($user);
        }

        return $user;
    }

    /**
     * Save user
     */
    public function save(User $user): void
    {
        $this->em->save($user);
    }

    /**
     * Delete user
     */
    public function delete(User $user): void
    {
        $this->em->delete($user);
    }

    // =========================================================================
    // Role Management
    // =========================================================================

    /**
     * Load roles and permissions for user
     */
    public function loadRolesAndPermissions(User $user): void
    {
        // Load roles
        $stmt = $this->db->prepare("
            SELECT r.machine_name FROM roles r
            INNER JOIN user_roles ur ON ur.role_id = r.id
            WHERE ur.user_id = :user_id
            ORDER BY r.weight
        ");
        $stmt->execute(['user_id' => $user->getId()]);
        $user->setRoles($stmt->fetchAll(\PDO::FETCH_COLUMN));

        // Load permissions
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.machine_name FROM permissions p
            INNER JOIN role_permissions rp ON rp.permission_id = p.id
            INNER JOIN user_roles ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $user->getId()]);
        $user->setPermissions($stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Assign role to user
     */
    public function assignRole(User $user, string $roleName): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO user_roles (user_id, role_id)
            SELECT :user_id, id FROM roles WHERE machine_name = :role
        ");
        $stmt->execute(['user_id' => $user->getId(), 'role' => $roleName]);
        
        $this->loadRolesAndPermissions($user);
    }

    /**
     * Remove role from user
     */
    public function removeRole(User $user, string $roleName): void
    {
        $stmt = $this->db->prepare("
            DELETE ur FROM user_roles ur
            INNER JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :user_id AND r.machine_name = :role
        ");
        $stmt->execute(['user_id' => $user->getId(), 'role' => $roleName]);
        
        $this->loadRolesAndPermissions($user);
    }

    /**
     * Sync user roles
     * 
     * @param string[] $roles Role machine names
     */
    public function syncRoles(User $user, array $roles): void
    {
        // Remove all current roles
        $stmt = $this->db->prepare("DELETE FROM user_roles WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user->getId()]);

        // Add new roles
        foreach ($roles as $role) {
            $this->assignRole($user, $role);
        }
    }

    // =========================================================================
    // Password Reset
    // =========================================================================

    /**
     * Store password reset token
     */
    public function storePasswordResetToken(int $userId, string $tokenHash, \DateTimeImmutable $expires): void
    {
        // Delete old tokens for this user
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);

        // Insert new token
        $stmt = $this->db->prepare("
            INSERT INTO password_resets (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expires->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Find user by password reset token
     */
    public function findUserByResetToken(string $tokenHash): ?int
    {
        $stmt = $this->db->prepare("
            SELECT user_id FROM password_resets 
            WHERE token_hash = :token_hash AND expires_at > NOW()
        ");
        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? (int) $row['user_id'] : null;
    }

    /**
     * Delete password reset token
     */
    public function deletePasswordResetToken(string $tokenHash): void
    {
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token_hash = :token_hash");
        $stmt->execute(['token_hash' => $tokenHash]);
    }

    // =========================================================================
    // Two-Factor Authentication
    // =========================================================================

    /**
     * Store 2FA secret for user
     */
    public function store2FASecret(int $userId, ?string $secret): void
    {
        $stmt = $this->db->prepare("
            UPDATE users SET two_factor_secret = :secret WHERE id = :id
        ");
        $stmt->execute(['id' => $userId, 'secret' => $secret]);
    }

    /**
     * Get 2FA secret for user
     */
    public function get2FASecret(int $userId): ?string
    {
        $stmt = $this->db->prepare("SELECT two_factor_secret FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row['two_factor_secret'] ?? null;
    }

    /**
     * Store 2FA recovery codes
     */
    public function store2FARecoveryCodes(int $userId, array $codes): void
    {
        $stmt = $this->db->prepare("
            UPDATE users SET two_factor_recovery_codes = :codes WHERE id = :id
        ");
        $stmt->execute([
            'id' => $userId,
            'codes' => json_encode($codes),
        ]);
    }

    /**
     * Get 2FA recovery codes
     */
    public function get2FARecoveryCodes(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT two_factor_recovery_codes FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return json_decode($row['two_factor_recovery_codes'] ?? '[]', true);
    }

    /**
     * Check if user has 2FA enabled
     */
    public function has2FAEnabled(int $userId): bool
    {
        return $this->get2FASecret($userId) !== null;
    }

    // =========================================================================
    // Email Verification
    // =========================================================================

    /**
     * Mark email as verified
     */
    public function markEmailVerified(int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE users SET email_verified_at = NOW(), status = 'active' 
            WHERE id = :id AND status = 'pending'
        ");
        $stmt->execute(['id' => $userId]);
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified(int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT email_verified_at FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row['email_verified_at'] !== null;
    }

    // =========================================================================
    // Sessions
    // =========================================================================

    /**
     * Store user session
     */
    public function storeSession(string $sessionId, int $userId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (id, user_id, ip_address, user_agent, payload, last_activity)
            VALUES (:id, :user_id, :ip, :ua, :payload, :activity)
            ON DUPLICATE KEY UPDATE
                payload = VALUES(payload),
                last_activity = VALUES(last_activity)
        ");
        $stmt->execute([
            'id' => $sessionId,
            'user_id' => $userId,
            'ip' => $data['ip'] ?? null,
            'ua' => $data['user_agent'] ?? null,
            'payload' => json_encode($data),
            'activity' => time(),
        ]);
    }

    /**
     * Get user sessions
     */
    public function getUserSessions(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM user_sessions 
            WHERE user_id = :user_id 
            ORDER BY last_activity DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Delete user session
     */
    public function deleteSession(string $sessionId): void
    {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = :id");
        $stmt->execute(['id' => $sessionId]);
    }

    /**
     * Delete all user sessions
     */
    public function deleteAllSessions(int $userId): void
    {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
    }

    // =========================================================================
    // Statistics
    // =========================================================================

    /**
     * Get recent logins
     */
    public function getRecentLogins(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.username, u.last_login_at, u.last_login_ip
            FROM users u
            WHERE u.last_login_at IS NOT NULL
            ORDER BY u.last_login_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get login count by date range
     */
    public function getLoginStats(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $stmt = $this->db->prepare("
            SELECT DATE(last_login_at) as date, COUNT(*) as count
            FROM users
            WHERE last_login_at BETWEEN :start AND :end
            GROUP BY DATE(last_login_at)
            ORDER BY date
        ");
        $stmt->execute([
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
