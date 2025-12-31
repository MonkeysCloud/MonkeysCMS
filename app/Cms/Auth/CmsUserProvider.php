<?php

declare(strict_types=1);

namespace App\Cms\Auth;

use App\Modules\Core\Entities\User;
use MonkeysLegion\Auth\Contract\UserProviderInterface;
use MonkeysLegion\Auth\Contract\AuthenticatableInterface;
use App\Cms\Entity\EntityManager; // Keep purely for constructor compatibility if registered that way

/**
 * CmsUserProvider - Implements UserProviderInterface for MonkeysLegion-Auth
 *
 * Bridges the CMS User entity with the authentication library.
 * Now uses App\Modules\Core\Entities\User directly to ensure compatibility with PermissionService.
 */
class CmsUserProvider implements UserProviderInterface
{
    private \PDO $db;

    public function __construct(EntityManager $em) // Keep signature for DI compatibility
    {
        $this->db = $em->getConnection();
    }

    // =========================================================================
    // UserProviderInterface Implementation
    // =========================================================================

    /**
     * Find user by unique identifier (ID)
     */
    public function findById(int|string $id): ?AuthenticatableInterface
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $user = new User();
        $user->hydrate($row);
        $this->loadRolesAndPermissions($user);

        return $user;
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?AuthenticatableInterface
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email AND deleted_at IS NULL");
        $stmt->execute(['email' => strtolower($email)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $user = new User();
        $user->hydrate($row);
        $this->loadRolesAndPermissions($user);

        return $user;
    }

    /**
     * Find user by credentials (email or username)
     */
    public function findByCredentials(array $credentials): ?AuthenticatableInterface
    {
        // Try to find a suitable identifier from the credentials
        $identifier = $credentials['email'] ?? $credentials['username'] ?? $credentials['identifier'] ?? null;

        // If not found by standard keys, try to find first non-password field
        if (!$identifier) {
            foreach ($credentials as $key => $value) {
                if (!str_contains($key, 'password')) {
                    $identifier = $value;
                    break;
                }
            }
        }

        if (!$identifier) {
            return null;
        }

        return $this->findByEmailOrUsername($identifier);
    }

    /**
     * Validate user credentials
     */
    public function validateCredentials(AuthenticatableInterface $user, string $password): bool
    {
        return password_verify($password, $user->getAuthPassword());
    }

    /**
     * Create a new user.
     * 
     * @param array<string, mixed> $attributes
     * @throws \RuntimeException If creation fails
     */
    public function create(array $attributes): AuthenticatableInterface
    {
        try {
            // Prepare basic fields
            $username = trim($attributes['username'] ?? '');
            $email = strtolower(trim($attributes['email'] ?? ''));
            $passwordHash = isset($attributes['password']) 
                ? password_hash($attributes['password'], PASSWORD_ARGON2ID) 
                : ($attributes['password_hash'] ?? '');
            
            $displayName = $attributes['display_name'] ?? $username;
            $status = $attributes['status'] ?? 'pending';
            $now = date('Y-m-d H:i:s');

            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, display_name, status, created_at, updated_at)
                VALUES (:username, :email, :hash, :name, :status, :now, :now)
            ");
            
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'hash' => $passwordHash,
                'name' => $displayName,
                'status' => $status,
                'now' => $now
            ]);

            $id = $this->db->lastInsertId();
            
            return $this->findById($id);

        } catch (\Throwable $e) {
            throw new \RuntimeException('User creation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update user's password hash.
     */
    public function updatePassword(int|string $userId, string $passwordHash): void
    {
        $stmt = $this->db->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $userId, 'hash' => $passwordHash]);
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
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username AND deleted_at IS NULL");
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $user = new User();
        $user->hydrate($row);
        $this->loadRolesAndPermissions($user);

        return $user;
    }

    public function findByEmailOrUsername(string $identifier): ?User
    {
        $stmt = $this->db->prepare("
            SELECT * FROM users 
            WHERE (email = :email OR username = :username) 
            AND deleted_at IS NULL
        ");
        $stmt->execute(['email' => strtolower($identifier), 'username' => $identifier]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $user = new User();
        $user->hydrate($row);
        $this->loadRolesAndPermissions($user);

        return $user;
    }

    /**
     * Find user by remember token
     */
    public function findByRememberToken(string $token): ?User
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE remember_token = :token AND deleted_at IS NULL");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $user = new User();
        $user->hydrate($row);
        $this->loadRolesAndPermissions($user);

        return $user;
    }

    /**
     * Save user
     */
    public function save(User $user): void
    {
        // Simple save for minimal fields we manage. 
        // ideally should use CmsRepository if complex saving is needed, but we are breaking dependency.
        // For Auth, we mostly care about password, remember_token, timestamps.
        
        $data = $user->toArray();
        // Construct UPDATE query dynamically or explicit fields
        // Since User extends BaseEntity, using a Repository is better for "save".
        // But preventing circular dependency or legacy repo usage is key.
        // We really only need to save specific fields updated by Auth logic (token, login time, etc.)
        
        // If we strictly only update auth fields:
        $stmt = $this->db->prepare("
            UPDATE users SET 
                remember_token = :remember_token,
                last_login_at = :last_login_at,
                last_login_ip = :last_login_ip,
                email_verified_at = :email_verified_at,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            'remember_token' => $user->remember_token,
            'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
            'last_login_ip' => $user->last_login_ip,
            'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
            'status' => $user->status,
            'id' => $user->id
        ]);
    }

    /**
     * Delete user
     */
    public function delete(User $user): void
    {
        $stmt = $this->db->prepare("UPDATE users SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $user->getId()]);
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
            SELECT r.* FROM roles r
            INNER JOIN user_roles ur ON ur.role_id = r.id
            WHERE ur.user_id = :user_id
            ORDER BY r.weight DESC
        ");
        $stmt->execute(['user_id' => $user->getId()]);
        
        $roles = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $role = new \App\Modules\Core\Entities\Role();
            $role->hydrate($row);
            
            // Load permissions for this role
            $stmtPerms = $this->db->prepare("
                SELECT p.* FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = :role_id
            ");
            $stmtPerms->execute(['role_id' => $role->id]);
            
            while ($pRow = $stmtPerms->fetch(\PDO::FETCH_ASSOC)) {
                $perm = new \App\Modules\Core\Entities\Permission();
                $perm->hydrate($pRow);
                $role->permissions[] = $perm;
            }
            
            $roles[] = $role;
        }
        
        $user->roles = $roles;
        $user->directPermissions = [];
    }

    /**
     * Assign role to user
     */
    public function assignRole(User $user, string $roleName): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO user_roles (user_id, role_id)
            SELECT :user_id, id FROM roles WHERE slug = :role
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
            WHERE ur.user_id = :user_id AND r.slug = :role
        ");
        $stmt->execute(['user_id' => $user->getId(), 'role' => $roleName]);

        $this->loadRolesAndPermissions($user);
    }

    /**
     * Sync user roles
     *
     * @param string[] $roles Role slugs
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

    public function deletePasswordResetToken(string $tokenHash): void
    {
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token_hash = :token_hash");
        $stmt->execute(['token_hash' => $tokenHash]);
    }

    // =========================================================================
    // Two-Factor Authentication
    // =========================================================================

    public function store2FASecret(int $userId, ?string $secret): void
    {
        $stmt = $this->db->prepare("
            UPDATE users SET two_factor_secret = :secret WHERE id = :id
        ");
        $stmt->execute(['id' => $userId, 'secret' => $secret]);
    }

    public function get2FASecret(int $userId): ?string
    {
        $stmt = $this->db->prepare("SELECT two_factor_secret FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row['two_factor_secret'] ?? null;
    }

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

    public function get2FARecoveryCodes(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT two_factor_recovery_codes FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return json_decode($row['two_factor_recovery_codes'] ?? '[]', true);
    }

    public function has2FAEnabled(int $userId): bool
    {
        return $this->get2FASecret($userId) !== null;
    }

    // =========================================================================
    // Email Verification
    // =========================================================================

    public function markEmailVerified(int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE users SET email_verified_at = NOW(), status = 'active' 
            WHERE id = :id AND status = 'pending'
        ");
        $stmt->execute(['id' => $userId]);
    }

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

    public function deleteSession(string $sessionId): void
    {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = :id");
        $stmt->execute(['id' => $sessionId]);
    }

    public function deleteAllSessions(int $userId): void
    {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
    }

    // =========================================================================
    // Statistics
    // =========================================================================

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
