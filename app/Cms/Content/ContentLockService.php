<?php

declare(strict_types=1);

namespace App\Cms\Content;

use MonkeysLegion\DI\Attributes\Singleton;
use PDO;

/**
 * ContentLockService — Pessimistic content locking.
 *
 * Prevents concurrent editing by acquiring a row-level lock in the
 * `content_locks` table. Locks auto-expire after a configurable TTL
 * and are renewed via a heartbeat from the editor.
 */
#[Singleton]
final class ContentLockService
{
    private const int DEFAULT_TTL_MINUTES = 15;

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // Public API
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Attempt to acquire a lock on a content node.
     *
     * Uses INSERT … ON DUPLICATE KEY UPDATE for atomicity.
     * - If no lock exists → acquired.
     * - If the caller already holds the lock → renewed.
     * - If a non-expired lock is held by another user → LOCKED_BY_OTHER.
     * - If an expired lock exists → overwritten (acquired).
     *
     * @return array{result: LockResult, lockInfo: ?LockInfo}
     */
    public function acquire(int $nodeId, int $userId, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): array
    {
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify("+{$ttlMinutes} minutes");

        // Check current lock state
        $existing = $this->getRawLock($nodeId);

        if ($existing !== null) {
            $existingExpires = new \DateTimeImmutable($existing['expires_at']);

            // Lock is held by another user and not expired
            if ((int) $existing['user_id'] !== $userId && $existingExpires > $now) {
                return [
                    'result'   => LockResult::LOCKED_BY_OTHER,
                    'lockInfo' => new LockInfo(
                        userId:    (int) $existing['user_id'],
                        userName:  $existing['user_name'] ?? 'Unknown',
                        expiresAt: $existingExpires,
                    ),
                ];
            }
        }

        // Acquire or renew: upsert
        $stmt = $this->pdo->prepare(
            'INSERT INTO content_locks (node_id, user_id, locked_at, expires_at)
             VALUES (:node_id, :user_id, :locked_at, :expires_at)
             ON DUPLICATE KEY UPDATE
                user_id    = VALUES(user_id),
                locked_at  = VALUES(locked_at),
                expires_at = VALUES(expires_at)'
        );

        $stmt->execute([
            'node_id'    => $nodeId,
            'user_id'    => $userId,
            'locked_at'  => $now->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        $result = ($existing !== null && (int) $existing['user_id'] === $userId)
            ? LockResult::RENEWED
            : LockResult::ACQUIRED;

        return [
            'result'   => $result,
            'lockInfo' => null,
        ];
    }

    /**
     * Extend an existing lock by the default TTL.
     *
     * Only succeeds if the caller currently owns the lock.
     */
    public function renew(int $nodeId, int $userId, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): bool
    {
        $expiresAt = (new \DateTimeImmutable())->modify("+{$ttlMinutes} minutes");

        $stmt = $this->pdo->prepare(
            'UPDATE content_locks
             SET expires_at = :expires_at, locked_at = NOW()
             WHERE node_id = :node_id AND user_id = :user_id'
        );

        $stmt->execute([
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'node_id'    => $nodeId,
            'user_id'    => $userId,
        ]);

        // rowCount can be 0 if values didn't change (MySQL optimization).
        // Verify the lock row still exists and is owned by this user.
        if ($stmt->rowCount() > 0) {
            return true;
        }

        $check = $this->pdo->prepare(
            'SELECT 1 FROM content_locks WHERE node_id = :node_id AND user_id = :user_id'
        );
        $check->execute(['node_id' => $nodeId, 'user_id' => $userId]);

        return (bool) $check->fetchColumn();
    }

    /**
     * Release a lock — only removes the lock if owned by the given user.
     */
    public function release(int $nodeId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM content_locks WHERE node_id = :node_id AND user_id = :user_id'
        );
        $stmt->execute([
            'node_id' => $nodeId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Admin force-unlock — removes any lock regardless of owner.
     */
    public function breakLock(int $nodeId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM content_locks WHERE node_id = :node_id');
        $stmt->execute(['node_id' => $nodeId]);
    }

    /**
     * Check if a node is currently locked (non-expired).
     *
     * @return ?LockInfo  null if no active lock, or info about the holder.
     */
    public function isLocked(int $nodeId): ?LockInfo
    {
        $row = $this->getRawLock($nodeId);

        if ($row === null) {
            return null;
        }

        $expiresAt = new \DateTimeImmutable($row['expires_at']);
        if ($expiresAt <= new \DateTimeImmutable()) {
            return null; // expired
        }

        return new LockInfo(
            userId:    (int) $row['user_id'],
            userName:  $row['user_name'] ?? 'Unknown',
            expiresAt: $expiresAt,
        );
    }

    /**
     * Delete all expired locks. Intended for cron.
     *
     * @return int Number of expired locks removed.
     */
    public function cleanExpired(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM content_locks WHERE expires_at < NOW()');
        $stmt->execute();

        return $stmt->rowCount();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Internals
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Fetch the raw lock row with the user's name joined.
     */
    private function getRawLock(int $nodeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cl.*, cu.name AS user_name
             FROM content_locks cl
             LEFT JOIN cms_users cu ON cl.user_id = cu.id
             WHERE cl.node_id = :node_id'
        );
        $stmt->execute(['node_id' => $nodeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
