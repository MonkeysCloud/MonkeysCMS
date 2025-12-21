<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\SessionManager;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use MonkeysLegion\Router\Attributes\Route;

/**
 * TwoFactorController - Handles 2FA setup and management
 */
class TwoFactorController
{
    private CmsAuthService $auth;
    private SessionManager $session;

    public function __construct(CmsAuthService $auth, SessionManager $session)
    {
        $this->auth = $auth;
        $this->session = $session;
    }

    /**
     * Show 2FA settings page
     */
    #[Route('GET', '/settings/2fa', name: 'settings.2fa')]
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        // Check if 2FA is already enabled
        $has2FA = $user->has2FAEnabled ?? false;

        return $this->view('auth/2fa-settings', [
            'user' => $user,
            'has_2fa' => $has2FA,
            'csrf_token' => $this->session->getCsrfToken(),
            'success' => $this->session->getFlash('success'),
            'error' => $this->session->getFlash('error'),
        ]);
    }

    /**
     * Generate 2FA setup (QR code)
     */
    #[Route('POST', '/settings/2fa/setup', name: 'settings.2fa.setup')]
    public function setup(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $setupData = $this->auth->generate2FASetup();

        if (!$setupData) {
            return $this->jsonResponse(['error' => 'Failed to generate 2FA setup'], 500);
        }

        // Store secret temporarily in session for verification
        $this->session->set('2fa_temp_secret', $setupData['secret']);

        return $this->jsonResponse([
            'secret' => $setupData['secret'],
            'qr_code' => $setupData['qr_code'],
            'recovery_codes' => $setupData['recovery'] ?? [],
        ]);
    }

    /**
     * Enable 2FA after verification
     */
    #[Route('POST', '/settings/2fa/enable', name: 'settings.2fa.enable')]
    public function enable(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        $data = $request->getParsedBody();
        $code = $data['code'] ?? '';
        $secret = $this->session->get('2fa_temp_secret');

        if (!$secret) {
            $this->session->flash('error', 'Please start the setup process again');
            return $this->redirect('/settings/2fa');
        }

        if (empty($code)) {
            $this->session->flash('error', 'Verification code is required');
            return $this->redirect('/settings/2fa');
        }

        if ($this->auth->enable2FA($secret, $code)) {
            $this->session->forget('2fa_temp_secret');
            $this->session->flash('success', 'Two-factor authentication has been enabled');
            return $this->redirect('/settings/2fa');
        }

        $this->session->flash('error', 'Invalid verification code. Please try again.');
        return $this->redirect('/settings/2fa');
    }

    /**
     * Disable 2FA
     */
    #[Route('POST', '/settings/2fa/disable', name: 'settings.2fa.disable')]
    public function disable(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        $data = $request->getParsedBody();
        $password = $data['password'] ?? '';

        if (empty($password)) {
            $this->session->flash('error', 'Password is required to disable 2FA');
            return $this->redirect('/settings/2fa');
        }

        if ($this->auth->disable2FA($password)) {
            $this->session->flash('success', 'Two-factor authentication has been disabled');
            return $this->redirect('/settings/2fa');
        }

        $this->session->flash('error', 'Invalid password');
        return $this->redirect('/settings/2fa');
    }

    /**
     * Show recovery codes
     */
    #[Route('GET', '/settings/2fa/recovery', name: 'settings.2fa.recovery')]
    public function showRecoveryCodes(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        // Require recent password verification
        if (!$this->session->has('password_confirmed_at') || 
            $this->session->get('password_confirmed_at') < time() - 300) {
            $this->session->setIntendedUrl('/settings/2fa/recovery');
            return $this->redirect('/settings/confirm-password');
        }

        // TODO: Get recovery codes from user provider
        $recoveryCodes = [];

        return $this->view('auth/2fa-recovery', [
            'recovery_codes' => $recoveryCodes,
            'csrf_token' => $this->session->getCsrfToken(),
        ]);
    }

    /**
     * Regenerate recovery codes
     */
    #[Route('POST', '/settings/2fa/recovery/regenerate', name: 'settings.2fa.regenerate')]
    public function regenerateRecoveryCodes(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        $data = $request->getParsedBody();
        $password = $data['password'] ?? '';

        if (!$user->verifyPassword($password)) {
            $this->session->flash('error', 'Invalid password');
            return $this->redirect('/settings/2fa/recovery');
        }

        // TODO: Regenerate recovery codes
        $this->session->flash('success', 'Recovery codes have been regenerated');
        return $this->redirect('/settings/2fa/recovery');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function redirect(string $url, int $status = 302): ResponseInterface
    {
        return new \Nyholm\Psr7\Response($status, ['Location' => $url]);
    }

    private function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        return new \Nyholm\Psr7\Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );
    }

    private function view(string $template, array $data = []): ResponseInterface
    {
        $html = $this->renderTemplate($data);
        return new \Nyholm\Psr7\Response(200, ['Content-Type' => 'text/html'], $html);
    }

    private function renderTemplate(array $data): string
    {
        $csrf = $data['csrf_token'] ?? '';
        $has2FA = $data['has_2fa'] ?? false;
        $success = $data['success'] ?? '';
        $error = $data['error'] ?? '';

        $messageHtml = '';
        if ($success) {
            $messageHtml = "<div class=\"bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4\">{$success}</div>";
        }
        if ($error) {
            $messageHtml = "<div class=\"bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4\">{$error}</div>";
        }

        if ($has2FA) {
            return $this->enabled2FATemplate($csrf, $messageHtml);
        }

        return $this->setup2FATemplate($csrf, $messageHtml);
    }

    private function setup2FATemplate(string $csrf, string $messageHtml): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - MonkeysCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen py-12">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white p-8 rounded-lg shadow-md">
            <h1 class="text-2xl font-bold mb-6">Two-Factor Authentication</h1>
            
            {$messageHtml}
            
            <div class="mb-6">
                <p class="text-gray-600 mb-4">
                    Add an extra layer of security to your account by enabling two-factor authentication.
                </p>
                <p class="text-gray-600">
                    You'll need an authenticator app like Google Authenticator, Authy, or 1Password.
                </p>
            </div>
            
            <div id="setup-container" class="hidden">
                <div class="border rounded-lg p-6 mb-6">
                    <h3 class="font-semibold mb-4">1. Scan this QR code</h3>
                    <div id="qr-code" class="flex justify-center mb-4"></div>
                    
                    <h3 class="font-semibold mb-2">Or enter this code manually:</h3>
                    <code id="secret-code" class="block bg-gray-100 p-3 rounded text-center text-lg tracking-widest"></code>
                </div>
                
                <form method="POST" action="/settings/2fa/enable" class="space-y-4">
                    <input type="hidden" name="_token" value="{$csrf}">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">2. Enter verification code</label>
                        <input type="text" name="code" required pattern="[0-9]{6}" maxlength="6"
                               class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center text-xl tracking-widest">
                    </div>
                    
                    <button type="submit" 
                            class="bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                        Enable 2FA
                    </button>
                </form>
            </div>
            
            <button id="start-setup" type="button"
                    class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Set Up 2FA
            </button>
        </div>
    </div>
    
    <script>
        document.getElementById('start-setup').addEventListener('click', async function() {
            this.disabled = true;
            this.textContent = 'Loading...';
            
            try {
                const response = await fetch('/settings/2fa/setup', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{$csrf}'
                    }
                });
                
                const data = await response.json();
                
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                // Show QR code
                document.getElementById('qr-code').innerHTML = '<img src="' + data.qr_code + '" alt="QR Code">';
                document.getElementById('secret-code').textContent = data.secret;
                document.getElementById('setup-container').classList.remove('hidden');
                this.classList.add('hidden');
                
            } catch (error) {
                alert('Failed to start setup. Please try again.');
                this.disabled = false;
                this.textContent = 'Set Up 2FA';
            }
        });
    </script>
</body>
</html>
HTML;
    }

    private function enabled2FATemplate(string $csrf, string $messageHtml): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - MonkeysCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen py-12">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white p-8 rounded-lg shadow-md">
            <h1 class="text-2xl font-bold mb-6">Two-Factor Authentication</h1>
            
            {$messageHtml}
            
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-green-800 font-medium">Two-factor authentication is enabled</span>
                </div>
            </div>
            
            <div class="space-y-4">
                <a href="/settings/2fa/recovery" 
                   class="block text-blue-600 hover:underline">
                    View recovery codes
                </a>
                
                <hr>
                
                <form method="POST" action="/settings/2fa/disable" class="space-y-4">
                    <input type="hidden" name="_token" value="{$csrf}">
                    
                    <p class="text-gray-600">To disable 2FA, enter your password:</p>
                    
                    <div>
                        <input type="password" name="password" required placeholder="Your password"
                               class="block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    
                    <button type="submit" 
                            class="bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                            onclick="return confirm('Are you sure you want to disable 2FA?')">
                        Disable 2FA
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
