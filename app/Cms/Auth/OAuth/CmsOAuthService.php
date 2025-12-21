<?php

declare(strict_types=1);

namespace App\Cms\Auth\OAuth;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\CmsUserProvider;
use App\Cms\Auth\SessionManager;
use App\Cms\User\User;
use MonkeysLegion\Auth\OAuth\OAuthService;
use MonkeysLegion\Auth\OAuth\GoogleProvider;
use MonkeysLegion\Auth\OAuth\GitHubProvider;

/**
 * CmsOAuthService - OAuth integration for MonkeysCMS
 *
 * Wraps MonkeysLegion-Auth OAuth with CMS-specific functionality:
 * - Provider management
 * - User linking
 * - Account creation from OAuth
 */
class CmsOAuthService
{
    private OAuthService $oauth;
    private CmsUserProvider $userProvider;
    private CmsAuthService $auth;
    private SessionManager $session;
    private \PDO $db;
    private array $config;

    public function __construct(
        CmsUserProvider $userProvider,
        CmsAuthService $auth,
        SessionManager $session,
        \PDO $db,
        array $config = []
    ) {
        $this->userProvider = $userProvider;
        $this->auth = $auth;
        $this->session = $session;
        $this->db = $db;
        $this->config = $config;

        $this->oauth = new OAuthService();
        $this->registerProviders();
    }

    /**
     * Register configured OAuth providers
     */
    private function registerProviders(): void
    {
        // Google
        if (!empty($this->config['google']['client_id'])) {
            $this->oauth->register(new GoogleProvider(
                clientId: $this->config['google']['client_id'],
                clientSecret: $this->config['google']['client_secret'],
                redirectUri: $this->config['google']['redirect_uri'],
            ));
        }

        // GitHub
        if (!empty($this->config['github']['client_id'])) {
            $this->oauth->register(new GitHubProvider(
                clientId: $this->config['github']['client_id'],
                clientSecret: $this->config['github']['client_secret'],
                redirectUri: $this->config['github']['redirect_uri'],
            ));
        }
    }

    /**
     * Get available providers
     *
     * @return string[]
     */
    public function getAvailableProviders(): array
    {
        $providers = [];

        if (!empty($this->config['google']['client_id'])) {
            $providers[] = 'google';
        }
        if (!empty($this->config['github']['client_id'])) {
            $providers[] = 'github';
        }

        return $providers;
    }

    /**
     * Get authorization URL for a provider
     */
    public function getAuthorizationUrl(string $provider, array $scopes = []): string
    {
        // Generate and store state for CSRF protection
        $state = $this->oauth->generateState();
        $this->session->set('oauth_state', $state);
        $this->session->set('oauth_provider', $provider);

        return $this->oauth->getAuthorizationUrl($provider, $state, $scopes);
    }

    /**
     * Handle OAuth callback
     */
    public function handleCallback(string $provider, string $code, string $state): OAuthResult
    {
        // Verify state
        $sessionState = $this->session->pull('oauth_state');

        if (!$sessionState || !hash_equals($sessionState, $state)) {
            return new OAuthResult(
                success: false,
                error: 'Invalid state parameter',
            );
        }

        try {
            // Exchange code for user info
            $oauthUser = $this->oauth->handleCallback($provider, $code);

            // Find or create user
            $user = $this->findOrCreateUser($provider, $oauthUser);

            if (!$user) {
                return new OAuthResult(
                    success: false,
                    error: 'Failed to create user',
                );
            }

            // Log the user in
            $tokens = $this->auth->getAuthService()->issueTokenPair($user);

            return new OAuthResult(
                success: true,
                user: $user,
                tokens: $tokens,
                isNewUser: $this->isNewUser,
            );
        } catch (\Exception $e) {
            return new OAuthResult(
                success: false,
                error: $e->getMessage(),
            );
        }
    }

    private bool $isNewUser = false;

    /**
     * Find existing user or create new one from OAuth data
     */
    private function findOrCreateUser(string $provider, object $oauthUser): ?User
    {
        // Check if OAuth account is already linked
        $existingLink = $this->findOAuthAccount($provider, $oauthUser->providerId);

        if ($existingLink) {
            // Update tokens
            $this->updateOAuthTokens($existingLink['id'], $oauthUser);

            return $this->userProvider->findById($existingLink['user_id']);
        }

        // Check if user exists with this email
        $user = $this->userProvider->findByEmail($oauthUser->email);

        if ($user) {
            // Link OAuth account to existing user
            $this->createOAuthAccount($user->getId(), $provider, $oauthUser);
            return $user;
        }

        // Create new user
        $this->isNewUser = true;
        return $this->createUserFromOAuth($provider, $oauthUser);
    }

    /**
     * Create a new user from OAuth data
     */
    private function createUserFromOAuth(string $provider, object $oauthUser): ?User
    {
        // Generate unique username
        $username = $this->generateUniqueUsername($oauthUser->name ?? $oauthUser->email);

        $user = new User();
        $user->setEmail($oauthUser->email);
        $user->setUsername($username);
        $user->setDisplayName($oauthUser->name);
        $user->setAvatar($oauthUser->avatar);
        $user->setPassword(bin2hex(random_bytes(32))); // Random password
        $user->setStatus('active');
        $user->verifyEmail(); // OAuth emails are pre-verified

        $this->userProvider->save($user);
        $this->userProvider->assignRole($user, 'authenticated');

        // Create OAuth account link
        $this->createOAuthAccount($user->getId(), $provider, $oauthUser);

        return $user;
    }

    /**
     * Generate a unique username
     */
    private function generateUniqueUsername(string $base): string
    {
        // Clean the base
        $username = preg_replace('/[^a-z0-9]/', '', strtolower($base));
        $username = substr($username, 0, 20);

        if (empty($username)) {
            $username = 'user';
        }

        // Check if unique
        $original = $username;
        $counter = 1;

        while ($this->userProvider->findByUsername($username)) {
            $username = $original . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Find OAuth account by provider and provider user ID
     */
    private function findOAuthAccount(string $provider, string $providerId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM oauth_accounts 
            WHERE provider = :provider AND provider_user_id = :provider_id
        ");
        $stmt->execute([
            'provider' => $provider,
            'provider_id' => $providerId,
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create OAuth account link
     */
    private function createOAuthAccount(int $userId, string $provider, object $oauthUser): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO oauth_accounts 
            (user_id, provider, provider_user_id, access_token, refresh_token, expires_at)
            VALUES (:user_id, :provider, :provider_id, :access, :refresh, :expires)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'provider' => $provider,
            'provider_id' => $oauthUser->providerId,
            'access' => $oauthUser->accessToken ?? null,
            'refresh' => $oauthUser->refreshToken ?? null,
            'expires' => $oauthUser->expiresAt ?? null,
        ]);
    }

    /**
     * Update OAuth tokens
     */
    private function updateOAuthTokens(int $accountId, object $oauthUser): void
    {
        $stmt = $this->db->prepare("
            UPDATE oauth_accounts 
            SET access_token = :access, refresh_token = :refresh, expires_at = :expires
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $accountId,
            'access' => $oauthUser->accessToken ?? null,
            'refresh' => $oauthUser->refreshToken ?? null,
            'expires' => $oauthUser->expiresAt ?? null,
        ]);
    }

    /**
     * Get linked OAuth accounts for a user
     */
    public function getLinkedAccounts(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT provider, provider_user_id, created_at 
            FROM oauth_accounts 
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Link OAuth account to existing user
     */
    public function linkAccount(User $user, string $provider, string $code): OAuthResult
    {
        try {
            $oauthUser = $this->oauth->handleCallback($provider, $code);

            // Check if already linked to another user
            $existing = $this->findOAuthAccount($provider, $oauthUser->providerId);

            if ($existing && $existing['user_id'] !== $user->getId()) {
                return new OAuthResult(
                    success: false,
                    error: 'This account is already linked to another user',
                );
            }

            if (!$existing) {
                $this->createOAuthAccount($user->getId(), $provider, $oauthUser);
            }

            return new OAuthResult(success: true, user: $user);
        } catch (\Exception $e) {
            return new OAuthResult(
                success: false,
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Unlink OAuth account
     */
    public function unlinkAccount(int $userId, string $provider): bool
    {
        // Ensure user has password or other OAuth account
        $accounts = $this->getLinkedAccounts($userId);

        if (count($accounts) <= 1) {
            // Check if user has a password
            $user = $this->userProvider->findById($userId);
            if (!$user || empty($user->getPasswordHash())) {
                return false; // Can't unlink last authentication method
            }
        }

        $stmt = $this->db->prepare("
            DELETE FROM oauth_accounts 
            WHERE user_id = :user_id AND provider = :provider
        ");
        $stmt->execute([
            'user_id' => $userId,
            'provider' => $provider,
        ]);

        return $stmt->rowCount() > 0;
    }
}

/**
 * OAuthResult - Result of an OAuth operation
 */
class OAuthResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?User $user = null,
        public readonly ?object $tokens = null,
        public readonly bool $isNewUser = false,
        public readonly ?string $error = null,
    ) {
    }

    public function failed(): bool
    {
        return !$this->success;
    }
}
