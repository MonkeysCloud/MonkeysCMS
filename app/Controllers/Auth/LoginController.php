<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\SessionManager;
use App\Cms\Auth\LoginAttempt;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * LoginController - Handles user authentication
 */
class LoginController
{
    private CmsAuthService $auth;
    private SessionManager $session;
    private LoginAttempt $loginAttempt;

    public function __construct(
        CmsAuthService $auth,
        SessionManager $session,
        LoginAttempt $loginAttempt
    ) {
        $this->auth = $auth;
        $this->session = $session;
        $this->loginAttempt = $loginAttempt;
    }

    /**
     * Show login form
     * 
     * GET /login
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        // If already authenticated, redirect to dashboard
        if ($this->auth->check()) {
            return $this->redirect('/admin');
        }

        return $this->view('auth/login', [
            'csrf_token' => $this->session->getCsrfToken(),
            'error' => $this->session->getFlash('error'),
            'success' => $this->session->getFlash('success'),
        ]);
    }

    /**
     * Process login
     * 
     * POST /login
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $ip = $this->session->getClientIp();

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $remember = isset($data['remember']);

        // Validate input
        if (empty($email) || empty($password)) {
            $this->session->flash('error', 'Email and password are required');
            return $this->redirect('/login');
        }

        // Check lockout
        $lockout = $this->loginAttempt->checkLockout($email, $ip);
        if ($lockout['locked']) {
            $minutes = ceil($lockout['remaining'] / 60);
            $this->session->flash('error', "Too many login attempts. Try again in {$minutes} minutes.");
            return $this->redirect('/login');
        }

        // Attempt login
        $result = $this->auth->attempt($email, $password, $remember, $ip);

        if ($result->success) {
            // Clear login attempts
            $this->loginAttempt->recordSuccess($email, $ip);

            // Regenerate session
            $this->session->regenerate();

            // Redirect to intended URL or dashboard
            $intended = $this->session->pullIntendedUrl('/admin');
            return $this->redirect($intended);
        }

        // Handle 2FA requirement
        if ($result->requires2FA) {
            $this->session->set('2fa_challenge', $result->challengeToken);
            $this->session->set('2fa_email', $email);
            return $this->redirect('/login/2fa');
        }

        // Record failed attempt
        $this->loginAttempt->recordFailure($email, $ip);

        // Show remaining attempts
        $remaining = $this->loginAttempt->getRemainingAttempts($email, $ip);
        $message = $result->error ?? 'Invalid email or password';
        
        if ($remaining > 0 && $remaining <= 3) {
            $message .= ". {$remaining} attempts remaining.";
        }

        $this->session->flash('error', $message);
        return $this->redirect('/login');
    }

    /**
     * Show 2FA verification form
     * 
     * GET /login/2fa
     */
    public function show2FA(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->session->has('2fa_challenge')) {
            return $this->redirect('/login');
        }

        return $this->view('auth/2fa', [
            'csrf_token' => $this->session->getCsrfToken(),
            'error' => $this->session->getFlash('error'),
        ]);
    }

    /**
     * Verify 2FA code
     * 
     * POST /login/2fa
     */
    public function verify2FA(ServerRequestInterface $request): ResponseInterface
    {
        $challengeToken = $this->session->get('2fa_challenge');
        
        if (!$challengeToken) {
            return $this->redirect('/login');
        }

        $data = $request->getParsedBody();
        $code = $data['code'] ?? '';
        $ip = $this->session->getClientIp();

        if (empty($code)) {
            $this->session->flash('error', 'Verification code is required');
            return $this->redirect('/login/2fa');
        }

        $result = $this->auth->verify2FA($challengeToken, $code, $ip);

        if ($result->success) {
            // Clear 2FA session data
            $this->session->forget('2fa_challenge');
            $this->session->forget('2fa_email');
            $this->session->regenerate();

            $intended = $this->session->pullIntendedUrl('/admin');
            return $this->redirect($intended);
        }

        $this->session->flash('error', 'Invalid verification code');
        return $this->redirect('/login/2fa');
    }

    /**
     * Cancel 2FA and return to login
     * 
     * GET /login/2fa/cancel
     */
    public function cancel2FA(ServerRequestInterface $request): ResponseInterface
    {
        $this->session->forget('2fa_challenge');
        $this->session->forget('2fa_email');
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
        // This would render the template
        // For now, return a simple HTML response
        $html = $this->renderTemplate($template, $data);
        return new \Nyholm\Psr7\Response(200, ['Content-Type' => 'text/html'], $html);
    }

    private function renderTemplate(string $template, array $data): string
    {
        // Simple template rendering - in production use proper template engine
        extract($data);
        ob_start();
        
        if ($template === 'auth/login') {
            echo $this->loginTemplate($data);
        } elseif ($template === 'auth/2fa') {
            echo $this->twoFactorTemplate($data);
        }
        
        return ob_get_clean();
    }

    private function loginTemplate(array $data): string
    {
        $error = $data['error'] ?? '';
        $success = $data['success'] ?? '';
        $csrf = $data['csrf_token'] ?? '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MonkeysCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold text-center mb-6">Login</h1>
        
        {$this->renderMessages($error, $success)}
        
        <form method="POST" action="/login" class="space-y-4">
            <input type="hidden" name="_token" value="{$csrf}">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" required 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" required 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            
            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-blue-600 shadow-sm">
                    <span class="ml-2 text-sm text-gray-600">Remember me</span>
                </label>
                <a href="/password/forgot" class="text-sm text-blue-600 hover:underline">Forgot password?</a>
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Sign In
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <span class="text-sm text-gray-600">Don't have an account?</span>
            <a href="/register" class="text-sm text-blue-600 hover:underline ml-1">Register</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function twoFactorTemplate(array $data): string
    {
        $error = $data['error'] ?? '';
        $csrf = $data['csrf_token'] ?? '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - MonkeysCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold text-center mb-6">Two-Factor Authentication</h1>
        <p class="text-gray-600 text-center mb-6">Enter the verification code from your authenticator app.</p>
        
        {$this->renderMessages($error, '')}
        
        <form method="POST" action="/login/2fa" class="space-y-4">
            <input type="hidden" name="_token" value="{$csrf}">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Verification Code</label>
                <input type="text" name="code" required autofocus
                       pattern="[0-9]{6}" maxlength="6"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center text-2xl tracking-widest">
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Verify
            </button>
        </form>
        
        <div class="mt-4 text-center">
            <a href="/login/2fa/cancel" class="text-sm text-gray-600 hover:underline">Back to login</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderMessages(string $error, string $success): string
    {
        $html = '';
        
        if ($error) {
            $html .= <<<HTML
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
    {$error}
</div>
HTML;
        }
        
        if ($success) {
            $html .= <<<HTML
<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
    {$success}
</div>
HTML;
        }
        
        return $html;
    }
}
