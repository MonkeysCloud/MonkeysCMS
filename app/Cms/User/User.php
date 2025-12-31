<?php

declare(strict_types=1);

namespace App\Cms\User;

use App\Cms\Entity\BaseEntity;
use App\Cms\Entity\SoftDeleteInterface;
use App\Cms\Entity\SoftDeleteTrait;
use MonkeysLegion\Auth\Contract\AuthenticatableInterface;

/**
 * User - User entity for the CMS
 *
 * Represents a user account with authentication and profile information.
 */
class User extends BaseEntity implements SoftDeleteInterface, AuthenticatableInterface
{
    use SoftDeleteTrait;

    protected ?int $id = null;
    protected string $email = '';
    protected string $username = '';
    protected string $password_hash = '';
    protected ?string $display_name = null;
    protected ?string $avatar = null;
    protected ?string $bio = null;
    protected string $status = UserStatus::PENDING;
    protected ?\DateTimeImmutable $email_verified_at = null;
    protected ?string $remember_token = null;
    protected ?string $two_factor_secret = null;
    protected ?array $two_factor_recovery_codes = null;
    protected int $token_version = 1;
    protected ?\DateTimeImmutable $last_login_at = null;
    protected ?string $last_login_ip = null;
    protected ?\DateTimeImmutable $created_at = null;
    protected ?\DateTimeImmutable $updated_at = null;
    protected ?\DateTimeImmutable $deleted_at = null;

    /** @var array<string> Loaded roles */
    protected array $roles = [];

    /** @var array<string> Loaded permissions */
    protected array $permissions = [];

    /** @var bool Whether 2FA is enabled (computed) */
    public bool $has2FAEnabled = false;

    public static function getTableName(): string
    {
        return 'users';
    }

    public static function getFillable(): array
    {
        return [
            'email',
            'username',
            'password_hash',
            'display_name',
            'avatar',
            'bio',
            'status',
        ];
    }

    public static function getTransient(): array
    {
        return ['roles', 'permissions', 'has2FAEnabled'];
    }

    public static function getHidden(): array
    {
        return ['password_hash', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'deleted_at'];
    }

    public static function getCasts(): array
    {
        return [
            'id' => 'int',
            'token_version' => 'int',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'roles' => 'array',
            'permissions' => 'array',
            'two_factor_recovery_codes' => 'json',
        ];
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPasswordHash(): string
    {
        return $this->password_hash;
    }

    public function getDisplayName(): string
    {
        return $this->display_name ?? $this->username;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->email_verified_at;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->last_login_at;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->last_login_ip;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    // =========================================================================
    // Setters
    // =========================================================================

    public function setEmail(string $email): static
    {
        $this->email = strtolower(trim($email));
        return $this;
    }

    public function setUsername(string $username): static
    {
        $this->username = trim($username);
        return $this;
    }

    public function setPassword(string $plainPassword): static
    {
        $this->password_hash = password_hash($plainPassword, PASSWORD_ARGON2ID);
        return $this;
    }

    public function setPasswordHash(string $hash): static
    {
        $this->password_hash = $hash;
        return $this;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->display_name = $displayName;
        return $this;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function setStatus(string $status): static
    {
        if (!UserStatus::isValid($status)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
        $this->status = $status;
        return $this;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    // =========================================================================
    // Password Methods
    // =========================================================================

    /**
     * Verify password against hash
     */
    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->password_hash);
    }

    /**
     * Check if password needs rehashing
     */
    public function needsRehash(): bool
    {
        return password_needs_rehash($this->password_hash, PASSWORD_ARGON2ID);
    }

    // =========================================================================
    // Token & Authentication Methods (for MonkeysLegion-Auth)
    // =========================================================================

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get auth identifier (for JWT)
     */
    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    /**
     * Get auth password hash
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Get token version for JWT validation
     */
    public function getTokenVersion(): int
    {
        return $this->token_version;
    }

    /**
     * Increment token version (invalidates all tokens)
     */
    public function incrementTokenVersion(): static
    {
        $this->token_version++;
        return $this;
    }

    /**
     * Generate remember token
     */
    public function generateRememberToken(): string
    {
        $this->remember_token = bin2hex(random_bytes(32));
        return $this->remember_token;
    }

    /**
     * Get the remember token
     */
    public function getRememberToken(): ?string
    {
        return $this->remember_token;
    }

    /**
     * Clear remember token
     */
    public function clearRememberToken(): static
    {
        $this->remember_token = null;
        return $this;
    }

    /**
     * Set the token value for the "remember me" session.
     */
    public function setRememberToken(string $value): void
    {
        $this->remember_token = $value;
    }

    /**
     * Record login
     */
    public function recordLogin(?string $ip = null): static
    {
        $this->last_login_at = new \DateTimeImmutable();
        $this->last_login_ip = $ip;
        return $this;
    }

    // =========================================================================
    // Two-Factor Authentication
    // =========================================================================

    /**
     * Get 2FA secret
     */
    public function getTwoFactorSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    /**
     * Set 2FA secret
     */
    public function setTwoFactorSecret(?string $secret): static
    {
        $this->two_factor_secret = $secret;
        $this->has2FAEnabled = $secret !== null;
        return $this;
    }

    /**
     * Check if 2FA is enabled
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null;
    }

    /**
     * Get 2FA recovery codes
     */
    public function getTwoFactorRecoveryCodes(): array
    {
        return $this->two_factor_recovery_codes ?? [];
    }

    /**
     * Set 2FA recovery codes
     */
    public function setTwoFactorRecoveryCodes(array $codes): static
    {
        $this->two_factor_recovery_codes = $codes;
        return $this;
    }

    // =========================================================================
    // Permissions
    // =========================================================================

    /**
     * Get user permissions
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Set user permissions
     */
    public function setPermissions(array $permissions): static
    {
        $this->permissions = $permissions;
        return $this;
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(string $permission): bool
    {
        // Admin has all permissions
        if ($this->hasRole('admin')) {
            return true;
        }

        // Wildcard permission
        if (in_array('*', $this->permissions, true)) {
            return true;
        }

        return in_array($permission, $this->permissions, true);
    }

    /**
     * Check if user has any of the permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // Status Checks
    // =========================================================================

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    /**
     * Check if user is blocked
     */
    public function isBlocked(): bool
    {
        return $this->status === UserStatus::BLOCKED;
    }

    /**
     * Check if user is pending
     */
    public function isPending(): bool
    {
        return $this->status === UserStatus::PENDING;
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Activate the user
     */
    public function activate(): static
    {
        $this->status = UserStatus::ACTIVE;
        return $this;
    }

    /**
     * Block the user
     */
    public function block(): static
    {
        $this->status = UserStatus::BLOCKED;
        return $this;
    }

    /**
     * Mark email as verified
     */
    public function verifyEmail(): static
    {
        $this->email_verified_at = new \DateTimeImmutable();
        return $this;
    }



    // =========================================================================
    // Role Checks
    // =========================================================================

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return !empty(array_intersect($roles, $this->roles));
    }

    /**
     * Check if user has all of the given roles
     */
    public function hasAllRoles(array $roles): bool
    {
        return empty(array_diff($roles, $this->roles));
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }



    // =========================================================================
    // Avatar
    // =========================================================================

    /**
     * Get avatar URL (with Gravatar fallback)
     */
    public function getAvatarUrl(int $size = 80): string
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        // Gravatar fallback
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=mp";
    }

    // =========================================================================
    // Serialization
    // =========================================================================

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['roles'] = $this->roles;
        return $data;
    }

    /**
     * Convert to array for API response
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->getDisplayName(),
            'avatar_url' => $this->getAvatarUrl(),
            'bio' => $this->bio,
            'created_at' => $this->created_at?->format('c'),
        ];
    }

    /**
     * Convert to array for admin
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'display_name' => $this->getDisplayName(),
            'avatar_url' => $this->getAvatarUrl(),
            'status' => $this->status,
            'roles' => $this->roles,
            'email_verified' => $this->isEmailVerified(),
            'last_login_at' => $this->last_login_at?->format('c'),
            'created_at' => $this->created_at?->format('c'),
        ];
    }
}

/**
 * UserStatus - Status constants for users
 */
final class UserStatus
{
    public const ACTIVE = 'active';
    public const BLOCKED = 'blocked';
    public const PENDING = 'pending';

    public static function all(): array
    {
        return [self::ACTIVE, self::BLOCKED, self::PENDING];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::ACTIVE => 'Active',
            self::BLOCKED => 'Blocked',
            self::PENDING => 'Pending',
            default => ucfirst($status),
        };
    }

    public static function color(string $status): string
    {
        return match ($status) {
            self::ACTIVE => 'green',
            self::BLOCKED => 'red',
            self::PENDING => 'yellow',
            default => 'gray',
        };
    }
}
