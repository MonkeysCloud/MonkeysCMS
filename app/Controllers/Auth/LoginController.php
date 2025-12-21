<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\SessionManager;
use App\Cms\Auth\LoginAttempt;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use MonkeysLegion\Template\Renderer;
use MonkeysLegion\Router\Attributes\Route;

/**
 * LoginController - Handles user authentication
 */
class LoginController
{
    private CmsAuthService $auth;
    private SessionManager $session;
    private LoginAttempt $loginAttempt;
    private Renderer $renderer;

    public function __construct(
        CmsAuthService $auth,
        SessionManager $session,
        LoginAttempt $loginAttempt,
        Renderer $renderer
    ) {
        $this->auth = $auth;
        $this->session = $session;
        $this->loginAttempt = $loginAttempt;
        $this->renderer = $renderer;
    }

    /**
     * Show login form
     */
    #[Route('GET', '/login', name: 'login')]
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        // If already authenticated, redirect to dashboard
        if ($this->auth->check()) {
            return redirect('/admin');
        }

        return $this->view('auth/login', [
            'csrf_token' => $this->session->getCsrfToken(),
            'error' => $this->session->getFlash('error'),
            'success' => $this->session->getFlash('success'),
        ]);
    }

    /**
     * Process login
     */
    #[Route('POST', '/login', name: 'login.submit')]
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
            return redirect('/login');
        }

        // Check lockout
        $lockout = $this->loginAttempt->checkLockout($email, $ip);
        if ($lockout['locked']) {
            $minutes = ceil($lockout['remaining'] / 60);
            $this->session->flash('error', "Too many login attempts. Try again in {$minutes} minutes.");
            return redirect('/login');
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
            return redirect($intended);
        }

        // Handle 2FA requirement
        if ($result->requires2FA) {
            $this->session->set('2fa_challenge', $result->challengeToken);
            $this->session->set('2fa_email', $email);
            return redirect('/login/2fa');
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
        return redirect('/login');
    }

    /**
     * Show 2FA verification form
     */
    #[Route('GET', '/login/2fa', name: 'login.2fa')]
    public function show2FA(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->session->has('2fa_challenge')) {
            return redirect('/login');
        }

        return $this->view('auth/2fa', [
            'csrf_token' => $this->session->getCsrfToken(),
            'error' => $this->session->getFlash('error'),
        ]);
    }

    /**
     * Verify 2FA code
     */
    #[Route('POST', '/login/2fa', name: 'login.2fa.verify')]
    public function verify2FA(ServerRequestInterface $request): ResponseInterface
    {
        $challengeToken = $this->session->get('2fa_challenge');

        if (!$challengeToken) {
            return redirect('/login');
        }

        $data = $request->getParsedBody();
        $code = $data['code'] ?? '';
        $ip = $this->session->getClientIp();

        if (empty($code)) {
            $this->session->flash('error', 'Verification code is required');
            return redirect('/login/2fa');
        }

        $result = $this->auth->verify2FA($challengeToken, $code, $ip);

        if ($result->success) {
            // Clear 2FA session data
            $this->session->forget('2fa_challenge');
            $this->session->forget('2fa_email');
            $this->session->regenerate();

            $intended = $this->session->pullIntendedUrl('/admin');
            return redirect($intended);
        }

        $this->session->flash('error', 'Invalid verification code');
        return redirect('/login/2fa');
    }

    /**
     * Cancel 2FA and return to login
     */
    #[Route('GET', '/login/2fa/cancel', name: 'login.2fa.cancel')]
    public function cancel2FA(ServerRequestInterface $request): ResponseInterface
    {
        $this->session->forget('2fa_challenge');
        $this->session->forget('2fa_email');
        return redirect('/login');
    }

    // =========================================================================
    // Helpers
    // =========================================================================



    private function view(string $template, array $data = []): ResponseInterface
    {
        $html = $this->renderer->render($template, $data);
        return response($html, 200, ['Content-Type' => 'text/html']);
    }
}
