<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Cms\Auth\CmsAuthService;
use App\Cms\Auth\CmsUserProvider;
use App\Cms\Auth\SessionManager;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use MonkeysLegion\Router\Attributes\Route;

/**
 * ProfileController - Handles user profile management
 */
class ProfileController
{
    private CmsAuthService $auth;
    private CmsUserProvider $userProvider;
    private SessionManager $session;

    public function __construct(
        CmsAuthService $auth,
        CmsUserProvider $userProvider,
        SessionManager $session
    ) {
        $this->auth = $auth;
        $this->userProvider = $userProvider;
        $this->session = $session;
    }

    /**
     * Show user profile
     */
    #[Route('GET', '/profile', name: 'profile.show')]
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        return $this->view('profile/show', [
            'user' => $user,
            'csrf_token' => $this->session->getCsrfToken(),
            'success' => $this->session->getFlash('success'),
            'error' => $this->session->getFlash('error'),
        ]);
    }

    /**
     * Show edit profile form
     */
    #[Route('GET', '/profile/edit', name: 'profile.edit')]
    public function edit(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        return $this->view('profile/edit', [
            'user' => $user,
            'csrf_token' => $this->session->getCsrfToken(),
            'errors' => $this->session->getFlash('errors') ?? [],
        ]);
    }

    /**
     * Update profile
     */
    #[Route('PUT', '/profile', name: 'profile.update')]
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        $data = $request->getParsedBody();
        $errors = [];

        // Validate display name
        if (isset($data['display_name']) && strlen($data['display_name']) > 100) {
            $errors['display_name'] = 'Display name cannot exceed 100 characters';
        }

        // Validate bio
        if (isset($data['bio']) && strlen($data['bio']) > 500) {
            $errors['bio'] = 'Bio cannot exceed 500 characters';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            return $this->redirect('/profile/edit');
        }

        // Update user
        $user->setDisplayName($data['display_name'] ?? null);
        $user->setBio($data['bio'] ?? null);
        
        $this->userProvider->save($user);

        $this->session->flash('success', 'Profile updated successfully');
        return $this->redirect('/profile');
    }

    /**
     * Show change email form
     */
    #[Route('GET', '/profile/email', name: 'profile.email')]
    public function showEmailForm(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        return $this->view('profile/email', [
            'user' => $user,
            'csrf_token' => $this->session->getCsrfToken(),
            'error' => $this->session->getFlash('error'),
        ]);
    }

    /**
     * Update email
     */
    #[Route('PUT', '/profile/email', name: 'profile.email.update')]
    public function updateEmail(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // Validate
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'Invalid email address');
            return $this->redirect('/profile/email');
        }

        if (!$user->verifyPassword($password)) {
            $this->session->flash('error', 'Invalid password');
            return $this->redirect('/profile/email');
        }

        // Check if email already exists
        $existing = $this->userProvider->findByEmail($email);
        if ($existing && $existing->getId() !== $user->getId()) {
            $this->session->flash('error', 'Email already in use');
            return $this->redirect('/profile/email');
        }

        // Update email
        $user->setEmail($email);
        // TODO: Mark email as unverified and send verification
        $this->userProvider->save($user);

        $this->session->flash('success', 'Email updated successfully');
        return $this->redirect('/profile');
    }

    /**
     * Show change password form
     */
    #[Route('GET', '/profile/password', name: 'profile.password')]
    public function showPasswordForm(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        return $this->view('profile/password', [
            'csrf_token' => $this->session->getCsrfToken(),
            'error' => $this->session->getFlash('error'),
        ]);
    }

    /**
     * Update password
     */
    #[Route('PUT', '/profile/password', name: 'profile.password.update')]
    public function updatePassword(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        $data = $request->getParsedBody();
        $current = $data['current_password'] ?? '';
        $new = $data['new_password'] ?? '';
        $confirm = $data['new_password_confirmation'] ?? '';

        // Validate
        if (strlen($new) < 8) {
            $this->session->flash('error', 'New password must be at least 8 characters');
            return $this->redirect('/profile/password');
        }

        if ($new !== $confirm) {
            $this->session->flash('error', 'Passwords do not match');
            return $this->redirect('/profile/password');
        }

        if (!$this->auth->changePassword($current, $new)) {
            $this->session->flash('error', 'Current password is incorrect');
            return $this->redirect('/profile/password');
        }

        $this->session->flash('success', 'Password changed successfully');
        return $this->redirect('/profile');
    }

    /**
     * Show active sessions
     */
    #[Route('GET', '/profile/sessions', name: 'profile.sessions')]
    public function showSessions(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        $sessions = $this->userProvider->getUserSessions($user->getId());

        return $this->view('profile/sessions', [
            'sessions' => $sessions,
            'current_session' => $this->session->getId(),
            'csrf_token' => $this->session->getCsrfToken(),
            'success' => $this->session->getFlash('success'),
        ]);
    }

    /**
     * Revoke a session
     */
    #[Route('DELETE', '/profile/sessions/{id}', name: 'profile.sessions.revoke')]
    public function revokeSession(ServerRequestInterface $request, string $sessionId): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        // Don't allow revoking current session
        if ($sessionId === $this->session->getId()) {
            $this->session->flash('error', 'Cannot revoke current session. Use logout instead.');
            return $this->redirect('/profile/sessions');
        }

        $this->userProvider->deleteSession($sessionId);

        $this->session->flash('success', 'Session revoked successfully');
        return $this->redirect('/profile/sessions');
    }

    /**
     * Revoke all other sessions
     */
    #[Route('DELETE', '/profile/sessions', name: 'profile.sessions.revoke_all')]
    public function revokeAllSessions(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        $data = $request->getParsedBody();
        $password = $data['password'] ?? '';

        if (!$user->verifyPassword($password)) {
            $this->session->flash('error', 'Invalid password');
            return $this->redirect('/profile/sessions');
        }

        // Increment token version to invalidate all JWT tokens
        $this->userProvider->incrementTokenVersion($user->getId());
        
        // Delete all sessions except current
        $this->userProvider->deleteAllSessions($user->getId());

        $this->session->flash('success', 'All other sessions have been revoked');
        return $this->redirect('/profile/sessions');
    }

    /**
     * Show linked accounts (OAuth)
     */
    #[Route('GET', '/profile/accounts', name: 'profile.accounts')]
    public function showLinkedAccounts(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        // TODO: Get linked accounts
        $linkedAccounts = [];

        return $this->view('profile/accounts', [
            'linked_accounts' => $linkedAccounts,
            'available_providers' => ['google', 'github'],
            'csrf_token' => $this->session->getCsrfToken(),
            'success' => $this->session->getFlash('success'),
            'error' => $this->session->getFlash('error'),
        ]);
    }

    /**
     * Delete account
     */
    #[Route('DELETE', '/profile', name: 'profile.delete')]
    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return $this->redirect('/login');
        }

        $data = $request->getParsedBody();
        $password = $data['password'] ?? '';
        $confirmation = $data['confirmation'] ?? '';

        if ($confirmation !== 'DELETE') {
            $this->session->flash('error', 'Please type DELETE to confirm');
            return $this->redirect('/profile');
        }

        if (!$user->verifyPassword($password)) {
            $this->session->flash('error', 'Invalid password');
            return $this->redirect('/profile');
        }

        // Soft delete user
        $this->userProvider->delete($user);

        // Logout
        $this->auth->logout();

        return $this->redirect('/');
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
        $user = $data['user'] ?? null;
        $csrf = $data['csrf_token'] ?? '';
        $success = $data['success'] ?? '';
        $error = $data['error'] ?? '';

        $messageHtml = '';
        if ($success) {
            $messageHtml = "<div class=\"bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4\">{$success}</div>";
        }
        if ($error) {
            $messageHtml = "<div class=\"bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4\">{$error}</div>";
        }

        $displayName = htmlspecialchars($user?->getDisplayName() ?? '');
        $email = htmlspecialchars($user?->getEmail() ?? '');
        $username = htmlspecialchars($user?->getUsername() ?? '');
        $bio = htmlspecialchars($user?->getBio() ?? '');
        $avatarUrl = htmlspecialchars($user?->getAvatarUrl() ?? '');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - MonkeysCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen py-12">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-8 py-12">
                <div class="flex items-center">
                    <img src="{$avatarUrl}" alt="Avatar" class="w-24 h-24 rounded-full border-4 border-white shadow-lg">
                    <div class="ml-6 text-white">
                        <h1 class="text-2xl font-bold">{$displayName}</h1>
                        <p class="opacity-90">@{$username}</p>
                    </div>
                </div>
            </div>
            
            <div class="p-8">
                {$messageHtml}
                
                <div class="space-y-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Email</h3>
                        <p class="mt-1 text-gray-900">{$email}</p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Bio</h3>
                        <p class="mt-1 text-gray-900">{$bio}</p>
                    </div>
                </div>
                
                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="/profile/edit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">
                        Edit Profile
                    </a>
                    <a href="/profile/password" class="bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700">
                        Change Password
                    </a>
                    <a href="/settings/2fa" class="bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700">
                        2FA Settings
                    </a>
                    <a href="/profile/sessions" class="bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700">
                        Active Sessions
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
