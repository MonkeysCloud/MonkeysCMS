# MonkeysCMS Authentication System

The authentication system is built on top of [MonkeysLegion-Auth](https://github.com/MonkeysCloud/MonkeysLegion-Auth), providing comprehensive authentication and authorization for MonkeysCMS.

## Features

| Feature | Description |
|---------|-------------|
| **JWT Authentication** | Stateless auth with access/refresh token pairs |
| **Session Management** | Secure session handling with CSRF protection |
| **Password Hashing** | Argon2id with bcrypt fallback |
| **Two-Factor Auth** | TOTP-based 2FA with recovery codes |
| **OAuth/Social Login** | Google, GitHub providers (extensible) |
| **Brute Force Protection** | Exponential backoff lockouts |
| **Remember Me** | Persistent login with rotating tokens |
| **RBAC** | Role-based access control |

## Installation

```bash
composer require monkeyscloud/monkeyslegion-auth
```

Run the migrations:

```bash
php cms migrate
```

## Quick Start

### Initialize Authentication

```php
use App\Cms\Auth\AuthServiceProvider;

// Initialize with database connection
AuthServiceProvider::init($pdo, [
    'jwt_secret' => $_ENV['JWT_SECRET'],
    'app_name' => 'My CMS',
]);

// Get auth service
$auth = AuthServiceProvider::getAuthService();
```

### Login

```php
$result = $auth->attempt($email, $password, $remember);

if ($result->requires2FA) {
    // Handle 2FA verification
    redirect('/login/2fa');
}

if ($result->success) {
    redirect('/admin');
}

// Handle error
$error = $result->error;
```

### Check Authentication

```php
if ($auth->check()) {
    $user = $auth->user();
    echo "Hello, " . $user->getDisplayName();
}

// Check permissions
if ($auth->can('create_content')) {
    // User can create content
}

// Check roles
if ($auth->hasRole('admin')) {
    // User is admin
}
```

### Logout

```php
// Single device
$auth->logout();

// All devices
$auth->logout(allDevices: true);
```

## Controllers

### Routes

| Method | Path | Controller | Description |
|--------|------|------------|-------------|
| GET | /login | LoginController@show | Show login form |
| POST | /login | LoginController@login | Process login |
| GET | /login/2fa | LoginController@show2FA | Show 2FA form |
| POST | /login/2fa | LoginController@verify2FA | Verify 2FA code |
| POST | /logout | LogoutController@logout | Logout user |
| GET | /register | RegisterController@show | Show registration |
| POST | /register | RegisterController@register | Process registration |
| GET | /password/forgot | PasswordResetController@showForgot | Forgot password |
| POST | /password/forgot | PasswordResetController@sendReset | Send reset email |
| GET | /password/reset/{token} | PasswordResetController@showReset | Reset form |
| POST | /password/reset | PasswordResetController@reset | Process reset |
| GET | /profile | ProfileController@show | User profile |
| PUT | /profile | ProfileController@update | Update profile |
| GET | /settings/2fa | TwoFactorController@show | 2FA settings |

## Middleware

### AuthMiddleware

Validates authentication and attaches user to request:

```php
use App\Cms\Auth\Middleware\AuthMiddleware;

$middleware = AuthServiceProvider::getAuthMiddleware(
    publicPaths: ['/login', '/register', '/password/*', '/api/public/*'],
    guestOnlyPaths: ['/login', '/register']
);
```

### AdminMiddleware

Restricts access to admin area:

```php
use App\Cms\Auth\Middleware\AdminMiddleware;

$middleware = AuthServiceProvider::getAdminMiddleware();
```

### CsrfMiddleware

Validates CSRF tokens on state-changing requests:

```php
use App\Cms\Auth\Middleware\CsrfMiddleware;

$middleware = AuthServiceProvider::getCsrfMiddleware(
    excludePaths: ['/api/*']
);
```

## Two-Factor Authentication

### Setup 2FA

```php
// Generate setup data
$setup = $auth->generate2FASetup();

// Returns:
// - secret: Manual entry code
// - qr_code: Base64 QR image
// - recovery_codes: Backup codes

// Enable after user verifies first code
$auth->enable2FA($setup['secret'], $userCode);
```

### Verify 2FA on Login

```php
// After password verification
if ($result->requires2FA) {
    // Store challenge token in session
    $_SESSION['2fa_challenge'] = $result->challengeToken;
    
    // Later, verify the code
    $result = $auth->verify2FA($challengeToken, $code);
}
```

### Disable 2FA

```php
// Requires password confirmation
$auth->disable2FA($password);
```

## OAuth / Social Login

### Available Providers

- Google
- GitHub

### Setup

```php
// In .env
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=https://yoursite.com/auth/google/callback

GITHUB_CLIENT_ID=your-client-id
GITHUB_CLIENT_SECRET=your-client-secret
GITHUB_REDIRECT_URI=https://yoursite.com/auth/github/callback
```

### Usage

```php
$oauth = AuthServiceProvider::getOAuthService();

// Redirect to provider
$url = $oauth->getAuthorizationUrl('google');
redirect($url);

// Handle callback
$result = $oauth->handleCallback('google', $code, $state);

if ($result->success) {
    // User is logged in
    // $result->isNewUser indicates if account was just created
}
```

## Brute Force Protection

The `LoginAttempt` class tracks failed login attempts:

```php
$loginAttempt = AuthServiceProvider::getLoginAttempt();

// Check if locked out
$lockout = $loginAttempt->checkLockout($email, $ip);

if ($lockout['locked']) {
    $minutes = ceil($lockout['remaining'] / 60);
    echo "Try again in {$minutes} minutes";
}

// Record failure
$loginAttempt->recordFailure($email, $ip);

// Clear on success
$loginAttempt->recordSuccess($email, $ip);
```

### Configuration

```php
[
    'max_attempts' => 5,           // Attempts before lockout
    'lockout_minutes' => 15,       // Initial lockout duration
    'lockout_multiplier' => 2,     // Exponential backoff
    'max_lockout_minutes' => 1440, // Max 24 hours
]
```

## Password Policy

Configure in `config/auth.php`:

```php
'password' => [
    'min_length' => 8,
    'require_uppercase' => true,
    'require_number' => true,
    'require_special' => false,
    'algorithm' => PASSWORD_ARGON2ID,
]
```

## User Entity

The CMS User entity integrates with MonkeysLegion-Auth:

```php
use App\Cms\User\User;

// Authentication
$user->verifyPassword($password);
$user->needsRehash();
$user->setPassword($newPassword);

// Status
$user->isActive();
$user->isBlocked();
$user->activate();
$user->block();

// Roles & Permissions
$user->hasRole('admin');
$user->hasPermission('create_content');
$user->hasAnyRole(['admin', 'editor']);
$user->hasAllPermissions(['edit', 'publish']);

// 2FA
$user->has2FAEnabled;
```

## Database Tables

| Table | Purpose |
|-------|---------|
| users | User accounts |
| roles | Role definitions |
| permissions | Permission definitions |
| user_roles | User-role assignments |
| role_permissions | Role-permission assignments |
| login_attempts | Failed login tracking |
| login_lockouts | Account lockouts |
| oauth_accounts | OAuth provider links |
| token_blacklist | Revoked JWT tokens |
| api_keys | API key storage |
| password_resets | Password reset tokens |
| email_verifications | Email verification tokens |

## API Authentication

The authentication system provides a complete REST API for token-based authentication.

### Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | /api/auth/login | Get access/refresh tokens |
| POST | /api/auth/2fa/verify | Verify 2FA code |
| POST | /api/auth/refresh | Refresh access token |
| POST | /api/auth/logout | Revoke tokens |
| GET | /api/auth/me | Get authenticated user |
| POST | /api/auth/register | Register new user |
| POST | /api/auth/password/forgot | Request password reset |
| POST | /api/auth/password/reset | Reset password with token |
| PUT | /api/auth/password | Change password |
| GET | /api/auth/api-keys | List API keys |
| POST | /api/auth/api-keys | Create API key |
| DELETE | /api/auth/api-keys/{id} | Revoke API key |

### Login Example

```bash
# Login and get tokens
curl -X POST https://yoursite.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "secret"}'

# Response:
# {
#   "access_token": "eyJ...",
#   "refresh_token": "eyJ...",
#   "token_type": "Bearer",
#   "expires_in": 1800,
#   "user": {...}
# }
```

### Authenticated Requests

```bash
# Use Bearer token
curl https://yoursite.com/api/content \
  -H "Authorization: Bearer <access_token>"

# Or use API key
curl https://yoursite.com/api/content \
  -H "X-API-Key: ml_xxx_yyy"
```

### Refresh Token

```bash
curl -X POST https://yoursite.com/api/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token": "<refresh_token>"}'
```

## API Keys

For machine-to-machine authentication:

```php
use App\Cms\Auth\CmsApiKeyService;

$apiKeys = new CmsApiKeyService($db, $userProvider);

// Create a key
$result = $apiKeys->create(
    userId: $user->getId(),
    name: 'Production Server',
    scopes: ['read:content', 'write:content'],
    expiresAt: new DateTime('+1 year'),
);

// Key is only shown once!
echo $result['key']; // ml_abc123_secretpart

// Validate a key
$keyData = $apiKeys->validate($apiKey);
if ($keyData && $apiKeys->hasScope($keyData, 'write:content')) {
    // Authorized
}

// List user's keys
$keys = $apiKeys->listForUser($userId);

// Revoke a key
$apiKeys->revoke($keyId, $userId);
```

## Events

The auth system dispatches events for audit logging:

| Event | When |
|-------|------|
| UserRegistered | New user created |
| LoginSucceeded | Successful login |
| LoginFailed | Failed login attempt |
| Logout | User logged out |
| PasswordChanged | Password updated |
| TwoFactorEnabled | 2FA turned on |

## Security Best Practices

1. **Use strong JWT secrets** - Min 32 characters of random data
2. **Keep access tokens short-lived** - 15-30 minutes
3. **Enable HTTPS** - Required for secure cookies
4. **Enable 2FA for admins** - Require for privileged accounts
5. **Monitor login failures** - Alert on suspicious activity
6. **Regular token rotation** - Refresh tokens on each use
7. **Secure session cookies** - HttpOnly, Secure, SameSite

## File Structure

```
app/Cms/Auth/
├── CmsAuthService.php         # Main auth service
├── CmsUserProvider.php        # User data provider
├── CmsApiKeyService.php       # API key authentication
├── SessionManager.php         # Session handling
├── LoginAttempt.php           # Brute force protection
├── EmailVerification.php      # Email verification
├── AuthServiceProvider.php    # Dependency injection
├── OAuth/
│   └── CmsOAuthService.php    # OAuth integration
├── Middleware/
│   ├── AuthMiddleware.php     # Web route protection
│   └── ApiAuthMiddleware.php  # API route protection
└── Tests/
    └── AuthSystemTest.php     # Authentication tests

app/Controllers/Auth/
├── LoginController.php
├── LogoutController.php
├── RegisterController.php
├── PasswordResetController.php
├── TwoFactorController.php
└── ProfileController.php

app/Controllers/Api/
└── ApiAuthController.php      # API authentication endpoints

app/Cms/Database/migrations/
├── 001_users.php              # Users table
├── 002_roles_permissions.php  # RBAC tables
└── 011_authentication.php     # Auth-specific tables
```
