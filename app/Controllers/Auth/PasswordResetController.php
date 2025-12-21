<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\SessionManager;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PasswordResetController - Handles password reset flow
 */
class PasswordResetController
{
    private CmsAuthService $auth;
    private SessionManager $session;

    public function __construct(CmsAuthService $auth, SessionManager $session)
    {
        $this->auth = $auth;
        $this->session = $session;
    }

    /**
     * Show forgot password form
     * 
     * GET /password/forgot
     */
    public function showForgot(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view('auth/forgot-password', [
            'csrf_token' => $this->session->getCsrfToken(),
            'error' => $this->session->getFlash('error'),
            'success' => $this->session->getFlash('success'),
        ]);
    }

    /**
     * Send password reset email
     * 
     * POST /password/forgot
     */
    public function sendReset(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'Please enter a valid email address');
            return $this->redirect('/password/forgot');
        }

        // Always show success to prevent email enumeration
        $this->auth->sendPasswordReset($email);

        $this->session->flash('success', 
            'If an account exists with that email, you will receive a password reset link shortly.');
        return $this->redirect('/password/forgot');
    }

    /**
     * Show reset password form
     * 
     * GET /password/reset/{token}
     */
    public function showReset(ServerRequestInterface $request, string $token): ResponseInterface
    {
        return $this->view('auth/reset-password', [
            'token' => $token,
            'csrf_token' => $this->session->getCsrfToken(),
            'error' => $this->session->getFlash('error'),
        ]);
    }

    /**
     * Reset password with token
     * 
     * POST /password/reset
     */
    public function reset(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';
        $confirmation = $data['password_confirmation'] ?? '';

        // Validate
        if (empty($token)) {
            $this->session->flash('error', 'Invalid reset token');
            return $this->redirect('/password/forgot');
        }

        if (empty($password)) {
            $this->session->flash('error', 'Password is required');
            return $this->redirect("/password/reset/{$token}");
        }

        if (strlen($password) < 8) {
            $this->session->flash('error', 'Password must be at least 8 characters');
            return $this->redirect("/password/reset/{$token}");
        }

        if ($password !== $confirmation) {
            $this->session->flash('error', 'Passwords do not match');
            return $this->redirect("/password/reset/{$token}");
        }

        // Attempt reset
        if ($this->auth->resetPassword($token, $password)) {
            $this->session->flash('success', 'Password reset successfully! You can now log in.');
            return $this->redirect('/login');
        }

        $this->session->flash('error', 'Invalid or expired reset token');
        return $this->redirect('/password/forgot');
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
        $html = $this->renderTemplate($template, $data);
        return new \Nyholm\Psr7\Response(200, ['Content-Type' => 'text/html'], $html);
    }

    private function renderTemplate(string $template, array $data): string
    {
        if ($template === 'auth/forgot-password') {
            return $this->forgotTemplate($data);
        }
        return $this->resetTemplate($data);
    }

    private function forgotTemplate(array $data): string
    {
        $csrf = $data['csrf_token'] ?? '';
        $error = $data['error'] ?? '';
        $success = $data['success'] ?? '';

        $messageHtml = '';
        if ($error) {
            $messageHtml = "<div class=\"bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4\">{$error}</div>";
        }
        if ($success) {
            $messageHtml = "<div class=\"bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4\">{$success}</div>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MonkeysCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold text-center mb-2">Forgot Password</h1>
        <p class="text-gray-600 text-center mb-6">Enter your email and we'll send you a reset link.</p>
        
        {$messageHtml}
        
        <form method="POST" action="/password/forgot" class="space-y-4">
            <input type="hidden" name="_token" value="{$csrf}">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" required autofocus
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Send Reset Link
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="/login" class="text-sm text-blue-600 hover:underline">Back to login</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function resetTemplate(array $data): string
    {
        $csrf = $data['csrf_token'] ?? '';
        $token = $data['token'] ?? '';
        $error = $data['error'] ?? '';

        $errorHtml = $error 
            ? "<div class=\"bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4\">{$error}</div>" 
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MonkeysCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold text-center mb-6">Reset Password</h1>
        
        {$errorHtml}
        
        <form method="POST" action="/password/reset" class="space-y-4">
            <input type="hidden" name="_token" value="{$csrf}">
            <input type="hidden" name="token" value="{$token}">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">New Password</label>
                <input type="password" name="password" required 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <p class="text-gray-500 text-xs mt-1">Min 8 characters</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                <input type="password" name="password_confirmation" required 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Reset Password
            </button>
        </form>
    </div>
</body>
</html>
HTML;
    }
}
