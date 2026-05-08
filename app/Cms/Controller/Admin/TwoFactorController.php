<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Auth\TwoFactorService;
use App\Cms\Log\ActivityLogger;
use App\Cms\Theme\PageAssets;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

/**
 * TwoFactorController — Handles 2FA setup, challenge, and verification.
 *
 * Routes:
 *   GET  /admin/2fa/challenge  — Show TOTP code entry form
 *   POST /admin/2fa/verify     — Verify TOTP code
 *   POST /admin/2fa/recovery   — Verify recovery code
 *   GET  /admin/2fa/setup      — Show QR code + recovery codes (user profile)
 *   POST /admin/2fa/enable     — Enable 2FA for current user
 *   POST /admin/2fa/disable    — Disable 2FA for current user
 */
final class TwoFactorController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly TwoFactorService $twoFactor,
        private readonly SessionManager $session,
        private readonly PDO $pdo,
        private readonly ActivityLogger $activity,
        private readonly PageAssets $pageAssets,
    ) {}

    // ── Challenge Page ─────────────────────────────────────────────────

    #[Route('GET', '/admin/2fa/challenge', name: 'admin::2fa.challenge')]
    public function challenge(ServerRequestInterface $request): Response
    {
        if (!$this->session->get('2fa_pending')) {
            return Response::redirect('/admin');
        }

        $this->pageAssets->attachLibrary('admin/auth');

        return Response::html($this->renderer->render('admin::auth.2fa-challenge', [
            'title' => 'Two-Factor Authentication',
            'error' => $_GET['error'] ?? '',
        ]));
    }

    // ── Verify TOTP Code ───────────────────────────────────────────────

    #[Route('POST', '/admin/2fa/verify', name: 'admin::2fa.verify')]
    public function verify(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $code = trim((string) ($body['code'] ?? ''));
        $userId = (int) $this->session->get('2fa_user_id');

        if (!$userId) {
            return Response::redirect('/admin/login');
        }

        // Get user's TOTP secret
        $stmt = $this->pdo->prepare('SELECT totp_secret FROM cms_users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $secret = $stmt->fetchColumn();

        if (!$secret || !$this->twoFactor->verify($secret, $code)) {
            return Response::redirect('/admin/2fa/challenge?error=invalid_code');
        }

        // 2FA verified — complete login
        $this->completeLogin($userId, $request);

        // Set remember device cookie (30 days)
        if (!empty($body['remember_device'])) {
            $this->setDeviceCookie($userId);
        }

        $intended = $this->session->get('cms_intended_url', '/admin');
        $this->session->forget('cms_intended_url');

        return Response::redirect($intended);
    }

    // ── Recovery Code ──────────────────────────────────────────────────

    #[Route('POST', '/admin/2fa/recovery', name: 'admin::2fa.recovery')]
    public function recovery(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $code = trim((string) ($body['recovery_code'] ?? ''));
        $userId = (int) $this->session->get('2fa_user_id');

        if (!$userId) {
            return Response::redirect('/admin/login');
        }

        // Get recovery codes
        $stmt = $this->pdo->prepare('SELECT recovery_codes FROM cms_users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $raw = $stmt->fetchColumn();
        $hashedCodes = $raw ? json_decode($raw, true) : [];

        $matchIndex = $this->twoFactor->verifyRecoveryCode($code, $hashedCodes);

        if ($matchIndex === false) {
            return Response::redirect('/admin/2fa/challenge?error=invalid_recovery');
        }

        // Remove used code
        unset($hashedCodes[$matchIndex]);
        $hashedCodes = array_values($hashedCodes);

        $upd = $this->pdo->prepare('UPDATE cms_users SET recovery_codes = :codes WHERE id = :id');
        $upd->execute([':codes' => json_encode($hashedCodes), ':id' => $userId]);

        // Complete login
        $this->completeLogin($userId, $request);

        return Response::redirect('/admin');
    }

    // ── Setup Page ─────────────────────────────────────────────────────

    #[Route('GET', '/admin/2fa/setup', name: 'admin::2fa.setup')]
    public function setup(ServerRequestInterface $request): Response
    {
        $userId = (int) $this->session->get('cms_user_id');
        if (!$userId) {
            return Response::redirect('/admin/login');
        }

        $email = (string) $this->session->get('cms_user_email');

        // Get current 2FA status
        $stmt = $this->pdo->prepare('SELECT totp_enabled, totp_secret FROM cms_users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $isEnabled = (bool) ($user['totp_enabled'] ?? false);

        // Generate a new secret for setup (or use existing if already enabled)
        $secret = $isEnabled ? $user['totp_secret'] : $this->twoFactor->generateSecret();

        $otpAuthUrl = $this->twoFactor->getOtpAuthUrl($secret, $email);
        $qrCodeUrl = $this->twoFactor->getQrCodeUrl($otpAuthUrl);

        return Response::html($this->renderer->render('auth.2fa-setup', [
            'title'      => 'Two-Factor Authentication',
            'is_enabled' => $isEnabled,
            'secret'     => $secret,
            'qr_url'     => $qrCodeUrl,
            'error'      => $_GET['error'] ?? '',
            'success'    => $_GET['success'] ?? '',
        ]));
    }

    // ── Enable 2FA ─────────────────────────────────────────────────────

    #[Route('POST', '/admin/2fa/enable', name: 'admin::2fa.enable')]
    public function enable(ServerRequestInterface $request): Response
    {
        $userId = (int) $this->session->get('cms_user_id');
        if (!$userId) {
            return Response::redirect('/admin/login');
        }

        $body = $this->parseBody($request);
        $secret = (string) ($body['secret'] ?? '');
        $code = trim((string) ($body['code'] ?? ''));

        // Verify the code with the provided secret before enabling
        if (!$this->twoFactor->verify($secret, $code)) {
            return Response::redirect('/admin/2fa/setup?error=invalid_code');
        }

        // Generate recovery codes
        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();
        $hashedCodes = $this->twoFactor->hashRecoveryCodes($recoveryCodes);

        // Enable 2FA in DB
        $stmt = $this->pdo->prepare("
            UPDATE cms_users 
            SET totp_secret = :secret, totp_enabled = 1, recovery_codes = :codes 
            WHERE id = :id
        ");
        $stmt->execute([
            ':secret' => $secret,
            ':codes'  => json_encode($hashedCodes),
            ':id'     => $userId,
        ]);

        $this->activity->setContext($request);
        $this->activity->log('2fa_enabled', 'user', $userId, 'Two-Factor Authentication enabled');

        // Render setup page with recovery codes (one-time display)
        $email = (string) $this->session->get('cms_user_email');
        $otpAuthUrl = $this->twoFactor->getOtpAuthUrl($secret, $email);
        $qrCodeUrl = $this->twoFactor->getQrCodeUrl($otpAuthUrl);

        return Response::html($this->renderer->render('auth.2fa-setup', [
            'title'          => 'Two-Factor Authentication',
            'is_enabled'     => true,
            'secret'         => $secret,
            'qr_url'         => $qrCodeUrl,
            'error'          => '',
            'success'        => 'enabled',
            'recovery_codes' => $recoveryCodes,
        ]));
    }

    // ── Disable 2FA ────────────────────────────────────────────────────

    #[Route('POST', '/admin/2fa/disable', name: 'admin::2fa.disable')]
    public function disable(ServerRequestInterface $request): Response
    {
        $userId = (int) $this->session->get('cms_user_id');
        if (!$userId) {
            return Response::redirect('/admin/login');
        }

        $body = $this->parseBody($request);
        $password = (string) ($body['password'] ?? '');

        // Require password confirmation to disable 2FA
        $stmt = $this->pdo->prepare('SELECT password FROM cms_users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($password, $hash)) {
            return Response::redirect('/admin/2fa/setup?error=invalid_password');
        }

        // Disable 2FA
        $stmt = $this->pdo->prepare("
            UPDATE cms_users 
            SET totp_secret = NULL, totp_enabled = 0, recovery_codes = NULL 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $userId]);

        $this->activity->setContext($request);
        $this->activity->log('2fa_disabled', 'user', $userId, 'Two-Factor Authentication disabled');

        return Response::redirect('/admin/2fa/setup?success=disabled');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function completeLogin(int $userId, ServerRequestInterface $request): void
    {
        // Load full user
        $stmt = $this->pdo->prepare("SELECT id, name, email, role_id FROM cms_users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Clear 2FA pending state
        $this->session->forget('2fa_pending');
        $this->session->forget('2fa_user_id');

        // Set session data
        $this->session->set('cms_user_id', (int) $user['id']);
        $this->session->set('cms_user_name', $user['name']);
        $this->session->set('cms_user_email', $user['email']);
        $this->session->set('cms_user_role', (int) $user['role_id']);

        // Log
        $this->activity->setUser((int) $user['id'], $user['name']);
        $this->activity->setContext($request);
        $this->activity->log('login_2fa', 'user', $user['id'], $user['name']);
    }

    private function setDeviceCookie(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $expires = time() + (30 * 86400); // 30 days

        // Store in DB (simplified — could use a separate table)
        $this->session->set('2fa_device_' . $userId, $token);

        setcookie('2fa_device', $token, [
            'expires'  => $expires,
            'path'     => '/admin',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => isset($_SERVER['HTTPS']),
        ]);
    }

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        if (is_array($body) && !empty($body)) {
            return $body;
        }

        $stream = $request->getBody();
        $stream->rewind();
        $raw = $stream->getContents();
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
