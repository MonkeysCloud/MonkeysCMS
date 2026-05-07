<?php

declare(strict_types=1);

namespace App\Cms\Content;

/**
 * LockInfo — Immutable snapshot of who holds a content lock.
 */
final readonly class LockInfo
{
    public function __construct(
        public int $userId,
        public string $userName,
        public \DateTimeImmutable $expiresAt,
    ) {}

    /**
     * Minutes remaining before this lock expires.
     */
    public function minutesRemaining(): int
    {
        $diff = (new \DateTimeImmutable())->getTimestamp();
        $remaining = $this->expiresAt->getTimestamp() - $diff;

        return max(0, (int) ceil($remaining / 60));
    }
}
