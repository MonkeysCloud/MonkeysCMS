<?php

declare(strict_types=1);

namespace App\Cms\Auth;

use App\Cms\User\User;

/**
 * EmailVerification - Handles email verification for user accounts
 *
 * Features:
 * - Generate verification tokens
 * - Verify email addresses
 * - Resend verification emails
 * - Token expiration
 */
class EmailVerification
{
    private \PDO $db;
    private CmsUserProvider $userProvider;
    private int $tokenExpiry;

    public function __construct(\PDO $db, CmsUserProvider $userProvider, int $tokenExpiry = 3600)
    {
        $this->db = $db;
        $this->userProvider = $userProvider;
        $this->tokenExpiry = $tokenExpiry; // Default 1 hour
    }

    /**
     * Generate verification token for user
     *
     * @return array{token: string, expires_at: \DateTimeImmutable}
     */
    public function generateToken(int $userId): array
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = new \DateTimeImmutable("+{$this->tokenExpiry} seconds");

        // Delete existing tokens for this user
        $stmt = $this->db->prepare("DELETE FROM email_verifications WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);

        // Insert new token
        $stmt = $this->db->prepare("
            INSERT INTO email_verifications (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $hash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Verify email with token
     */
    public function verify(string $token): VerificationResult
    {
        $hash = hash('sha256', $token);

        $stmt = $this->db->prepare("
            SELECT user_id, expires_at FROM email_verifications 
            WHERE token_hash = :hash
        ");
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return new VerificationResult(false, 'Invalid verification token');
        }

        // Check expiration
        if (strtotime($row['expires_at']) < time()) {
            // Delete expired token
            $this->deleteToken($hash);
            return new VerificationResult(false, 'Verification token has expired');
        }

        $userId = (int) $row['user_id'];
        $user = $this->userProvider->findById($userId);

        if (!$user) {
            return new VerificationResult(false, 'User not found');
        }

        // Mark email as verified
        $this->userProvider->markEmailVerified($userId);

        // Delete token
        $this->deleteToken($hash);

        // Reload user
        $user = $this->userProvider->findById($userId);

        return new VerificationResult(true, 'Email verified successfully', $user);
    }

    /**
     * Resend verification email
     */
    public function resend(string $email): ResendResult
    {
        $user = $this->userProvider->findByEmail($email);

        if (!$user) {
            // Don't reveal if email exists
            return new ResendResult(true, 'If your email is registered, you will receive a verification email.');
        }

        if ($user->isEmailVerified()) {
            return new ResendResult(false, 'Email is already verified');
        }

        // Check rate limit (max 3 per hour)
        if (!$this->canResend($user->getId())) {
            return new ResendResult(false, 'Please wait before requesting another verification email');
        }

        // Generate new token
        $tokenData = $this->generateToken($user->getId());

        // TODO: Send verification email
        // $this->sendVerificationEmail($user, $tokenData['token']);

        return new ResendResult(
            true,
            'Verification email sent',
            $tokenData['token'] // In production, don't return this
        );
    }

    /**
     * Check if user is verified
     */
    public function isVerified(int $userId): bool
    {
        return $this->userProvider->isEmailVerified($userId);
    }

    /**
     * Check if resend is allowed (rate limiting)
     */
    private function canResend(int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM email_verifications 
            WHERE user_id = :user_id 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn() < 3;
    }

    /**
     * Delete token by hash
     */
    private function deleteToken(string $hash): void
    {
        $stmt = $this->db->prepare("DELETE FROM email_verifications WHERE token_hash = :hash");
        $stmt->execute(['hash' => $hash]);
    }

    /**
     * Cleanup expired tokens
     */
    public function cleanup(): int
    {
        $stmt = $this->db->prepare("DELETE FROM email_verifications WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Get verification URL
     */
    public function getVerificationUrl(string $token, string $baseUrl = ''): string
    {
        return rtrim($baseUrl, '/') . '/verify-email?token=' . urlencode($token);
    }
}

/**
 * VerificationResult - Result of email verification
 */
class VerificationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?User $user = null,
    ) {
    }
}

/**
 * ResendResult - Result of resend request
 */
class ResendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $token = null,
    ) {
    }
}
