<?php

declare(strict_types=1);

namespace App\Cms\Auth;

/**
 * LoginAttempt - Tracks and manages login attempts for brute force protection
 *
 * Features:
 * - Track failed login attempts
 * - Exponential backoff for lockouts
 * - IP-based and user-based tracking
 * - Configurable thresholds
 */
class LoginAttempt
{
    private \PDO $db;
    private array $config;

    public function __construct(\PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'max_attempts' => 5,
            'lockout_minutes' => 15,
            'lockout_multiplier' => 2,      // Exponential backoff
            'max_lockout_minutes' => 1440,  // 24 hours max
            'track_by_ip' => true,
            'track_by_user' => true,
            'cleanup_after_days' => 30,
        ], $config);
    }

    /**
     * Record a failed login attempt
     */
    public function recordFailure(string $identifier, ?string $ip = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (identifier, ip_address, attempted_at)
            VALUES (:identifier, :ip, NOW())
        ");
        $stmt->execute([
            'identifier' => strtolower($identifier),
            'ip' => $ip,
        ]);
    }

    /**
     * Record a successful login (clears attempts)
     */
    public function recordSuccess(string $identifier, ?string $ip = null): void
    {
        // Clear attempts for this identifier
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts 
            WHERE identifier = :identifier
        ");
        $stmt->execute(['identifier' => strtolower($identifier)]);

        // Clear IP attempts if configured
        if ($ip && $this->config['track_by_ip']) {
            $stmt = $this->db->prepare("
                DELETE FROM login_attempts 
                WHERE ip_address = :ip
            ");
            $stmt->execute(['ip' => $ip]);
        }

        // Clear any lockouts
        $stmt = $this->db->prepare("
            DELETE FROM login_lockouts 
            WHERE identifier = :identifier OR ip_address = :ip
        ");
        $stmt->execute([
            'identifier' => strtolower($identifier),
            'ip' => $ip,
        ]);
    }

    /**
     * Check if identifier/IP is locked out
     *
     * @return array{locked: bool, until: ?int, remaining: int}
     */
    public function checkLockout(string $identifier, ?string $ip = null): array
    {
        // Check user lockout
        if ($this->config['track_by_user']) {
            $lockout = $this->getLockout($identifier, null);
            if ($lockout) {
                return [
                    'locked' => true,
                    'until' => $lockout['locked_until'],
                    'remaining' => max(0, $lockout['locked_until'] - time()),
                ];
            }
        }

        // Check IP lockout
        if ($ip && $this->config['track_by_ip']) {
            $lockout = $this->getLockout(null, $ip);
            if ($lockout) {
                return [
                    'locked' => true,
                    'until' => $lockout['locked_until'],
                    'remaining' => max(0, $lockout['locked_until'] - time()),
                ];
            }
        }

        // Check if should be locked based on attempts
        $attempts = $this->getRecentAttempts($identifier, $ip);

        if ($attempts >= $this->config['max_attempts']) {
            $this->createLockout($identifier, $ip, $attempts);
            $lockoutMinutes = $this->calculateLockoutDuration($attempts);

            return [
                'locked' => true,
                'until' => time() + ($lockoutMinutes * 60),
                'remaining' => $lockoutMinutes * 60,
            ];
        }

        return [
            'locked' => false,
            'until' => null,
            'remaining' => 0,
        ];
    }

    /**
     * Get remaining attempts before lockout
     */
    public function getRemainingAttempts(string $identifier, ?string $ip = null): int
    {
        $attempts = $this->getRecentAttempts($identifier, $ip);
        return max(0, $this->config['max_attempts'] - $attempts);
    }

    /**
     * Get recent failed attempts count
     */
    private function getRecentAttempts(string $identifier, ?string $ip): int
    {
        $window = $this->config['lockout_minutes'] * 60;
        $since = date('Y-m-d H:i:s', time() - $window);

        $sql = "SELECT COUNT(*) FROM login_attempts WHERE attempted_at > :since AND (";
        $params = ['since' => $since];
        $conditions = [];

        if ($this->config['track_by_user']) {
            $conditions[] = "identifier = :identifier";
            $params['identifier'] = strtolower($identifier);
        }

        if ($ip && $this->config['track_by_ip']) {
            $conditions[] = "ip_address = :ip";
            $params['ip'] = $ip;
        }

        if (empty($conditions)) {
            return 0;
        }

        $sql .= implode(' OR ', $conditions) . ")";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get active lockout
     */
    private function getLockout(?string $identifier, ?string $ip): ?array
    {
        $sql = "SELECT * FROM login_lockouts WHERE locked_until > :now AND (";
        $params = ['now' => time()];
        $conditions = [];

        if ($identifier) {
            $conditions[] = "identifier = :identifier";
            $params['identifier'] = strtolower($identifier);
        }

        if ($ip) {
            $conditions[] = "ip_address = :ip";
            $params['ip'] = $ip;
        }

        if (empty($conditions)) {
            return null;
        }

        $sql .= implode(' OR ', $conditions) . ") LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create a lockout entry
     */
    private function createLockout(string $identifier, ?string $ip, int $attempts): void
    {
        $duration = $this->calculateLockoutDuration($attempts);
        $lockedUntil = time() + ($duration * 60);

        $stmt = $this->db->prepare("
            INSERT INTO login_lockouts (identifier, ip_address, locked_until, attempt_count)
            VALUES (:identifier, :ip, :until, :count)
            ON DUPLICATE KEY UPDATE
                locked_until = VALUES(locked_until),
                attempt_count = VALUES(attempt_count)
        ");
        $stmt->execute([
            'identifier' => strtolower($identifier),
            'ip' => $ip,
            'until' => $lockedUntil,
            'count' => $attempts,
        ]);
    }

    /**
     * Calculate lockout duration with exponential backoff
     */
    private function calculateLockoutDuration(int $attempts): int
    {
        $base = $this->config['lockout_minutes'];
        $multiplier = $this->config['lockout_multiplier'];
        $max = $this->config['max_lockout_minutes'];

        // Calculate exponential backoff
        $exponent = max(0, floor(($attempts - $this->config['max_attempts']) / $this->config['max_attempts']));
        $duration = $base * pow($multiplier, $exponent);

        return min($duration, $max);
    }

    /**
     * Clear all attempts for an identifier
     */
    public function clearAttempts(string $identifier): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts WHERE identifier = :identifier
        ");
        $stmt->execute(['identifier' => strtolower($identifier)]);

        $stmt = $this->db->prepare("
            DELETE FROM login_lockouts WHERE identifier = :identifier
        ");
        $stmt->execute(['identifier' => strtolower($identifier)]);
    }

    /**
     * Clear attempts by IP
     */
    public function clearIpAttempts(string $ip): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts WHERE ip_address = :ip
        ");
        $stmt->execute(['ip' => $ip]);

        $stmt = $this->db->prepare("
            DELETE FROM login_lockouts WHERE ip_address = :ip
        ");
        $stmt->execute(['ip' => $ip]);
    }

    /**
     * Cleanup old records
     */
    public function cleanup(): int
    {
        $days = $this->config['cleanup_after_days'];
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stmt = $this->db->prepare("
            DELETE FROM login_attempts WHERE attempted_at < :cutoff
        ");
        $stmt->execute(['cutoff' => $cutoff]);
        $attempts = $stmt->rowCount();

        $stmt = $this->db->prepare("
            DELETE FROM login_lockouts WHERE locked_until < :now
        ");
        $stmt->execute(['now' => time()]);
        $lockouts = $stmt->rowCount();

        return $attempts + $lockouts;
    }

    /**
     * Get recent attempts for display
     */
    public function getAttemptHistory(string $identifier, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM login_attempts 
            WHERE identifier = :identifier
            ORDER BY attempted_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue('identifier', strtolower($identifier));
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        // Total attempts today
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM login_attempts 
            WHERE DATE(attempted_at) = CURDATE()
        ");
        $todayAttempts = (int) $stmt->fetchColumn();

        // Active lockouts
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM login_lockouts 
            WHERE locked_until > :now
        ");
        $stmt->execute(['now' => time()]);
        $activeLockouts = (int) $stmt->fetchColumn();

        // Unique IPs with failures today
        $stmt = $this->db->query("
            SELECT COUNT(DISTINCT ip_address) FROM login_attempts 
            WHERE DATE(attempted_at) = CURDATE()
        ");
        $uniqueIps = (int) $stmt->fetchColumn();

        return [
            'attempts_today' => $todayAttempts,
            'active_lockouts' => $activeLockouts,
            'unique_ips' => $uniqueIps,
        ];
    }
}
