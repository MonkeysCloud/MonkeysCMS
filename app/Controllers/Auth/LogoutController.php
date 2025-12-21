<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\SessionManager;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use MonkeysLegion\Router\Attributes\Route;

/**
 * LogoutController - Handles user logout
 */
class LogoutController
{
    private CmsAuthService $auth;
    private SessionManager $session;

    public function __construct(CmsAuthService $auth, SessionManager $session)
    {
        $this->auth = $auth;
        $this->session = $session;
    }

    /**
     * Logout user
     */
    #[Route('POST', '/logout', name: 'logout')]
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $allDevices = isset($data['all_devices']);

        $this->auth->logout($allDevices);

        // For API requests
        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return json(['message' => 'Logged out successfully']);
        }

        $this->session->flash('success', 'You have been logged out');
        return redirect('/login');
    }

    /**
     * Logout from all devices
     */
    #[Route('POST', '/logout/all', name: 'logout.all')]
    public function logoutAll(ServerRequestInterface $request): ResponseInterface
    {
        $this->auth->logout(true);

        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return json(['message' => 'Logged out from all devices']);
        }

        $this->session->flash('success', 'You have been logged out from all devices');
        return redirect('/login');
    }
}
