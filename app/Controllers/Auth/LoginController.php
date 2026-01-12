<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use MonkeysLegion\Auth\Service\AuthService;
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
    private AuthService $auth;
    private SessionManager $session;
    private LoginAttempt $loginAttempt;
    private Renderer $renderer;

    public function __construct(
        AuthService $auth,
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
        // If already authenticated (via cookie middleware), redirect to dashboard
        if ($request->getAttribute('user')) {
             return redirect('/admin');
        }

        return $this->view('auth/login', [
            'csrf_token' => $this->session->getCsrfToken(),
            'error' => $this->session->getFlash('error'),
            'success' => $this->session->getFlash('success'),
            'old' => $this->session->getFlash('old', []),
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
        // $remember = isset($data['remember']); // JWT is stateless, remember me controls token TTL usually, or refresh token persistence. ignoring for now.

        // Flash old input
        $this->session->flash('old', ['email' => $email]);

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

        try {
            // Attempt login via MonkeysLegion Auth
            $result = $this->auth->login($email, $password, $ip);
            
            if ($result->success) {
                // Clear login attempts
                $this->loginAttempt->recordSuccess($email, $ip);

                // Intended URL
                $intended = $this->session->pullIntendedUrl('/admin');
                
                // Get Tokens
                $tokens = $result->tokens;
                
                // Store authentication data in session for CmsAuthService
                // This ensures BaseAdminController can access the current user
                $this->session->set('user_id', $result->user->getAuthIdentifier());
                $this->session->set('access_token', $tokens->accessToken);
                $this->session->set('refresh_token', $tokens->refreshToken);
                // Token expires at: accessExpiresAt is already a timestamp from the vendor auth
                $this->session->set('token_expires', $tokens->accessExpiresAt);
                
                // Get secure setting
                $isSecure = \App\Cms\Auth\AuthServiceProvider::getConfig('session_secure', true);
                $secureFlag = $isSecure ? "; Secure" : "";
                $cookieParams = "Path=/; HttpOnly; SameSite=Lax" . $secureFlag;
                
                return redirect($intended)
                    ->withAddedHeader('Set-Cookie', "auth_token=" . $tokens->accessToken . "; " . $cookieParams)
                    // Refresh token might have longer TTL, but simplified here
                    ->withAddedHeader('Set-Cookie', "refresh_token=" . $tokens->refreshToken . "; " . $cookieParams);
            }
            
            // Handle 2FA (if implemented in result)
            if ($result->requires2FA) {
                $this->session->set('2fa_challenge', $result->challengeToken);
                $this->session->set('2fa_email', $email);
                 // We don't have tokens yet, so just redirect to 2FA form
                return redirect('/login/2fa');
            }
        
        } catch (\MonkeysLegion\Auth\Exception\InvalidCredentialsException $e) {
             $this->loginAttempt->recordFailure($email, $ip);
             $this->session->flash('error', 'Invalid email or password');
        } catch (\MonkeysLegion\Auth\Exception\AccountLockedException $e) {
             $this->session->flash('error', 'Account is locked. Please try again later.');
        } catch (\Exception $e) {
             // Generic error
             $this->session->flash('error', 'An error occurred during login.');
             file_put_contents('debug_trace.log', "Login Error: " . $e->getMessage() . "\n", FILE_APPEND);
        }

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

        try {
            // Verify 2FA
             // NOTE: AuthService might not have verify2FA method exposed exactly same way, checking...
             // It does: verify2FA(string $challengeToken, string $code, ?string $ip = null): AuthResult
             $result = $this->auth->verify2FA($challengeToken, $code, $ip);

            if ($result->success) {
                // Clear 2FA session data
                $this->session->forget('2fa_challenge');
                $this->session->forget('2fa_email');
                
                $intended = $this->session->pullIntendedUrl('/admin');
                $tokens = $result->tokens;

                // Get secure setting
                $isSecure = \App\Cms\Auth\AuthServiceProvider::getConfig('session_secure', true);
                $secureFlag = $isSecure ? "; Secure" : "";
                $cookieParams = "Path=/; HttpOnly; SameSite=Lax" . $secureFlag;

                return redirect($intended)
                     ->withAddedHeader('Set-Cookie', "auth_token=" . $tokens->accessToken . "; " . $cookieParams)
                     ->withAddedHeader('Set-Cookie', "refresh_token=" . $tokens->refreshToken . "; " . $cookieParams);
            }
            
            $this->session->flash('error', 'Invalid verification code');

        } catch (\Exception $e) {
            $this->session->flash('error', 'Invalid verification code or error.');
        }

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
