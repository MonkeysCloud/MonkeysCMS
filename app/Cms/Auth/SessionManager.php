<?php

declare(strict_types=1);

namespace App\Cms\Auth;

/**
 * SessionManager - Handles session management for authentication
 *
 * Features:
 * - Secure session configuration
 * - Session data management
 * - Cookie handling for remember me
 * - Flash messages
 */
class SessionManager
{
    private bool $started = false;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'name' => 'cms_session',
            'lifetime' => 7200,              // 2 hours
            'path' => '/',
            'domain' => null,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ], $config);
    }

    /**
     * Start the session
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        // Configure session
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $this->config['secure'] ? '1' : '0');
        ini_set('session.cookie_samesite', $this->config['samesite']);
        ini_set('session.gc_maxlifetime', (string) $this->config['lifetime']);

        session_name($this->config['name']);
        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'],
            'path' => $this->config['path'],
            'domain' => $this->config['domain'],
            'secure' => $this->config['secure'],
            'httponly' => $this->config['httponly'],
            'samesite' => $this->config['samesite'],
        ]);

        session_start();
        $this->started = true;

        // Regenerate ID periodically for security
        if (!$this->has('_regenerated') || $this->get('_regenerated') < time() - 300) {
            $this->regenerate();
        }
    }

    /**
     * Regenerate session ID
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        session_regenerate_id($deleteOldSession);
        $this->set('_regenerated', time());
    }

    /**
     * Destroy the session
     */
    public function destroy(): void
    {
        if (!$this->started && session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        $this->started = false;
    }

    /**
     * Get a session value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * Check if session has a key
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session value
     */
    public function forget(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * Get and remove a session value
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    /**
     * Get all session data
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION;
    }

    /**
     * Clear all session data
     */
    public function flush(): void
    {
        $this->ensureStarted();
        $_SESSION = [];
    }

    /**
     * Get session ID
     */
    public function getId(): string
    {
        $this->ensureStarted();
        return session_id();
    }

    /**
     * Set session ID
     */
    public function setId(string $id): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Cannot change session ID after session has started');
        }
        session_id($id);
    }

    // =========================================================================
    // Flash Messages
    // =========================================================================

    /**
     * Flash a value for the next request
     */
    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get a flashed value
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION['_flash'][$key] ?? $default;
    }

    /**
     * Check if flash message exists
     */
    public function hasFlash(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Clear flash messages
     */
    public function clearFlash(): void
    {
        $this->ensureStarted();
        unset($_SESSION['_flash']);
    }

    /**
     * Flash success message
     */
    public function success(string $message): void
    {
        $this->flash('success', $message);
    }

    /**
     * Flash error message
     */
    public function error(string $message): void
    {
        $this->flash('error', $message);
    }

    /**
     * Flash warning message
     */
    public function warning(string $message): void
    {
        $this->flash('warning', $message);
    }

    /**
     * Flash info message
     */
    public function info(string $message): void
    {
        $this->flash('info', $message);
    }

    // =========================================================================
    // Cookie Handling
    // =========================================================================

    /**
     * Set a cookie
     */
    public function setCookie(
        string $name,
        string $value,
        int $expires = 0,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        ?bool $httponly = null,
        ?string $samesite = null
    ): void {
        setcookie($name, $value, [
            'expires' => $expires > 0 ? time() + $expires : 0,
            'path' => $path ?? $this->config['path'],
            'domain' => $domain ?? $this->config['domain'],
            'secure' => $secure ?? $this->config['secure'],
            'httponly' => $httponly ?? $this->config['httponly'],
            'samesite' => $samesite ?? $this->config['samesite'],
        ]);
    }

    /**
     * Get a cookie value
     */
    public function getCookie(string $name, mixed $default = null): mixed
    {
        return $_COOKIE[$name] ?? $default;
    }

    /**
     * Check if cookie exists
     */
    public function hasCookie(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Delete a cookie
     */
    public function deleteCookie(string $name): void
    {
        $this->setCookie($name, '', -3600);
        unset($_COOKIE[$name]);
    }

    // =========================================================================
    // CSRF Protection
    // =========================================================================

    /**
     * Generate CSRF token
     */
    public function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->set('_csrf_token', $token);
        return $token;
    }

    /**
     * Get current CSRF token
     */
    public function getCsrfToken(): string
    {
        if (!$this->has('_csrf_token')) {
            return $this->generateCsrfToken();
        }
        return $this->get('_csrf_token');
    }

    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken(string $token): bool
    {
        $sessionToken = $this->get('_csrf_token');

        if (!$sessionToken) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    // =========================================================================
    // Previous URL
    // =========================================================================

    /**
     * Store intended URL
     */
    public function setIntendedUrl(string $url): void
    {
        $this->set('_intended_url', $url);
    }

    /**
     * Get and clear intended URL
     */
    public function pullIntendedUrl(string $default = '/'): string
    {
        return $this->pull('_intended_url', $default);
    }

    /**
     * Store previous URL
     */
    public function setPreviousUrl(string $url): void
    {
        $this->set('_previous_url', $url);
    }

    /**
     * Get previous URL
     */
    public function getPreviousUrl(string $default = '/'): string
    {
        return $this->get('_previous_url', $default);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Ensure session is started
     */
    private function ensureStarted(): void
    {
        if (!$this->started && session_status() !== PHP_SESSION_ACTIVE) {
            $this->start();
        }
    }

    /**
     * Get client IP address
     */
    public function getClientIp(): ?string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Get user agent
     */
    public function getUserAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}
