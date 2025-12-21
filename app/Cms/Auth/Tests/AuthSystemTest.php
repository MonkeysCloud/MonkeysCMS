<?php

declare(strict_types=1);

namespace App\Cms\Auth\Tests;

use App\Cms\Auth\AuthServiceProvider;
use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\CmsUserProvider;
use App\Cms\Auth\SessionManager;
use App\Cms\Auth\LoginAttempt;
use App\Cms\Auth\CmsApiKeyService;
use App\Cms\Auth\EmailVerification;
use App\Cms\User\User;
use App\Cms\Entity\EntityManager;

/**
 * AuthSystemTest - Comprehensive tests for the authentication system
 * 
 * Run: php app/Cms/Auth/Tests/AuthSystemTest.php
 */
class AuthSystemTest
{
    private \PDO $db;
    private EntityManager $em;
    private CmsUserProvider $userProvider;
    private CmsAuthService $auth;
    private SessionManager $session;
    private LoginAttempt $loginAttempt;
    private CmsApiKeyService $apiKeys;
    
    private array $testResults = [];
    private int $passed = 0;
    private int $failed = 0;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
        $this->em = new EntityManager($db);
        
        // Initialize services
        AuthServiceProvider::init($db, [
            'jwt_secret' => 'test-secret-at-least-32-chars-long',
            'access_ttl' => 300,
            'refresh_ttl' => 3600,
        ]);

        $this->session = new SessionManager(['secure' => false]);
        $this->userProvider = AuthServiceProvider::getUserProvider();
        $this->auth = AuthServiceProvider::getAuthService();
        $this->loginAttempt = AuthServiceProvider::getLoginAttempt();
        $this->apiKeys = AuthServiceProvider::getApiKeyService();
    }

    public function run(): void
    {
        echo "\n=== MonkeysCMS Authentication System Tests ===\n\n";

        // Setup
        $this->setupTestUser();

        // Run test groups
        $this->testUserEntity();
        $this->testPasswordHashing();
        $this->testAuthentication();
        $this->testLoginAttempts();
        $this->testSessionManager();
        $this->testApiKeys();
        $this->testEmailVerification();

        // Cleanup
        $this->cleanup();

        // Report
        $this->printReport();
    }

    // =========================================================================
    // Test Groups
    // =========================================================================

    private function testUserEntity(): void
    {
        $this->group('User Entity');

        // Test user creation
        $user = new User();
        $user->setEmail('entity@test.com');
        $user->setUsername('entitytest');
        $user->setPassword('TestPass123');
        $user->setDisplayName('Entity Test');
        $user->setStatus('active');
        
        $this->em->save($user);

        $this->assert($user->getId() > 0, 'User should have ID after save');
        $this->assert($user->getEmail() === 'entity@test.com', 'Email should be set');
        $this->assert($user->getUsername() === 'entitytest', 'Username should be set');
        $this->assert($user->getDisplayName() === 'Entity Test', 'Display name should be set');
        $this->assert($user->isActive(), 'User should be active');

        // Test user retrieval
        $found = $this->userProvider->findByEmail('entity@test.com');
        $this->assert($found !== null, 'Should find user by email');
        $this->assert($found->getId() === $user->getId(), 'Found user should have same ID');

        // Cleanup
        $this->em->delete($user);
    }

    private function testPasswordHashing(): void
    {
        $this->group('Password Hashing');

        $user = new User();
        $user->setPassword('SecurePass123!');

        // Test hash is different from plain password
        $this->assert($user->getPasswordHash() !== 'SecurePass123!', 'Password should be hashed');

        // Test verification
        $this->assert($user->verifyPassword('SecurePass123!'), 'Correct password should verify');
        $this->assert(!$user->verifyPassword('WrongPassword'), 'Wrong password should not verify');

        // Test Argon2id is used
        $this->assert(
            str_starts_with($user->getPasswordHash(), '$argon2id$'),
            'Should use Argon2id hashing'
        );
    }

    private function testAuthentication(): void
    {
        $this->group('Authentication');

        // Test successful login
        $result = $this->auth->attempt('test@example.com', 'TestPassword123', false, '127.0.0.1');
        $this->assert($result->success, 'Login should succeed with correct credentials');
        $this->assert($result->user !== null, 'Should return user on success');
        $this->assert($result->tokens !== null, 'Should return tokens on success');

        // Test check()
        $this->assert($this->auth->check(), 'Should be authenticated after login');

        // Test user()
        $user = $this->auth->user();
        $this->assert($user !== null, 'Should return authenticated user');
        $this->assert($user->getEmail() === 'test@example.com', 'Should return correct user');

        // Test can()
        $this->assert($this->auth->can('access_admin') || $this->auth->hasRole('admin'), 
            'Test user should have some permissions');

        // Test logout
        $this->auth->logout();
        $this->assert($this->auth->guest(), 'Should be guest after logout');

        // Test failed login
        $result = $this->auth->attempt('test@example.com', 'WrongPassword', false, '127.0.0.1');
        $this->assert(!$result->success, 'Login should fail with wrong password');
        $this->assert($result->error !== null, 'Should return error message');
    }

    private function testLoginAttempts(): void
    {
        $this->group('Login Attempts & Brute Force Protection');

        $email = 'bruteforce@test.com';
        $ip = '192.168.1.100';

        // Clear any existing attempts
        $this->loginAttempt->clearAttempts($email);

        // Record failures
        for ($i = 0; $i < 4; $i++) {
            $this->loginAttempt->recordFailure($email, $ip);
        }

        // Check remaining attempts
        $remaining = $this->loginAttempt->getRemainingAttempts($email, $ip);
        $this->assert($remaining === 1, "Should have 1 attempt remaining, got {$remaining}");

        // One more failure
        $this->loginAttempt->recordFailure($email, $ip);

        // Should be locked out now
        $lockout = $this->loginAttempt->checkLockout($email, $ip);
        $this->assert($lockout['locked'], 'Should be locked after 5 failed attempts');
        $this->assert($lockout['remaining'] > 0, 'Should have lockout time remaining');

        // Test clear on success
        $this->loginAttempt->recordSuccess($email, $ip);
        $lockout = $this->loginAttempt->checkLockout($email, $ip);
        $this->assert(!$lockout['locked'], 'Should not be locked after success');
    }

    private function testSessionManager(): void
    {
        $this->group('Session Manager');

        // Note: Session tests are limited without actual HTTP context
        $session = new SessionManager(['secure' => false]);

        // Test CSRF token generation
        $token1 = $session->getCsrfToken();
        $this->assert(strlen($token1) === 64, 'CSRF token should be 64 chars');

        // Token should be consistent
        $token2 = $session->getCsrfToken();
        $this->assert($token1 === $token2, 'CSRF token should be consistent');

        // Test token verification
        $this->assert($session->verifyCsrfToken($token1), 'Should verify correct token');
        $this->assert(!$session->verifyCsrfToken('invalid'), 'Should reject invalid token');
    }

    private function testApiKeys(): void
    {
        $this->group('API Keys');

        // Get test user
        $user = $this->userProvider->findByEmail('test@example.com');
        if (!$user) {
            $this->assert(false, 'Test user not found');
            return;
        }

        // Create API key
        $result = $this->apiKeys->create(
            $user->getId(),
            'Test Key',
            ['read:content', 'write:content'],
            new \DateTime('+30 days')
        );

        $this->assert(!empty($result['key']), 'Should return key');
        $this->assert($result['id'] > 0, 'Should return key ID');
        $this->assert(str_starts_with($result['key'], 'ml_'), 'Key should start with ml_');

        // Validate key
        $keyData = $this->apiKeys->validate($result['key']);
        $this->assert($keyData !== null, 'Should validate correct key');
        $this->assert($keyData['user_id'] === $user->getId(), 'Should return correct user ID');

        // Check scopes
        $this->assert(
            $this->apiKeys->hasScope($keyData, 'read:content'),
            'Should have read:content scope'
        );
        $this->assert(
            $this->apiKeys->hasScope($keyData, 'write:content'),
            'Should have write:content scope'
        );
        $this->assert(
            !$this->apiKeys->hasScope($keyData, 'delete:content'),
            'Should not have delete:content scope'
        );

        // List keys
        $keys = $this->apiKeys->listForUser($user->getId());
        $this->assert(count($keys) >= 1, 'Should list at least one key');

        // Revoke key
        $revoked = $this->apiKeys->revoke($result['id'], $user->getId());
        $this->assert($revoked, 'Should revoke key');

        // Validate revoked key
        $keyData = $this->apiKeys->validate($result['key']);
        $this->assert($keyData === null, 'Revoked key should not validate');
    }

    private function testEmailVerification(): void
    {
        $this->group('Email Verification');

        // Create email verification service
        $emailVerification = AuthServiceProvider::getEmailVerification();

        // Get test user
        $user = $this->userProvider->findByEmail('test@example.com');
        if (!$user) {
            $this->assert(false, 'Test user not found');
            return;
        }

        // Generate token
        $tokenData = $emailVerification->generateToken($user->getId());
        $this->assert(!empty($tokenData['token']), 'Should generate token');
        $this->assert($tokenData['expires_at'] > new \DateTimeImmutable(), 'Token should expire in future');

        // Verify token
        $result = $emailVerification->verify($tokenData['token']);
        $this->assert($result->success, 'Should verify valid token');
        $this->assert($result->user !== null, 'Should return user');

        // Verify same token again (should fail - one-time use)
        $result = $emailVerification->verify($tokenData['token']);
        $this->assert(!$result->success, 'Used token should not verify again');
    }

    // =========================================================================
    // Setup & Cleanup
    // =========================================================================

    private function setupTestUser(): void
    {
        // Clean up any existing test user
        $existing = $this->userProvider->findByEmail('test@example.com');
        if ($existing) {
            $this->em->delete($existing);
        }

        // Create test user
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('TestPassword123');
        $user->setDisplayName('Test User');
        $user->setStatus('active');
        $user->verifyEmail();
        
        $this->em->save($user);

        // Assign role
        $this->userProvider->assignRole($user, 'admin');
    }

    private function cleanup(): void
    {
        // Clean up test users
        $emails = ['test@example.com', 'entity@test.com'];
        foreach ($emails as $email) {
            $user = $this->userProvider->findByEmail($email);
            if ($user) {
                $this->em->delete($user);
            }
        }

        // Clean up login attempts
        $this->loginAttempt->clearAttempts('bruteforce@test.com');

        // Reset service provider
        AuthServiceProvider::reset();
    }

    // =========================================================================
    // Test Helpers
    // =========================================================================

    private function group(string $name): void
    {
        echo "\n  {$name}\n";
        echo "  " . str_repeat('-', strlen($name)) . "\n";
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            echo "    ✓ {$message}\n";
            $this->passed++;
        } else {
            echo "    ✗ {$message}\n";
            $this->failed++;
        }
        
        $this->testResults[] = [
            'message' => $message,
            'passed' => $condition,
        ];
    }

    private function printReport(): void
    {
        $total = $this->passed + $this->failed;
        
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Results: {$this->passed}/{$total} tests passed\n";
        
        if ($this->failed > 0) {
            echo "\nFailed tests:\n";
            foreach ($this->testResults as $result) {
                if (!$result['passed']) {
                    echo "  - {$result['message']}\n";
                }
            }
        }
        
        echo str_repeat('=', 50) . "\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

// =========================================================================
// Run Tests
// =========================================================================

// Check if running directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    // Load autoloader
    require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

    // Load database config
    $configFile = dirname(__DIR__, 4) . '/config/database.php';
    if (!file_exists($configFile)) {
        echo "Error: config/database.php not found\n";
        echo "Copy config/database.example.php to config/database.php and configure it.\n";
        exit(1);
    }

    $config = require $configFile;

    try {
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $db = new \PDO($dsn, $config['username'], $config['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $test = new AuthSystemTest($db);
        $test->run();

    } catch (\PDOException $e) {
        echo "Database connection failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
