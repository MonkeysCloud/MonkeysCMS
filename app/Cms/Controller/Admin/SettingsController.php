<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Log\ActivityLogger;
use App\Cms\Service\SettingsService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * SettingsController — Admin UI for CMS settings.
 */
#[RoutePrefix('/admin/settings')]
final class SettingsController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly SettingsService $settings,
        private readonly SessionManager $session,
        private readonly ActivityLogger $activity,
    ) {}

    #[Route('GET', '/', name: 'admin::settings.index')]
    public function index(ServerRequestInterface $request): Response
    {
        return Response::html($this->renderer->render('admin::settings.index', [
            'title'        => 'Settings',
            'settings'     => $this->settings->all(),
            'flashSuccess' => $this->session->getFlash('settings_success'),
            'flashError'   => $this->session->getFlash('settings_error'),
        ]));
    }

    #[Route('POST', '/', name: 'admin::settings.save')]
    public function save(ServerRequestInterface $request): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            // Group: General
            $generalKeys = ['site_name', 'site_tagline', 'site_url', 'contact_email', 'timezone'];
            foreach ($generalKeys as $key) {
                if (array_key_exists($key, $body)) {
                    $this->settings->set($key, trim($body[$key] ?? ''), 'general');
                }
            }

            // Group: Content
            $contentKeys = ['default_status', 'posts_per_page'];
            foreach ($contentKeys as $key) {
                if (array_key_exists($key, $body)) {
                    $this->settings->set($key, trim($body[$key] ?? ''), 'content');
                }
            }

            // Checkboxes (not present in body when unchecked)
            $this->settings->set('enable_revisions', isset($body['enable_revisions']) ? '1' : '0', 'content');
            $this->settings->set('enable_comments', isset($body['enable_comments']) ? '1' : '0', 'content');
            $this->settings->set('comments_moderation', isset($body['comments_moderation']) ? '1' : '0', 'content');
            $this->settings->set('comments_threaded', isset($body['comments_threaded']) ? '1' : '0', 'content');
            $this->settings->set('comments_require_login', isset($body['comments_require_login']) ? '1' : '0', 'content');

            // Group: Media
            $mediaKeys = ['max_upload_size', 'allowed_types', 'thumb_width', 'thumb_height'];
            foreach ($mediaKeys as $key) {
                if (array_key_exists($key, $body)) {
                    $this->settings->set($key, trim($body[$key] ?? ''), 'media');
                }
            }

            // Group: API
            $this->settings->set('api_enabled', isset($body['api_enabled']) ? '1' : '0', 'api');
            $apiKeys = ['cors_origins', 'api_rate_limit'];
            foreach ($apiKeys as $key) {
                if (array_key_exists($key, $body)) {
                    $this->settings->set($key, trim($body[$key] ?? ''), 'api');
                }
            }

            // Group: Advanced
            $this->settings->set('cache_enabled', isset($body['cache_enabled']) ? '1' : '0', 'advanced');
            if (array_key_exists('cache_ttl', $body)) {
                $this->settings->set('cache_ttl', trim($body['cache_ttl'] ?? '3600'), 'advanced');
            }

            $this->activity->setContext($request);
            $this->activity->log('updated', 'setting', null, 'Site Settings', [
                'changed_keys' => array_keys($body),
            ]);

            $this->session->flash('settings_success', 'Settings saved successfully.');
        } catch (\Throwable $e) {
            $this->session->flash('settings_error', 'Failed to save settings: ' . $e->getMessage());
        }

        return Response::redirect('/admin/settings');
    }
}
