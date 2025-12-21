<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\SessionManager;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

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
     * 
     * POST /logout
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $allDevices = isset($data['all_devices']);

        $this->auth->logout($allDevices);

        // For API requests
        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return new \Nyholm\Psr7\Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(['message' => 'Logged out successfully'])
            );
        }

        $this->session->flash('success', 'You have been logged out');
        return new \Nyholm\Psr7\Response(302, ['Location' => '/login']);
    }

    /**
     * Logout from all devices
     * 
     * POST /logout/all
     */
    public function logoutAll(ServerRequestInterface $request): ResponseInterface
    {
        $this->auth->logout(true);

        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return new \Nyholm\Psr7\Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(['message' => 'Logged out from all devices'])
            );
        }

        $this->session->flash('success', 'You have been logged out from all devices');
        return new \Nyholm\Psr7\Response(302, ['Location' => '/login']);
    }
}
