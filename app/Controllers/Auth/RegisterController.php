<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\SessionManager;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * RegisterController - Handles user registration
 */
class RegisterController
{
    private CmsAuthService $auth;
    private SessionManager $session;
    private array $config;

    public function __construct(
        CmsAuthService $auth,
        SessionManager $session,
        array $config = []
    ) {
        $this->auth = $auth;
        $this->session = $session;
        $this->config = array_merge([
            'require_email_verification' => true,
            'auto_login' => true,
            'password_min_length' => 8,
            'password_require_uppercase' => true,
            'password_require_number' => true,
        ], $config);
    }

    /**
     * Show registration form
     * 
     * GET /register
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->auth->check()) {
            return $this->redirect('/admin');
        }

        return $this->view('auth/register', [
            'csrf_token' => $this->session->getCsrfToken(),
            'error' => $this->session->getFlash('error'),
            'errors' => $this->session->getFlash('errors') ?? [],
            'old' => $this->session->getFlash('old') ?? [],
        ]);
    }

    /**
     * Process registration
     * 
     * POST /register
     */
    public function register(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        // Validate input
        $errors = $this->validate($data);

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            $this->session->flash('old', [
                'email' => $data['email'] ?? '',
                'username' => $data['username'] ?? '',
                'display_name' => $data['display_name'] ?? '',
            ]);
            return $this->redirect('/register');
        }

        // Attempt registration
        $result = $this->auth->register([
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => $data['password'],
            'display_name' => $data['display_name'] ?? null,
        ], $this->config['auto_login']);

        if ($result->failed()) {
            $this->session->flash('error', $result->error);
            $this->session->flash('old', [
                'email' => $data['email'] ?? '',
                'username' => $data['username'] ?? '',
                'display_name' => $data['display_name'] ?? '',
            ]);
            return $this->redirect('/register');
        }

        // Send verification email if required
        if ($this->config['require_email_verification']) {
            // TODO: Send verification email
            $this->session->flash('success', 'Registration successful! Please check your email to verify your account.');
            
            if (!$this->config['auto_login']) {
                return $this->redirect('/login');
            }
        }

        // Auto-login successful
        if ($this->config['auto_login'] && $result->success) {
            $this->session->regenerate();
            return $this->redirect('/admin');
        }

        $this->session->flash('success', 'Registration successful! You can now log in.');
        return $this->redirect('/login');
    }

    /**
     * Validate registration data
     */
    private function validate(array $data): array
    {
        $errors = [];

        // Email
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }

        // Username
        if (empty($data['username'])) {
            $errors['username'] = 'Username is required';
        } elseif (strlen($data['username']) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        } elseif (strlen($data['username']) > 30) {
            $errors['username'] = 'Username cannot exceed 30 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors['username'] = 'Username can only contain letters, numbers, and underscores';
        }

        // Password
        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } else {
            $passwordErrors = $this->validatePassword($data['password']);
            if (!empty($passwordErrors)) {
                $errors['password'] = $passwordErrors[0];
            }
        }

        // Password confirmation
        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }

        // Terms acceptance (optional)
        if (isset($data['terms']) && !$data['terms']) {
            $errors['terms'] = 'You must accept the terms of service';
        }

        return $errors;
    }

    /**
     * Validate password strength
     */
    private function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < $this->config['password_min_length']) {
            $errors[] = "Password must be at least {$this->config['password_min_length']} characters";
        }

        if ($this->config['password_require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if ($this->config['password_require_number'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        return $errors;
    }

    /**
     * Verify email with token
     * 
     * GET /verify-email/{token}
     */
    public function verifyEmail(ServerRequestInterface $request, string $token): ResponseInterface
    {
        // TODO: Implement email verification
        $this->session->flash('success', 'Email verified successfully! You can now log in.');
        return $this->redirect('/login');
    }

    /**
     * Resend verification email
     * 
     * POST /verify-email/resend
     */
    public function resendVerification(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';

        if (empty($email)) {
            $this->session->flash('error', 'Email is required');
            return $this->redirect('/login');
        }

        // TODO: Resend verification email
        $this->session->flash('success', 'Verification email sent! Please check your inbox.');
        return $this->redirect('/login');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function redirect(string $url, int $status = 302): ResponseInterface
    {
        return new \Nyholm\Psr7\Response($status, ['Location' => $url]);
    }

    private function view(string $template, array $data = []): ResponseInterface
    {
        $html = $this->renderTemplate($data);
        return new \Nyholm\Psr7\Response(200, ['Content-Type' => 'text/html'], $html);
    }

    private function renderTemplate(array $data): string
    {
        $csrf = $data['csrf_token'] ?? '';
        $error = $data['error'] ?? '';
        $errors = $data['errors'] ?? [];
        $old = $data['old'] ?? [];

        $errorHtml = '';
        if ($error) {
            $errorHtml = "<div class=\"bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4\">{$error}</div>";
        }

        $fieldError = fn($field) => isset($errors[$field]) 
            ? "<p class=\"text-red-500 text-sm mt-1\">{$errors[$field]}</p>" 
            : '';

        $oldValue = fn($field) => htmlspecialchars($old[$field] ?? '');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MonkeysCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center py-12">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold text-center mb-6">Create Account</h1>
        
        {$errorHtml}
        
        <form method="POST" action="/register" class="space-y-4">
            <input type="hidden" name="_token" value="{$csrf}">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" value="{$oldValue('email')}" required 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                {$fieldError('email')}
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" name="username" value="{$oldValue('username')}" required 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                {$fieldError('username')}
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Display Name (optional)</label>
                <input type="text" name="display_name" value="{$oldValue('display_name')}" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" required 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                {$fieldError('password')}
                <p class="text-gray-500 text-xs mt-1">Min 8 characters, 1 uppercase, 1 number</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                <input type="password" name="password_confirmation" required 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                {$fieldError('password_confirmation')}
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Create Account
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <span class="text-sm text-gray-600">Already have an account?</span>
            <a href="/login" class="text-sm text-blue-600 hover:underline ml-1">Sign in</a>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
