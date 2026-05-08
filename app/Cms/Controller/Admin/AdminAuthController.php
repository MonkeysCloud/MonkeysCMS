<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use App\Cms\Log\ActivityLogger;
use App\Cms\Theme\PageAssets;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * AdminAuthController — Session-based login/logout for the admin panel.
 */
final class AdminAuthController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly PDO $pdo,
        private readonly SessionManager $session,
        private readonly PageAssets $pageAssets,
        private readonly ActivityLogger $activity,
    ) {}

    // ── GET /admin/login ───────────────────────────────────────────────

    #[Route('GET', '/admin/login', name: 'admin::login')]
    public function showLogin(ServerRequestInterface $request): Response
    {
        // Attach auth layout styles + login page logic
        $this->pageAssets->attachLibrary('admin/auth');
        $this->pageAssets->attachLibrary('admin/login');

        // Already logged in? Redirect to dashboard
        if ($this->session->get('cms_user_id')) {
            return Response::redirect('/admin');
        }

        $error = $_GET['error'] ?? '';

        $form = \App\Cms\Render\Organisms\Form::create('/admin/login', 'POST')
            ->setAttribute('id', 'login-form')
            ->addClass('space-y-6');

        $form->add(
            \App\Cms\Render\Molecules\FormGroup::create('email', 'Email Address', 'email')
                ->setRequired(true)
        );

        $form->add(
            \App\Cms\Render\Molecules\FormGroup::create('password', 'Password', 'password')
                ->setRequired(true)
        );

        $form->add(
            \App\Cms\Render\Atoms\Button::submit('Sign in to Dashboard')
                ->setAttribute('id', 'login-btn')
                ->setIcon('log-in')
        );

        return Response::html($this->renderer->render('admin::auth.login', [
            'title' => 'Sign In',
            'error' => $error,
            'form'  => $form,
        ]));
    }

    // ── POST /admin/login ──────────────────────────────────────────────

    #[Route('POST', '/admin/login', name: 'admin::login.attempt')]
    public function attemptLogin(ServerRequestInterface $request): Response
    {
        $body = $request->getParsedBody() ?? [];

        // MonkeysJS http.post sends JSON — parse raw body if form parser returned empty
        if (empty($body)) {
            $stream = $request->getBody();
            $stream->rewind();
            $raw = $stream->getContents();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        $email    = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $password === '') {
            return Response::json(['success' => false, 'message' => 'Email and password are required.'], 422);
        }

        // Look up user (include 2FA columns)
        $stmt = $this->pdo->prepare(
            "SELECT id, name, email, password, role_id, active, totp_enabled, totp_secret
             FROM cms_users
             WHERE email = :email
             LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return Response::json(['success' => false, 'message' => 'Invalid email or password.'], 401);
        }

        // Check active
        if (!(int) $user['active']) {
            return Response::json(['success' => false, 'message' => 'Your account has been deactivated.'], 403);
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            return Response::json(['success' => false, 'message' => 'Invalid email or password.'], 401);
        }

        // Regenerate session to prevent fixation
        $this->session->regenerate();

        // ── Two-Factor Authentication check ────────────────────────────
        if (!empty($user['totp_enabled']) && !empty($user['totp_secret'])) {
            // Set pending 2FA state (user is NOT fully logged in yet)
            $this->session->set('2fa_pending', true);
            $this->session->set('2fa_user_id', (int) $user['id']);

            // Store intended URL for after 2FA
            $intended = $this->session->get('cms_intended_url', '/admin');
            $this->session->set('cms_intended_url', $intended);

            return Response::json(['success' => true, 'redirect' => '/admin/2fa/challenge']);
        }

        // Store user in session (no 2FA — direct login)
        $this->session->set('cms_user_id', (int) $user['id']);
        $this->session->set('cms_user_name', $user['name']);
        $this->session->set('cms_user_email', $user['email']);
        $this->session->set('cms_user_role', (int) $user['role_id']);

        // Update last_login_at
        $upd = $this->pdo->prepare(
            "UPDATE cms_users SET last_login_at = NOW() WHERE id = :id"
        );
        $upd->execute(['id' => $user['id']]);

        // Log the login
        $this->activity->setUser((int) $user['id'], $user['name']);
        $this->activity->setContext($request);
        $this->activity->log('login', 'user', $user['id'], $user['name']);

        // Return success with redirect URL
        $intended = $this->session->get('cms_intended_url', '/admin');
        $this->session->forget('cms_intended_url');

        return Response::json(['success' => true, 'redirect' => $intended]);
    }

    // ── GET /admin/logout ──────────────────────────────────────────────

    #[Route('GET', '/admin/logout', name: 'admin::logout')]
    public function logout(ServerRequestInterface $request): Response
    {
        $userId = $this->session->get('cms_user_id');
        $userName = $this->session->get('cms_user_name');

        $this->activity->setUser($userId, $userName);
        $this->activity->setContext($request);
        $this->activity->log('logout', 'user', $userId, $userName);

        $this->session->forget('cms_user_id');
        $this->session->forget('cms_user_name');
        $this->session->forget('cms_user_email');
        $this->session->forget('cms_user_role');
        $this->session->forget('cms_intended_url');
        $this->session->regenerate();

        return Response::redirect('/admin/login');
    }
}
