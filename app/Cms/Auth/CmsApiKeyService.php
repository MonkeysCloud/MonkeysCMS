<?php

declare(strict_types=1);

namespace App\Cms\Auth;

use App\Cms\User\User;

/**
 * CmsApiKeyService - API Key authentication for MonkeysCMS
 * 
 * Features:
 * - Create scoped API keys
 * - Validate API keys
 * - Manage key lifecycle
 * - Rate limiting per key
 * 
 * Key format: ml_{keyId}_{secret}
 * Only keyId is stored; secret is hashed
 */
class CmsApiKeyService
{
    private \PDO $db;
    private CmsUserProvider $userProvider;

    private const KEY_PREFIX = 'ml_';
    private const KEY_ID_LENGTH = 16;
    private const SECRET_LENGTH = 32;

    public function __construct(\PDO $db, CmsUserProvider $userProvider)
    {
        $this->db = $db;
        $this->userProvider = $userProvider;
    }

    /**
     * Create a new API key
     * 
     * @param string[] $scopes Permission scopes (e.g., ['read:content', 'write:content'])
     * @return array{key: string, id: int, name: string, scopes: array}
     */
    public function create(
        int $userId,
        string $name,
        array $scopes = ['*'],
        ?\DateTimeInterface $expiresAt = null
    ): array {
        // Generate key components
        $keyId = bin2hex(random_bytes(self::KEY_ID_LENGTH / 2));
        $secret = bin2hex(random_bytes(self::SECRET_LENGTH / 2));
        $keyHash = password_hash($secret, PASSWORD_ARGON2ID);

        // Store in database
        $stmt = $this->db->prepare("
            INSERT INTO api_keys (user_id, name, key_id, key_hash, scopes, expires_at)
            VALUES (:user_id, :name, :key_id, :key_hash, :scopes, :expires_at)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'key_id' => $keyId,
            'key_hash' => $keyHash,
            'scopes' => json_encode($scopes),
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->lastInsertId();

        // Return full key (only time it's available)
        return [
            'key' => self::KEY_PREFIX . $keyId . '_' . $secret,
            'id' => $id,
            'name' => $name,
            'scopes' => $scopes,
        ];
    }

    /**
     * Validate an API key
     * 
     * @return array{id: int, user_id: int, name: string, scopes: array}|null
     */
    public function validate(string $apiKey): ?array
    {
        // Parse key
        $parsed = $this->parseKey($apiKey);
        
        if (!$parsed) {
            return null;
        }

        // Find key record
        $stmt = $this->db->prepare("
            SELECT * FROM api_keys 
            WHERE key_id = :key_id 
            AND revoked_at IS NULL
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute(['key_id' => $parsed['keyId']]);
        $keyData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$keyData) {
            return null;
        }

        // Verify secret
        if (!password_verify($parsed['secret'], $keyData['key_hash'])) {
            return null;
        }

        // Update last used
        $this->updateLastUsed((int) $keyData['id']);

        return [
            'id' => (int) $keyData['id'],
            'user_id' => (int) $keyData['user_id'],
            'name' => $keyData['name'],
            'scopes' => json_decode($keyData['scopes'], true),
        ];
    }

    /**
     * Check if key has a specific scope
     */
    public function hasScope(array $keyData, string $scope): bool
    {
        $scopes = $keyData['scopes'] ?? [];

        // Wildcard grants all
        if (in_array('*', $scopes, true)) {
            return true;
        }

        // Exact match
        if (in_array($scope, $scopes, true)) {
            return true;
        }

        // Wildcard scope match (e.g., 'content.*' matches 'content.read')
        $scopeParts = explode('.', $scope);
        foreach ($scopes as $s) {
            if (str_ends_with($s, '.*')) {
                $prefix = rtrim($s, '.*');
                if (str_starts_with($scope, $prefix . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get user from validated key
     */
    public function getUser(array $keyData): ?User
    {
        return $this->userProvider->findById($keyData['user_id']);
    }

    /**
     * List API keys for a user
     * 
     * @return array<array{id: int, name: string, scopes: array, last_used_at: ?string, expires_at: ?string, created_at: string}>
     */
    public function listForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, key_id, scopes, last_used_at, expires_at, created_at
            FROM api_keys 
            WHERE user_id = :user_id AND revoked_at IS NULL
            ORDER BY created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        $keys = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $keys[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'key_prefix' => self::KEY_PREFIX . $row['key_id'] . '_***',
                'scopes' => json_decode($row['scopes'], true),
                'last_used_at' => $row['last_used_at'],
                'expires_at' => $row['expires_at'],
                'created_at' => $row['created_at'],
            ];
        }

        return $keys;
    }

    /**
     * Revoke an API key
     */
    public function revoke(int $keyId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE api_keys 
            SET revoked_at = NOW()
            WHERE id = :id AND user_id = :user_id AND revoked_at IS NULL
        ");
        $stmt->execute(['id' => $keyId, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Revoke all keys for a user
     */
    public function revokeAllForUser(int $userId): int
    {
        $stmt = $this->db->prepare("
            UPDATE api_keys 
            SET revoked_at = NOW()
            WHERE user_id = :user_id AND revoked_at IS NULL
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->rowCount();
    }

    /**
     * Update key name
     */
    public function updateName(int $keyId, int $userId, string $name): bool
    {
        $stmt = $this->db->prepare("
            UPDATE api_keys 
            SET name = :name
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute(['id' => $keyId, 'user_id' => $userId, 'name' => $name]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Update key scopes
     */
    public function updateScopes(int $keyId, int $userId, array $scopes): bool
    {
        $stmt = $this->db->prepare("
            UPDATE api_keys 
            SET scopes = :scopes
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            'id' => $keyId,
            'user_id' => $userId,
            'scopes' => json_encode($scopes),
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get key by ID
     */
    public function getById(int $keyId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, name, scopes, last_used_at, expires_at, revoked_at, created_at
            FROM api_keys WHERE id = :id
        ");
        $stmt->execute(['id' => $keyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'name' => $row['name'],
            'scopes' => json_decode($row['scopes'], true),
            'last_used_at' => $row['last_used_at'],
            'expires_at' => $row['expires_at'],
            'revoked_at' => $row['revoked_at'],
            'created_at' => $row['created_at'],
            'is_active' => $row['revoked_at'] === null && 
                          ($row['expires_at'] === null || strtotime($row['expires_at']) > time()),
        ];
    }

    /**
     * Cleanup expired keys
     */
    public function cleanup(): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM api_keys 
            WHERE (expires_at IS NOT NULL AND expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
            OR (revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
        ");
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Get usage statistics
     */
    public function getStats(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN revoked_at IS NOT NULL THEN 1 ELSE 0 END) as revoked,
                SUM(CASE WHEN expires_at IS NOT NULL AND expires_at < NOW() THEN 1 ELSE 0 END) as expired
            FROM api_keys WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Parse API key into components
     */
    private function parseKey(string $apiKey): ?array
    {
        if (!str_starts_with($apiKey, self::KEY_PREFIX)) {
            return null;
        }

        $key = substr($apiKey, strlen(self::KEY_PREFIX));
        $parts = explode('_', $key, 2);

        if (count($parts) !== 2) {
            return null;
        }

        return [
            'keyId' => $parts[0],
            'secret' => $parts[1],
        ];
    }

    /**
     * Update last used timestamp
     */
    private function updateLastUsed(int $keyId): void
    {
        $stmt = $this->db->prepare("
            UPDATE api_keys SET last_used_at = NOW() WHERE id = :id
        ");
        $stmt->execute(['id' => $keyId]);
    }
}
